<?php
class RouteParse
{ 
    private static $instance = NULL;

    //获取单例
    public static function getInstance() {
        if (!isset (self::$instance)) {
            self::$instance = new RouteParse();
        }
        return self::$instance;
    }

	private function __construct(){} 

	public function GetActionMethod(){

        $server = $_SERVER;
        if(!empty($server['PATH_INFO'])){
            $path = trim($server['PATH_INFO'],"/");
            $param = explode("/",$path);
            $actionstr = isset($param[0])?$param[0]."Action":C("indexaction");
            $methodstr = isset($param[1])?$param[1]:C("indexmethod");
        }else{
            $actionstr = C("indexaction");
            $methodstr = C("indexmethod");
        }
        
        $action = new $actionstr;
        call_user_func(array($action, $methodstr));
        return $action;
	} 

} 

