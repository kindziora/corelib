<?php
/*
 * * corelibPHP by alexander kindziora 2012
 * * bootstrap klasse
 */
class corelib_boot {

    public static $_config;
    public static $db           = null;
    public static $request      = null;
    public static $response     = array();
    protected $_cache           = null;
    public static $_view        = null;
    public static $global       = null;
    public $view_template       = null;
    public $controllerOBJ;
    public static $app          = 'app';
    public static $translator   = null;
    public static $isAjax       = false;
    public static $deepfolder   = '../../';

    /**
     *
     * @param <type> $name
     * @return <type>
     */
    public function __get($name) {
        if (isset($this->$name))
            return $this->$name;
    }

    /**
     * simpler autoloader
     * @param <type> $class_name
     */
    public function autoloader() {
        spl_autoload_register(function ( $class_name ) {
            $path = str_replace("_", "/", $class_name);
            if (!class_exists($class_name)) {
                if (!is_numeric(strpos($class_name, 'corelib'))) {
                    //HELPER
                    if (is_numeric(strpos($class_name, 'helper')) && substr($class_name, -1) == '_') {
                        $path = corelib_boot::$app . '/' . $path . '/index';
                        if (file_exists(corelib_boot::$deepfolder . $path . '.php'))
                            require_once corelib_boot::$deepfolder . $path . '.php';
                        return true;
                    } elseif (is_numeric(strpos($class_name, 'helper'))) {
                        $path = corelib_boot::$app . '/' . $path;
                    }
                    //MODEL
                    if (is_numeric(strpos($class_name, 'model'))) {
                        $path = corelib_boot::$app . '/' . $path;
                        if (file_exists(corelib_boot::$deepfolder . $path . '.php'))
                            require_once corelib_boot::$deepfolder . $path . '.php';
                        return true;
                    }
                    //CONTROLLER
                    if (is_numeric(strpos($class_name, 'controller'))) {
                        $path = corelib_boot::$app . '/' . $path;
                    }
                }

                if (!file_exists(corelib_boot::$deepfolder . $path . '.php')) {
                    require_once corelib_boot::$deepfolder . corelib_boot::$app . '/controller/error.php';
                    return false;
                } else {
                    require corelib_boot::$deepfolder . $path . '.php';
                }
                return true;
            }
        });
    }

    /**
     * konstruktor für app
     * @param string $application
     */
    public function __construct($application = 'app') {
        session_start();
        date_default_timezone_set('Europe/Berlin');
        
        $this->autoloader();

        if ($application != self::$app) {
            self::$app = $application;
        }
        $env            = (getenv('APPLICATION_ENV')) ? getenv('APPLICATION_ENV') : 'config';
        $ConfigLocation = static::$deepfolder . self::$app . '/config/' . $env . '.php';
        self::$_config  = require_once((file_exists($ConfigLocation)) ?
                            $ConfigLocation : static::$deepfolder . self::$app . '/config/config.php');
        
        $this->setErrorReporting();

        if (self::$_config['multi_language']) {
            if (self::$_config['db']['type'] == 'mongodb') {
                self::$translator = new helper_translationMongo_();
            } else {
                self::$translator = new helper_translation_();
            }
        }
    }

    /**
     * 
     */
    protected function setErrorReporting() {
       // error_reporting(((self::$_config['debug']) ? E_All : E_ERROR));
    }

    /**
     * handelt einen request und routet ihn auf einen controller und eine Action
     */
    public function handle() {
        $this->initDb(((!isset(self::$_config['db']['type'])) ? 'mysql' : self::$_config['db']['type']));
        $request = new corelib_request(self::$_config['routing'], self::$_config['reg_routing']);
        $path = parse_url($_SERVER['REQUEST_URI']);

        if (self::$_config['home'] == "/" || self::$_config['home'] == "") {
            $uri = ltrim($path['path'], '/');
        } else {
            $uri = str_replace(self::$_config['home'], '', $path['path']);
        }

        if ("" == $uri || "/" == $uri) {
            $uri = self::$_config['routing']['default']['controller'] . '/' . self::$_config['routing']['default']['action'];
        }
        self::$isAjax            = $request->isAjax();
        self::$request           = $request->route($uri);
        self::$request['uri']    = $uri;

        if (self::$request['action'] == "")
            self::$request['action'] = self::$_config['routing']['default']['action'];

        $this->_run( self::$request['params'] );
    }

    /**
     *
     * @return <type>
     */
    public function initDb($type = 'mysql') {
        if ($type == 'mysql') {
            self::$db = mysql_connect(self::$_config['db']['host'], self::$_config['db']['user'], self::$_config['db']['pw']);
            mysql_select_db(self::$_config['db']['dbname']);
            mysql_query("SET NAMES 'utf8'");
            mysql_query("SET CHARACTER SET 'utf8'");
            self::$_config['db']['abstraction_layer'] = 'corelib_db_mysql';
        } elseif ($type == 'mongodb') {
            try {
                self::$_config['db']['abstraction_layer'] = 'corelib_db_mongodb';
                self::$db = new Mongo(self::$_config['db']['host'], array("connect" => self::$_config['db']['connect']));
                self::$db->connect();
                self::$db = self::$db->selectDB(self::$_config['db']['dbname']);
            } catch (Exception $e) {

                debug(self::$_config['db'], true);
                die("Mongodb connection failed");
            }
        }
        return self::$db;
    }

