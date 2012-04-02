<?php

class corelib_validation {

    private $_model;
    private $data = array();
    private $_validator = array(
        'SECRET' => array(
            'rule' => '', //'/{7,15}/',
            'message' => 105
        ),
        'USERNAME' => array(
            'rule' => '', //'/[A-Z_0-9]{4,15}/i',
            'method' => '_validation_unique',
            'message' => 105,
            'messageU' => 104
        ),
        'STUDIVZ_ID' => array(
            'rule' => '',
            'method' => '_validation_unique',
            'message' => 105,
            'messageU' => 106
        ),
        'PLZ' => array(
            'rule' => '/[0-9]/',
            'message' => 105
        ),
        'DAYOFBIRTH' => array(
            'rule' => '', //'/[0-9]-/',///(19|20)[0-9]{2}[- /.](0[1-9]|1[012])[- /.](0[1-9]|[12][0-9]|3[01])/',
            'message' => 105
            ));
    private $_result; // das resultat der validierung

    /**
     * für unique prüfung wird model übergeben
     * @param object $model
     */

    public function __construct($rules = array(), &$model = null) {
        $this->_model = $model;
        $this->_result->message = 101;
        $this->_result->success = true;
///////FALLS REGELN ÜBERGEBEN WERDEN DIESE HINZUFÜGEN//ODER ÜBERSCHREIBEN
        if (!empty($rules)) {
            $this->_validator = array_merge($this->_validator, $rules);
        }
    }

    /**
     * validierung
     * @param string $fieldname | (validierungsregel)
     * @param string $value | wert der validiert werden soll
     * @return stdClass $this->_result
     */
    public function isValid($fieldname, $value) {
        $fieldNameUpper = strtoupper($fieldname);

        if (isset($this->_validator[$fieldNameUpper])) {//exisiert eine validierung im $_this->validator array für das angegene Feld (z.b username )
            if ($this->_validator[$fieldNameUpper]['rule'] != "") {
                if (!preg_match($this->_validator[$fieldNameUpper]['rule'], $value)) {
                    $this->_result = (object) $this->_validator[$fieldNameUpper];
                    $this->_result->success = false;
                    $this->_result->field = $fieldname;
                }
            }

            if (isset($this->_validator[$fieldNameUpper]['method'])) {//wenn das feld eine methode für die prüfung hat... dann aufrufen
                $METHOD = $this->_validator[$fieldNameUpper]['method'];

                if (method_exists($this, $METHOD)) {
                    $this->_result = $this->{ $METHOD }($fieldname, $value);
                }
            }
        }
        return $this->_result;
    }

    public function notempty($fieldname, $value) {
        $value = trim($value);
        if (empty($value)) {
            $this->_result = (object) $this->_validator[strtoupper($fieldname)];
            $this->_result->success = false;
            $this->_result->field = $fieldname;
        }
        return $this->_result;
    }

    /**
     * @todo verallgemeinern bzw kein corelib
     * @param <type> $fieldname
     * @param <type> $value
     * @return <type>
     */
    public function iftext($fieldname, $value) {
        $value = trim($value);
        $valuem = trim(corelib_boot::$request['params']['text']);
        $valuemail = trim(corelib_boot::$request['params']['mail']);

        if (!empty($valuem) && $value == "false") {
            $this->_result = (object) $this->_validator[strtoupper($fieldname)];
            $this->_result->success = false;
            $this->_result->field = $fieldname;
        }

        if (!empty($valuem) && empty($valuemail)) {
            $this->_result = (object) $this->_validator['MAIL'];
            $this->_result->success = false;
            $this->_result->field = 'mail';
        }

        return $this->_result;
    }

}