<?php
/*
*@auhtor jack.z
*@date 20140912
 */
function  smarty_modifier_entity_html($str){
	return html_entity_decode($str);
}