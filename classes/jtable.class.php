<?php
/*
 * jtable.class.php - A multidimensional map/dictionary object.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/
// TODO restructure, these are mostly obsolete and terribly difficult to debug.
class jtable
{
	//Later on you might want to add code for flexible "table-name" management.

	public $contents;
	public $lastVar;

	function __construct()
	{
		$this->contents = array();
		$this->lastVar = 0;
	}
	function search($str,$strictness)
	{
		foreach($this->contents as $i=>$v)
			foreach($v as $j=>$w)
				if($w->search($str,$strictness) >= 0) return 1;
		return -1;
	}
	function merge($x,$contents_index = 0)
	{
		//Merge all x contents for this row into the jtable
		foreach($x->contents[$contents_index] as $i=>$v)
			if(!isset($this->contents[$contents_index][$i]))
				$this->contents[$contents_index][$i] = $v;
			else	$this->contents[$contents_index][$i]->merge($v);

		//If leftovers in x, dump them all into the jtable too
		if($contents_index == count($this->contents)-1 && count($x->contents) > count($this->contents))
			for($i = $contents_index+1; $i < count($x->contents); $i++)
				$this->contents[$i] = $x->contents[$i];

		if($contents_index < count($this->contents)-1) $this->merge($x,$contents_index+1);
	}
	function get($x,$y)
	{
		if(!isset($this->contents[$x][$y]))
		{
			$this->lastVar = 0;
			return;
		}
		else
		{
			$out = $this->contents[$x][$y]->select();
			$this->lastVar = $this->contents[$x][$y]->getLastVar();
			return $out;
		}
	}
	function getYVals($x)
	{
		$out = array();
		if(!isset($this->contents[$x]))
		{
			return array();
		}
		else
		{
			$y = array_keys($this->contents[$x]);
			return($y);
		}
	}
	function getVals($x,$y)
	{
		if(!isset($this->contents[$x][$y]))
		{
			return array();
		}
		else
		{
			$out = $this->contents[$x][$y]->getValues();
			return $out;
		}
	}
	function getAllVals()
	{
		$out = array();
		$x = array_keys($this->contents);
		for($i = 0; $i < count($x); $i++) //For each primary key
		{
			$y = array_keys($this->contents[$x[$i]]);
			for($j = 0; $j < count($y); $j++) //For each secondary key
			{
				if($this->contents[$x[$i]][$y[$j]]==null) //If the conteints corresponding to the key set is not null
					continue;

				$z = $this->contents[$x[$i]][$y[$j]]->getValues(); //Assign those values to z.
				for($k = 0; $k < count($z); $k++)
					$out[$z[$k]] = intval($out[$z[$k]]) + 1;
			}
		}
		return array_keys($out);
	}
	function update($x,$y,$val) //For weightstacks
	{
		//echo $val."<br/>";
		if(!isset($this->contents[$x][$y]))
		{
			$this->contents[$x][$y] = new weightstack();
			$this->contents[$x][$y]->add($val);
		}
		else
		{
			$this->contents[$x][$y]->add($val);
		}
	}
	function set($x,$y,$val) //For non-weighstacks (e.g., 3D JTables)
	{
		$this->contents[$x][$y] = $val;
	}
	function getNWS($x,$y) //get, no weightstack
	{
		$out = $this->contents[$x][$y];
		if($out==null)
		{
			return -1;
		}
		else
		{
			return $out;
		}
	}
	static function load($table)
	{
		$a = readf('db/jtable.'.$table.'.txt');
		if(strlen($a)==0)
		{
			return new jtable();
		}
		else
		{
			$b = unserialize($a);
			return $b;
		}
	}
	static function store($table,$contents)
	{
		writef('db/jtable.'.$table.'.txt',serialize($contents));
	}
	function getLastVar()
	{
		return $this->lastVar;
	}
}
?>
