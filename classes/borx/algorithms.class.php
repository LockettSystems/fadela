<?php
/*
 * algorithms.class.php - A collection of esoteric algorithms.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * TODO refactor and make more user friendly
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/

class algorithms
{
	function priscilla_hash($n,$h)
	{
		
	}
	static function priscilla($ar,$n)
	{
		$a = pow(2,count($ar));
		return algorithms::p($ar,$a-$n);
	}
	static function p($ar,$n)
	{
		$nBinary = base_convert($n,10,2);
		$firstOne = count($ar)-strlen($nBinary);
		$structs = array();
		$terms = array();
		for($i = 0; $i < count($ar); $i++)
		{
			if($i<$firstOne)
			{
				if((count($structs) > 0)&&($structs[count($structs)-1]=="*"))
				{
					$terms[count($terms)-1] = $terms[count($terms)-1]." ".$ar[$i];
				}
				else
				{
					$structs[] = "*";
					$terms[] = $ar[$i];
				}
			}
			else
			{
				if($nBinary[$i-$firstOne]==0)
				{
					if($structs[count($structs)-1]=="*")
					{
						$terms[count($terms)-1] = $terms[count($terms)-1]." ".$ar[$i];
					}
					else
					{
						$structs[] = "*";
						$terms[] = $ar[$i];
					}
				}
				else
				{
					$structs[] = $ar[$i];
					//$terms[] = "";
				}
			}
		}
		return array($structs,$terms);
	}	
	static function ng($ar,$n)
	{
		$nBinary = base_convert($n,10,2);
		$firstOne = count($ar)-strlen($nBinary);
	
		$struct = array($ar[0]);
	
		for($i = 1; $i < $firstOne; $i++)
		{
			$struct[count($struct)-1] = $struct[count($struct)-1]." ".$ar[$i];
		}
		for($i = $firstOne; $i < count($ar); $i++)
		{
			if($nBinary[$i-$firstOne]==0)
			{
				$struct[count($struct)-1] = $struct[count($struct)-1]." ".$ar[$i];
			}
			else
			{
				$struct[] = $ar[$i];
			}
		}
		return $struct;
	}
	static function pSerialize($ar)
	{
		$s = implode(" ",$ar[0]);
		$t = implode("|",$ar[1]);
		return array($s,$t);
	}
	static function ngSerialize($ar)
	{
		$s = implode("|",$ar);
		return $s;
	}

	static function ng2($ar,$n = 2) {
		if($n < 0) return [$ar];
		$out = [];
		$lit = [];
		foreach($ar as $item) {
			$lit[] = $item;
			consume1($ar);
			$next = algorithms::ng2($ar,$n-1);
			foreach($next as $next_item) {
				$out_add = array_merge([implode(" ",$lit)],$next_item);
				if(count($out_add) <= $n+1 || $n == -1) $out[] = $out_add;
			}
			if(empty($next)) $out[] = [implode(" ",$lit)];
		}
		return $out;
	}

	static function ga($ar,$n)
	{
		$ops = 0;
		$rem = $n;
		$sub = count($ar);
		$out = array();

		if(($n > ($sub*($sub+1)/2 - 1))||($n<0))
		{
			return -1;
		}

		while($rem>=$sub)
		{
			$rem = $rem - $sub;
			$ops++;
			$sub--;
		}
		for($i = $ops; $i <= $ops + $rem; $i++)
		{
			$out[] = $ar[$i];
		}
		return $out;
	}
	static function g($ar,$n)
	{
		$out = algorithms::ga($ar,$n);
		$out = implode(" ",$out);
		return $out;
	}
	static function gset(array $ar)
	{
		$out = array();
		for($i = 0; $i < count($ar)*(count($ar)+1)/2; $i++)
			$out[] = algorithms::g($ar,$i);
		return $out;
	}
	static function gk($ar,$lambda = null,$len = 1)
	{
		if(is_string($ar)) $ar = explode(" ",$ar);
		$out = [];
		$keys = array_keys($ar);
		do {
			$add = [];
			foreach($keys as $i=>$v)
			{
				if($i >= $len) break;
				$add[$v] = $ar[$v];
				if(isset($lambda)) $add = call_user_func_array($lambda,[$add]);
				$out[] = $add;
			}
			consume1($keys);
		} while(count($keys));
		return $out;
	}
}
?>
