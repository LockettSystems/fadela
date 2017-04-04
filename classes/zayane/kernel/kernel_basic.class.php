<?php
/*
 * kernel_basic.class.php - Kernel helper functions.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class kernel_basic extends kernel_core
{
	function get_latest_base($sender = null,$addr = false)
	{
		$base_keys = $this->list_base_keys();
		$base_keys = array_reverse($base_keys);
		while(count($base_keys))
		{
			$key = consume1($base_keys);
			if(empty($sender)) return ($addr)?$key:[$key=>$this->get($key)];
			if($this->get($key)->sender == $sender) return ($addr)?$key:[$key=>$this->get($key)];
		}
		return null;
	}
	function prev_base($addr)
	{
		$keys = array_reverse($this->list_base_keys());
		foreach($keys as $i=>$v)
		{
			if($v < $addr) return $v;
		}
		return null;
	}
	function next_base($addr)
	{
		$keys = $this->list_base_keys();
		foreach($keys as $i=>$v)
		{
			if($v > $addr) return $v;
		}
		return null;
	}
	function getFlag($addr)
	{
		$out = $this->get($addr)->flag;
		if($out==null) return "";
		else return $out;
	}
	function getPointers($addr)
	{
		$out = $this->get($addr)->get_pointers();
		if($out==null) return array();
		else return $out;
	}
	function getTerm($addr)
	{
		if($addr == 0) $addr = $this->receiver;
		if($addr == 3) $addr = $this->sender;
		return $this->get($addr)->get_term();
	}
	function get_heap_addr($addr)
	{
		if(isset($this->heap[$addr])) return $this->heap[$addr];
	else	throw new Exception("Invalid Kernel heap address requested: $addr");
	}
	function list_base_keys($sender = null, $not_sender = null)
	{
		$out = [];
		foreach($this->list_keys() as $i=>$v)
			if(
			$this->get($v)->root == 1 &&
			($sender == null || $this->get($v)->sender == $sender) &&
			($not_sender == null || $this->get($v)->sender != $not_sender))
				$out[] = $v;
		return $out;
	}
	function get_vectorized_last($len = 1,&$ptr)
	{
		// all statement base ptrs
		$keys = $this->list_base_keys();

		// so we can iterate from end to front
		$keys = array_reverse($keys);

		// identify most recent kernel node sent by SYS
		while($this->get($keys[0])->sender == 2) consume1($keys);

		$ptr = $keys[0];
		$keys = array_reverse($keys);
		$sets = [];
		if(count($keys) < $len) $len = count($keys);

		// it looks like identify all vectors from where keys left off, up to length len.
		foreach($keys as $i=>$v)
		{
			$add = [];
			for($it = $len-1; $it >= 0; $it--)
			       $add[$keys[count($keys)-1-$it]] = $this->get($keys[count($keys)-1-$it]);
			$add = n2::vectorize_set($add);
			if(!count($add)) break; // in case of malfunctions, you may want to remove this line -- 10/26/14
			$sets[] = $add;
			$len--;
		}
		return $sets;
	}
	function extract_literals($addr,$flags = [],$prev_flag = null)
	{
		$out = [];
		$node = $this->get($addr);
		$t = $node->get_term();
		if(!is_array($t))
		{	//TODO do something more appropriate and better integrated with existing infrastructure
			if(!in_array($prev_flag,$flags)) return $out;
			if(in_array(3,$node->get_pointers())) return ['_SELF'];
			if(in_array(0,$node->get_pointers())) return ['_OTHER'];
			return [$t];
		}
	else	foreach($t as $i=>$v)
		{
			if($i == 0) continue;
			$out = array_unique(array_merge($out,$this->extract_literals($v,$flags,$t[0])));
		}
		return $out;
	}
	function extract_tree_pointers($addr)
	{
		$out = [];
		$base = $this->get($addr);
		if(is_array($base->get_term())) {
			foreach($base->get_term() as $i=>$v)
				if(!$i) continue;
			else	$out = array_merge($out,$this->extract_tree_pointers($v));
		}
	else	$out = array_merge($out,$base->get_pointers());
		// TODO in the future you may want to run the same conceptual extraction process as the scope does.
		return array_unique($out);
	}
	function extract_pointers($addr)
	{//kernel::extract_concept_roots()
		$out = [];
		$node = $this->get($addr);
		$t = $node->term;
		if(!is_array($t)) return $this->extract_concept_roots($addr);
	else	foreach($t as $i=>$v)
		{
			if($i == 0 || !isset($this->get($v)->flag)) continue;
			$out = array_unique(array_merge($out,$this->extract_pointers($v)));
		}
		return $out;
	}
	function get_vectorized_response_candidates($len = 1)
	{
		// all statement base addresses
		$keys = $this->list_base_keys();
		// converted to nodes
		$nodes = $this->query_by_keys($keys);
		// apply algorithm g
		$sets = algorithms::gk($nodes,null,$len);
		// vectorize
		foreach($sets as $i=>$v)
		{
			$sets[$i] = n2::vectorize_set($v,null,$len);
		}
		return $sets;
	}
	function get_trailing_vectors($len,$addr)
	{
		$keys = $this->list_base_keys();
		while(count($keys) && $keys[0] <= $addr)
		{
			consume1($keys);
		}
		if(!count($keys)) break;
		$nodes = $this->query_by_keys($keys);
		$set = [];
		if($len > count($keys)) $len = count($keys);
		foreach($nodes as $i=>$v)
		{
			$add = [];
			for($it = 0; $it < $len; $it++)
			{
				$add[$keys[$it]] = $nodes[$keys[$it]];
			}
			if(!count($add)) break; // consider for removal if malfunction -- 10/26/14
			$len--;
			$set[] = n2::vectorize_set($add);
		}
		return $set;
	}
	function generate_commonality_ballot($last_ptr,$sender,$receiver,$sender_override = 0)
	{
		$last_node = [$last_ptr => $this->get($last_ptr)];
		$last_vec = [n2::vectorize_set($last_node)];

		// Previous statements + optionally, context similar to last
		$prev = $this->get_vectorized_response_candidates(1);

		// We're attempting to identify statements by SYS most similar to the last one by USR.

		// Filter -- must be from SYS, must not be last statement.
		foreach($prev as $i=>$v)
			if(in_array($last_ptr,array_keys($v))) unset($prev[$i]);
		else	{
				$keys = array_reverse(array_keys($v));
				if((!$sender_override && $this->get($keys[0])->sender != 2) || ($sender_override && $this->get($keys[0])->sender != 1))
				{
					unset($prev[$i]);
				}
			}
		$prev = array_values($prev);
		if(!count($prev)) return [];

		// Identify previous statement most like last through vector comparison
		$ballot = [];
		foreach($last_vec as $i=>$v)
		{
			foreach($prev as $j=>$w)
			{
				if(count($v) != count($w)) continue;
				$ptr = last_key($w);
				$comp = n2::compare_set($v,$w);
				$mean = array_mean($comp);
				$add = [
					'ptr' => $ptr,
					'val' => $mean,
					'raw' => $w[last_key($w)]
				];
				$ballot[] = $add;
			}
		}

		$ballot2 = [];

		// Compress previous information
		foreach($ballot as $i=>$v)
		{
			if(!isset($ballot2[$v['ptr']])) $ballot2[$v['ptr']] = ['ptr'=>$v['ptr'],'weight' => $v['val'],'hits' => 1,'raw'=>$v['raw']];
		else	{
				$ballot2[$v['ptr']]['hits']++;
				$ballot2[$v['ptr']]['weight'] += $v['val'];
			}
		}

		// Sort based on relevance
		usort($ballot2,function($x,$y){
			$a = $x['weight']/$x['hits'];
			$b = $y['weight']/$y['hits'];
			if($a == $b) return 0;
			return ($a > $b)?-1:1;
		});

		return $ballot2;
	}
}
?>
