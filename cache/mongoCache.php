<?php

/*
 * * corelibPHP by alexander kindziora 2012
 * and open the template in the editor.
 * corelib bereich caching ausbauen, soweit möglich auf memcached auslagern
 */

class corelib_cache_mongoCache implements corelib_cache {

    public $cache           = null;
    public $lifetime        = 15;
    public $use_compressed  = false;
    public $ns              = "";

    /**
    *
    * @param type $config 
    */
    public function __construct($config = array()) {
        $this->cache = new corelib_db_mongodb('_pagecache');
        try {
            if (isset($config['lifetime']))
                $this->lifetime = (int) $config['lifetime'];
            if (isset($config['ns']))
                $this->ns = (int) $config['ns'];
        } catch (Exception $e) {
            var_dump($e);
        }
        #$this->clean();
    }
    
    /**
     *
     * @param <string> id
     */
    public function load($id) {
        $resObj = new stdClass;

        $result = $this->cache->findOne(array('url' => $id));

        if ($result) {
            $resObj->data = $result;
            $resObj->success = true;
            return $resObj;
        } else {
            $resObj->data = $id;
            $resObj->success = false;
            return $resObj;
        }
    }

    /**
     *
     * @param type $id
     * @param type $data
     * @param type $time
     * @return type 
     */
    public function save($id, $data, $time = null) {
        if (null === $time)
            $time = $this->lifetime;
        $entry = array('url' => $id, 'data' => $data, 'lifetime' => $time, 'fid' => new MongoId('_pagecache'));
        return $this->cache->insert($entry);
    }

    /**
     * löscht ein oder alle elemente im cache
     * @param type $id
     * @return type 
     */
    public function clean($id = null) {
        if ($id != null) {
            return $this->cache->delete($this->ns . $id);
        } else {
            return $this->cache->flush();
        }
    }

}

