<?php

/*
 * *    corelibPHP by alexander kindziora 2012
 *      mysql abstration layer für corelib
 */

class corelib_db_mysql implements corelib_models {

    public $db;
    public $model;
    public $validation          = null;
    public $debug               = true;
    public $fields              = Array();
    private $_result_pointer    = null;

    /**
     *
     * @param type $db
     * @param type $table 
     */
    public function __construct($db = null, $table = null) {
        if ($db != null && !is_string($db))
            $this->db = $db;
        else if (is_string($db)) {
            $table = $db;
            $this->db = corelib_boot::$db;
        } else {
            $this->db = corelib_boot::$db;
        }

        if ($table) {
            $this->model = $table;
        }
    }

    /**
     *
     * @param type $result_ref
     * @param type $force_multi_dimension
     * @param type $mode
     * @return type 
     */
    public function AlltoArray($result_ref, $force_multi_dimension = true, $mode = MYSQL_ASSOC) {
        $result_array = array();
        while ($row = mysql_fetch_array($result_ref, $mode)) {
            $result_array[] = $row;
        }
        if ($force_multi_dimension) {
            if (count($result_array) == 1)
                $result_array = $result_array[0];
        }
        return (array) $result_array;
    }

    /**
     *
     * @param mysql result $result_ref
     * @return array
     */
    public function toArray($result_ref) {
        return (array) mysql_fetch_array($result_ref, MYSQL_ASSOC);
    }

    /**
     *
     * @param array $where
     * @param string $extra | optional
     * @param bool $toArray
     * @param array $fields
     * @return mysql result | array
     */
    public function get($where = array(), $fields = array('*'), $toArray = true, $extra = "") {
        if ($fields[0] == '*') {
            if (!empty($this->fields))
                $fields = $this->fields;
        }

        $fields = implode(',', $fields);

        if (!empty($where))
            $wq = $this->_buildWhereQuery($where);

        $result = $this->query("SELECT " . ltrim($fields, ',') . " FROM `" . $this->model . '` ' . $wq . ' ' . $extra);
        if ($toArray)
            return $this->AlltoArray($result);
        else
            return $result;
    }

    /**
     *
     * @param integer $id
     * @return array
     */
    public function getbyId($id) {
        $result = $this->query("SELECT * FROM `" . $this->model . "` WHERE id= " . (integer) $id);
        return mysql_fetch_assoc($result);
    }

    /**
     * Läd einen Datensatz anhand der ID, es werden auch in relation stehende Datensätze geladen (relation durch column name mit underscore _)
     * @param integer $id
     * @return array
     */
    public function load($where = array(), $fields = array('*'), $toArray = true, $extra = "") {
        $allRaw = $this->get($where, $fields, $toArray, $extra);

        if (!isset($allRaw[0])) { // nur ein ergebnis
            foreach ($allRaw as $key => &$val) {
                $pos = strpos($key, '_');
                if (is_numeric($pos)) {
                    list($name) = explode('_', $key);
                    $modelName = "models_$name";
                    if (class_exists($modelName))
                        $join = new $modelName();
                    if (!$join) {//try model
                        $join = new corelib_db_mysql($this->db, $name);
                    }
                    $jdata = $join->get(array('id' => (integer) $val));
                    $allRaw[$key] = $jdata;
                }
            }

            return array($allRaw);
        }

        foreach ($allRaw as $k => $result) {

            foreach ($result as $key => $val) {
                $pos = strpos($key, '_');
                if (is_numeric($pos)) {
                    list($name) = explode('_', $key);
                    $modelName = "models_$name";
                    if (class_exists($modelName))
                        $join = new $modelName();
                    if (!$join) {//try model
                        $join = new corelib_db_mysql($this->db, $name);
                    }
                    $allRaw[$k][$key] = $join->get(array('id' => (integer) $val));
                }
            }
        }

        return $allRaw;
    }
    
    /**
     *
     * @param type $where
     * @param type $returnNum
     * @return type 
     */
    public function count($where = array(), $returnNum = true) {
        $result = $this->query("SELECT COUNT(*) as num FROM `" . $this->model . '` ' . $this->_buildWhereQuery($where));
        if (!$returnNum)
            return mysql_fetch_assoc($result);
        else
            return (integer) mysql_fetch_object($result)->num;
    }

    /**
     *
     * @param array $query
     * @return <type>
     */
    protected function _buildWhereQuery($query = array()) {
        $operators = array('<', '>', 'OR', 'AND', 'LIKE', 'NOT LIKE', '!=');
        $i = 0;
        $keys = array_keys($query);

        if (!empty($query)) {

            if (!in_array($keys[0], $operators)) {
                $queryString = ' WHERE ' . mysql_escape_string($keys[0]) . "='" . mysql_escape_string($query[$keys[0]]) . "'";
            } else {
                $queryString .= ' AND ' . mysql_escape_string($keys[0][0]) . $keys[0] . "'" . mysql_escape_string($key[0][1]) . "'";
            }
        } else {
            $queryString = ' '; // wenn kein such kriterium/filter
        }
        if (count($query) > 1) {
            if (!in_array($keys[0], $operators))
                unset($query[$keys[0]]);

            foreach ($query as $key => $val) {
                if (!in_array($key, $operators)) {
                    $queryString .= ' AND ' . mysql_escape_string($key) . '=' . "'" . mysql_escape_string($val) . "'";
                } else {
                    $queryString .= ' AND ' . mysql_escape_string($val[0]) . $key . " '" . mysql_escape_string($val[1]) . "'";
                }
                $i++;
            }
        }
        return $queryString;
    }

