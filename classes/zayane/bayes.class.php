<?php
/*
 * bayes.class.php - Bayesian analysis module.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/

class bayes
{
	static $instance;
	public $contents = [];
	function __construct()
	{
	}
	static function get_instance()
	{
		if(bayes::$instance == null) bayes::$instance = new bayes();
		return bayes::$instance;
	}
	function add($k1,$v1,$k2,$v2)
	{
	// In the future, considering neighbors as well as followers might be interesting.
		foreach($this->contents as $i=>$v)
			if($v[0] != $k1) continue;
		else	if($v[1] != $v1) continue;
		else	if($v[2] != $k2) continue;
		else	if($v[3] != $v2) continue;
		else	{
				$this->contents[$i][4]++;
				return;
			}
		$this->contents[] = [
			$k1,$v1,
			$k2,$v2,
			1
		];
	}
	function assoc($a,$b)
	{
		foreach($a as $i=>$v)
			foreach($b as $j=>$w)
				$this->add($i,$v,$j,$w);
	}

	static function subsets($set,$len)
	{
		if(!$len) return [];
		$out = [];
		foreach($set as $i=>$v)
		{
			$add = [];
			$subset = $set;
			unset($subset[$i]);
			$subset = array_values($subset);
			$add[] = $v;
			$rem = bayes::subsets($subset,$len-1);
			if(count($rem))
				foreach($rem as $j=>$w)
					$out[] = array_merge($add,$w);
			else	$out[] = $add;
		}
		//printDat($out);
		return $out;
	}

	// p( attrib[] | attrib[] )
	function naive_classify_set($set,$pre)
	{
//		echo 'p('.implode(",",array_keys($set)).' | '.implode(',',array_keys($pre)).')'."\n";hr();
		$out = 1;
		$successes = 0;
		$probs = [];
		foreach($set as $k=>$v)
		{
			$eval = $this->naive_classify($k,$v,$pre);
			if(0 && $eval == -1) continue;
		else	$probs["$k-".intval($v)] = $eval;
			$successes++;
		}
//		echo "Result: $out<br/>";
//		hr();

		// traditional approach (doesn't work) -- grand intersection

		if(0) foreach($probs as $i=>$v) $out *= $v;
		// union
		// p(AuBuC) = p(A) + p(B) - p(AuB)
		else if(1) $out = $probs;
		else
		{
			$p = 0;
			for($i = 0; $i < count($probs); $i++)
			{
				$n = $i+1;
				$sum = 0;
				$subsets = bayes::subsets($probs,$n);
				foreach($subsets as $j=>$w) $sum += array_product($w);
				if( !($i%2) ) $p += $sum;
				if( ($i%2) ) $p -= $sum;
			}
			$out = $p;
		}

		if($successes) return $out;
	else	return 0; //orig -1
	}

	// p( attrib | attrib[] )
	function naive_classify($key,$val,$set)
	{
		$out = $this->pfollowing($key,$val);
		if($out == -1) return -1;
		$successes = 0;
		foreach($set as $k=>$v)
		{
			$num = $this->pfollowing($k,$v,$key,$val);
			$den = $this->pfollowing($k,$v);
			if( ($num <= 0 || $den <= 0)) {
			    	  $out *= 0.000001;
				continue;
			}
			$out *= ($num/$den);
		//echo "($num/$den) = $out<br/>";
			$successes++;
		}
		$out;
		if(!$successes) $out = 0; //orig -1
//		echo "p($key | {".implode(",",array_keys($set))."}) = $out\n";
		return $out;
	}

	// p( attrib | optional-givens )
	function pfollowing($key,$val,$given_k = null,$given_v = null,$both = 0)
	{
		$subtotal = 0;
		$total = 0;
		foreach($this->contents as $k=>$v)
		{
			if($both && ($v[2] == $key || $v[0] == $key))
			{
				$total += $v[4];
				if($v[0] == $key && $v[1] == $val || $v[2] == $key && $v[3] == $val) $subtotal += $v[4];
			}
		else	if($v[2] == $key || $v[0] == $given_k)
			{
				$total += $v[4];
				if($v[2] == $key && $v[3] == $val && (is_null($given_k) || ($given_k == $v[0] && $given_v = $v[1]))) $subtotal += $v[4];
			}
		}
		if(!empty($total)) $out = $subtotal/$total;
	else	return -1;
		$b = 1;
		if(!is_null($given_k)) $b = $this->pfollowing($given_k,$given_v,null,null,1);

		if($out > $b)
		{
			echo "$subtotal/$total = $out -> / $b<br/>";
		}

		return $out/$b;
	}

	function get_lits()
	{
		$out = [];
		foreach($this->contents as $v)
		{
			$x = intval(consume($v[0],'lits-'));
			$y = intval(consume($v[2],'lits-'));
			if($x) $out[] = $v[0];
			if($y) $out[] = $v[2];
		}
		return array_unique($out);
	}
}
?>
