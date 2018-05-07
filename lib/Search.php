<?php
class Search{
    
    const DEFAULT_PORT = 9312;
    const DEFAULT_TIMEOUT = 3;
        
    protected static $instance;
    
    //获取 Redis 的实例
    public function __construct() {
    }
    
    public static function getSphinxInstance() {
        static $_instance = NULL;
        if ( empty( $_instance ) ) {
            $_instance = new SphinxClient;
            $config = C("sphinx");
            $host = isset($config['host'])?$config['host']:'localhost';
            $port = isset($config['port'])?$config['port']:self::DEFAULT_PORT;
            $_instance->setServer($host, $port);
            $_instance->setMatchMode(SPH_MATCH_ALL);
            $_instance->setArrayResult(true);
            $_instance->setMaxQueryTime(5);
        }
        return $_instance;
    }    
    
    //设置sphinxMode
    public function setMatchMode($mode = SPH_MATCH_ANY) {
        $_instance = self::getSphinxInstance();
        $_instance->setMatchMode($mode);
        return $_instance;
    }

    //检索方法
    public function Query($qstr, $field="*") {
        $_instance = self::getSphinxInstance();
        $ret = $_instance->query($qstr, $field="*");
        return $ret;
    }
    
    //设置sphinxMode
    public function setLimit($offet, $size, $maxnum) {
        $_instance = self::getSphinxInstance();
        $_instance->SetLimits($offet, $size, $maxnum);
        return $_instance;
    }
    
    //设置sphinxMode
    public function setFilter($field, $range, $opposite) {
        $_instance = self::getSphinxInstance();
        $_instance->SetFilter($field, $range, $opposite);
        return $_instance;
    }
    
    //设置sphinxMode
    public function setFilterRange($field, $min, $max, $opposite) {
        $_instance = self::getSphinxInstance();
        $_instance->SetFilterRange($field, $min, $max, $opposite);
        return $_instance;
    }
    
    //设置sphinxMode
    public function setSortMode($mode = SPH_MATCH_ANY) {
        $_instance = self::getSphinxInstance();
        $_instance->SetSortMode($mode);
        return $_instance;
    }

}
