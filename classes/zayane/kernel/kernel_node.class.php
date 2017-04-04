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
	private
		$sender = -1,//kernelize2
		$receiver = -1,//kernelize2
		$term = ""//kernelize2
		;

	public	
		$root = -1,//kernelize2
		$block = -1,//kernelize2
		$flag = null,//process_terminal_pointers
		$logical = -1,
		$ambiguous = 0,
		$user = 0,
		$estat = null,
		$has_command = 0;
	private $NULL;

	private	$pointer = [], //process_terminal_pointers
		$backtrace = []; //process_terminal_pointers
		
	function __construct($term)
	{

		$this->set_term($term);
	}

	function set_term($x) {
		if(empty($x)) {
			print_r(kernel::neuter(debug_backtrace()));
#			throw new Exception("Empty term string received");
		}
		$this->term = $x;
	}

	function get_term() {
		return $this->term;
	}

	function set_sender($x) {
		$this->sender = $x;
	}

	function get_sender() {
		return $this->sender;
	}

	function set_receiver($x) {
		$this->receiver = $x;
	}

	function get_receiver() {
		return $this->receiver;
	}

	function get_pointers() {
		 return $this->pointer;
	}
	function add_pointer(int $addr) {
		 $this->pointer[] = $addr;
		 $this->pointer = array_unique($this->pointer);
	}
	function set_pointers(array $addr) {
		 foreach($addr as $v) $this->add_pointer((int)$v);
	}
	function set_backtrace(array $addr) {
		 foreach($addr as $v) $this->add_backtrace((int)$v);
	}
	function get_backtrace() {
		 return $this->backtrace;
	}
	function merge($x)
	{
		$this->pointer = array_merge($this->pointer,$x->pointer);
		$this->backtrace = array_merge($this->backtrace,$x->backtrace);
		//TODO: name, pronoun, further inspection
	}

	static function getNamesBallot($kernel, $type, int $addr, $show_all = false, int $sender, int $receiver) {
		$oaddr = $addr;
		if($type == '#') {
			$addr = kernel::ind2dir($addr, $sender, $receiver);
		}

		$names = [];
		$set = $kernel->get($addr)->get_backtrace();
		
		foreach($set as $i => $v) {
			$node = $kernel->get($v);
			$name = $node->term;
			if($type != $kernel->get($v)->flag && !$show_all) continue;
			if(isset($names[$name])) $names[$name]++;
			else $names[$name] = 1;
		}

		return $names;
	}
	
	static function getName($type,int $addr, int $sender, int $receiver)
	{
		kernel::get_global($kernel);
			
		$node = $kernel->get($addr);
		$pointer = $node->get_pointers();
		

		if(count($pointer) == 1 && $type == '#') {
			$redir = kernel::mirrorize($pointer[0],$sender,$receiver);
			if(!in_array($redir,[1,2]) && $redir != $addr) {
				return self::getName($type,$redir,$sender,$receiver);
			}
		}
		
		if($type == 'name' || $type == '#') {
			$type = '#';
		}
		if($type == 'pronoun' || $type == '%') {
			$type = '%';
		}
		if($type == 'default' || $type == '') {
			$type = '';
		}

		$result = null;

		// temporary placeholder -- we're going to want to write some kernel functions for inference
		switch($type) {
			case '&': break;
			case '#': break;
			case '%': break;
		}
		if(0) {
			// Contextual Analysis
		} else {
			// Ballot
			$names = self::getNamesBallot($kernel, $type, $addr, false, $sender, $receiver);
			if(count($names)) {
				arsort($names);
				$result = key($names);
			}
		}
		if(empty($result) && !in_array($addr,[1,2])) {
			// Default term
			$result = $kernel->get($addr)->term;
		} elseif (empty($result) && in_array($addr,[1,2])) {
			throw new Exception("Exception: Primitive literal returned in kernel_node::getName().");
		}

		return $result;
	}

	//If kernel compression is to exist, this seriously needs to be phased out.
	//Better be wary of the heap too.
	function add_backtrace(int $index)
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
/*
		else	if($this->name['bef']->search($str,$strictness) >= 0) return 1;
		else	if($this->name['aft']->search($str,$strictness) >= 0) return 1;
		else	if($this->name['default']->search($str,$strictness) >= 0) return 1;
		return 0;
*/
	}
}
?>
