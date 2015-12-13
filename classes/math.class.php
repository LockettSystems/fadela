<?php
/*
 * math.class.php - Math functions library.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/

class math //Verify that function calls in other files point to this class.
{
	static function median($ar)
	{
		sort($ar);
		while(count($ar)>2)
		{
			$a = 0;
			$b = count($ar)-1;
			unset($ar[$a]);
			unset($ar[$b]);
			$ar = array_values($ar);
		}
		return math::mean($ar);
	}
	static function rnd()
	{
		$out = (rand(0,1000000000)/1000000000);
		return($out);
	}
	static function sigmoid($x)
	{
		$c = 1;
		return 1/(1+exp(0-exp(1)*$c*$x));
		return $x/(1+abs($x));
	}
	static function log2($x)
	{
		return log($x)/log(2);
	}
	static function logn($x,$n)
	{
		return log($x)/log($n);
	}
	static function goldenRatio()
	{
		return 1.618034;
	}
	static function base36($x)
	{
		return base_convert($x,10,36);
	}
	static function mean($ar)
	{
		if(count($ar)==0) throw new Exception("Empty array given");
		return array_sum($ar)/count($ar);
	}
	static function variance($set)
	{
		$mean = math::mean($set);
		$out = 0;
		foreach($set as $i=>$v)
		{
			$out += (1/count($set))*pow(($v-$mean),2);
		}
		return $out;
	}
	static function stdev($set)
	{
		return sqrt(math::variance($set));
	}
}
?>
