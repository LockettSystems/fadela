<?php
/*
 * scope.class.php - Handles scoping of logic.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

/*
 Root Codes:
 -1	Hypothetically Active Rule
 0	Inactive Rule
 1	Active Rule
 2	Hypothetically Inactive Rule
 TODO:
 - Constants.  Seriously.
 - Hypothetically invalid rule code (derived from root 1)
 - Expanding / invalidating rules via sleep or evaluation process
 - Instead of hypothetically inactive rules,
   you may want to create new rules with different root codes
   to prevent disrupting the dependencies.
 - SQL migration.  It's optimized for this in a lot of ways.
*/
class scope extends fmr
{
	public $contents;
	public $conflicts;
	function __construct()
	{
		$this->contents = [];
		$this->conflicts = [];
	}
	function get($i) {
		if(!isset($this->contents[$i])) {
			throw new Exception("scope::get() error: address '$i' not found.\n".print_r($this,1));
		}
		return $this->contents[$i];
	}
	function disambiguate($addr,$kernel)
	{
		foreach($this->contents as $i=>$v)
			if($v->root != 1) continue; //TODO for now
		else	if($v->type != '^') continue;
		else	if(!in_array($addr,$v->subj2_addr())) continue;
		else	{
				$x = array_map([$kernel,'addr2returntype'],$v->subj1_addr());
				$z = [];
				foreach($x as $j=>$w) $z = array_merge($w->addrs());
				$z = array_unique($z);
				foreach($z as $j=>$w)
					if($kernel->get($w)->search($kernel->get($addr)->term)==1)
					{
						return $w;
					}
			}
		return -1;
	}
	function getnodesbyidset($addrs)
	{
		$out = [];
		foreach($this->contents as $i=>$v)
		{
			$x = array_intersect($addrs,$v->subj1_addr());
			$y = array_intersect($addrs,$v->act_addr());
			$z = array_intersect($addrs,$v->subj2_addr());
			if(count($x)+count($y)+count($z)) $out[$i] = $v;
		}
		return $out;
	}
	function dump_hypotheticals() //I smell a memory leak.
	{
		foreach($this->contents as $i=>$v)
			if($v->type == -1) $this->get($i)->root = 0;
	}
	function sleep($kernel,$hypotheticals = 0)
	{
		$ops = 0;
		foreach($this->contents as $i=>$v)
		{
			if($v == null) continue;
			if($v->root > 0 || $v->root == -1 && $hypotheticals)
			{
				$eval = $this->evaluate($v,$kernel,1,1)*2-1;
				if($eval == 1 && isset($eval->impl))
					foreach($eval->impl->contents as $i=>$v)
					{
						if($this->get($kernel->get($v)->logical)->root == 0)
						{
							$this->get($kernel->get($v)->logical)->root = ($hypotheticals)?-1:1;
							$ops = 1;
						}
					}
			}
		}
		return $ops;
	}
	function verify($x,$y,$cat,$kernel)
	{
		if(!count($x->$cat->get_contents()) && !count($x->$cat->get_contents())) return 1;
	else	if(!count($x->$cat->get_contents()) || !count($y->$cat->get_contents())) return 0;

		$x_roots = $kernel->extract_concept_roots($x->$cat->get(0));
		if(!count($x_roots)) throw new AmbiguousRootException('Ambiguous root.');
		$y_roots = $kernel->extract_concept_roots($y->$cat->get(0));

		$intersection = array_intersect($x_roots,$y_roots);
		$out = count($intersection)/count($x_roots);
		return $out;
	}
	function verify_truth($x,$y,$kernel)
	{
		$xt = $x->truth->getType(0);
		$yt = $y->truth->getType(0);

		$vals = ['=' => 1, '~' => 0, '-' => -1];

		return $vals[$xt] * $vals[$yt];
	}
	function verify_cond($x,$kernel)
	{
		if(get_class($x->cond) != 'logic')
			$x_cond_eval = 1;
		else	$x_cond_eval = $this->evaluate($x->cond,$kernel,1,1)*2-1;
		if(get_class($y->cond) != 'logic')
			$y_cond_eval = 1;
		else	$y_cond_eval = $this->evaluate($y->cond,$kernel,1,1)*2-1; //TODO is this *2-1 stuff valid?
		return $x_cond_eval * $y_cond_eval;
	}
	function val2truth($val)
	{
		if($val > 2.0/3.0) return '=';
	else	if($val > 1.0/3.0) return '~';
	else	return '-';
	}
	function focus(&$logic)
	{
		if(isset($logic->impl) && get_class($logic->impl)=='logic')
		{
			$focus = $logic->impl;
			$precond = $logic;
		}
	else	if(isset($logic->cond) && get_class($logic->cond)=='logic')
		{
			$focus = $logic;
			$precond = $logic->cond;
		}
	else
		{
			$focus = $logic;
			$precond = null;
		}

		if($precond != null)
		{
			$precond->root = -1;
			$this->contents[] = $precond;
		}

		return $focus;
	}
	function create_hypotheticals($logic,$kernel)
	{
		//if implications, set them to temporarily true -- TODO QA seriously
		if(isset($logic->cond) && $kernel->get($logic->cond->get(0))->flag != '&')
		{
			foreach($logic->cond->get_contents() as $i=>$v)
			{
				if($this->get($kernel->get($v)->logical)->root == 0)
					$this->get($kernel->get($v)->logical)->root = -1;
			}
		}
		else return 0;
		return 1;
	}
	function complete(&$logic)
	{
		kernel::get_global($kernel);

//		if(!count($logic->subj1_addr())) return -1;
//		if(!count($logic->subj2_addr())) return -1;

		$subj1 = $kernel->extract_concept_roots($logic->subj1->get(0));

		if(count($logic->act->get_contents()))
			$act = $kernel->extract_concept_roots($logic->act->get(0));
		else	$act = [null];

		$subj2 = $kernel->extract_concept_roots($logic->subj2->get(0));


		if(isset($logic->cond) && count($logic->cond->get_contents()))
			$cond = $kernel->extract_concept_roots($logic->cond->get(0));
		else	$cond = [null];

		if(count($subj1) && count($act) && count($subj2) && count($cond)) return 0;

		//TODO you should consider doing a logical merge here for a smooth yet verbose display of intellect.

		if(count($subj1) && count($act) && count($subj2))
		{
			$eval_logic = object_clone($logic);
			$eval_logic->cond = null;
			$truth = $this->evaluate($eval_logic,$kernel);
			$type = $truth->getType(0);
			if($type != '=') return 0;
		}

		foreach($this->contents as $i=>$v)
		{
			if($v->root == 0) continue;
		else	if($logic->type != $v->type) continue;
		else	if($kernel->get($v->subj1->get(0))->flag == '&') continue;
		else	if(count($v->act->get_contents()) && $kernel->get($v->act->get(0))->flag == '&') continue;
		else	if($kernel->get($v->subj2->get(0))->flag == '&') continue;
		else	if(isset($v->cond) && $kernel->get($v->cond->get(0))->flag == '&') continue;
			$subj1; $action; $subj2; $cond; $truth;
			$subj1_replacement;
			$act_replacement;
			$subj2_replacement;

			try {
				$subj1 = $this->verify($logic,$v,"subj1",$kernel);
				if($subj1 < 2/3) continue;
			} catch(Exception $e) {
				$subj1 = -1;
			}

			try {
				$action = ($logic->type == '_'||$logic->type == '^')?1:$this->verify($logic,$v,"act",$kernel);
				if($action < 2/3) continue;
			} catch(Exception $e) {
				$action = -1;
			}

			try {
				$subj2 = $this->verify($logic,$v,"subj2",$kernel);
				if($subj2 < 2/3) continue;
			} catch(Exception $e) {
				$subj2 = -1;
			}

			$truth = $this->verify_truth($logic,$v,$kernel);
			if($truth < 1) continue;

			$cond = 0;
			if(isset($v->cond))
			{
				foreach($v->cond->get_contents() as $j=>$w)
				{
					$l = $this->get($kernel->get($w)->logical);
					$eval = $this->evaluate($l,$kernel,1,1);
					$cond += ($eval*2-1);
				}
			}
			else $cond = 1;
			if($cond < 2/3) continue;
			else $cond = -1;

			if($subj1 != -1 && $action != -1 && $subj2 != -1 && $cond != -1) continue;

			if($subj1 == -1) $logic->subj1->set(0, $v->subj1->get(0));
			if($action == -1) $logic->act->set(0, $v->act->get(0));
			if($subj2 == -1) $logic->subj2->set(0, $v->subj2->get(0));
			if($cond == -1 && isset($v->cond) ) $logic->cond = object_clone($v->cond);

			return 1;
		}
		return -1;		
	}	
	function simple_logic_eval(logic $logic)
	{
		$filter = function($logic,$v)
		{
			kernel::get_global($kernel);
			if($v->root == 0) return 0;
			if($logic->type != $v->type) return 0;
			
                        $subj1 = $this->verify($logic,$v,"subj1",$kernel);
                        $action = ($logic->type == '_'||$logic->type == '^')?1:$this->verify($logic,$v,"act",$kernel);
                        $subj2 = $this->verify($logic,$v,"subj2",$kernel);
                        $truth = $this->verify_truth($logic,$v,$kernel);

			return $subj1 * $action * $subj2 * $truth;
		};
		$set = array_map($filter,array_fill(0,count($this->contents),$logic),object_clone($this->contents));
		return remnull($set,0,0);
	}

