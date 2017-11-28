<?php
class HttpClient {

    protected $_api;
    protected $_request;
    protected $_called;
    protected $_opts = array();

    public function __construct() {}

    public static function getInstance() {
        static $_instance = NULL;
        if (empty($_instance)) {
            $_instance = new static();
        }
        return $_instance;
    }

    public function post($url, $data = array(), $timeout = 30){
        if (is_array($data)) {
            $data = http_build_query($data);
        }

        $SSL = substr($url, 0, 8) == "https://" ? true : false;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout-2);
        if ($SSL) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); //避免data数据过长问题
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        foreach($this->_opts as $opt => $value){
            curl_setopt($ch, $opt, $value);
        }
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    public function get($url, $data = array(), $timeout = 30) {
        if (is_array($data)) {
            $data = http_build_query($data);
        }
        $flag = "?";
        if(strpos($url, "?")!== false){
            $flag = "&";
        }
        $url = $url.$flag.$data;
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout-2);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Expect:')); //避免data数据过长问题
        foreach($this->_opts as $opt => $value){
            curl_setopt($ch, $opt, $value);
        }
        $ret = curl_exec($ch);
        curl_close($ch);
        return $ret;
    }

    public function setCurlOpt($opts = array()){
        $this->_opts = $opts;
    }
}