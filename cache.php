<?php

/*
 * Schnittstelle für db klassen
 */
interface corelib_cache {
    
    /**
     * 
     */
    public function __construct($config = array());
    
    /**
     * lade eintrag
     */
    public function load($data);
    
    /**
     * speicher eintrag
     */
    public function save($id, $data, $lifetime = null);
    
    /**
     * lösche cache eintrag
     */
    public function clean($id = null);
}