	function simple_compare($logic,$v)
	{
		if(is_int($logic)) $logic = $this->get($logic);
		if(is_int($v)) $v = $this->get($v);
		kernel::get_global($kernel);
		$subj1 = $this->verify($logic,$v,"subj1",$kernel);
        	$action = ($logic->type == '_'||$logic->type == '^')?1:$this->verify($logic,$v,"act",$kernel);
		$subj2 = $this->verify($logic,$v,"subj2",$kernel);
      		$truth = $this->verify_truth($logic,$v,$kernel);
		return $subj1 * $action * $subj2 * $truth;
	}

	function compare_impl($logic,$v)
	{
		kernel::get_global($kernel);
		$impl = 0.5;
		if(!isset($v->impl)) $impl = 0;
	else	foreach($logic->impl->contents as $j=>$w)
		{
			$sum = 0;
			foreach($v->impl->contents as $k=>$x)
			{
				if($j > $k) continue;
				$impl_mag = $this->simple_compare($kernel->get($w)->logical,$kernel->get($x)->logical);
				$cond = 1; //for now TODO this will be dealt with in verbose mode or something
				$sum += $impl_mag * $cond;
				// The usual "simple" logic magnitude calculations.
				// ...conversely, you may want to make this somehow recursive for greater system expressiveness and general modularity
				// By the way, a stack tracking everything being analyzed could prevent another stack overflow, especially if we start referencing existing logic, which has the potential for indefinite recursion
			}
			$impl += $sum/count($logic->impl->contents);
		}
		return $impl*2-1;
	}

