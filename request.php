<?php

/*
 * * corelibPHP by alexander kindziora 2012
 * and open the template in the editor.
 */

class corelib_request {

    public $routes;
    public $regRoutes;
    public $urls = null;

    /**
     *
     * @param type $routes
     * @param type $regRoutes 
     */
    public function __construct($routes, $regRoutes = null) {
        $this->routes = $routes;
        $this->regRoutes = $regRoutes;
        $this->urls = new corelib_boot::$_config['db']['abstraction_layer']('urlAlias');
    }

    /**
     *
     * @return type
     */
    public function isAjax() {
        return (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest');
    }

    /**
     *
     * @param <type> $url_data
     * @return <type>
     */
    public function route($url_data) {
        #	$this->log = new corelib_db_mongodb('log');
        #	$this->log->insert(array($url_data));
        $result_Route = $this->_load_statics($url_data); // load static routes from php config array

        if ($result_Route['type'] != 'static') {
            $result_Route = $this->_load_regex($result_Route['uri']); // load regex rules from php config array

            if ($result_Route) {
                $result_ = $this->load_from_db($result_Route['uri']);  // load routes from db
                if (!$result_) {
                    $this->_save_to_db($result_Route['uri']);
                }
            } else {
                $result_ = $this->load_from_db($result_Route['uri']);
                if (!$result_) {
                    $result_Route = $result_;
                }
            }
        }

        return $result_Route;
    }

    /**
     *
     * @param <type> $dirty
     * @return <type>
     */
    public function cleanRequest($dirty = array()) {
        $clean_array = array();
        foreach ($dirty as $key => $val) {
            if (!is_array($val)) {
                $clean_array[$key] = mysql_escape_string($val);
            } else {
                $clean_array[$key] = $this->cleanRequest($val);
            }
        }
        unset($clean_array['ZDEDebuggerPresent']);
        unset($clean_array['PHPSESSID']);

        return $clean_array;
    }

    /**
     * prüft ob es sich um eine bekannte route handelt
     * @param array $url_data
     * @return <type>
     */
    private function _load_statics($url) {

        if (isset($url) && isset($this->routes[$url])) {
            $regel = $this->routes[$url];

            if (!isset($regel['params'])) {
                return array('controller' => $regel['controller'], 'action' => $regel['action'], 'params' => $this->cleanRequest($_REQUEST), 'type' => 'static', 'uri' => $url);
            } else {
                return array('controller' => $regel['controller'], 'action' => $regel['action'], 'params' => (array) $regel['params'] + $this->cleanRequest($_REQUEST), 'type' => 'static', 'uri' => $url);
            }
        } else if (isset($url)) { // route nicht in statisch gefunden
            $baseUrl = explode('/', $url);
            //bei mehrsprachiger seite, /languageCode/controller/action
            if (corelib_boot::$_config['multi_language'] && corelib_boot::$translator->getLanguage($baseUrl[0])) {

                if (!isset($baseUrl[2])) {
                    $baseUrl[2] = $this->routes['default']['action'];
                }
                $_REQUEST['language'] = $baseUrl[0];
                unset($baseUrl[0]);
                return $this->_load_statics(implode('/', $baseUrl));
            } else { //keine mehrsprachige seite also /controller/action
                return $this->_loadLikeDefault($url);
            }
        }
    }

    /**
     * default explode auf uri controller/action durchführen ... +  params
     * @param <type> $url
     * @return <type>
     */
    private function _loadLikeDefault($url) {
        $baseUrl = explode('/', $url);
        if (!isset($baseUrl[1])) {
            $baseUrl[1] = $this->routes['default']['action'];
        }
        return array('controller' => $baseUrl[0], 'action' => $baseUrl[1], 'params' => $this->cleanRequest($_POST + $_REQUEST), 'type' => 'default', 'uri' => $url);
    }

    /**
     * prüft ob es sich um eine bekannte route handelt
     * @param array $url_data
     * @return <type>
     */
    private function _load_regex($url) {
        if (isset($url) && !empty($this->regRoutes)) {
            $regex = array_keys($this->regRoutes);
            $rules = array_values($this->regRoutes);
            $result_tmp = preg_replace($regex, $rules, $url);
            if (!$result_tmp) {
                return false;
            }
            if (substr($result_tmp, 0, 1) == '/')
                $result_tmp = substr($result_tmp, 1, strlen($result_tmp) - 1);
            return $this->_loadLikeDefault($result_tmp);
        }
        return false;
    }

    /**
     * url path aus db laden
     * @param <type> $url
     * @return <type>
     */
    public function load_from_db($url) {
        $found = $this->urls->alltoarray($this->urls->find(array('url' => $url))->limit(1));
        return $found['intern'];
    }

    /**
     * url in db speichern
     * @param <type> $url
     * @return <type>
     */
    private function _save_to_db($url) {

        $insert = array();
        $insert['intern'] = $this->_loadLikeDefault($url);
        $insert['url'] = $url;
        $result = $this->urls->insert($insert);
        if ($result) {
            $this->urls->ensureIndex(array('url' => true));
        }
        return $result;
    }

}