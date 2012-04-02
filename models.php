<?php

/*
 * interface für db implementationen
 */
interface corelib_models {

    /**
     * @param <filepointer> $db
     * @return <type>
     */
    public function __construct($db = null);

    /**
     *
     * @param <array> $where
     * @return <type>
     */
    public function get($where = array());

    /**
     *
     * @param <type> $query
     * @param <array> $fields
     * @return <type>
     */
    public function find($query = '', $fields = array());

    /**
     * 
     * @param <type> $field
     * @param <type> $searchstring
     * @param <type> $limit
     * @return <type>
     */
    public function search($field, $searchstring, $limit = 10);

    /**
     *
     * @param <type> $id
     * @return <type>
     */
    public function getbyId($id);

    /**
     *
     * @param <type> $data
     * @param <type> $where
     * @return <type>
     */
    public function insert($data = array());

    /**
     *
     * @param <type> $data
     * @param <type> $where
     * @return <type>
     */
    public function update($data = array(), $where = array());

    /**
     *
     * @param <type> $data
     * @return <type>
     */
    public function upsert($data = array());

    /**
     *
     * @param <type> $where
     * @return <type>
     */
    public function delete($where = array());

    /**
     *
     * @param <type> $query
     * @return <type>
     */
    public function query($query = 'SELECT');
}