    /**
     *
     * @param array $data
     * @return <type>
     */
    public function validateInput($data = array()) {
        foreach ($data as $fieldname => $value) {
            $result = $this->validation->isValid($fieldname, $value);
            if (!$result->success)
                return $result->message;
        }
        return true;
    }

    /**
     *
     * @param array $data
     * @param array $where
     * @return <type>
     */
    public function insert($data = array()) {

        $data = $this->cleanData($data);
        $data['created'] = date('Y-m-d H:i:s', time());
        if ($this->validation = new corelib_validation( )) {
            $res = $this->validateInput($data);
            if (!is_bool($res))
                return $res;
        }

        $i = 0;
        $cdata = count($data);
        $queryString = '';
        $valueString = '';

        foreach ($data as $key => $val) {
            $i++;
            $comma = '';
            if ($i < $cdata)
                $comma .= ",";

            $queryString .= ' `' . $key . '` ' . $comma;
            $valueString .= "'" . ($val) . "'" . $comma;
        }
        $this->query("INSERT INTO `" . $this->model . "` (" . $queryString . ") VALUES (" . $valueString . ")");
        return mysql_insert_id();
    }

    /**
     * 
     * @param array $data
     * @param array $where
     * @return <type>
     */
    public function update($data = array(), $where = array()) {

        $data['updated'] = date('Y-m-d H:i:s', time());

        if ($this->validation = new corelib_validation( )) {
            $res = $this->validateInput($data);
            if (!is_bool($res))
                return $res;
        }

        $queryString = '';
        $i = 0;
        $cdata = count($data);

        foreach ($data as $key => $val) {
            $i++;
            $comma = '';
            if ($i < $cdata)
                $comma .= ",";
            $queryString .= ' `' . $key . '` =' . "'$val'" . $comma;
        }

        if(!$this->query("UPDATE `$this->model` SET " . $queryString . $this->_buildWhereQuery($where)))
            return false;

        if (isset($data['id'])) {
            $id = $data['id'];
        } else {
            $ste = $this->get($where, array('id'));
            $id = $ste['id'];
        }

        return $id;
    }

    /**
     * update oder insert
     * @param type $data
     * @param string $unique_fields
     * @return type 
     */
    public function upsert($data = array(), $unique_fields = array()) {

        $data = $this->cleanData($data);

        if (empty($unique_fields)) {
            $unique_fields = array('id');
        }

        $where = array();
        $vorhanden = false;
        foreach ($unique_fields as $val) {
            if (isset($data[$val]) && $this->get(array($val => $data[$val]))) {
                $vorhanden = true;

                $where[$val] = $data[$val];
            } else {
                $vorhanden = false;
            }
        }

        if ($vorhanden) {
            return $this->update($data, $where);
        } else {
            return $this->insert($data);
        }
    }

    /**
     *
     * @param array $where
     * @return <type>
     */
    public function delete($where = array()) {
        return $this->query("DELETE FROM `" . $this->model . "` " . $this->_buildWhereQuery($where));
    }

    /**
     *
     * @param string $query
     * @return <type>
     */
    public function query($query = 'SHOW tables;') {

        if ($this->debug)
            debug($query);

        $this->_result_pointer = mysql_query($query, $this->db);
        if (!$this->_result_pointer) {
            debug(mysql_error(), $query);
        }
        return $this->_result_pointer;
    }

    /**
     * gibt das nächste element zurück
     * @return <type>
     */
    public function getNext() {
        return mysql_fetch_assoc($this->_result_pointer);
    }

    /**
     *
     * @param string $query
     * @return <type>
     */
    public function find($query = '', $fields = array()) {

        if (!empty($fields)) {
            $fields = implode(',', $fields);
        }else
            $fields = '*';

        if (is_numeric($query)) {
            $query = "SELECT $fields FROM `" . $this->model . "` LIMIT 0, " . $query;
        } elseif (!empty($query)) {
            $query = "SELECT $fields FROM `" . $this->model . "` WHERE " . $query;
        } else {
            $query = "SELECT $fields FROM `" . $this->model . '`';
        }
        return $this->query($query);
    }

    /**
     *
     * @param type $field
     * @param type $searchstring
     * @param type $limit
     */
    public function search($field, $searchstring, $limit = 10) {
        $query = "SELECT * FROM `" . $this->model . '` WHERE ' . $field . " LIKE '%" . $searchstring . "%' LIMIT 0," . $limit;
        return $this->query($query);
    }

    /**
     * nimmt nur daten eines arrays wenn die db die felder auch besitzt
     * die Felder die nicht dem db schema entsprechen werden ignoriert
     * @param <type> $data
     * @return <type>
     */
    public function cleanData($data = array()) {
        $dbErwartet = $this->getFields($this->info());
        return array_intersect_key($data, $dbErwartet);
    }
    
    /**
     *
     * @param type $dbErwartet
     * @return type 
     */
    public function getFields($dbErwartet = array()) {
        $new = array();
        foreach ($dbErwartet as &$val) {
            $new[$val['Field']] = $val['Field'];
        }
        return $new;
    }

    /**
     * gibt schema der table zurück
     * @return <type>
     */
    public function info() {
        return $this->AlltoArray($this->query("DESCRIBE `$this->model`"));
    }

}

