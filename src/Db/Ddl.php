<?php namespace Sintattica\Atk\Db;

use Sintattica\Atk\Core\Tools;
use Sintattica\Atk\Core\Config;

/**
 * The Data Definition Language abstract base class.
 *
 * Database drivers should derive a class from this base class to implement
 * vendor specific ddl commands.
 *
 * @author Ivo Jansch <ivo@achievo.org>
 * @package atk
 * @subpackage db
 * @abstract
 */
class Ddl
{
    /**
     * Some flags that can be used to configure database fields.
     */
    const DDL_PRIMARY = 1;
    const DDL_UNIQUE = 2;
    const DDL_NOTNULL = 4;

    var $m_table = array();
    var $m_fields = array();
    var $m_remove_field;
    var $m_indexes = array(); // not yet implemented
    var $m_primarykey = array();

    /** @var Db */
    var $m_db;

    /**
     * Postfix for index names
     *
     * @access private
     * @var string
     */
    protected $m_idxnameFormat = "%s_idx";

    /**
     * Default constructor
     */
    function __construct()
    {

    }

    /**
     * Static factory method for creating a new atkDDL instance. This static
     * method will determine the database type (mysql, oci, etc) and
     * instantiate the correct DDL class.
     *
     * @param string $database The database driver to use
     * @return Ddl instance of db specific DDL driver
     */
    public static function &create($database = null)
    {
        $db = Config::getGlobal("db");
        $database = $database === null ? $db["default"]["driver"] : $database;
        $classname = __NAMESPACE__ . "\\" . $database . "Ddl";
        return new $classname();
    }

    /**
     * Set all table data at once using the given table meta data,
     * retrieved using the metadata function of the db instance.
     *
     * @param array $tablemeta table meta data array
     */
    function loadMetaData($tablemeta)
    {
        $this->setTable($tablemeta[0]["table"]);
        $this->addFields($tablemeta);
    }

    /**
     * Add a field to the table definition.
     *
     * @param string $name The name of the field
     * @param string $generictype The datatype of the field (should be one of the
     *                            generic types supported by ATK).
     * @param int $size The size of the field (if appropriate)
     * @param int $flags The self::DDL_ flags for this field.
     * @param mixed $default The default value to be used when inserting new
     *                         rows.
     */
    function addField($name, $generictype, $size = 0, $flags = 0, $default = null)
    {
        if (Tools::hasFlag($flags, self::DDL_PRIMARY)) {
            $this->m_primarykey[] = $name;
            $flags |= self::DDL_NOTNULL; // primary keys may never be null.
        }

        // Fix the size if the type is decimal
        if ($generictype == "decimal") {
            $size = $this->calculateDecimalFieldSize($size);
        }

        $this->m_fields[$name] = array(
            "type" => $generictype,
            "size" => $size,
            "flags" => $flags,
            "default" => $default
        );
    }

    /**
     * Calculate the correct field size for decimal fields
     * We should add the decimals to the size, since
     * size is specified including the decimals.
     *
     * @param string $size Current size
     * @return string New size
     */
    function calculateDecimalFieldSize($size)
    {
        list($tmp_size, $decimals) = explode(",", $size);
        $tmp_size += intval($decimals); // we should add the decimals to the size, since
        // size is specified including the decimals.
        return sprintf('%d,%d', $tmp_size, $decimals);
    }

    /**
     * Drop a field from the table definition.
     *
     * @param string $name The name of the field
     */
    function dropField($name)
    {
        $this->m_remove_field = $name;
    }

    /**
     * Add multiple fields at once using the given metadata.
     *
     * NOTE: defaults are not supported yet!
     *
     * @param array $meta The fields meta data.
     */
    function addFields($meta)
    {
        foreach ($meta as $field) {
            $flags = Tools::hasFlag($field["flags"], Db::MF_PRIMARY) ? self::DDL_PRIMARY : 0;
            $flags |= Tools::hasFlag($field["flags"], Db::MF_UNIQUE) ? self::DDL_UNIQUE : 0;
            $flags |= Tools::hasFlag($field["flags"], Db::MF_NOT_NULL) ? self::DDL_NOTNULL : 0;

            $this->addField($field["name"], $field["gentype"], $field["len"], $flags);
        }
    }

