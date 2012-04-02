<?php

/*
 * * corelibPHP by alexander kindziora 2012
 * and open the template in the editor.
 * corelib bereich caching ausbauen, soweit möglich auf memcached auslagern
 */

class corelib_cache_memcached implements corelib_cache {

    public $cache           = null;
    public $lifetime        = 15;
    public $use_compressed  = false;
    public $ns              = "";

    /**
     *
     * @param <type> $host
     * @param <type> $port
     */
    public function __construct($config = array()) {

        $this->cache = new Memcache();
        try {
            $this->cache->connect($config['host'], $config['port']);
            if (isset($config['lifetime']))
                $this->lifetime = (int) $config['lifetime'];
            if (isset($config['ns']))
                $this->ns = (string) $config['ns'];
        } catch (Exception $e) {
            $this->cache->connect('127.0.0.1', 11211);
        }
        #$this->clean();
    }

    /**
     *
     * @param <string> id
     */
    public function load($id) {
        $resObj = new stdClass;
        $id = $this->ns . md5($id);
        $result = $this->cache->get($id);

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
     * @param <string> id
     * @param <string> data
     */
    public function save($id, $data, $time = null) {
        if (stripos($id, $this->ns) === 0) {
            #ist schon eine interen cache id
            $id = $id;
        } else {
            $id = $this->ns . md5($id);
        }
        if (null === $time)
            $time = $this->lifetime;
        return $this->cache->set($id, $data, $this->use_compressed, $time);
    }

    /**
     * löscht ein oder alle elemente im cache
     */
    public function clean($id = null) {
        if ($id != null) {
            return $this->cache->delete($this->ns . $id);
        } else {
            return $this->cache->flush();
        }
    }

}

