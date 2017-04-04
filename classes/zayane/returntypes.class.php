<?php
/*
 * returntypes.class.php - Class definitions for objects passed around kernel.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

//Kernel address
class kaddr
{
	private $contents;	//int[]
	public	$flag,		//string
		$group;		//string

	function __construct($addr)
	{
		if(is_array($addr)) $this->contents = $addr;
	else	if(is_int($addr)) $this->contents = [$addr];
	else	if($addr == null) $this->contents = [];
	else	if(is_string($addr)) $this->contents = [intval($addr)];
	}
	function merge($kaddr)
	{
		foreach($kaddr->contents as $i=>$v) $this->contents = array_merge($this->contents,$kaddr->contents);
		$this->contents = array_unique($this->contents);
	}
	function split()
	{
		$out = [];
		foreach($this->contents as $i=>$v)
		{
			$add = new kaddr($v);
			$add->flag = $this->flag;
			$add->group = $this->group;
			$out[] = $add;
		}
		return $out;
	}
	function addrs()
	{
		return $this->contents;
	}
	function validate_logical() {
		kernel::get_global($kernel);
		foreach($this->contents as $v) {
			if($kernel->get($v)->logical == -1 && !lang::is_ambiguous_flag($kernel->get($v)->flag)) {
				throw new Exception('kernel::validate_logical: not logical');
			}
		}
	}
	public function get($i) {
		if(!isset($this->contents[$i])) {
			throw new Exception("kaddr::get() - Index $i does not exist.");
		}

		return $this->contents[$i];
	}
	public function set($i,$v) {
		$this->contents[$i] = $v;
	}
	public function add($v) {
		$this->contents[$i] = $v;
	}
	public function get_contents() {
		return $this->contents;
	}
	public function remove($i) {
		unset($this->contents[$i]);
		$this->contents = array_values($this->contents);
	}
}

//Sentimental info
class estat
{
	public	$l,	$p,	$d,	$a,
		$maxl,	$maxp,	$maxd,	$maxa,
		$minl,	$minp,	$mind,	$mina,
		$decay_regular = 0.75,
		$decay_radical = 0.99,
		$time = [0,0,0]; //-1 for anticipated, 0 for present, 1 for past

	public	$s1,
		$s2,
		$when;

	function __construct($l = 0, $p = 0, $d = 0, $a = 0, $t = [0,0,0])
	{
		$this->l = $l;
		$this->p = $p;
		$this->d = $d;
		$this->a = $a;
		$this->time = $t;
		$this->adjust();
	}
	function parse_from_parser($parser)
	{
		foreach($parser->estat as $i=>$v)
			$this->$i = $v['val'];
		return $parser;
	}
	function parse($lit)
	{
		$parser = new literal_parser($lit);
		foreach($parser->estat as $i=>$v)
			$this->$i = $v['val'];
		return $parser;
	}
	function generate()
	{
		$lit = '';
		$chars = ['L','P','D','A'];
		foreach($chars as $i=>$v)
		{
			$diff = $this->expressable($v);
			if(!$diff) continue;
			$diff_indicator = ($diff>0)?'+':'-';
			switch($this->time[$i])
			{
				case -1: $lit .= "'$v$diff_indicator "; break;
				case 0: $lit .= "$v$diff_indicator "; break;
				case 1: $lit .= "$v$diff_indicator' "; break;
			}
		}
		if($lit == '') return null;
		return ['e',"$lit%0|"];
	}
	function inc($c,$amt = 1)
	{	$c = strtolower($c);
		$this->$c += $amt;
		$this->adjust();
	}
	function dec($c,$amt = 1)
	{	$c = strtolower($c);
		$this->$c -= $amt;
		$this->adjust();
	}
	function get($s)
	{	$s = strtolower($s);
		return $this->$s;
	}
	function express($c)
	{	$c = strtolower($c);
		$this->$c *= $this->decay_regular;
	}
	function get_expression($s)
	{
		$s = strtolower($s);
		return atan($this->$s)*(1-$this->decay_regular);
	}
	function get_max_threshold($c)
	{
		$c = strtolower($c);
		$k = "max$c";
		return atan($this->$k/math::goldenRatio())*(1-$this->decay_regular);
	}
	function get_min_threshold($c)
	{
		$c = strtolower($c);
		$k = "min$c";
		return atan($this->$k/math::goldenRatio())*(1-$this->decay_regular);
	}
	function expressable($s)
	{	$s = strtolower($s);
		if($this->get_expression($s) > $this->get_max_threshold($s))
			return 1;
		if($this->get_expression($s) < $this->get_min_threshold($s))
			return -1;
		return 0;
	}
	function iterate()
	{
		$this->l *= $this->decay_regular;
		$this->p *= $this->decay_regular;
		$this->d *= $this->decay_regular;
		$this->maxl *= $this->decay_radical;
		$this->maxp *= $this->decay_radical;
		$this->maxd *= $this->decay_radical;
		$this->minl *= $this->decay_radical;
		$this->minp *= $this->decay_radical;
		$this->mind *= $this->decay_radical;
		$this->adjust();
	}
	function adjust() // TODO simplify with foreach
	{
		if($this->l > $this->maxl || !isset($this->maxl)) $this->maxl = $this->l;
		if($this->p > $this->maxp || !isset($this->maxp)) $this->maxp = $this->p;
		if($this->d > $this->maxd || !isset($this->maxd)) $this->maxd = $this->d;
		if($this->l < $this->minl || !isset($this->minl)) $this->minl = $this->l;
		if($this->p < $this->minp || !isset($this->minp)) $this->minp = $this->p;
		if($this->d < $this->mind || !isset($this->mind)) $this->mind = $this->d;
	}
}

//I think this is what's returned from eval...
class instr
{
	public	$type;	//string
	public	$kaddr = [],	//kaddr []
		$logic = [],	//logic	[]
		$estat = null,	//work in progress -- use for sentence building
		$owner = null,	//which user does this represent
		$meta = [];	//metadata
	function __construct($type,$owner = -1)
	{
		$this->type = $type;
		$this->owner = $owner;
	}
	function init_informal($kaddr)
	{
		$this->kaddr[] = $kaddr;
	}
	function init_logical($logic)
	{
		$this->logic[] = $logic;
	}
}

//Logical info
class logic
{
	public	$subj1 = [],	//kaddr[]
		$act = [],	//kaddr[]
		$subj2 = [],	//kaddr[]
		$truth = ['~'],	//truth[]
		$cond = null,	//kaddr[]
		$impl = null,	//kaddr[]
		$type = null,	//string -- ihd
			/*
			Let's establish some root-codes.
			0 = Default.  Usually used for defining rules.
			1 = True Root.  The facts, as they are.
						-1 = Temporary hypotheticals.
			*/
		$root = 0,
		$uuid,
		$addressed = 0;
		/* TODO: it may be wise to also store the base type (i.e. i, ?) here.
		*/
	function __construct()
	{
	}
	function init($ihd,$subj1,$act,$subj2,$truth,$cond = null,$impl = null)
	{
		$this->type = $ihd;
		$this->subj1 = $subj1;
		$this->act = $act;
		$this->subj2 = $subj2;
		$this->truth = $truth;
		$this->cond = $cond;
		$this->impl = $impl;

		//$this->uuid = uuid();

		if($this->subj1 == null) $this->subj1 = new kaddr(null);
		if($this->act == null) $this->act = new kaddr(null);
		if($this->subj2 == null) $this->subj2 = new kaddr(null);
		$this->subj1->flag = '`';
		$this->act->flag = '.';
		$this->subj2->flag = '"';

		$this->self_validate();
	}
	function self_validate() {
		kernel::get_global($kernel);
		if(isset($this->cond) && is_object($this->cond)) {
			$this->cond->validate_logical();
		}
	}
	function matches($logic)
	{
		kernel::get_global($kernel);
		return $kernel->scope->simple_compare($this,$logic);
	}
	function add($cat,$v)
	{
		$this->{$cat}[] = $v;
		$this->$cat = array_values($this->$cat);
	}
	function merge_by($logic,$ar)
	{
		if(is_string($ar)) $ar = [$ar];
		foreach($ar as $q => $cat)
		{
			foreach($logic->$cat->contents as $i=>$v)
				$this->$cat->contents = array_merge($this->$cat->contents,$logic->$cat->contents);
			$this->$cat->contents = array_unique($this->$cat->contents);
		}
		return 1;
	}
	function addrs()
	{
		return array_unique(array_merge($this->subj1_addr(),$this->subj2_addr(),$this->act_addr()));
	}
	function subj1_addr()
	{
		return $this->subj1->get_contents();
	}
	function subj2_addr()
	{
		return $this->subj2->get_contents();
	}
	function act_addr()
	{
		return $this->act->get_contents();
	}
	function merge($logic,$kernel)
	{
		//logic::type
		if($this->type != $logic->type) return 0;
		//logic::truth
		if($this->truth->getType(0) != $logic->truth->getType(0)) return 0;

		if($this->truth->getType(0) == $logic->truth->getType(0) && $this->type == '^')
			return $this->merge_by($logic,['subj1','act','subj2']);

		$pass1 = 1;
		$pass2 = 1;
		$pass3 = 1;

		//logic::subj1 -- match if subj1 OR act+subj2
		$x = $kernel->extract_concept_roots_kaddr($this->subj1);
		$y = $kernel->extract_concept_roots_kaddr($logic->subj1);
		if(count(array_intersect($x,$y)) != count($x)) $pass1 = 0;

		//logic::act -- match if subj1+act OR act+subj2
		$x = $kernel->extract_concept_roots_kaddr($this->act);
		$y = $kernel->extract_concept_roots_kaddr($logic->act);
		if(count(array_intersect($x,$y)) != count($x)) $pass2 = 0;
		
		//logic::subj2 -- match if subj2 OR subj1+act
		$x = $kernel->extract_concept_roots_kaddr($this->subj2);
		$y = $kernel->extract_concept_roots_kaddr($logic->subj2);
		if(count(array_intersect($x,$y)) != count($x)) $pass3 = 0;


		if($pass1 && $pass2 && $pass3)
			return 1;
		if($pass2 && $pass3)
			return $this->merge_by($logic,'subj1');
	else	if($pass1 && $pass2)
			return $this->merge_by($logic,'act');
	else	if($pass1 && $pass3)
			return $this->merge_by($logic,'subj2');

		//logic::cond -- TODO or ignore for now
		//logic::impl -- TODO or ignore for now
		return 0;
	}
}

class truth
{
	private	$type;	//string[]
	function getType($n = 0) {
		return $this->type[$n];
	}
	function setType(string $val,$index = 0) {
		$this->type[$index] = $val;
	}

	function __construct(string $type)
	{
		$this->type = $type;
		if(!is_array($this->type)) $this->type = [$this->type];
	}	
	function split()
	{
		$out = [];
		if(count($this->type)<=1) return $this;
		else foreach($this->type as $i=>$v)
			$out[] = new truth($v);
		return $out;
	}
}
?>
