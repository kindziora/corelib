<?php

/*
 * * corelibPHP by alexander kindziora 2012
 * and open the template in the editor.
 */

class corelib_helper {

    public $view;
    public $pages       = array();
    public $params      = array();
    public $template    = null;
    public $caching     = array(
        'active' => true,
        'deliver' => 'everytime', // normal mode
            //	'deliver' => 'once'// delivered once and not providing render output until client side triggers it
    );

    /**
     *
     * @param <type> $view
     */
    public function __construct(&$view = null) {
        if ($view != null)
            $this->view = $view;
        else
            $this->view = corelib_boot::$_view;
        $this->init();
    }

    /**
     *
     */
    public function __toString() {
        return get_class($this);
    }

    /**
     * initialisieren des helpers
     */
    public function init() {
        if (isset($this->Js)) {
            $this->view->addJs($this->Js);
        } else {
            $files = $this->auto_loadResources('js');
            $this->view->addJs($files);
        }

        if (isset($this->Css)) {
            $this->view->addCss($this->Css);
        } else {
            $this->view->addCss($this->auto_loadResources('css'));
        }

        $this->params = corelib_boot::$request['params'];
    }

    /**
     *
     * @param <type> $location
     * @return <type>
     */
    public function auto_loadResources($location) {
        //dummy
        /*
          $files = $this->scanDirectory(dirname(__FILE__) . "/$location/");
          foreach ($files as &$val) {
          $val = dirname(__FILE__) . "/$location/" . $val;
          }
          return $files;
         * */
    }

    /**
     *
     * @param <type> $startDirectory
     * @param <type> $x
     * @param <type> $start
     * @return string
     */
    public function scanDirectory($startDirectory) {
        return array_diff(scandir($startDirectory), array(".", "..", ".svn"));
    }

    /**
     * vor dem rendern
     */
    public function preRender(&$data = array(), $template_file) {


        return $data;
    }

    /**
     * rendern von kleinen templates für helper
     * @param <type> $data
     * @param <type> $template_file
     * @return <type>
     */
    public function render($data = array(), $template_file = '') {

        $data = $this->preRender($data, &$template_file);

        $parts = explode('_', get_class($this));
        $small = $parts;
        unset($small[count($small) - 1]);
        $path = implode('/', $small);

        if (empty($template_file)) {
            $file = end($parts);
            if ($file == "")
                $file = 'index';
            $template_file =  corelib_boot::$deepfolder . corelib_boot::$app . '/' . $path . '/tpl/' . $file . '.php';
        }else {
            $template_file =  corelib_boot::$deepfolder . corelib_boot::$app . '/' . $path . '/tpl/' . $template_file . '.php';
        }

///RENDER PROZESS///////////////////////////////////////////////////////
        ob_start();
        if (!empty($data))
            extract($data);
        include( $template_file );
        $this->postRender();
        $contents = ob_get_contents();
        ob_end_clean();
////////////////////////////////////////////////////////////////////////

        return $contents;
    }

    public function postRender() {
        
    }

}
