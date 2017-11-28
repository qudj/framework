<?php
/**
*@author jack.z
*@param int $month 月总数
*@param Boolean false|true;
*@return int|float  
*/
function smarty_modifier_monthtoyear($month,$accurate = false){
		
		if($accurate){
			$year = number_format($month/12,1);
		}else{
			$year = ceil($month/12);
		}
		return $year;	
}
?>