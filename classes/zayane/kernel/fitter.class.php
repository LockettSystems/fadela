<?php
/*
 * fitter.class.php - Functions related to structure reuse / fitting
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

trait fitter
{

	/*
	SYS	SYS'	USR
	L+	N/A	L+
	L-	L+	N/A
	*/
	// TODO when the time comes to up functionality, don't forget crosspollination.
	function smart_mirror($index,$sender,$receiver,$sender_override = 0)
	{
		$bases = $this->list_base_keys();

		$base_nodes = $this->query_by_keys($bases);
		$last_vec = n2::vectorize_set([last_key($base_nodes) => last($base_nodes)]);

		$ballot = [];

		$pre_vec = null;

		$sent = [
			'el-0' => $this->status->l,
			'ep-0' => $this->status->p,
			'ed-0' => $this->status->d,
			'ea-0' => $this->status->a,
		];

		foreach($base_nodes as $i /* index */ => $v /* response candidate */)
		{
			$cur = [$i=>$v];
			$prev_base = $this->prev_base($i);
			$cur_vec = null;

			if(
				// temporary - don't respond with logical statements
				!lang::is_logical_base_flag($v->term[0])
				// don't attempt to respond to nonexistent previous statement
				&& !empty($pre)
				// follow the leader
				&& $v->sender == 1
				// but not when he's talking to himself
				&& $this->get($prev_base)->sender != 1
				// and he's not barking instructions
				&& !$this->get($i)->has_command
			) {
				$pair = $pre + $cur;
				$vec = n2::vectorize_set($pair);
				$cur_vec = last($vec);
				$first_vec = [first_key($vec)=>first($vec)];

				// anticipate usr response
				$next = $this->next_base($i);
				$next_vec = null;
				if(!empty($next)) {
					$next_set = n2::vectorize_set([$next=>$this->get($next)]);
					$next_vec = first($next_set);
				}

				$nvsent = !empty($next_vec)?[
							'el-0' => $cur_vec['el-0'],
							'ep-0' => $cur_vec['ep-0'],
							'ed-0' => $cur_vec['ed-0'],
							'ea-0' => $cur_vec['ea-0'],
						]:null;

				// comparison
				$cmp = n2::compare_set(
					n2::softbayes($first_vec),
					n2::softbayes($last_vec),
					1,
					(!empty($next_vec)?[
						$sent,
						$nvsent
					]:null));

				if(!empty($next_vec) && $next_vec['ea-0'] < 0) {
					$pre = $cur;
					continue;
				}

				if($cur_vec['ea-0'] < 0) {
					$pre = $cur;
					continue;
				}

				$ballot[] = [
					'base_ptr' => $i,
					'prob'=>$cmp,
					'seq'=>0,
					'dopa'=>0,
					'built'=>$this->build($i),
					'cur' => $cur_vec,
					'pre' => $pre_vec,
					'next' => $next_vec,
					'sent' => $sent,
					'nvsent' => $nvsent,
				];
				$pre_vec = $cur_vec;
			}
			$pre = $cur;
		}
		n2::crossref2($ballot);

		usort($ballot,function($x,$y){
			$a = $x['prob'];
			$b = $y['prob'];
			if($a > $b) return 1;
		else	if($a < $b) return -1;
		else	return 0;
		});

		$winner = first($ballot);

		if(empty($ballot)) {
			$vec = first($last_vec);
			if($vec['ea-0'] >= 0) return [];
		else	return [':|',['`','...']];
		}
		return $this->mirror($winner['base_ptr'],$sender,$receiver);
	}

	function fitted_base($returns,$type)
	{
	}

	function precise_fit_logical($logic,$i /* index of fitting candidate */)
	{
		$tree = $this->build($i);
		$layout = interpreter::getLayout($tree);
		$struct = interpreter::getLayout($tree);

		//logic::type
		$struct[0] = $logic->type;
		unset($layout[0]);

		//logic::truth
		//TODO precise fit for truth-terminals
		if(!in_array('LOG',$struct) && $logic->truth->getType(0) != '=') return 0;
	else	if($logic->truth->getType(0) != '=')
		{
			if($tree[array_search('LOG',$layout)] != $logic->truth->getType(0)) {
				$log_add = [$logic->truth->getType(0),$this->get_first_truth_literal($logic->truth->getType(0))];
				$struct[array_search('LOG',$layout)] = $log_add;
			}
			unset($layout[array_search('LOG',$layout)]);
		}

		$args = [];

		//TODO super QA on 1/25-1/26 expansion
		//TODO radical refactoring if possible

		if(!kernel::precise_fit_logical_terminal($logic,'subj1','ARG1',1,$layout,$args,$struct)) return 0; //logic::subj1
		if(!kernel::precise_fit_logical_terminal($logic,'subj2','ARG2',2,$layout,$args,$struct)) return 0; //logic::subj2
		if(!kernel::precise_fit_logical_terminal($logic,'act','CMT',0,$layout,$args,$struct)) return 0; //logic::act

		if(!kernel::precise_fit_logical_block($logic,'act',0,$layout,$args,$struct)) return 0; //plural cmt

		$krv = 0;
		do
		{
			$rv = kernel::precise_fit_logical_block($logic,'subj1',1,$layout,$args,$struct); //plural subj1
			if($krv && $rv == 1) break;
		else	if($krv && $rv != 1) return 0;
			$rv = kernel::precise_fit_logical_block($logic,'subj2',2,$layout,$args,$struct); //plural subj2
			$krv++;
		}
		while(1);

		foreach(array_keys($layout,'ARG') as $i=>$v) unset($layout[$i]);

		//logic::cond
		$cond;
		$cond_addr = -1;

		if(isset($logic->cond))
			$cond_addr = $logic->cond->contents[0];

		if(in_array('IF',$layout) && !isset($logic->cond)) return 0;
		if(!in_array('IF',$layout) && isset($logic->cond) && $this->get($cond_addr)->flag!='&') return 0;
		if(isset($logic->cond) && $this->get($cond_addr)->flag!='&')
			foreach($logic->cond->contents as $i=>$v)
			{
				$instr = new instr('i');
				$instr->init_logical($this->scope->contents[$this->get($v)->logical]);
				$cond = $this->instr_logic_reply($instr,'i',$instr->logic[0]->truth->getType(0));
			}

		//logic::impl
		$impl;
		if(in_array('THEN',$layout) && !isset($logic->impl)) return 0;
		if(!in_array('THEN',$layout) && isset($logic->impl)) return 0;
		if(isset($logic->impl))
			foreach($logic->impl->contents as $i=>$v)
			{
				$instr = new instr('i');
				$instr->init_logical($this->scope->contents[$this->get($v)->logical]);
				$impl = $this->instr_logic_reply($instr,'i',$instr->logic[0]->truth->getType(0));
			}

		foreach($layout as $i=>$v)
			if($v == 'IF' && isset($cond))
			{
				$fitted = kernel::fit_ifthen('{',$cond);
				if($fitted !== 0) $struct[$i] = $fitted;
			else	$struct[$i] = ['{',$cond];
				unset($layout[$i]);
			}
		else	if($v == 'THEN' && isset($impl))
			{
				$fitted = kernel::fit_ifthen('}',$impl);
				if($fitted !== 0) $struct[$i] = $fitted;
			else	$struct[$i] = ['}',$impl];
				unset($layout[$i]);
			}

		if(	count($logic->subj1->contents)||
			count($logic->subj2->contents)||
			count($logic->act->contents)
		) return 0;

		//terminals
		foreach($layout as $i=>$v)
		{
			if($tree[$i][0] == ',')
				$struct[$i] = $tree[$i];
			else return 0;
		}
		return $struct;
	}

	function fit_ifthen($type,$tree)
	{
		foreach($this->contents as $i=>$v)
			if($v->block==1 && $v->term[0]==$type)
			{
				$t = $this->build($i);
				$layout = interpreter::getLayout($t);
				$t[array_search('ARG',$layout)] = $tree;
				return $t;
			}
		return 0;
	}

	function and_fit($results)
	{
		$struct;
		foreach($this->contents as $i=>$v)
		{
			$results_orig = $results;
			if($v->block != 1 || $v->term[0] != '+') continue;
			$tree = $this->build($i);
			$struct = interpreter::getLayout($tree);
			$struct[0] = '+';

			foreach($struct as $i=>$v)
				if($i == 0) continue;
			else	if(!count($results_orig))
				{	$results_orig[] = 'no';
					break;
				}
			else	if($v != "UIP") $struct[$i] = consume1($results_orig);
			else	if($v == "UIP") $struct[$i] = $tree[$i];

			if(count($results_orig)) continue;
			else
			{
				return remnull($struct);
			}
		}
		return array_merge(['+'],$results);
	}
	function or_fit($results)
	{
		$struct;
		foreach($this->contents as $i=>$v)
		{
			$results_orig = $results;
			if($v->block != 1 || $v->term[0] != '/') continue;
			$tree = $this->build($i);
			$struct = interpreter::getLayout($tree);
			$struct[0] = '/';

			foreach($struct as $i=>$v)
				if($i == 0) continue;
			else	if(!count($results_orig))
				{	$results_orig[] = 'no';
					break;
				}
			else	if($v != "UIP") $struct[$i] = consume1($results_orig);
			else	if($v == "UIP") $struct[$i] = $tree[$i];

			if(count($results_orig)) continue;
			else
			{
				return remnull($struct);
			}
		}
		return array_merge(['/'],$results);
	}

	function logical_base_fit($block,$type)
	{
		foreach($this->contents as $i=>$v)
		{
			if($v->block != 1) continue;
		else	if($v->term[0] != $type) continue;
			$tree = $this->build($i);
			$layout = interpreter::getLayout($tree);
			$tree[array_search('ARG',$layout)] = $block;
			return $tree;
		}
		return 0;
	}

	function precise_fit_logical_terminal(&$logic,$cat,$layout_label,$n,&$layout,&$args,&$struct)
	{
		$flags = ['ARG1'=>'`','ARG2'=>'"','CMT'=>'.'];
		foreach($logic->$cat->contents as $i=>$v)
		{
			if(in_array($layout_label,$layout))
			{
				$kaddr = new kaddr($v);
				$kaddr->flag = $flags[$layout_label];
				$assn = $this->build_from_kaddr($kaddr);
				$struct[array_search($layout_label,$layout)] = $assn;
				$args[$n] = 1;
				unset($layout[array_search($layout_label,$layout)]);
				unset($logic->$cat->contents[$i]);
			}
		}
		$return = !intval(in_array($layout_label,$layout));
		return $return;
	}

	function precise_fit_logical_block(&$logic,$cat,$n,&$layout,$args,&$struct)
	{
		$counts = array_count_values($layout);
		$ocounts = array_count_values(interpreter::getLayout($struct));
		$count;
		$tree = $this->build_from_kaddr($logic->$cat);
		if(!isset($args[$n]) && $count = count($logic->$cat->contents))
		{
			if(!isset($counts['ARG'])) return 0;
			if($counts['ARG'] > $count/2 && (isset($ocounts['ARG1'])||isset($ocounts['ARG2']))) return 0;
		else	if(!isset($ocounts['ARG1'])&&!isset($ocounts['ARG2'])&&!in_array($tree[0],['`','.','"']))
		{
			if($counts['ARG'] == 1)
			{
				$logic->$cat->contents = [];
				$struct[array_search('ARG',$layout)] = $tree;
				unset($layout[array_search('ARG',$layout)]);
				return 1;
			}
			$result = 0;
			foreach($struct as $i=>$v)
				if($i == 0) continue;
			else	if(!is_string($v)) continue;
			else	if($v == 'ARG')
				{
					$de = interpreter::deep_eval($tree);	
					$max = array_keys($de,max($de));
					if(count($max) != 1) continue;
					$logic->$cat->contents = [];
					$struct[$i] = $tree;
					unset($layout[$i]);
					return 1;
				}
			if($result == 0) return -1;
			return 1;
		}
		else	{
				$keys;
				foreach($logic->$cat->contents as $i=>$v)
				{
					$keys = array_keys($layout,'ARG');
					$index = $keys[$i%count($keys)]; //TODO nope
					if(!is_array($struct[$index])) $struct[$index] = [];
					$struct[$index][] = $this->build_from_kaddr(new kaddr($v));
					unset($logic->$cat->contents[$i]);
				}
				foreach($keys as $i=>$v)
				{
					if(count($struct[$v])<2) return 0;
					$struct[$v] = kernel::and_fit($struct[$v]);
				}
			}

			foreach($layout as $i=>$v)
				if($v == 'ARG') unset($layout[$i]);
			foreach($struct as $i=>$v)
				if(is_array($v) && $v[0]=='+')
					$struct[$i] = kernel::and_fit(array_values(consume1_and_return($struct[$i])));
		}
		return 1;
	}
}
?>
