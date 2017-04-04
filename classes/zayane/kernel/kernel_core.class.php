<?php
/*
 * kernel_core.class.php - Most basic kernel operations.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class kernel_core extends fmr
{
	function add(kernel_node $node)
	{
		$this->contents[] = $node;
	}
	function set(int $addr, kernel_node $node)
	{
		$this->contents[$addr] = $node;
	}
	function exists(int $addr)
	{
		return intval(isset($this->contents[$addr]));
	}
	function get_nodes(array $addr) {
		$out = [];
		foreach($addr as $a) {
			$out[$a] = $this->get($a);
		}
		return $out;
	}
	
	function get(int $addr)
	{
		if($this->exists($addr) && is_object($this->contents[$addr]))
			return $this->contents[$addr];
		else	{
			throw new Exception("Invalid ".get_class($this)." address '$addr' requested");
		}
	}
	function list_keys()
	{
		return array_keys($this->contents);
	}
	function archive()
	{
		$this->last_state = null;
		$this->last_state = object_clone($this);
	}
	function size()
	{	//With radical restructuring, this will become obsolete.
		return count($this->contents);
	}
	function clear_address(int $addr)
	{
		//This is just a terrible idea
		$this->contents[$addr] = null;
	}
	function get_contents() {
		return $this->contents;
	}
}
?>