    /**
     * Convert an ATK generic datatype to a database specific type.
     *
     * This function will be overrided by the database specific subclasses of
     * Db.
     * Note: in all derived subclasses, the following types *must* be
     * supported: number, decimal, string, date, text, datetime, time,
     * boolean.
     * If the database does not have a proper field type, consider using
     * a varchar or number to store the value.
     *
     * @param string $generictype The datatype to convert.
     * @abstract
     * @return string
     */
    function getType($generictype)
    {
        return ""; // in case we have an unsupported type.
    }

    /**
     * Convert an database specific type to an ATK generic datatype.
     *
     * This function will be overrided by the database specific subclasses of
     * Db.
     *
     * @param string $type The database specific datatype to convert.
     * @abstract
     */
    function getGenericType($type)
    {
        return ""; // in case we have an unsupported type.
    }

    /**
     * Set the name of the table.
     *
     * @param string $tablename The name of the table
     */
    function setTable($tablename)
    {
        $this->m_table = $tablename;
    }

    /**
     * Build a CREATE TABLE query and return it as a string.
     *
     * @return string The CREATE TABLE query.
     */
    function buildCreate()
    {
        if ($this->m_table != "") {
            $fields = $this->buildFields();
            if ($fields != "") {
                $q = "CREATE TABLE " . $this->m_table . "\n(";

                $q .= $fields;

                $constraints = $this->buildConstraints();

                if ($constraints != "") {
                    $q .= ",\n" . $constraints;
                }

                $q .= ")";
            }
            return $q;
        }
        return "";
    }

    /**
     * Build one or more ALTER TABLE queries and return them as an array of
     * strings.
     *
     * The default implementation assumes that multiple fields can be added
     * with one single ALTER TABLE statement. If a database needs to be
     * supported which doesn't have this ability, then an override for this
     * function should be implemented in the appropriate atk<database>ddl
     * class.
     *
     * @return array of ALTER TABLE queries.
     */
    function buildAlter()
    {
        if ($this->m_table != "") {
            $fields = $this->buildFields();

            if ($fields != "" || $this->m_remove_field) {
                $q = "ALTER TABLE " . $this->m_db->quoteIdentifier($this->m_table);

                if ($this->m_remove_field) {
                    $q .= " DROP\n " . $this->m_db->quoteIdentifier($this->m_remove_field);
                } else {
                    $q .= " ADD\n (";


                    $q .= $fields;

                    $constraints = $this->buildConstraints();

                    if ($constraints != "") {
                        $q .= ",\n" . $constraints;
                    }

                    $q .= ")";
                }
                return array($q);
            }
        }
        return "";
    }

    /**
     * Build a DROP TABLE query and return it as a string.
     *
     * @return string The DROP TABLE query.
     */
    function buildDrop()
    {
        if ($this->m_table != "") {

            $q = "DROP TABLE " . $this->m_db->quoteIdentifier($this->m_table) . "";
            return $q;
        }
        return "";
    }

    /**
     * Generate a string for a field, to be used inside a CREATE TABLE
     * statement.
     * This function tries to be generic, so it will work in the largest
     * number of databases. Databases that won't work with this syntax,
     * should override this method in the database specific ddl class.
     *
     * @param string $name The name of the field
     * @param string $generictype The datatype of the field (should be one of the
     *                            generic types supported by ATK).
     * @param int $size The size of the field (if appropriate)
     * @param int $flags The self::DDL_ flags for this field.
     * @param mixed $default The default value to be used when inserting new
     *                         rows.
     */
    function buildField($name, $generictype, $size = 0, $flags = 0, $default = null)
    {
        $res = $this->m_db->quoteIdentifier($name) . " " . $this->getType($generictype);
        if ($size > 0 && $this->needsSize($generictype)) {
            $res .= "(" . $size . ")";
        }
        if ($default !== null) {
            if ($this->needsQuotes($generictype)) {
                $default = "'" . $default . "'";
            }
            $res .= " DEFAULT " . $default;
        }
        if (Tools::hasFlag($flags, self::DDL_NOTNULL)) {
            $res .= " NOT NULL";
        }

        return $res;
    }

    /**
     * Generate a string that defines the primary key, for use
     * inside the CREATE TABLE statement.
     *
     * This function will be overrided by the database specific subclasses of
     * atkDDL.
     *
     * @param array $fieldlist An array of fields that define the primary key.
     */
    function buildPrimaryKey($fieldlist = array())
    {
        if (count($fieldlist) > 0) {
            return "PRIMARY KEY (" . implode(", ", $fieldlist) . ")";
        }
        return "";
    }

