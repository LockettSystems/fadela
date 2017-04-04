<?php
/*
 * commands.php - "Standard" functions library.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/

/*

goldenRatio() => math::goldenRatio()

base36(int) => math::base36(int)

log2($x) => math::log2(double)

swap($ar,$a,$b) => swap(&$a,&$b)

mean($ar) => math::mean($ar)

the rest are in commands_trim.txt

*/
include_once('uacommands.php');
include_once('udcommands.php');

function u_md5($x) {
	return md5(serialize($x));
}
function permutate($ar) {
	$out = [];
	foreach($ar as $i => $v) {
		$ar2 = $ar;
		unset($ar2[$i]);
		$s = permutate($ar2);
		foreach($s as $w) {
			$out[] = array_merge([$v],$w);
		}
		if(!count($s)) $out[] = [$v];
	}
	return $out;
}

function emit($s){
	 echo "$s\n";
	return true;
}

function not_empty($x) {
	return !empty($x);
}
function s64($x)
{
	return base64_encode(serialize($x));
}
function shred(&$x)
{
    if(is_string($x))
        for($i = 0; $i < strlen($x); $i++)
            $x[$i] = chr(rand(0,255));
    unset($x);
}

function uuid($len = 64)
{
    if($len == 0) return '';
    return base_convert(rand(0,35),10,36).uuid($len-1);
}
function ping($url)
{
	exec("ping $url -c 1 -W 5 -s 0",$foo,$rv);
	return intval($rv);
}
function object_clone($x)
{
	$y = serialize($x);
	$z = unserialize($y);
	return $z;
}
function colorguide()
{
	for($i = 0; $i < 100; $i++)
	       echo COLOR("0;$i",$i);
}
function COLOR($code,$str)
{
	return "\033[{$code}m$str\033[0m";
}
function BOLD($str) {
	return COLOR("1m",$str);
}
function GREEN($str)
{
	return COLOR("0;32",$str);
}
function CYAN($str)
{
	return COLOR("0;36",$str);
}
function LIGHT_GREEN($str)
{
	return COLOR("1;32",$str);
}
function BLUE($str)
{
	return COLOR("0;34",$str);
}
function LIGHT_BLUE($str)
{
	return COLOR("1;34",$str);
}
function RED($str)
{
	return COLOR("0;31",$str);
}
function YELLOW($str)
{
	return COLOR("0;33",$str);
}
function LIGHT_RED($str)
{
	return COLOR("1;31",$str);
}
function MAGENTA($str)
{
	return COLOR("1;35",$str);
}

function remnull($ar,$keep_arrays = 0,$array_values = 1) //Removes 0-length items from $ar.
{
	foreach($ar as $i=>$v)
	{
		if(is_array($v) && count($v)==0 && $keep_arrays == 0) unset($ar[$i]);
		else if(!is_array($v) && is_string($v) && strlen($v)==0) unset($ar[$i]);
		else if($v == null) unset($ar[$i]);
	}
	if($array_values)
		return array_values($ar);
	else
		return $ar;
}
function irc()
{
	return 0;
}
function smartscan() {
	 if(defined('STDIN'))
		return trim(fgets(STDIN));
	else if(!empty($_GET['stdin'])) {
	     $stdin = explode('\n',$_GET['stdin']);
	     $out = consume1($stdin);
	     $_GET['stdin'] = implode('\n',$stdin);
	     return $out;
	} else { return null; }
}
function scan()
{
	$a = fopen("php://stdin",'r');
	$b = fread($a,65536);
	fclose($a);
	return $b;
}
function scanword()
{
	$out = "";
	$c = scanchar();
	while($c!=" " && $c!="\t" && $c!="\n" && $c!=null)
	{
		$out .= $c;
		$c = scanchar();
	}
	return $out;
}
function scanchar()
{
	$a = fopen("php://stdin",'r');
	$b = fread($a,1);
	fclose($a);
	return $b;
}
function readf($file)
{
	if(!file_exists($file)) return;
	else return file_get_contents($file);
}
function writef($file,$s)
{
	$a = fopen($file,"w");
	$b = fwrite($a,$s,strlen($s));
	fclose($a);
}
function appendf($file,$s)
{
	$a = fopen($file,"a");
	$b = fwrite($a,$s,strlen($s));
	fclose($a);
}
function normalize($s,$space = 0)
{
	/*$alphanumerictext = ereg_replace("[^A-Za-z0-9]","",$s);
	return $alphanumerictext;*/
	$out = '';
	for($i = 0; $i < strlen($s); $i++)
	{
		$o = ord($s[$i]);
		$c = $s[$i];
		if
		(
			($o>=48 && $o<=57)
			||($o>=65 && $o<=90)
			||($o>=97 && $o<= 122)
			|| ($space && ($c=="_"||$c=="-"||$c==" "))
		)
		{
			$out = $out.$s[$i];
		}
	}
	return $out;
}
function neonormalize($s)
{
	$out;
	for($i = 0; $i < strlen($s); $i++)
	{
		if(
		(ord($s[$i]) >= 32)
		&&
		(ord($s[$i]) < 127)
		)
		$out = $out.$s[$i];
	}
	return $out;
}
function comment($id,$s)
{
	if((irc()==1)&&($id==0))
	{
		//session_start();
		$_SESSION["message"] = $s;
		$_SESSION["nownick"] = $nickname;
	}
}
function rnd()
{
	$out = (rand(0,1000000000)/1000000000);
	return($out);
}
function slope($a,$b,$time)
{
	return(($b-$a)/$time);
}
function remove_front($ar,$val)
{
	return decapitate($ar,$val);
}
function remove_back($ar,$val)
{
	return castrate($ar,$val);
}
function trim_from_back($ar,$val)
{
	for($i = 0; $i < $val; $i++)
	{
		unset($ar[count($ar)-1]);
		$ar = array_values($ar);
	}
	return $ar;
}
function decapitate($ar,$val)
{
	for($i = 0; $i < $val; $i++)
	{
		unset($ar[$i]);
	}
	return array_values($ar);
}
function castrate($ar,$val)
{
	for($i = $val; $i < count($ar); $i++)
	{
		unset($ar[$i]);
		$i--;
		$ar = array_values($ar);
	}
	return $ar;
}
function printCat($cmt,$ar)
{
	echo $cmt;printDat($ar);
}
function cli()
{
	return PHP_SAPI == "cli";
}
function stderr($msg)
{
	if(cli())
	file_put_contents('php://stderr',$msg."\n");
	else echo $msg;
}
function printDat($ar,$multiplier = 1)
{
	#if(cli()) file_put_contents('php://stderr',print_r($ar,1));
	if(cli()) error_log(print_r($ar,1));
	else echo '<textarea rows="'.(10*$multiplier).'" cols=100">' . print_r($ar,1) . '</textarea><br/>';
}
function printFat($ar)
{
	echo '<textarea rows=1000" cols=300">';
	print_r($ar);
	echo '</textarea><br/>';
}

