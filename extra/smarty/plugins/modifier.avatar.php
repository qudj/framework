<?php
/**
 * 按尺寸获取头像
 * @author gaunxiongbo
 *
 * @param int $size 尺寸大小  180|90|45|30
 *
 * @return string|false 
 * 
 */
function smarty_modifier_avatar($string,$size = 90)
{
    if(empty($string)){
        return '';
    }
    $arr = json_decode($string,true);
    if(isset($arr[$size])){
        return $arr[$size];
    }else{
        return '';
    }
} 

?>
