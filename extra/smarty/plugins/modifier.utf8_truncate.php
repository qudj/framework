<?php
/**
 * Smarty truncate modifier plugin
 *
 * Type:     modifier<br>
 * Name:     utf8_truncate<br>
 * @author  jack.z
 * @param string
 * @param integer
 * @param string
 * @param boolean
 * @param boolean
 * @return string
 */
function smarty_modifier_utf8_truncate($string, $length = 80, $etc = '...', $break_words = false, $middle = false)
{
	return $returnstr =substr_utf8(StripHTML($string), 0, $length).$etc;
}

function substr_utf8($str, $start=0, $length=-1, $return_ary=false) 
{
    $len = strlen($str);if ($length == -1) $length = $len;
    $r = array();
    $n = 0;
    $m = 0;

    for($i = 0; $i < $len; $i++) {
        $x = substr($str, $i, 1);
        $a = base_convert(ord($x), 10, 2);
        $a = substr('00000000'.$a, -8);
        if ($n < $start) {
            if (substr($a, 0, 1) == 0) {
            }elseif (substr($a, 0, 3) == 110) {
                $i += 1;
            }elseif (substr($a, 0, 4) == 1110) {
                $i += 2;
            }
            $n++;
        }else {
            if (substr($a, 0, 1) == 0) {
                $r[] = substr($str, $i, 1);
            }elseif (substr($a, 0, 3) == 110) {
                $r[] = substr($str, $i, 2);
                $i += 1;
            }elseif (substr($a, 0, 4) == 1110) {
                $r[] = substr($str, $i, 3);
                $i += 2;
            }else {
                $r[] = '';
            }
            if (++$m >= $length) {
                break;
            }
        }
    }

    return $return_ary ? $r : implode("",$r);
}


function StripHTML($string)
{
	$pattern=array ("'<script[^>]*?>.*?</script>'si", "'<style[^>]*?>.*?</style>'si",  "'<[/!]*?[^<>]*?>'si",  "'([rn])[s]+'",  "'&(quot|#34);'i",  "'&(amp|#38);'i",  "'&(lt|#60);'i",  "'&(gt|#62);'i",  "'&(nbsp|#160);'i",  "'&(iexcl|#161);'i",  "'&(cent|#162);'i",  "'&(ldquo|rdquo);'i",  "'&(apos|#39);'i",  "'&(pound|#163);'i",  "'&(copy|#169);'i", "'quo;'i", "'&#(d+);'e");
	return preg_replace ($pattern, '', strip_tags($string));
}
?>