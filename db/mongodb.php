<?php

/*
 * * corelibPHP by alexander kindziora 2012
 * mongodb abstration layer für corelib
 */

class corelib_db_mongodb implements corelib_models {

    public $db;
    public $model;
    protected $_fields  = Array();
    private $_name      = null;

    /**
     * name der collection ohne db name suffix
     * @return <type>
     */
    public function name() {
        return $this->_name;
    }

    /**
     * get internal name of model mit db suffix etc.
     */
    public function __toString() {
        return (STRING) $this->model;
    }

    /**
     *
     * @param <type> $db
     * @param <type> $collection
     */
    public function __construct($db = null, $collection = null) {

        if ($db != null && !is_string($db))
            $this->db = $db;
        else if (is_string($db)) {
            $collection = $db;
            $this->db = corelib_boot::$db;
        } else {
            $this->db = corelib_boot::$db;
        }
        if ($collection) {
            $this->model = $this->db->$collection;
        } else {
            $this->model = $this->db->{$this->model};
        }
        $this->_name = explode('.', (STRING) $this->model);
        $this->_name = $this->_name[1];
    }

    /**
     * methoden die nicht implementiert sind aber die mongo kennt ...
     * @param <type> $name
     * @param <type> $args
     * @return <type>
     */
    public function __call($name, $args) {
        #error_reporting(E_ALL);

        if ($this->model) {
            return call_user_func_array(array($this->model, $name), $args);
        } else {
            debug($name);
            debug('$this->model does not exist.');
        }
    }

    /**
     *
     * @param <type> $where
     * @return <type>
     */
    public function count($where = array()) {
        return (integer) $this->model->find($where)->count();
    }

    /**
     *
     * @param <type> $where
     * @param <type> $fields
     * @param <type> $sort
     * @return <type>
     */
    public function get($where = array(), $fields = array(), $sort = null) {
        $data = array();

        $cursor = $this->model->find($where, $fields);

        if ($sort !== null)
            $cursor->sort($sort);
        while ($cursor->hasNext()) {
            $data[] = $cursor->getNext();
        }

        return $data;
    }

    /**
     *
     * @param mysql result $result_ref
     * @return array
     */
    public function AlltoArray($result_ref, $force_multi_dimension = true) {
        $result_array = array();
        while ($result_ref->hasNext()) {
            $result_array[] = $result_ref->getNext();
        }
        if ($force_multi_dimension) {
            if (count($result_array) == 1)
                $result_array = $result_array[0];
        }
        return (array) $result_array;
    }

    /**
     *
     * @param type $query
     * @param type $fields
     * @return type 
     */
    public function findOne($query = array(), $fields = array()) {

        return $this->model->findOne($query, $fields);
    }

    /**
     *
     * @param type $query
     * @param type $fields
     * @return type 
     */
    public function find($query = '', $fields = array()) {
        return $this->model->find($query, $fields);
    }

    /**
     *  
     * @param type $field
     * @param type $searchstring
     * @param type $limit
     * @return type 
     */
    public function search($field, $searchstring, $limit = 10) {
        return $this->model->find(array($field => new MongoRegex('/.*' . preg_quote($searchstring, '/') . '.*/i')))->limit($limit);
    }

    /**
     *
     * @param <type> $id
     * @return <type>
     */
    public function getbyId($id) {
        $qu['_id'] = $this->_getId($id);
        #debug((string)$data['_id'], true);
        return $this->findOne($qu);
    }

    /**
     *
     * @param <type> $query
     * @return <type>
     */
    private function _buildWhereQuery($query = array()) {
        
    }

    /**
     *
     * @param <type> $data
     * @param <type> $where
     * @return <type>
     */
    public function insert($data = array()) {
        $data['updated'] = new MongoDate();
        if ($data['_id']) {
            $data['_id'] = $this->_getId($data['_id']);
        }

        if (!isset($data['created']))
            $data['created'] = new MongoDate();
        if (!isset($data['status']))
            $data['status'] = 0;



        $this->model->save($data);

        //$data = $this->get( $data );

        return $data['_id'];
    }