    /**
     * Method to determine whether quotes are needed around the values
     * for a given generic datatype.
     *
     * @param string $generictype The type of field.
     * @return true  if quotes should be put around values for the given type
     *               of field.
     *         false if quotes should not be used.
     */
    function needsQuotes($generictype)
    {
        return !($generictype == "number" || $generictype == "decimal");
    }

    /**
     * Method to determine whether a given generic field type needs
     * to have a size defined.
     *
     * @param string $generictype The type of field.
     * @return true  if a size should be specified for the given field type.
     *         false if a size does not have to be specified.
     */
    function needsSize($generictype)
    {
        switch ($generictype) {
            case "number":
            case "decimal":
            case "string":
                return true;
                break;
            case "date":
            case "text":
            case "datetime":
            case "time":
            case "boolean":
                return false;
                break;
        }
        return false; // in case we have an unsupported type.
    }

    /**
     * Convert all fields to string that can be used in a CREATE or ALTER
     * TABLE statement. Fields will be returned in an array. (INTERNAL USE ONLY)
     *
     * @access private
     */
    function _buildFieldsArray()
    {
        $fields = array();

        foreach ($this->m_fields as $fieldname => $fieldconfig) {
            if ($fieldname != "" && $fieldconfig["type"] != "" && $this->getType($fieldconfig["type"]) != "") {
                $fields[] = $this->buildField($fieldname, $fieldconfig["type"], $fieldconfig["size"],
                    $fieldconfig["flags"], $fieldconfig["default"]);
            }
        }

        return $fields;
    }

    /**
     * Convert all fields to a string that can be used in a CREATE or ALTER
     * TABLE statement.
     *
     * @return String containing fields to be used in a CREATE or ALTER TABLE statement
     */
    function buildFields()
    {
        $fields = $this->_buildFieldsArray();
        if (count($fields) > 0) {
            return implode(",\n", $fields);
        } else {
            return "";
        }
    }

    /**
     * Convert all constraints to an array that can be used in a CREATE or
     * ALTER TABLE statement.
     *
     * @return array of constraints
     * @access private
     */
    function _buildConstraintsArray()
    {
        $constraints = array();
        $pk = $this->buildPrimaryKey($this->m_primarykey);
        if (!empty($pk)) {
            $constraints[] = $pk;
        }
        return $constraints;
    }

    /**
     * Convert all constraints to a string that can be used in a CREATE TABLE
     * statement.
     *
     * @return String containing constraints to be used in a CREATE or ALTER TABLE statement
     */
    function buildConstraints()
    {
        $constraints = $this->_buildConstraintsArray();
        if (count($constraints) > 0) {
            return implode(",\n", $constraints);
        } else {
            return "";
        }
    }

    /**
     * Build and execute the CREATE TABLE query.
     *
     * @return true  if the table was created successfully
     *         false if anything went wrong, or if no table could be created.
     */
    function executeCreate()
    {
        if (!isset($this->m_db)) {
            $this->m_db = Tools::atkGetDb();
        }

        $query = $this->buildCreate();
        if ($query != "") {
            return $this->m_db->query($query);
        } else {
            Tools::atkdebug("ddl::executeCreate: nothing to do!");
        }
        return false;
    }

    /**
     * Build and execute ALTER TABLE queries.
     *
     * Note that more than one query might be performed, depending on the
     * number of fields added, and the database capabilities (some databases
     * are capable of adding several fields in one ALTER TABLE query, others
     * aren't and need to perform multiple queries).
     *
     * @return true  if the table was altered successfully
     *         false if anything went wrong, or if no table could be altered.
     */
    function executeAlter()
    {
        if (!isset($this->m_db)) {
            $this->m_db = Tools::atkGetDb();
        }

        $queries = $this->buildAlter();
        if (count($queries) > 0) {
            for ($i = 0, $_i = count($queries); $i < $_i; $i++) {
                if ($queries[$i] != "") {
                    if (!$this->m_db->query($queries[$i])) {
                        return false;
                    }
                }
            }
            return true;
        } else {
            Tools::atkdebug("ddl::executeCreate: nothing to do!");
        }
        return false;
    }

