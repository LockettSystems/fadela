<?php
error_reporting(E_ALL);
include 'commands.php';
include 'classes/index.php';

$msg = $_POST['msg'];
$kernel = unserialize(base64_decode($_POST['kernel']));
kernel::set_global($kernel);

$results = $kernel->process($msg,$tree);

$hist = [];
$estat = [];
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

function eproc(&$status)
{
	$out = [];


	$lestat = unserialize(base64_decode($status[0][1]));
	$estat = unserialize(base64_decode($status[0][2]));

	$l = ($le = $estat->expressable('l'))?$le*1.5:$estat->get('l');
	$p = ($pe = $estat->expressable('p'))?$pe*1.5:$estat->get('p');
	$d = ($de = $estat->expressable('d'))?$de*1.5:$estat->get('d');

	foreach(['l','p','d'] as $i=>$v)
	{
		$max = "max$v";
		$min = "min$v";
		$out[$v] = [
			'total' => round($estat->$v,4),
			'expr' => round($estat->get_expression($v),4),
			'min' => round($estat->$min,4),
			'min_thresh' => round($estat->get_min_threshold($v),4),
			'max' => round($estat->$max,4),
			'max_thresh' => round($estat->get_max_threshold($v),4),
			$v => $$v,
			"avatar" => $$v
		];
	}
	$status = $out;
}

foreach($estat as $i => $es)
	eproc($estat[$i]);

$o = json_encode(array(
	'kernel' => base64_encode(serialize($kernel)),
	'response' => base64_encode(serialize($results)),
	'status' => $estat,
	'omsg' => $msg,
	'hist' => $hist,
	'log' => $kernel->log
));

echo $o;
?>
