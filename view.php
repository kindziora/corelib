<?php

/*
 * * corelibPHP by alexander kindziora 2012
 */

class corelib_view {

    public $template        = null;
    public $sub_template    = null;
    private $_css           = array();
    private $_js            = array();
    private $_meta          = array();
    public $grid            = 1;
    public $region          = array();
    static $_config         = array();
    public $title           = null;

    /**
     *
     * @param <type> $config
     */
    public function __construct($config) {
        self::$_config = $config;
        if (isset(self::$_config['template']))
            $this->template = self::$_config['template'];
    }

    /**
     * return template name
     */
    public function __toString() {
        return $this->template;
    }

    /**
     * daten oder renderer in region adden
     * @param type $region
     * @param type $renderer
     * @param type $params
     * @param type $optionalTemplate
     */
    public function add($region, $renderer, $params, $optionalTemplate) {

        if (!isset(corelib_boot::$request['params']['noregion'])) {
            if (!is_object($renderer)) {
                $Content = $renderer;
            } else {
                $Content = $renderer->render($params, $optionalTemplate);
            }

            $this->region[$region] .= $Content;
        } else {
            $this->region[$region] = true;
        }
    }

    /**
     * 
     * @param <type> $i
     */
    public function grid($i = 0) {
        $this->grid++;
    }

    /**
     *
     * @return <type>
     */
    public function getCss() {
        if (self::$_config['merged_css'] == false) {
            $css = '';
            foreach ($this->_css as &$val)
                $css .= '<link href="' . $val . '" type="text/css" rel="stylesheet" />';
        } else {
            return $this->getCssMerged();
        }

        return $css;
    }

    /**
     * css files dem layout adden
     * @param <type> $filename
     */
    public function addCss($filename) {
        if (!is_array($filename)) {
            if (!empty($filename))
                $this->_css[md5($filename)] = self::$_config['home'] . $filename;
        } else {
            foreach ($filename as &$val) {
                $this->_css[md5($filename)] = self::$_config['home'] . $val;
            }
        }
    }

