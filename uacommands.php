<?php
/*
 * Functions with unknown/questionable authorship.
 * Because the unknown author deserves credit too.
 * No license for this code.
*/

function br2nl($string)
{
    return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
}
function rgb2html($r, $g=-1, $b=-1)
{
    if (is_array($r) && sizeof($r) == 3)
        list($r, $g, $b) = $r;

    $r = intval($r); $g = intval($g);
    $b = intval($b);

    $r = dechex($r<0?0:($r>255?255:$r));
    $g = dechex($g<0?0:($g>255?255:$g));
    $b = dechex($b<0?0:($b>255?255:$b));

    $color = (strlen($r) < 2?'0':'').$r;
    $color .= (strlen($g) < 2?'0':'').$g;
    $color .= (strlen($b) < 2?'0':'').$b;
    return '#'.$color;
}

function supertrim($s)
{
	$chars = array(" ","\t","\n","\r","\0","\x0b");
	foreach($chars as $a)
	{
		$s = trim($s,$a);
	}
	return $s;
}
?>
