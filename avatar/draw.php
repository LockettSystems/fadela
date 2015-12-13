<?php
/*
 * draw.php - Generates Avatar.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

include '../commands.php';

function rgba($image,$x,$y)
{
	$rgb = imagecolorat($image, $x, $y);
	$rgba = imagecolorsforindex($image, $rgb);
	return $rgba;
}

function transpx(&$image,$i,$j,$inverted = 0,$dampen=1)
{
	$rgba = rgba($image,$i,$j);
	$mean = round(($rgba['red'] + $rgba['green'] + $rgba['blue'])/3/255*127);
	if($mean == 127)
	{
		$alpha = imagecolorallocatealpha($image,$rgba['red'],$rgba['green'],$rgba['blue'],($inverted)?0:127);
		imagesavealpha($image, true);
		imagesetpixel($image,$i,$j,$alpha);
	}
}

function transparent(&$image,$rgb)
{
	for($i = 0; $i < 194; $i++)
		for($j = 0; $j < 194; $j++)
			transpx($image,$i,$j);
//	$color = imagecolorallocatealpha($image,$rgb[0],$rgb[1],$rgb[2],127);
//	imagecolortransparent($image,$color);
//	imagesavealpha($image , true);
}
function load_part($type,$no,$color = [])
{
	$transp = 0;
	if(consume($type,'#')) $transp = 1;

	$img = load_image('foxrichards/'.$type.'/'.$no.'.png');
	if($transp) transparent($img,[255,255,255]);

	if(count($color) > 0)
		imagefilter($img,IMG_FILTER_COLORIZE,$color[0],$color[1],$color[2]);
	return $img;
}
function load_image($path)
{
	$out = imagecreatefromstring(file_get_contents($path));
	imagepalettetotruecolor($out);
	imagealphablending($out, false);
	return $out;
}
function transp(&$png,$colors = [0,0,0])
{
	$pngTransparency = imagecolorallocatealpha($png , 0, 0, 0, 127);
	imagefill($png , 0, 0, $pngTransparency);
	imagesavealpha($png , true);
}
function add_parts(&$img,$parts)
{
	$it = 0;

	foreach($parts as $i=>$v)
	{
		if(count($v) == 1) $v[1] = [];
		if(count($v) == 2 || empty($v[2])) $v[2] = [0,0];
		if(count($v) == 3) $v[3] = 0;
		$img2 = load_part($i,$v[0],$v[1]);
		if($v[3])
		{
			$img2 = imagerotate($img2,$v[3],0);
			transp($img2);
		}
		imagecopy($img,$img2,$v[2][0],$v[2][1],0,0,194,194);
		$it++;
	}
}

class avatar
{
	public	$skin_color,
		$hair_color,
		$bg_color,
		$mouth_color,
		$state = [0,0,0];

	public	$mouth =
		[
			-3 => 256, //nope
			-2 => 220, //nope
			-1 => 230, //nope
			0 => 268, //below-average
			1 => 218, //average
			2 => 208, //above-average
			3 => 228, //ecstatic
		],
		$eyebrow =
		[
			-3 => 532,
			-2 => 530,
			-1 => 528,
			0 => 526,
			1 => 526,
			2 => 526,
			3 => 526,
		],
		$eyes =
		[
			-3 => 403,
			-2 => 417,
			-1 => 419,
			0 => 419,
			1 => 419,
			2 => 419,
			3 => 419,
		],
		$iris =
		[
			-3 => 468,
			-2 => 464,
			-1 => 464,
			0 => 464,
			1 => 464,
			2 => 464,
			3 => 464,
		]
		;

	function __construct()
	{
	}
	function limit($x)
	{
		if($x > 3) return 3;
		if($x < -3) return -3;
		return $x;
	}
	function get_eyes($l,$p,$d)
	{
		$l = $this->limit(round($l));
		$p = $this->limit(round($p));
		$d = $this->limit(round($d));

		$y = ceil(($l+$p+$d)/3);
		return [$this->eyes[$y]];
	}
	function get_iris($l,$p,$d)
	{
		$l = $this->limit(round($l));
		$p = $this->limit(round($p));
		$d = $this->limit(round($d));

		$y = ceil(($l+$p+$d)/3);
		return [$this->iris[$y],[139-192,69-192,19-192],[2.5,0]];
	}
	function get_eyebrow($l,$p,$d)
	{
		$lo = $l; $po = $p; $do = $d;

		$l = $this->limit(round($l));
		$p = $this->limit(round($p));
		$d = $this->limit(round($d));

		$y = ceil(($l+$p+$d)/3);
		return [$this->eyebrow[$y],$this->hair_color,[0,$do*-3],0];
	}
	function get_mouth($l,$p,$d)
	{
		$l = $this->limit(round($l));
		$p = $this->limit(round($p));
		$d = $this->limit(round($d));

		$y = ceil(($l+$p+$d)/3);
		return [$this->mouth[$y],$this->mouth_color,[0,0],0];
	}
	function generate()
	{
		header('Content-Type: image/png');

		$img = imagecreatetruecolor(194,194);
		$fgreen = imagecolorallocate($img,$this->bg_color[0],$this->bg_color[1],$this->bg_color[2]);
		imagealphablending($img, true);
		imagefill($img,0,0,$fgreen);

		$l = $this->state[0]*2;
		$p = $this->state[1]*2;
		$d = $this->state[2]*2;

		$parts = [
			'neck' => [171,$this->skin_color],
			'hair' => [131,$this->hair_color],
			'face' => [180,$this->skin_color],
			'hair-2' => [339,$this->hair_color],
			'headband' => [553,[-255,-160,-255]],
			'eyes' => $this->get_eyes($l,$p,$d),
			'iris' => $this->get_iris($l,$p,$d),
			'#eyes' => $this->get_eyes($l,$p,$d),
			'nose' => [513,$this->skin_color,[],0],
			'mouth' => $this->get_mouth($l,$p,$d),
			'eyebrow' => $this->get_eyebrow($l,$p,$d),
			'eye-acc' => [588],
			'ear-acc' => [565,[-128,128,-128]],
		];


		add_parts($img,$parts);

		imagepng($img);
	}
}

$x = new avatar();
$x->bg_color = [240,255,240];
$x->skin_color = [0,-40,-72];
$x->mouth_color = [0,-40,-72];
$x->hair_color = [-224,-240,-255];
$l = @$_GET['l'];
$p = @$_GET['p'];
$d = @$_GET['d'];
$x->state = [$l,$p,$d];
$x->generate();
?>