    /**
     *
     * @param type $destpFilename
     * @param type $files
     * @return string
     */
    public function getMerged($destpFilename, $files = array(), $mode = 'js') {
        
        $hashed             = array();
        $global             = self::$_config['home'] . $destpFilename;
        $absGlobal          = dirname(__FILE__) . "/../public/$destpFilename";
        $globalFilemtime    = @filemtime($absGlobal);
        $refresh            = false;

        foreach ($files as &$val) {
            $val = explode('/public/', $val);
            $val = $val[sizeof($val) - 1];
            $file = dirname(__FILE__) . "/../public/" . $val;
            $filetime = filemtime($file);
            $hashed[$val] = $filetime;
            if ($filetime > $globalFilemtime) {
                $refresh = true;
            }
        }
        $hash = md5(serialize($hashed));
        if ($refresh) {
            file_put_contents($absGlobal, '//hash:' . $hash . '//time:' . date('Y-m-d h:i:s') . '
				');
            $contents = "";
            foreach ($files as $key => &$val) {
                $val = explode('/public/', $val);
                $val = $val[sizeof($val) - 1];
                $file = dirname(__FILE__) . "/../public/" . $val;
                $contents .= utf8_decode(file_get_contents($file));
            }
            if ($mode == 'js')
                $contents = corelib_extern_JSMin::minify($contents);
            file_put_contents($absGlobal, $contents, FILE_APPEND);
        }
        return $global . '?' . $hash;
    }

    /**
     * gibt die js tags + files zurück
     * @return <type>
     */
    public function getJsMerged() {
        return '<script type="text/javascript" src="' . $this->getMerged('js/comp.js', $this->_js, 'js') . '"  ></script>';
    }

    /**
     * gibt die js tags + files zurück
     * @return <type>
     */
    public function getCssMerged() {
        return '<link href="' . $this->getMerged('css/comp.css', $this->_css, 'css') . '" type="text/css" rel="stylesheet" />';
    }

    /**
     * gibt die js tags + files zurück
     * @return <type>
     */
    public function getJs() {
        if (self::$_config['merged_js'] == false) {
            $js = '';
            foreach ($this->_js as &$val) {
                $js .= '<script type="text/javascript" src="' . $val . '"  ></script>';
            }
            return $js;
        } else {
            return $this->getJsMerged();
        }
    }

    /**
     * fügt dem layout js files an
     * @param <type> $filename
     */
    public function addJs($filename) {
        if (!empty($filename)) {
            if (!is_array($filename)) {
                if (!empty($filename))
                    if (!is_numeric(strpos($filename, 'http://')) && !is_numeric(strpos($filename, 'https://')))
                        $this->_js[md5($filename)] = self::$_config['home'] . $filename;
                    else
                        $this->_js[md5($filename)] = $filename;
            } else {
                foreach ($filename as &$val) {

                    if (!strpos($val, 'http://')&& !strpos($val, 'https://'))
                        $this->_js[md5($val)] = self::$_config['home'] . $val;
                    else
                        $this->_js[md5($val)] = $val;

                    $this->_js[md5($val)] = self::$_config['home'] . $val;
                }
            }
        }
    }

    /**
     * fügt dem layout metadaten hinzu
     * @param <type> $filename
     */
    public function addMeta($data) {
        if (!empty($filename)) {
            if (!is_array($data)) {
                $this->_meta[] = $data;
            } else {
                foreach ($data as &$val) {
                    $this->_meta[] = $val;
                }
            }
        }
    }

    /**
     * vom layout metadaten zurückgeben
     * @param <type> $filename
     */
    public function getMeta() {
        $meta_string = '';
        foreach ($this->_meta as &$val) {
            $meta_string .= $val;
        }
        return $meta_string;
    }

    /**
     * url formatieren
     * @example url('user/test'); => 'http://localhost/path/to/project/user/test'
     * @param <type> $uri
     * @return <type>
     */
    public static function url($uri, $params = array()) {
        #$params = $params + corelib_boot::$request['params'];
        //language prefix
        if (!empty($_SESSION['corelib']['language'])) {

            if ($_SESSION['corelib']['language'] != corelib_boot::$_config['multi_language']['base_language']) {
                if ((strpos($uri, 'img/') === false) &&
                        (strpos($uri, 'css/') === false) &&
                        (strpos($uri, 'js/') === false) &&  (strpos($uri, (STRING) $_SESSION['corelib']['language']) === false)) {
                    $prefix = (STRING) $_SESSION['corelib']['language'] . '/';
                    if ((STRING) $_SESSION['corelib']['language'] == "Array")
                        $prefix = '';
                }
            }
        }
        return 'http://' . $_SERVER['HTTP_HOST'] . self::$_config['home'] . self::cleanUri($prefix . $uri) . self::paramsToString($params);
    }

    /**
     *
     * @param <type> $params
     * @return string
     */
    public static function paramsToString($params = array()) {
        $strparams = "";
        if (!empty($params)) {
            $strparams = "?";
            foreach ($params as $key => &$val) {
                $strparams .= $key . "=" . $val . "&";
            }
        }
        return rtrim($strparams, '&');
    }

    /**
     * url encoden
     * @todo zusätzliches umwandeln von sonderzeichen in url's
     * @param <type> $uri
     * @return <type>
     */
    public static function cleanUri($uri) {
        $arr = explode('/', $uri);
        $arr = array_map('urlencode', $arr);
        return implode('/', $arr);
    }
    
    /**
     *
     * @param type $data 
     */
    public function _preRender(&$data) {
        
    }

    /**
     *
     * @param <type> $path
     * @param <type> $args
     * @return <type>
     */
    public function render($path, &$args) {
        /**
         * @todo noch gzip serverseitig für die css und js, auch für html?
         * if (substr_count($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip'))
         * ob_start("ob_gzhandler");
         */
 
        $this->_preRender($args);
        ob_start();
        if (!empty($args))
            extract($args);

        if (!isset($this->none))
            include_once( $path );

        $contents = ob_get_contents();
        ob_end_clean();

        return $contents;
    }

    /**
     *
     * @return <type>
     */
    public function getTitle() {
        return $this->title;
    }

}
