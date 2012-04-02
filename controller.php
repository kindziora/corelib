<?php

/*
 * * corelibPHP by alexander kindziora 2012
 * and open the template in the editor.
 */

class corelib_controller {

    public $params;
    public $model       = null;
    public $view        = null;
    public $dblayer     = null;
    public $messages    = array();

    /**
     *
     * @param <type> $params
     * @param <type> $db
     * @param <type> $view
     */
    public function __construct($params, $db, &$view) {
        $this->view = $view;
        $this->dblayer = main::$_config['db']['abstraction_layer'];

        $this->params = $params;
        if (strlen($this->model) > 1) {
            $modelName = "models_" . $this->model;
            $this->model = new $modelName($db);
        } else if ($this->model !== false) {
            $modelName = str_replace('controller', 'models', get_class($this));
            $this->model = new $modelName($db);
        } else if ($this->model != null) {
            $modelName = 'models_' . $this->model;
            $this->model = new $modelName($db);
        }
        $this->view->title = get_class($this);
        $this->init();
    }

    /**
     * nachricht auf stack legen
     * @param <type> $msg
     */
    protected function setMessage($msg = array()) {
        array_push($this->messages, $msg);
        $_SESSION['Msg'] = $this->messages;
    }

    /**
     * nachricht vom stack holen
     * @return <type>
     */
    protected function getMessage() {
        $this->messages = $_SESSION['Msg'];
        $msg = array_pop($this->messages);
        $_SESSION['Msg'] = $this->messages;
        return $msg;
    }

    /**
     * alle nachrichten vom stack holen
     * @return <type>
     */
    protected function getMessages() {
        $tmp = $_SESSION['Msg'];
        unset($_SESSION['Msg']);
        return $tmp;
    }

    /**
     *
     * @param <type> $url
     * @param <type> $time
     */
    protected function redirect($url = '', $time = 0, $message = array(), $params = array()) {
        if ($time > 0)
            header('refresh: ' . $time . ';' . $this->view->url($url, $params));
        else
            header('location: ' . $this->view->url($url, $params));

        if (!empty($message)) {
            $this->setMessage($message);
        }
    }

    protected function init() {
        
    }

    /**
     *
     * @param <type> $name
     * @param <type> $arguments
     */
    public function __call($name, $arguments) {
        // Note: value of $name is case sensitive.
        return $this->view($arguments[0], $name);
    }

    /**
     *
     * @param <type> $isAjax
     * @param <type> $name
     * @return <type>
     */
    public function view($isAjax, $name = null) {
        $data = array();
        if ((integer) $name != 0) {
            $data = $this->model->getbyId((integer) $name);
        } else if (is_string($name)) {
            if (strpos($name, '/') > 0) {
                $params = explode('/', $name);
            }
            if (is_object($this->model)) {
                $data = $this->model->find(" `title` LIKE '%$name%' ");
            } else {
                $data = "Die angeforderte Seite Existiert nicht!";
                $this->redirect();
            }
        }
        $this->view->sub_template = "index";

        return array('data' => $data, 'isAjax' => $isAjax);
    }

    /**
     * gibt parameter order default zurück
     * @param <type> $name
     * @param <type> $default
     */
    protected function getParam($name, $default = false) {
        if (isset($this->params[$name])) {
            return $this->params[$name];
        } else {
            return $default;
        }
    }

    /**
     *
     * @param type $data
     */
    public function _postAction(&$data) {
        $template_helper = new helper_block_();
        $msgs = $this->getMessages();
        if ($msgs) {
            foreach ($msgs as $msg) {
                $nachrichten .= $template_helper->render(array('msg' => $msg), 'msg');
            }
            $this->view->add('content_top', $nachrichten);
        }
    }

}