    /**
     * Build and execute the DROP TABLE query.
     *
     * @return true  if the table was dropped successfully
     *         false if anything went wrong, or if no table could be dropped.
     */
    function executeDrop()
    {
        if (!isset($this->m_db)) {
            $this->m_db = Tools::atkGetDb();
        }

        $query = $this->buildDrop();
        if ($query != "") {
            return $this->m_db->query($query);
        } else {
            Tools::atkdebug("ddl::executeDrop: nothing to do!");
        }
        return false;
    }

    /**
     * Build and execute CREATE VIEW query
     *
     * @param string $name - name of view
     * @param string $select - SQL SELECT statement
     * @param string $with_check_option - use SQL WITH CHECK OPTION
     * @return  true  if view create successfully
     *          false if error take place
     */
    function executeCreateView($name, $select, $with_check_option)
    {
        if (!isset($this->m_db)) {
            $this->m_db = Tools::atkGetDb();
        }

        $query = $this->buildView($name, $select, $with_check_option);
        if ($query != "") {
            return $this->m_db->query($query);
        } else {
            Tools::atkdebug("ddl::executeCreateView: nothing to do!");
        }
        return false;
    }

    /**
     * Build CREATE VIEW query
     *
     * @param string $name - name of view
     * @param string $select - SQL SELECT statement
     * @param string $with_check_option - use SQL WITH CHECK OPTION
     * @return string CREATE VIEW query string
     */
    function buildView($name, $select, $with_check_option)
    {
        Tools::atkerror("buildView don't support by this db or by this db driver");
        return "";
    }

    /**
     * Build and execute DROP VIEW query
     *
     * @param string $name - name of view
     * @return  true  if view create successfully
     *          false if error take place
     */
    function executeDropView($name)
    {
        if (!isset($this->m_db)) {
            $this->m_db = Tools::atkGetDb();
        }

        $query = $this->dropView($name);
        if ($query != "") {
            return $this->m_db->query($query);
        } else {
            Tools::atkdebug("ddl::executeDropView: nothing to do!");
        }
        return false;
    }

    /**
     * Build DROP VIEW query
     *
     * @param string $name - name of view
     * @return string CREATE VIEW query string
     */
    function dropView($name)
    {
        Tools::atkerror("dropView don't support by this db or by this db driver");
        return "";
    }

    /**
     * Create an index
     *
     * @param string $name Index name
     * @param array $definition associative array that defines properties of the index to be created.
     *
     *                          example
     *                          array('fields' => array('user_id' => array('sorting' => 'ascending'
     *                                                                     'length' => 3
     *                                                                      ),
     *                                                  'lastname' => array()
     *                                                  )
     *                               )
     * @return boolean
     */
    function createIndex($name, $definition)
    {
        $table = $this->m_db->quoteIdentifier($this->m_table);
        $name = $this->m_db->quoteIdentifier($this->getIndexName($name));

        $query = "CREATE INDEX $name ON $table";
        $fields = array();
        foreach ($definition['fields'] as $field => $fieldinfo) {
            if (!empty($fieldinfo['length'])) {
                $fields[] = $this->m_db->quoteIdentifier($field) . "(" . $fieldinfo['length'] . ")";
            } else {
                $fields[] = $this->m_db->quoteIdentifier($field);
            }
        }
        $query .= ' (' . implode(', ', $fields) . ')';
        return $this->m_db->query($query);
    }

    /**
     * Drop an existing index
     *
     * @param string $name Index name
     * @return boolean
     */
    function dropIndex($name)
    {
        $table = $this->m_db->quoteIdentifier($this->m_table);
        $name = $this->m_db->quoteIdentifier($this->getIndexName($name));
        return $this->m_db->query("DROP INDEX $name ON $table");
    }

    /**
     * Get Indexname
     *
     * @param string $name Indexname
     * @return string
     */
    function getIndexName($name)
    {
        return sprintf($this->m_idxnameFormat, preg_replace('/[^a-z0-9_\$]/i', '_', $name));
    }

    /**
     * Rename sequence
     *
     * @param string $name The current sequence name
     * @param string $new_name The new sequence name
     * @return bool
     */
    function renameSequence($name, $new_name)
    {
        return true;
    }

    /**
     * Drop sequence
     *
     * @param string $name Sequence name
     * @return bool
     */
    function dropSequence($name)
    {
        return true;
    }

    /**
     * Rename table name
     *
     * @param string $name Table name
     * @param string $new_name New table name
     * @return bool
     */
    function renameTable($name, $new_name)
    {
        return true;
    }

}


