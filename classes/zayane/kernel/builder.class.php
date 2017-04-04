<?php
/*
 * builder.class.php - Functions related to structure or literal building.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

trait builder
{
	// buildStrLit & helpers: please move to more appropriate traits

	// extracted concept roots, but with 0s and 3s flipped.
	function get_inverted_concept_roots($addr) {
		$concept_roots = $this->extract_concept_roots($addr);
		foreach($concept_roots as $i => $raddr) {
			if($raddr == 0) $concept_roots[$i] = 3;
			else if($raddr == 3) $concept_roots[$i] = 0;
		}
		return $concept_roots;
	}

	// identify vectors with similar addresses.
	function get_synonym_candidates($vec) {
		 $out = [];
		 foreach($vec as $addr) {
		 	$node = $this->get($addr);
			if(empty($node->backtrace)) continue;
			foreach($node->backtrace as $bt) {
				if(in_array($bt,[0,3])) continue;
				$out[$bt] = $this->extract_concept_roots($bt);
			}
		 }
		 return $out;
	}
	
	function buildStrLit($addr,$root)
	{
		if($addr == 1) $addr = 3;
		if($addr == 2) $addr = 0;

		// Alternative implementation: deriving terminology from concept root vector analysis.
		#$concept_roots = $this->get_inverted_concept_roots($addr);
		$concept_roots = $this->extract_concept_roots($addr);
		
		$syncans = $this->get_synonym_candidates($concept_roots);
		$champion = ['addr' => $addr, 'set' => $concept_roots];
		$champion_weight = 0;
		foreach($syncans as $i => $can) {
			if($this->get($i)->flag != $this->get($addr)->flag) continue;
			
			$intersect_count = count(array_intersect($concept_roots,$can));
			$challenger_weight = $intersect_count/max(count($syncans),count($concept_roots));
			kernel_lib::microballot($champion,['addr'=>$i,'set'=>$can],$champion_weight,$challenger_weight);
		}
		$caddr = $champion['addr'];
		$icaddr = $caddr;

		$init_addr = $addr;

		$flag = '%';

		if($caddr == 3) {
			$init_addr = 0;
			$caddr = 0;
		} else if($caddr == 0) {
			$init_addr = 3;
			$caddr = 3;
		} else {
			$flag = '';
		}

		$cnode = $this->get($caddr);
		if(empty($flag)) $flag = $cnode->flag;

		$out = '';
		if(count($cnode->get_pointers())) {
			if(empty($flag)) $flag = '#';
			$out .= $flag . implode(",",$cnode->get_pointers()) . "|";
		} else if(!empty($flag)) {
			$out .= $flag . $init_addr . "|";
		}

		if(!empty($flag)) {
			$out .= kernel_node::getName($flag,$caddr,1,2);
		} else if(!empty($cnode->flag)) {
		        $out .= kernel_node::getName($cnode->flag,$caddr);
		} else {
			$out .= $cnode->get_term();
		}

		// Post-overhaul todo list:
		// - get the flags right depending on context & verification
		// - oat-driven flags.

		return $out;
		
		////////////////////////////////////////////////////////////////
		// Begin main implementation.
		////////////////////////////////////////////////////////////////
		
		$parent = $this->get_parent($addr);
		$out;
		$flag;
		$init_addr = $addr;

		if($addr == 0 || $addr == 3) $flag = '%';

		//TODO someday everything related to this needs to become better organized.
		if($addr == 0)
		{
			$addr = $this->receiver;
			$init_addr = 3;
		}
		if($addr == 3)
		{
			$addr = $this->sender;
			$init_addr = 0;
		}

		if(is_array($root)) $root = "";
		if(!isset($flag)) $flag = $this->getFlag($addr);

		if($flag==null && ($parent == null || ($parent->term[0]!=','))) $flag = '#';
		$pointers = $this->getPointers($addr);
		if(!count($pointers)) $pointers = [$init_addr];
		$pointers = implode(",",$pointers).(count($pointers)?"|":"");
		//TODO Does this account for the more esoteric oddities of literal pointers? (e.g., colons)
		if(isset($flag) && strlen($flag)>0)
			$out = $flag.$pointers.kernel_node::getName($flag,$addr);
		else	$out = $root;
		return $out;
	}
	function build_from_term($li)
	{
		foreach($li as $i=>$v)
			if($i == 0) continue;
			else $li[$i] = $this->build(consume1_and_return($v));
		return $li;
	}
	function build($addr)
	{
		//Please replace colons and such indicators with proper object types
		$root = $this->getTerm($addr);
		if($this->get($addr)->logical != -1) {
			return $this->build_logical($addr);
		} elseif(is_string($root) || count($root)==0) {
			$res = $this->buildStrLit($addr,$root);
			return $res;
		}
		foreach($root as $i=>$v)
			if($i == 0) continue;
			else if(is_numeric($v))
				$root[$i] = $this->build($v);
		return $root;
	}
	function build_from_kaddr($kaddr,$fitted = 0)
	{	//TODO QA
		$out = [];
		$addrs = $kaddr->get_contents();
		foreach($addrs as $i=>$v)
		{
			$result;

			if($this->get($v)->logical >= 0)
			{
				$log = $this->scope->contents[$this->get($v)->logical];
				$result = $this->build_logical($v);
			}
		else	if($this->get($v)->block==1)
				$result = $this->build($v);
		else
			{	
				$result = $this->build_from_lit_addr($v);
				if(isset($kaddr->flag)) $result[0] = $kaddr->flag;
			}

			if(count($addrs) == 1)
				$out = array_merge($out,$result);
			else	$out[] = $result;
		}

		if(count($kaddr->get_contents())>1)
			$out = $this->and_fit($out);
		return $out;
	}
	function build_from_logic($logic,$fixed_truth=null)
	{
		$instr = new instr('i');
		$instr->init_logical($logic);
		$t = $this->instr_logic_reply($instr,'i',$fixed_truth);
		return $t;
	}
	function build_logical($addr)
	{
		$log = $this->scope->contents[$this->get($addr)->logical];
		$out = $this->build_from_logic($log);
		return $out;
	}
	function build_from_lit_addr($addr)
	{
		return ['`',$this->build($addr)];
	}

}
?>
