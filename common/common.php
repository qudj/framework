<?php

function C($name='',$value=''){

	static $config = array();
	if(empty($name)){
		$defultconf = include("./framework/conf/conf.php");
		$userconf = include("./".__APPNAME__."/Conf/conf.php");
        $defultconf = array_change_key_case($defultconf);
		$userconf = array_change_key_case($userconf);
		$config = array_merge($defultconf,$userconf);
		return $config;	
	}
	$name = strtolower($name);
	if(empty($value)){
		if(array_key_exists($name, $config)){
			return $config[$name];
		}else{
			return null;
		}
	}

	if((!empty($name))&&(!empty($value))){
	   $config[$name] = $value;
	}
}