    /**
     *
     * @param <type> $data
     * @param <type> $where
     * @return <type>
     */
    public function update($data = array(), $where = array()) {
        $data['updated'] = new MongoDate();
        $upd = $data;
        unset($upd['_id']);
        $this->model->update($where, array('$set' => $upd));
       // $result = $this->db->lastError();
        return $data['_id'];
    }

    /**
     * wenn return der id benötigt ist ansonsten save( $data = array() )
     * @param <type> $data
     * @return MongoId
     */
    public function upsert($data = array(), $where = array(), $atomar = false) {
        $data['updated'] = new MongoDate();
        if (!isset($data['created']))
            $data['created'] = new MongoDate();
        if (!isset($data['status']))
            $data['status'] = 0;
        if (!empty($where)) {
            $res = $this->AlltoArray($this->find($where)->limit(1));

            unset($data['where']);
        } else {
            $res = $this->getbyId($data['_id']);
        }

        if (!empty($res)) {
            $upd = $data;
            unset($upd['_id']);
            if (!$atomar)
                $this->model->update(array('_id' => new MongoId($res['_id'])), array('$set' => $upd));
            else
                $this->model->update(array('_id' => new MongoId($res['_id'])), $upd);
        }else {
            $data['_id'] = new MongoId( );
            $this->model->insert($data, array('safe' => true));
        }
        $result = $this->db->lastError();

        return $data['_id'];
    }

    /**
     *
     * @param <type> $where
     * @return <type>
     */
    public function delete($where = array()) {
        $this->model->delete($where);
    }

    /**
     *
     * @param <type> $query
     * @return <type>
     */
    public function query($query = 'SELECT') {
        
    }

    /**
     *
     * @param <type> $query
     * @param <type> $fields
     * @param <type> $sort
     * @return <type>
     */
    public function findAll($query = array(), $fields = array(), $sort = null) {
        $data = array();
        $cursor = $this->find($query, $fields);
        if ($sort !== null)
            $cursor->sort($sort);
        while ($cursor->hasNext()) {
            $data[] = $cursor->getNext();
        }
        return $data;
    }

    /**
     *
     * @param <type> $key
     * @return MongoId
     */
    public function _getId($key) {
        if (is_array($key) && isset($key['_id']))
            $key = $key['_id'];
        return new MongoId($key);
    }

    /**
     *
     * @param <type> $key
     * @return <type>
     */
    public function _getDbRef($key) {
        $id = $this->_getId($key);
        return $this->app->db->createDbRef($this->_name, $id);
    }

    /**
     *
     * @param <type> $keys
     * @param <type> $initial
     * @param <type> $reduce
     * @param <type> $condition
     * @return <type>
     */
    public function group($keys = array(), $initial = array('count' => 0), $reduce = "function (obj, prev) { prev.count++; }", $condition = array()) {
        return $this->table->group($keys, $initial, $reduce, $condition);
    }

    /**
     *
     * @return <type>
     */
    public function groupByTypes() {
        $initial    = array("types" => new stdClass);
        $reduce     = "function (obj, prev) {
			if(!prev.types[obj.type])
				prev.types[obj.type] = 0;
			prev.types[obj.type]++;
		}";
        $ret    = $this->group(array('type'), $initial, $reduce);
        $types  = isset($ret['retval'][0]) ? $ret['retval'][0]['types'] : array();
        return (array) $types;
    }

    /**
     *
     * @param <type> $field
     * @return <type>
     */
    public function groupAndCountBy($field) {
        $initial    = array("types" => new stdClass);
        $reduce     = "function (obj, prev) {
			if(!prev.types[obj.$field])
				prev.types[obj.$field] = 0;
			prev.types[obj.$field]++;
		}";
        $ret    = $this->group(array($field), $initial, $reduce);
        $types  = isset($ret['retval'][0]) ? $ret['retval'][0]['types'] : array();
        return (array) $types;
    }

}

