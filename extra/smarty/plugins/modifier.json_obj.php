<?php
/*
 * @author	    zhangliang
 * @data	    2014-5-14 
 * @encode	    UTF-8
 * @description 获取json中field的值(如果传入值是JSON串，则返回JSON串中指定字段的值，
 * 				否则直接返回原数据
 */

function  smarty_modifier_json_obj($jsonStr,$feild){
	$jsonObj = json_decode($jsonStr);
	if(is_object($jsonObj)){
		return $jsonObj->$feild;
	}else{
		return $jsonStr;
	}
}