<?php
error_reporting(E_ALL);
register_shutdown_function('debug_backtrace');
set_time_limit(0);
/*
 * index.php - Browser frontend.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/
$header = 0;
$version = "0.1.9";
$year = "2014";

include 'commands.php';
include 'classes/index.php';

function foo($i)
{
echo
'
<script type="text/javascript">
var open_np'.$i.' = -1;
$("#content'.$i.'").hide();
$("#expand'.$i.'").click
(
	function ()
	{
		open_np'.$i.' *= -1;

		if(open_np'.$i.'==1)
			$("#content'.$i.'").slideDown(\'easing\');
		else if(open_np'.$i.'==-1)
			$("#content'.$i.'").slideUp(\'easing\');
	}
);
</script>
';
}

if(!isset($_POST['kernel']))
	$kernel = kernel::load('kernel.dat');
else	$kernel = base64_decode($_POST['kernel']);

$hist = unflatten('log.dat');
$estat = unflatten('ehist.dat');

include 'index_cli.php';
if($header)
echo	'<b>FadelaBOT</b> v'.$version.'<br/>&copy;'.$year.' Lockett Analytical Systems<p/>';
echo	'<html>',
	'<head>',
	'<style>html{font-family:"verdana",sans-serif; font-size:12px} td{font-size:11px;} /*textarea{border:none;}*/</style>',
	'<script type="text/javascript" src="jquery-1.10.2.js"></script>',
	'</head>',
	'<body>';

if(isset($_GET['viewkernel']))
{
	printFat($kernel);
	die();
}

$prev_fail = 0;
$msg;
if(isset($_POST['go']))
{
	//Retrieve query from POST
	$msg = $_POST['msg'];
	printDat($msg);
	//Prepare and process message
	// TODO: If an exception causes our tower to collapse,
	//     roll back and present the user with the previous form, message included.
	//     QA may threaten rollbacks by the way.
	//         With this in mind, please convert the kernel to a fully closed system.
	$results = $kernel->process($msg,$tree);
	foreach($results as $j=>$result)
		foreach($result as $i=>$v)
		{
			$man_name = $kernel->get(1)->name['default']->select();
			$bot_name = $kernel->get(2)->name['default']->select();
			if(strlen($man_name)==0) $man_name = "user";
			if(strlen($bot_name)==0) $bot_name = "system";
			$prettyprint = 1;

			$status = $v[count($v)-1];
			unset($result[$i][count($v)-1]);

			$smp = interpreter::simplify($tree[$i],$prettyprint);
			$nat = interpreter::naturalize($tree[$i]);
			$hist[] = [$man_name."  (usr)",$smp,$nat];
			$smp = interpreter::simplify($result[$i],$prettyprint);
			$nat = interpreter::naturalize($result[$i]);
			$hist[] = [$bot_name."  (sys)",$smp,$nat];
			$estat[] = [$status];
		}
}


echo	'<table width="100%">',
	'<tr><td colspan="3" bgcolor="d0d0d0"><b>Recent History:</b></td></tr>';

function eproc($status)
{
	$lestat = unserialize(base64_decode($status[0][1]));
	$estat = unserialize(base64_decode($status[0][2]));

	echo '<table width=100% border=1>';
	echo '<tr>';

	$l = ($le = $estat->expressable('l'))?$le*1.5:$estat->get('l');
	$p = ($pe = $estat->expressable('p'))?$pe*1.5:$estat->get('p');
	$d = ($de = $estat->expressable('d'))?$de*1.5:$estat->get('d');

	if(isset($_GET['avatar'])) echo "<tr><td colspan=5><img src='avatar/draw.php?l=$l&p=$p&d=$d'/></td></tr>";
	echo '<td>cat</td><td>total</td><td>expr</td><td>min (thresh)</td><td>max (thresh)</td></tr>';
	foreach(['l','p','d'] as $i=>$v)
	{
		$max = "max$v";
		$min = "min$v";
		echo	"<tr><td>$v</td><td>".round($estat->$v,4)."</td><td>".round($estat->get_expression($v),4)."</td>",
			"<td>".round($estat->$min,4)." (".round($estat->get_min_threshold($v),4).")</td>",
			"<td>".round($estat->$max,4)." (".round($estat->get_max_threshold($v),4).")</td>";
		echo	"</tr>";
	}
	echo '</table>';
}

foreach($hist as $i=>$v)
{
	$colors = array("f0f0f0","e0e0e0");
	$namecolors = array("000080","008000");

	//if($i>50) break;
	echo
	'<tr>',
	'<a name='.$i.'/>',
	'<td width="10%" bgcolor='.$colors[($i)%2].' valign="top">',
		'<font color="'.$namecolors[($i%2)].'">',
		'<b>'.$v[0].':</b></font>',
	'</td>',
	'<td width="40%" bgcolor='.$colors[($i+1)%2].' valign="top">',
		$v[2].' <a id="expand'.$i.'" style="text-decoration:none; color:#000080;">(FZPL)</a>',
		'<div id="content'.$i.'" style="font-size:10px;color:#000080;"><tt><pre>'.$v[1].'</pre></tt></div>',
	'</td>',
	'<td bgcolor='.$colors[($i)%2].' valign="top">';

	if($i%2)
	{
		$status = $estat[(int)($i/2)];
		eproc($status);
	}	else echo '<br/><br/><br/>';

	echo '</td>',
	'</tr>'
	;
	foo($i);
}
echo '</table>';

$path = $_SERVER['REQUEST_URI'];
echo '<form action="'.$path.'" method="POST">';
echo '<b>Query Input:</b><br/><textarea rows="10" cols="100" name="msg"></textarea>';
echo '<br/><input type="submit" name="go"></form>';

kernel_lib::dump();

echo 'Kernel size: '.round(strlen(serialize($kernel))/1024).'kb<br/>';
echo 'kernel<br/>';
printDat($kernel);
echo 'kernel contents<br/>';
printDat($kernel->getContents());
echo 'kernel scope<br/>';
printDat($kernel->scope);
echo 'kernel oats<br/>';
printDat($kernel->oats);
echo 'vectorized base chain (length 3)<br/>';
$chain = $kernel->get_vectorized_base_chain(3);
printDat($chain);
printDat(n2::compare_set($chain[2],$chain[3]));
printDat(get_class_methods($kernel));
printDat(file_get_contents('docs/fzpl_guide.txt'));
?>
