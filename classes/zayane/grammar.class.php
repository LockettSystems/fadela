<?php
/*
 * grammar.class.php - Manages parsing rules and parses according to them.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/
class unknown
{
	public $value;
	function __construct($val)
	{
		$this->value = $val;
	}
}
class rule
{
	public	$contents,
		$parent;

	function __construct($contents,$parent)
	{
		$this->contents = $contents;
		$this->parent = $parent;
	}
}
class terminal
{
	public	$code,
		$value,
		$operator,
		$baseflag,
		$rootflag;

	function __construct($value, $code, $operator = null, $rootflag = null, $baseflag = null)
	{
		$this->value = $value;
		$this->code = $code;
		$this->operator = $operator;
		$this->baseflag = $baseflag;
		$this->rootflag = $rootflag;
	}
}
class nonterminal
{
	public	$flag;

	function __construct($flag)
	{
		$this->flag = $flag;
	}
}
class grammar
{
	public	$bases,
		$stems,
		$terms;

	function __construct()
	{
		$this->bases = $this->stems = $this->terms = array();
	}
	function cleanup()
	{
		//Remove duplicates
		$janitor = function(&$x)
		{
			foreach($x as $i=>$v)
			{
				foreach($v as $j=>$w)
				{
					$success = 0;
					foreach($v as $k=>$ww)
						if($j!=$k && serialize($w) == serialize($ww))
						{
							unset($x[$i][$k]);
							$success = 1;
							break;
						}
					if($success) break;
				}
				$x[$i] = array_values($x[$i]);
			}
		};
		$janitor($this->bases);
		$janitor($this->stems);
		$janitor($this->terms);
	}
	function process($tree,$parent = null)
	{
		$flag = $tree[0];
		if($flag == 'c') return; //Disregard commands... for now.

		//The rule for this particular layer of the tree
		$rule = array();

		//For each non-command section of the tree, assuming no literals
		for($i = 1; $i < count($tree); $i++)
		{
			$v = $tree[$i];

			//Dissection safety check -- all arguments within subtree are assumed to be non-literals
			if(!lang::is_terminal_flag($v[0]))
			{
				//Process subtrees
				$this->process($v,$flag);
				//Add representative atom to new rule
				$rule[] = new nonterminal($v[0]);
			}
			else
			{
				//Check for jump/return terminals
				$code = null;
				if($v[0] == "," && $i < count($tree)-1 && !lang::is_terminal_flag($tree[$i+1][0])) $code = "J"; //Jump check
				if($v[0] == "," && $i == count($tree)-1) $code = "R"; //Return check

				//Add representative atom to new rule
				$this->addTerminal($v,$code);
				$rule[] = new terminal($v[1],$code,$v[0],$parent,$flag);
			}
		}
		//Incorporate rule into master list
		$this->add_rule($flag,$rule,$parent);
		$this->cleanup();
	}
	function add_rule($flag,$rule,$parent = null)
	{
		if(lang::is_base_flag($flag)) $this->bases[$flag][] = new rule($rule,$parent);
	else	if(lang::is_stem_flag($flag)) $this->stems[$flag][] = new rule($rule,$parent);
	}
	function addTerminal($tree,$special_code = 0)
	{
		$flag = $tree[0];
		if(!isset($this->terms[$flag])) $this->terms[$flag] = array();

		if($special_code == "J") $this->terms["J"][] = $tree[1];
		if($special_code == "R") $this->terms["R"][] = $tree[1];
		else $this->terms[$flag][] = $tree[1];
	}
	function get_terminal_options($atom)
	{
		$result = array();

		$foo = function($x,&$result,$atom)
		{
			foreach($x as $i=>$v)
				foreach($v as $j=>$w)
					foreach($w->contents as $k=>$ww)
						if(get_class($ww) == "terminal" && $ww->value == $atom && !in_array($ww,$result))
							$result[] = $ww;
		};

		$foo($this->bases,$result,$atom);
		$foo($this->stems,$result,$atom);

		if(count($result)==0)	return array(new unknown($atom));
					return $result;
	}
	function infer_types($ar)
	{
		$token = array();
		$result = array();
		foreach($ar as $i=>$v)
		{
			$token[] = $v;
			$flat = implode(" ",$token);
			$ar = decapitate($ar,1);

			//In this particular implementation, if we think we recognize a term, we will not consider the unknown factors.
			//You may want to change this in the future.
			$opts = $this->get_terminal_options($flat);
			$next = $this->infer_types($ar);

			foreach($opts as $j => $w)
				if(count($next)>0)
					foreach($next as $k => $ww)
					{
						$result[] = array_merge(array($w),$ww);
						$result[] = array_merge(array(new unknown($w->value)),$ww);
					}
				else $result[] = array($w);
		}
		return $result;
	}
	function validate_list(&$list)
	{
		//The unforgivables.
		foreach($list as $i=>$v)
		{
			$has_s1 = 0;
			$has_s2 = 0;
			$has_op = 0;
			$has_what = 0;
			foreach($v as $j=>$w)
			{
				if(get_class($w)=="terminal" && $w->operator == "`") $has_s1 = 1;
				if(get_class($w)=="terminal" && $w->operator == "\"") $has_s2 = 1;
				if(get_class($w)=="terminal" && $w->operator == ".") $has_op++;
			}
			//2. If there exists so little as one S1 instance, there must be an S2 instance.
			if($has_s1 != $has_s2) unset($list[$i]);

			//3. If there exist S1 and S2 instances, there must be an operator.
			if($has_s1 && !$has_op) unset($list[$i]);
		}

		$initial_list = $list;

		//Remove all options that contain unknowns.  Restore if there are no remaining options.
		foreach($list as $i=>$v)
			foreach($v as $j=>$w)
				if(get_class($w) == "unknown") unset($list[$i]);
		$list = array_values($list);
		if(count($list) == 0) $list = $initial_list;
		$list = array_values($list);
	}
	function build_base(&$list,$li,&$max_val,&$max_lit,$flag)
	{
		$local = 0;

		$front = array($flag);
		$back = array();

		$last = null;
		$jump = 0;

		$init_count = count($list);

		$consume1 = function(&$x,&$front)
		{
			unset($x[0]);
			$x = array_values($x);
		};

		while(count($list)>0 && count($li)>0)
		{
			//Jump Check
			if(get_class($last)=="terminal" && $last->code == "J")
			{
				if($last == $list[0])
				{
					$front[] = array($list[0]->operator,$list[0]->value);
					$consume1($list,$front);
				}
				$jump = 1;
				break; //Jump
			}

			$last = $list[0];

			//Trim leading terminals...
//			print_r(array($li,$list));
			if(get_class($li[0])=="terminal" && get_class($list[0])=="terminal")
				if($li[0]->operator==$list[0]->operator && strtolower($li[0]->value) == strtolower($list[0]->value))
				{
					$front[] = array($li[0]->operator,$li[0]->value);
					$li = remove_front($li,1);
					$consume1($list,$front);
					$local++;
					continue;
				}
				else
				{
					$local--;
					unset($li[0]);
					$li = array_values($li);
					continue;
				}

			//Unknown handling
			if(get_class($li[0])=="terminal" && get_class($list[0])=="unknown")
			{
				$front[] = array($li[0]->operator,$list[0]->value);
				$li = remove_front($li,1);
				$list = remove_front($list,1);
				continue;
			}

			//Trim trailing terminals...
			$e = count($li)-1;
			$f = count($list)-1;
			if(get_class($li[$e])=="terminal" && get_class($list[$f])=="terminal")
				if($li[$e]->operator==$list[$f]->operator && strtolower($li[$e]->value) == strtolower($list[$f]->value))
				{
					$back[] = array($li[$e]->operator, $li[$e]->value);
					$li = trim_from_back($li,1);
					$list = trim_from_back($list,1);
					$local++;
					continue;
				}
				else
				{
					$local--;
					unset($li[$e]);
				}

			//Unknown handling
			if(get_class($li[0])=="terminal" && get_class($list[0])=="unknown")
			{
				$front[] = array($li[0]->operator,$list[0]->value);
				$li = remove_front($li,1);
				$list = remove_front($list,1);
				continue;
			}
			break;
		}
		if(count($list)>0 && count($li)>0 && (count($list) != $init_count || !lang::is_logical_stem_flag($flag)))
		{
			$newflag = null;

			$flags = array();

			if(get_class($list[0])=="terminal") $flags[] = $list[0]->baseflag;
			if(get_class($li[0])=="terminal") $flags[] = $li[0]->baseflag;
			else $flags[] = $li[0]->flag;

			foreach($flags as $i=>$v) if(lang::is_stem_flag($v))
						{
							$newflag = $v;
							break;
						}

			if($jump)
			{	//uhhh
				$local += 1; //why is this even necessary
			}

			//Build next tree.
			$front_add =  $this->build_tree($list,0,$newflag,$local);

			$front[] = $front_add;
		}

		while(count($list)>0)
		{
			$jump = 0;
			$strip = function(&$list,&$front,&$jump)
			{
				for($i = 0; $i < count($list); $i++)
				{
					if(get_class($list[$i]) == "terminal")
					{
						$front[] = array($list[$i]->operator, $list[$i]->value);
						if($list[$i]->code == "J")
						{
							$jump = 1;
							break;
						}
					}
					if(get_class($list[$i]) == "unknown")
					{
						$front[] = array(",",$list[$i]->value);
					}
					unset($list[$i]);
				}
				if(count($list)>0)
					$list = array_values($list);
			};

			$strip($list,$front,$jump);

			if($jump)
			{
				$consume1($list,$front);
				if(get_class($list[0])=="terminal")
					$front[] = $this->build_tree($list,0,$list[0]->baseflag,$local);
				$local += 1;
			}
		}
		$list = array();

		$back = array_reverse($back);
		$out = array_merge($front,$back);

		if(!isset($max_val))
		{
			$max_val = $local;
			$max_lit = $out;
		}
		else if($local > $max_val)
		{
			$max_val = $local;
			$max_lit = $out;
		}
	}
	function build_tree(&$list,$base = 0,$_f = null,&$val) //0 - stem; 1 - base; 2 - nested base (tba)
	{
		$max_val = null;
		$max_lit = null;

		$flc = null;

		//1. Find a suitable base -- please use candidates to compare results
		if($base == 1 || $base == 2)
		{
			foreach($this->bases as $i=>$v)
			{
				if($_f != null)
				{
					$i = $_f;
					$v = $this->bases[$i];
				}
				foreach($v as $j=>$w)
				{
					$flag = null;
					$li = $w->contents;
					$list_copy = $list;
					
					$mx = $max_val;
					$this->build_base($list_copy,$li,$max_val,$max_lit,$i);
					if($mx != $max_val) $flc = $list_copy;
				}
				if($_f != null) break;
			}
		}
		//2. Find a suitable stem.
	else	if($base == 0)
		{
			foreach($this->stems as $i=>$v)
			{
				if($_f != null && isset($this->stems[$i]) && count($this->stems[$i])>0)
				{
					$i = $_f;
					$v = $this->stems[$i];
				} else $f = null;
				foreach($v as $j=>$w)
				{
					$li = $w->contents;
					$list_copy = $list;
					$mx = $max_val;
					$this->build_base($list_copy,$li,$max_val,$max_lit,$i);
					if($mx != $max_val) $flc = $list_copy;
				}
				if($_f != null) break;
			}
		}
		$list = $flc;
		$val = $max_val;
		return $max_lit;
	}
	function parse($ar,$debug = 0)
	{
		$li = $this->infer_types($ar);

		$this->validate_list($li);

		$trees = array();

		$maxcandidate;
		$maxballot;
		foreach($li as $i => $v)
		{
			$value = null;
			$tree = $this->build_tree($v,1,null,$value);
			if(!isset($maxballot))
			{
				$maxcandidate = $tree;
				$maxballot = $value;
			}
			else
			if($value > $maxballot)
			{
				$maxcandidate = $tree;
				$maxballot = $value;					
			}
		}
		return interpreter::simplify($maxcandidate,1,null,1);
	}
}
?>