function swap(&$a,&$b)
{
	$t = $a;
	$a = $b;
	$b = $t;
}
function is_alphanumeric($s)
{
	return intval(ctype_alnum($s));
}
function consume1_and_return($s)
{
	consume1($s);
	return $s;
}
function func_in_backtrace($f)
{
	$a = debug_backtrace();
	foreach($a as $i=>$v)
		if($v['function']==$f) return 1;
	return 0;
}
function sign($int)
{
	if($int) return $int/abs($int);
	return 0;
}
function consume1(&$s,$preserve_keys = 0)
{
	if(is_array($s))
	{
		if(!count($s)) return 0;
		$keys = array_keys($s);
		$out = $s[$keys[0]];
		unset($s[$keys[0]]);
		if(!$preserve_keys) $s = array_values($s);
		return $out;
	}

	if(strlen($s)==0) return null;
	$out = $s[0];
	$s = substr($s,1);
	return $out;
}
function unflatten($f)
{
	if(!file_exists($f)) return [];
	$a = file_get_contents($f);
	if(strlen($a)==0) return [];
	else return unserialize($a);
}
//Please accommodate arrays
function consumen(&$x,$n)
{
	$out = "";
	for($i = 0; $i < $n; $i++) $out .= consume1($x);
	return $out;
}
function consume(&$s,$ss)
{
	if(strlen($s)>0 && strlen($ss)==1 && ord($s[0])==ord($ss))
	{
		$s = substr($s,1,strlen($s)-1);
		return 1;
	}
	for($i = 0; $i < strlen($ss); $i++)
	{
		if($i >= strlen($s) || $s[$i]!=$ss[$i]) return 0;
	}
	$s = substr($s,strlen($ss),strlen($s)-strlen($ss));
	return 1;
}
function consume_leaf(&$ar)
{
	if(!count($ar)||$ar==null) return null;
	$atom = consume1($ar);
	if(!is_array($atom)) return $atom;
else	{
		$subatom = consume_leaf($atom);
		if($subatom==null) return consume_leaf($ar);
		$ar = array_merge([$atom],$ar);
		return $subatom;
	}
}
function array_in_array($ar)
{
	foreach($ar as $i=>$v)
	{
		if(is_array($v)) return 1;
	}
	return 0;
}
function all_in_array($needles,$haystack)
{
	foreach($needles as $i=>$v)
	{
		if(!in_array($v,$haystack)) return 0;
	}
	return 1;
}
function all_not_in_array($needles,$haystack)
{
	foreach($needles as $i=>$v)
	{
		if(in_array($v,$haystack)) return 0;
	}
	return 1;
}
function in_range($x,$a,$b)
{
	if($x >= $a && $x <= $b) return 1;
	return 0;
}
function varbase($ar)
{
	$sets = array();
	$keys = array_keys($ar);

	if(count($ar[$keys[0]])==0) return null;
	if(count($ar)==1) return $ar[$keys[0]];

	$head = $ar[$keys[0]];
	unset($ar[$keys[0]]);

	$final = $keys[1];
	$next = varbase($ar);

	if(!is_array($head)) $head = array($head);
	if(!is_array($next)) $next = array($next);

	if(count($head)==0) $head = array($keys[0]=>null);

	foreach($head as $i=>$v)
	{
		foreach($next as $j=>$w)
		{
			if(!is_array($v) && !is_array($w)) $sets[] = array_merge(array($keys[0]=>$v),array($final=>$w));
		else	if(!is_array($v)) $sets[] = array_merge(array($keys[0]=>$v),$w);
		else	if(!is_array($w))  $sets[] = array_merge($v,array($j=>$w));
		}
	}

	return $sets;
}
function varbase2($ar)
{
	$out = array();
	foreach($ar as $i=>$v)
	{
		$newout = array();
		if(!is_array($v)) $v = array($v);
		if(count($v)==0) $v = array(null);
		foreach($v as $j=>$w)
		{
			if(count($out)==0)
			{
				$newout[] = array($i=>$w); 
			}
			else
			{
				foreach($out as $k=>$x)
				{
					$newout[] = array_merge($x,array($i=>$w));
				}
			}
		}
		$out = $newout;
	}
	return $out;
}
function ar_replace($a,$b,$ar)
{
	foreach($ar as $i=>$v)
	{
		if($v==$a) $ar[$i] = $b;
	}
	return $ar;
}
function init_array($len,$val)
{
	$out = array();
	for($i = 0; $i < count($len); $i++)
	{
		$out[] = $val;
	}
	return $out;
}
function multi($s,$n)
{
	$out = "";
	for($i = 0; $i < $n; $i++)
		$out.=$s;
	return $out;
}
function decapsulate($s,$a,$b) //Separates $s from right-limit A and left-limit B
{
	$x = explode($a,$s);
	$y = $x[1];
	$z = explode($b,$y);
	return $z[0];
}
function in_str($needle,$haystack)
{
	$haystack = explode($needle,$haystack);
	if(count($haystack)>1) return 1;
	else return 0;
}
function prompt($q,$f,$deftext,$software = "emacs -nw")
{
	$path = $f."_prompt";

	if($q != null) passthru("xmessage \"$q\"");
	file_put_contents($path,$deftext);
	passthru("$software ".$path);
	$result = readf($path);
	passthru("rm ".$path);
	return $result;
}
function debug($v)
{
	print_r($v);exit(0);
}
function hr($stderr = 1)
{
	if(!cli())
	echo '<hr width="100%"/>';
	else if($stderr) stderr('----------------------------------------');
	else echo('----------------------------------------');
}
function array_mean($array)
{
	if(!count($array)) return 0;
	return array_sum($array)/count($array);
}
function has_keys($array,$keys)
{
	foreach($array as $i=>$v)
	{
		$arkeys = array_keys($v);
		if(count($keys) > count($arkeys)) swap($keys,$arkeys);
		if(!count(array_diff($arkeys,$keys))) return 1;
	else	swap($keys,$arkeys);
	}
	return 0;
}
function first_key($ar)
{
	if(!is_array($ar) || !count($ar)) return null;
	$keys = array_keys($ar);
	return $keys[0];
}
function first($ar)
{
	if(!is_array($ar) || !count($ar)) return null;
	$keys = array_keys($ar);
	return $ar[$keys[0]];
}
function last($ar)
{
	if(!is_array($ar) || !count($ar)) return null;
	$keys = array_keys($ar);
	return $ar[$keys[count($keys)-1]];
}
function last_key($ar)
{
	$keys = array_keys($ar);
	return $keys[count($keys)-1];
}
function min_key($ar,$slide = 0)
{
	$min = null;
	$key = null;
	foreach($ar as $i=>$v)
	{
		if($min == null || $v < $min)
		{
			$key = $i;
			$min = $v;
		}
	}
	return $key;
}
function max_key($ar,$latest = 0)
{
	$max = null;
	$key = null;
	foreach($ar as $i=>$v)
	{
		if($max == null || $v > $max || ($latest && $v >= $max))
		{
			$key = $i;
			$max = $v;
		}
	}
	return $key;
}
function str_nreplace($a,$b,$src)
{
    if(!is_array($a)) $a = array($a);
    if(!is_array($b)) $b = array_fill(0,count($a),$b);
    foreach($a as $i=>$v)
        $src = str_replace($a[$i],$b[$i],$src);
    return $src;
}
function unset_keys(&$ar,$keys)
{
	foreach($keys as $i=>$v)
		if(isset($ar[$i])) unset($ar[$i]);
}
function array_count_instances($needle,$haystack)
{
	$out = 0;
	foreach($haystack as $i=>$v)
		if($v == $needle) $out++;
	return $out;
}
/*function next(&$ar)
{
	array_shift($ar);
}*/
?>
