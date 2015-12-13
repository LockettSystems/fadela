<?php
/*
 * n2.class.php - Functions related to euclidean distance analysis.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class n2
{
	static function vectorize_base_logic($node)
	{
		if(!is_object($node) || get_class($node) !== 'kernel_node')
			throw new Exception('Invalid argument type.');
		switch($node->term[0])
		{
			case 'i':
			        $out = 1;
			     	break;
			case '?':
				$out = -1;
			     	break;
			default:
				$out = 0;
				break;
		}
		return $out;
	}
	static function vectorize_base_greet($node)
	{
		if(!is_object($node) || get_class($node) !== 'kernel_node')
			throw new Exception('Invalid argument type.');
		switch($node->term[0])
		{
			case '>':
			        return 1;
			     	break;
			case '<':
				return -1;
			     	break;
			default:
				return 0;
				break;
		}
	}
	static function vectorize_block($node,$key)
	{
		kernel::get_global($kernel);

		$term = $node->term;

		if($term[0] == $key) {
			return 1;
		} else if(lang::is_conjunction_flag($term[0]) || lang::is_logical_base_flag($term[0])) {
			for($i = 1; $i < count($term); $i++) {
				$subnode = $kernel->get($term[$i]);
				$res = n2::vectorize_block($subnode,$key);
				if($res == 1) return 1;
			}
			return -1;
		} else {
			return -1;
		}
	}
	static function vectorize_base_logic_truth($node,$addr)
	{
		$term = $node->term;
		if(!lang::is_logical_base_flag($term[0])) return 0;

		kernel::get_global($kernel);

		$base_addr = $kernel->get_base($addr,1);
		$log_node = $kernel->get($base_addr + 1);

		if($log_node->logical < 0) return 0;
		
		$log_logic = $kernel->scope->contents[$log_node->logical];
		try {
			$truth = $kernel->scope->evaluate($log_logic,$kernel);
		} catch(Exception $e) {
			$truth = new truth('~');
		}

		$truth_lit = $truth->getType(0);

		switch($truth_lit)
		{
			case '=': return 1; break;
			case '~': return 0; break;
			case '-': return -1; break;
		}

		return 0;
	}
	static function vectorize_terminal_flag($node)
	{
		if($node->block == 1) return 0;
		if($node->flag == '%') return -1;
	else	if($node->flag == '#') return 1;
	else	return 0;
	}

	static function vectorize_estat($node,$addr,$key)
	{
		kernel::get_global($kernel);
		do {
			if(isset($node->estat)) return $node->estat->get($key);
			$addr = $kernel->get_parent($addr,1);
			if($addr == null) break;
			$node = $kernel->get($addr);
		} while(1);

		return 0;
	}

	static function vectorize_auth($node)
	{
		if($node->sender == 1) return 1;
	else	return -1;
	}
	
	static function vectorize($node /* kernel_node */, $addr)
	{
		kernel::get_global($kernel);
		$out = [
		// Base pointer
		     'ptr' => $addr,
		// Logical Base Flag
		     'bl-0'    => n2::vectorize_base_logic($node),
		// Greeting Base Flag
		     'bg-0'    => n2::vectorize_base_greet($node),
		// Logical Types
		     'i-0'     => n2::vectorize_block($node,'_'),
		     'h-0'     => n2::vectorize_block($node,'^'),
		     'd-0'     => n2::vectorize_block($node,'*'),
		// Base Logic Truth
		     'blt-0'   => n2::vectorize_base_logic_truth($node,$addr),
		// Terminal flag -- still relevant?
		     'flag'   => n2::vectorize_terminal_flag($node),
		// Is this sentimental -- still relevant?
		     'sent'  => 1*(($node->estat == null)?-1:1),
		// Sentimental Info
		     'el-0'    => n2::vectorize_estat($node,$addr,'l'),
		     'ep-0'    => 1*n2::vectorize_estat($node,$addr,'p'),
		     'ed-0'    => 1*n2::vectorize_estat($node,$addr,'d'),
		     'ea-0'    => 1*n2::vectorize_estat($node,$addr,'a'),
		// Was this sent by USR -- still relevant?
		     'usr'    => n2::vectorize_auth($node),
		// Literals
		     '*lits'   => $kernel->extract_literals($addr),
		// Pointers
		     '*ptrs'   => $kernel->extract_tree_pointers($addr),
		// More specific literals
		     '*pri-lits' => $kernel->extract_literals($addr,['`','"']),
		     '*sec-lits' => $kernel->extract_literals($addr,['.']),
		     '*subj1-lits' => $kernel->extract_literals($addr,['`']),
		     '*subj2-lits' => $kernel->extract_literals($addr,['"']),
	//	     '*pri-ptrs' => [],
	//	     '*sec-ptrs' => [],
	//	     '*subj1-ptrs' => [],
	//	     '*subj2-ptrs' => [],
	//	     '*uip-lits' => []
		];
		return $out;
	}

	static function vectorize_historical($x,$y,$n)
	{
		foreach($y as $i=>$v)
		{
			$i = explode("-",$i);
			if(count($i)==1) continue;
			$new_key = "{$i[0]}-".($n+$i[1]);
			$x[$new_key] = $v;
		}
		return $x;
	}

	static function compare_set($x,$y,$dopa = 1,$status = null)
	{
		kernel::get_global($kernel);
		$out = [];
		$x = array_reverse($x);
		$y = array_reverse($y);
		foreach($x as $i=>$v)
		{
			if($i >= count($y)) break;
			$out[] = n2::compare_vectors($x[$i],$y[$i],$dopa);
		}
		if(!empty($status)) {
			$fooo = 0;
			foreach($status[0] as $i=>$v) {
				$fooo += 1 * pow($status[0][$i] - $status[1][$i],2);
			}
			$out[] = $fooo;
		}
		$out = array_reverse($out);
		return array_mean($out);
	}

	static function compare_terms($x,$y)
	{
		$intersect = (double) count(array_intersect($x,$y));
		$diff = (double) count(array_diff($x,$y));

		$out = $intersect/count($x) - abs(count($x) - count($y));

		return $out;
	}

	static function compare_vectors($x /* master */, $y /* contender */,$dopa = 1,$crossref = 0)
	{
		$x = array_reverse($x);
		$y = array_reverse($y);

		if(!$dopa)
		{
			unset($x['sent']);
			unset($y['sent']);
			unset($x['ep-0']);
			unset($y['ep-0']);
		}

		$sum = 0;
		$crossref_strikes = 0;
		foreach($x as $i=>$v)
		{
			if($i != 'ptr' && $i[0]!='*' && isset($y[$i]))
				$sum += pow($v-$y[$i],2);
		else	if($i[0] == '*')
			{
				if(!count($v))
				{
					$sum += 0;
					$crossref_strikes++;
					continue;
				}
				$w = $y[$i];
				$diff = n2::compare_terms($v,$w);
				if(!$diff) $crossref_strikes++;
				$sum += (1-$diff);
			}
		else	if(consume($i,'lits-') || consume($i,'ptrs-')) {
			        $k = "lits-$i";
				if(!isset($y[$k])) $sum += 2;
			}
		}
		//printDat([$crossref,$crossref_strikes]);
		if($crossref && $crossref_strikes == 2) return 9001;
		return sqrt($sum);
	}

	static function vectorize_set($set /* kernel_node, ... */)
	{
		$out = [];
		$keys = array_keys($set);
		foreach($set as $i=>$v)
			$out[$i] = n2::vectorize($v,$i);
		foreach($keys as $i=>$v) //container
		{
			$vec;
			foreach($keys as $j=>$w) //referenced
			{
				if($j >= $i) break;
				$diff = $i - $j;
				$vec = n2::vectorize_historical($out[$v],$out[$w],$diff);
			}
			if(isset($vec)) $out[$v] = $vec;
		}
		return $out;
	}
	static function simplify_vector($vec,$dopa = 1)
	{
		foreach($vec as $i=>$v)
		{
			$i =  explode("-",$i);
			if(count($i) > 1 && $i[1] > 0)
				unset($vec[implode('-',$i)]);
		}
		if(!$dopa)
		{
			unset($vec['sent']);
			unset($vec['ep-0']);
			unset($vec['ea-0']);
		}
		return $vec;
	}
	static function compare_basic($x,$y,$dopa = 1,$crossref = 0)
	{
		$x = n2::simplify_vector($x,$dopa);
		$y = n2::simplify_vector($y,$dopa);
		return n2::compare_vectors($x,$y,$dopa,$crossref);
	}
	static function crossref2(&$set)
	{
		// We don't work with empty sets.
		if(count($set) <= 1) return $set;
		// Compare the rest of the set to each index in the set.
		foreach($set as $i=>$v)
		{
			$subset = $set;
			unset($subset[$i]);
			$diffset = [];
			// Collect the euclidean distance values.
			foreach($subset as $j=>$w)
				$diffset[$j] = n2::compare_vectors($v['cur'],$w['cur']);
			// Take stdev.
			$stdev = math::stdev($diffset);
			// Take closest
			$min = min($diffset);
			// Compare stdev to euclidean distances and incrementally reassign dopa/seq.
			foreach($diffset as $j=>$w)
			{
				if(abs($w-$min) <= $stdev) $set[$i]['dopa'] += 0.1*$subset[$j]['dopa'];
				if(abs($w-$min) <= $stdev) $set[$i]['seq'] += 0.1*$subset[$j]['seq'];
			}
		}
	}
	static function crossref(&$set)
	{
		$cd = 1;
		if(count($set) <= 1)
		{
			unset($set[first_key($set)]['raw']);
			return;
		}
		$newset = $set;
		$mean = 0;$sum = 0;$cmps = 0;
		foreach($set as $i=>$v)
		{
			// calculate average commonality
			foreach($set as $j => $w)
			{
				if($i == $j) continue;
				$k = n2::compare_basic($v['raw'],$w['raw'],$cd);
				$sum += $k;
				$cmps++;
			}
		}
		if($cmps) $mean = $sum/$cmps;
		foreach($set as $i=>$v)
		{
			// calculate dopamine difference based on commonality
			foreach($set as $j => $w)
			{
				if($i == $j) continue;
				$k = n2::compare_basic($v['raw'],$w['raw'],$cd,1);
				$newdopa = n2::crossref_coefficient($k,$mean,$w['dopa']);
				$newseq = n2::crossref_coefficient($k,$mean,$w['seq']);
				if(0 /*$newseq < 0*/) printDat([
					'v' => $v['raw']['*lits'],
					'w' => $w['raw']['*lits'],
					'coeff' => n2::crossref_coefficient($k,$mean,$w['dopa']),
					'newdopa' => $newdopa,
					'newseq' => $newseq,
					'k' => $k,
					'mean' => $mean,
					'dopa' => $w['dopa']],2);
				$newset[$i]['dopa'] += $newdopa;
				$newset[$i]['seq'] += $newseq;
			// needs separate averaging
			}
		}
		//foreach($newset as $i=>$v) unset($newset[$i]['raw']);
		$set = $newset;
	}
	static function crossref_coefficient($comparison,$mean,$dopa)
	{
		if($comparison != 9001)
		{
			$foo = abs($comparison - $mean);
			// TODO please institute some sensible formulas here
			if(!$comparison) $foo = 1;
		else	if(!$mean) $foo = 1;
			return 0.1*$foo*$dopa;
		}
		else return 0;
	}
	static function bayes($set,$nolits = [])
	{
		$adds = [];
		$ptrs = $set['*ptrs'];
		$lits = $set['*lits'];
		//foreach($ptrs as $i=>$v) $adds["ptrs-$v"] = true;
		foreach($lits as $i=>$v) $adds["lits-$v"] = true;
		foreach($nolits as $i=>$v) if(!isset($adds["lits-$v"])) $adds["lits-$v"] = false;

		foreach($set as $i=>$v)
			if(!consume($i,'ptrs') && !consume($i,'lits')) unset($set[$i]);

		$out = array_merge($set,$adds);
		return $out;
	}
	static function softbayes($set)
	{
		return $set;

		$adds = [];

		$first_key = first_key($set);
		$set = first($set);

		if(isset($set['*ptrs'])) {
			$ptrs = $set['*ptrs'];
			unset($set['*ptrs']);
			foreach($ptrs as $i=>$v) $adds["ptrs-$v"] = 1;
		}
		if(isset($set['*lits'])) {
			$lits = $set['*lits'];
			foreach($lits as $i=>$v) $adds["lits-$v"] = 1;
			unset($set['*lits']);
		}
		$out = array_merge($set,$adds);
		//print_r($out);die();
		return [$first_key => $out];
	}
}
?>