	function evaluate(logic $logic,$kernel,$numeric = 0,$sleeping = 0)
	{
		// For each compatible entry, evaluate logic against it without considering COND/IMPL.
		$equivs = $this->simple_logic_eval($logic);

		// If an integer is given rather than a logic item, look for the logic in that particular index.
		$logic_addr = null;
		if(is_int($logic))
		{
			$logic_addr = $logic;
			$logic = $this->get($logic_addr);
		}

		//initialization
		$hypos = 0;

		// If sleep process is not occurring, validate hypothetical rules.
		if(!$sleeping) $hypos = $this->create_hypotheticals($logic,$kernel);

		// Main logic being evaluated is now hypothetically valid.
		if($hypos) $logic->cond = null;

		//sleep maybe after -- here's a point of interest for serotonin -- but before the iterations
		//Re-added while we TODO establish a coherent procedure for focus()/sleep
		//Sleeping should occur well before evaluation.  We should only sleep if hypotheticals are in play.
		if(!$sleeping)
			while($this->sleep($kernel,$hypos));

		//assess the validity of this statement
		$magnitude = 0;
		$conflict = [];

		//array_filter() candidate
		//I don't know what I did but everything works miraculously now.  Please clean this function up so it makes sense to you and the world.
		foreach($equivs as $i=>$mag) //TODO check for ambiguous blocks, or make provisions for avoiding them earlier on.
		{
			$v = $this->get($i);
			if($v->root == 0) continue;
		else	if($logic->type != $v->type) continue;

			$base_mag = $this->simple_compare($logic,$v);

			$subj1 = $this->verify($logic,$v,"subj1",$kernel);
			$action = ($logic->type == '_'||$logic->type == '^')?1:$this->verify($logic,$v,"act",$kernel);
			$subj2 = $this->verify($logic,$v,"subj2",$kernel);
			$truth = $this->verify_truth($logic,$v,$kernel);

			// this is the source of the problem.  address it:

			$cond = 0;
			if(!empty($v->cond))
			{
				foreach($v->cond->get_contents() as $j=>$w)
				{
					$l = $this->get($kernel->get($w)->logical);
					$eval = $this->evaluate($l,$kernel,1,1);
					$cond += ($eval*2-1);
				}
			}
			else $cond = 1;

			// even if there's an impending logical conflict, the conds must match.

			//TODO if/then consideration
			if(
				$subj1 > 2/3
				&& $action > 2/3
				&& $subj2 > 2/3
				&& $truth == -1
				&& $v->root==1
				&& $logic->root==1
				&& !count($conflict)
				&& $cond > 2/3
			) {
				$conflict = [$logic,$v];
			}

			$impl = 0;
			$impl_check = 0;
			if(isset($logic->impl) && !$sleeping)
			{
				$impl = $this->compare_impl($logic,$v);
				$impl_check = 1;
			}
			else $impl = 1;

			$result = $base_mag * $cond * $impl;
			$magnitude += $result;
		}
		$sigmoid = math::sigmoid($magnitude);
		if(!$sleeping) $this->dump_hypotheticals();
		$t = $this->val2truth($sigmoid);

		if($t == '~' && count($conflict)) $this->conflicts[] = $conflict;
		if(!$numeric) {
			$ret = new truth($this->val2truth($sigmoid));
			return $ret;
		}
		else return $sigmoid;
	}
	function conform($laddr,$kernel)
	{	//OBEY
		$master = $this->get($laddr);
		foreach($this->contents as $i=>$v)
			if($master->subj1->contents[0] != $v->subj1->contents[0]) continue;
		else	if($master->subj2->contents[0] != $v->subj2->contents[0]) continue;
		else	if($master->type != $v->type) continue;
		else	if($master->act->contents[0] != $v->act->contents[0] && $master->type != '_') continue;
		else	if($laddr == $i) continue;
		else	if($master->impl !== $v->impl) continue;
		else	if($master->cond !== $v->cond) continue;
		else	if($v->root == 0) continue;
		else	unset($this->contents[$i]);
	}
	static function invert($flag,$operator = null)
	{
		if($operator == null)
		{
			switch($flag)
			{
				case '=': return '-'; break;
				case '~': return '~'; break;
				case '-': return '='; break;
			}
		}
		else
		{
			if($operator == '=') return $flag; //(?,=)
		else	if($operator == '~') return '~'; //(?,~)
		else	if($operator == '-') return scope::invert($flag);
		}
	}
}
?>
