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
	function add($node)
	{
		$this->contents[] = $node;
	}
	function set($addr,$node)
	{
		$this->contents[$addr] = $node;
	}
	function exists($addr)
	{
		return intval(isset($this->contents[$addr]));
	}
	function get($addr)
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
	function clear_address($addr)
	{
		//This is just a terrible idea
		$this->contents[$addr] = null;
	}
}
?>
