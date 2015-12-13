<?php
/*
 * kernel_node.class.php - Specification for objects in kernel->contents
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class kernel_node
{
	public	$term = "",//kernelize2
		$pointer = [],//process_terminal_pointers
		$backtrace = [],//process_terminal_pointers
		$sentence = -1,//kernelize2
		$sentence_index = -1,
		$root = -1,//kernelize2
		$block = -1,//kernelize2
		$sender = -1,//kernelize2
		$flag = null,//process_terminal_pointers
		$name = [],//process_terminal_pointers
		$pronoun = [],//process_terminal_pointers
		$logical = -1,
		$ambiguous = 0,
		$user = 0,
		$rank = 0,
		$endpoint = 0,
		$estat = null,
		$has_command = 0;
		
	function __construct($term)
	{
		$this->term = $term;

		$this->name =
		[
			'bef' => new jtable(),
			'aft' => new jtable(),
			'default' => new weightStack()
		];

		$this->pronoun =
		[
			'bef' => new jtable(),
			'aft' => new jtable(),
			'default' => new weightStack()
		];

		$this->ambig =
		[
			'bef' => new jtable(),
			'aft' => new jtable(),
			'default' => new weightStack()
		];
	}

	function merge($x)
	{
		$this->pointer = array_merge($this->pointer,$x->pointer);
		$this->backtrace = array_merge($this->backtrace,$x->backtrace);
		$this->name['bef']->merge($x->name['bef']);
		$this->name['aft']->merge($x->name['aft']);
		$this->name['default']->merge($x->name['default']);
		//TODO: name, pronoun, further inspection
	}

	function update_reference_terminology($reftype,$term,$bef,$aft)
	{
		$jtable = &$this->$reftype;
		$jtable['default']->add($term);
		$jtable['bef']->update(0,$bef,$term);
		$jtable['aft']->update(0,$aft,$term);
	}

	function getName($type,$bef,$aft)
	{
		if($type=='&') $type = 'ambig';
	else	if($type=='#') $type = 'name';
	else	if($type=='%') $type = 'pronoun';
		$cat = $this->$type;

		$befs = $cat['bef']->get(0,$bef);
		$befmag = $cat['bef']->getLastVar();


		$afts = $cat['aft']->get(0,$aft);
		$aftmag = $cat['aft']->getLastVar();

		$default = $cat['default']->select();


		if($befs==null && $afts==null) $result = $default;
		else if($befmag>=$aftmag) $result = $befs;
		else $result = $afts;
		if($result == null) $result = $this->term;

		return $result;
	}

	//If kernel compression is to exist, this seriously needs to be phased out.
	//Better be wary of the heap too.
	function add_backtrace($index)
	{
		$this->backtrace[] = $index;
		$this->backtrace = array_unique($this->backtrace);
	}

	function search($str, $strictness = 3)
	{
			if($strictness == 0 && $this->term === $str) return 1;
		else	if($strictness == 1 && $this->term === normalize($str)) return 1;
		else	if($strictness == 2 && $this->term === strtolower($str)) return 1;
		else	if($strictness == 3 && $this->term === normalize(strtolower($str))) return 1;
		else	if($this->name['bef']->search($str,$strictness) >= 0) return 1;
		else	if($this->name['aft']->search($str,$strictness) >= 0) return 1;
		else	if($this->name['default']->search($str,$strictness) >= 0) return 1;
		return 0;
	}
}
?>