    /**
     * je nach caching klasse, in dem fall memcached
     */
    public function initCache() {
        $cacheclass = "corelib_cache_mongoCache";
        if (self::$_config['caching']['type']) {
            $cacheclass = "corelib_cache_" . self::$_config['caching']['type'] . "Cache";
        }
        $this->_cache = new $cacheclass(self::$_config['caching']);
    }
    /**
     *init bootstrap 
     */
    public function init() {
        self::$_view->addJs(self::$_config['js']);
        self::$_view->addCss(self::$_config['css']);
    }

    /**
     * führt action aus und übergibt die params
     */
    private function _run($params = array()) {

        if (self::$_config['caching']) {
            $this->initCache();
        }
        if (!self::$_config['caching']) {
            L_render:
                
            self::$_view    = new corelib_view(self::$_config);
            
            $this->init();
            
            $controllerName = 'controller_' . self::$request['controller'];
            $realfile       = str_replace("_", "/", $controllerName);
            
            if (empty($realfile) || !file_exists(self::$deepfolder . self::$app . '/' . $realfile . '.php')) {
                $cn = self::$_config['routing']['default']['controller'] ? self::$_config['routing']['default']['controller'] : 'error';
                $controllerName = 'controller_' . $cn;
            }
            $this->controllerOBJ = new $controllerName($params, self::$db, self::$_view);
            //sicherheitsmechanismus
            if (substr(self::$request['action'], 0, 1) != '__') {
                self::$request['action'] = self::$request['action'] ? self::$request['action'] : 'index';
                $data = (array) $this->controllerOBJ->{self::$request['action']}(isset($params['ajax']));
            } else {
                $data = (array) $this->controllerOBJ->_error('METHOD NOT ALLOWED');
            }
            $this->controllerOBJ->_postAction($data);
            if (!empty(self::$response))
                $data['data']['result'] = (array) $data['data']['result'] + (array) self::$response;

            $content = $this->render(self::$request['controller'], self::$request['action'], $data);

            echo $content;
            if (self::$_config['caching']) {
                $this->_cache->save($_SERVER['REQUEST_URI'], $content);
            }

            //STATIC PAGE CACHING PART//////////////////////////////////////////////
        } else {
            $cache = $this->_cache->load($_SERVER['REQUEST_URI']);
            #file_put_contents(urlencode($_SERVER['REQUEST_URI']) . '.htm', $data->data);

            if (!$cache->success) {
                goto L_render;
            } else {
                echo $cache->data['data'];
            }
        }
    }

    /**
     *
     * @param <type> $controller
     * @param <type> $action
     * @param <type> $args
     */
    public function render($controller, $action, &$args = array()) {
        #error_reporting(E_ALL);
        if (isset($args['ajax']) && $args['ajax'] == true || self::$isAjax || isset(self::$_view->region[self::$request['params']['region']])) {

            if (isset(self::$request['params']['jsonp_callback']))
                self::$_view->template = 'jsonp';
            else
                self::$_view->template = 'data';

            self::$_view->sub_template = 'none';
        }

        if (!isset(self::$_view->sub_template)) {
            self::$_view->sub_template = $action;
        }

        if (self::$_view->sub_template != 'none') {
            $path = self::$deepfolder . self::$app . '/views/' . $controller . '/' . self::$_view->sub_template . '.php';
            $content = array('content' => self::$_view->render($path, $args));
        } else {
            $content = array('content' => $args['data']);
        }

        $content = array('region' => self::$_view->region) + (array) $content;

        if (isset(self::$_view->region[self::$request['params']['region']])) {
            $content['content'] = self::$_view->region[self::$request['params']['region']];
        }

        if (self::$request['params']['extended']) {
            $content['content'] = $content;
        }

        return self::$_view->render(self::$deepfolder . self::$app . '/layouts/' . self::$_view->template . '.php', $content);
    }

    /**
     *
     * @param type $method
     * @param type $arguments
     * @return type
     */
    public function __call($method, $arguments) {
        return $this->instance->{$method}($arguments);
    }

}

/**
 *
 * @param <type> $data
 * @param <type> $force_print_out
 *
 */
function debug($data, $force_print_out = false) {
    #error_reporting(E_ALL);
    $files = debug_backtrace($data);
    foreach ($files as $file)
        $filetrack[] = substr($file['file'], strpos($file['file'], 'dynamo')) . '[' . $file['class'] . (( $file['type'] ) ? $file['type'] : '') . $file['function'] . '(' . implode(',', array_values($file['args'])) . ')] Line ' . $file['line'];

    if (!corelib_boot::$_config['debug']['console.log']) {
        if (isset($_REQUEST['debug']) || corelib_boot::$_config['debug'] || $force_print_out) {
            foreach ($files as $k => $file)
                $filetracki .= "<li>" . $filetrack[$k] . '~<pre style="font-size:90%;">' . var_export($file['args'], true) . '</pre>' . "</li>";
            $filetrack = '<ul class="debuglist debugstyle" style="">' . $filetracki . '</ul>';
            echo $filetrack;
            echo "<pre class='debugstyle' style=''>";
            var_dump($data);
            echo "</pre>";
        }
    } else {
        $cnt = count($files);
        foreach ($files as $k => $file)
            $filetracki[] = array('called as ' . ($cnt - $k) => $filetrack[$k], 'args' => $file['args']);
        echo '<script language="Javascript">';
        echo 'console.log(' . json_encode($data) . ',' . json_encode($filetracki) . ');';
        echo '</script>';
    }
}