<?php
error_reporting(E_ALL || ~E_NOTICE);
include("./framework/common/common.php");
include("./framework/extra/smarty/Smarty.class.php");

class Application
{
    private static $instance = NULL;
    private static $projectname = '';

    //获取单例
    public static function getInstance() {
        if (!isset (self::$instance)) {
            self::$instance = new Application();
        }
        return self::$instance;
    }

	private function __construct(){} 

	public function run($appname){
	    spl_autoload_register('Application::loader');
        if(!file_exists($appname)){
            $create = Create::getInstance();
            if($create->CreateProject($appname))
            {
                define('__APPNAME__',$appname);
                $this->run($appname);
            }else{
                echo "项目创建未成功！";exit;
            }
        }else{
            //echo 333;die;
            if(!defined('__APPNAME__')) {
                define('__APPNAME__',$appname);
            }
            C();
            $route = RouteParse::getInstance();
            $action = $route->GetActionMethod();
        }
	}

    public static function loader($class)   
    {   
        $mulse = strstr($class,"Action").strstr($class,"Service").strstr($class,"Model");
        if(!empty($mulse) && $class != $mulse){
            $file = './'.__APPNAME__.'/'.$mulse.'/'.$class.'.php';
            if (file_exists($file)) {
                include $file;
                return true;
            }else{
                return false;
            }
        }
        $file = './framework/lib/'.$class.'.php';
        if(file_exists($file)){   
            include $file;
            return true;
        }
        $file = './'.__APPNAME__.'/Common/'.$class.'.php';
        if(is_file($file)){
            include $file;
            return true;
        }
        return false;
    }

} 
