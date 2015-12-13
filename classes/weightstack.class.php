<?php
/*
 * weightstack.class.php - A semi-random self-adjusting prioritized stack object.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/

//Tell me again why we should consider select() scenarios in which there are recursive calls of select()?

class weightStack
{
	public $contents;
	public $total;
	public $lastVar; //Just cause.
	public $iterations;

	function __construct()
	{
		$this->contents = array();
		$this->total = 0;
		$this->lastVar = 0;
		$this->iterations = 0;
	}
	function merge($x)
	{
		foreach($x->contents as $i=>$v)
			$this->contents = array_merge([$v],$this->contents);
	}
	function search($str,$strictness = 0)
	{
		foreach($this->contents as $i=>$v)
			if($strictness == 0 && $v['term'] === $str) return $i;
		else	if($strictness == 1 && normalize($v['term']) === normalize($str)) return $i;
		else	if($strictness == 2 && strtolower($v['term']) === strtolower($str)) return $i;
		else	if($strictness == 3 && normalize(strtolower($v['term'])) === normalize(strtolower($str))) return $i;

		return -1;
	}
	function add($term)
	{
		$newContents = array();

		for($i = 0; $i < count($this->contents); $i++)
		{
			if($this->contents[$i]['term']!=$term) continue;

			$this->contents[$i]['count']++;
			$newContents[] = $this->contents[$i];

			for($ii = 0; $ii < count($this->contents); $ii++)
			{
				if($ii!=$i) $newContents[] = $this->contents[$ii];
			}

			$this->contents = $newContents;
			return;
		}

		$newContents = array();
		$newContents[] = ['term' => $term, 'count' => 1];

		for($i = 0; $i < count($this->contents); $i++) $newContents[] = $this->contents[$i];

		$this->contents = $newContents;
		$this->total++;
	}
	function select()
	{
		if($this->iterations > 0) $this->iterations = 0;

		if($this->total == 0)
		{
			$this->lastVar = 1;
			return;
		}
		$maxCount = $this->maxCount();
		if($maxCount<1) return "";

		foreach($this->contents as $i => $v)
		{
			$rand = math::rnd();
			$currentCount = $v['count'];
			if($rand<=(($currentCount/$maxCount)/($i+1)))
			{
				$this->iterations *= -1;
				$this->lastVar = $rand;
				return $v['term'];
			}
		}
		$this->iterations--;
		return $this->select();
	}
	function getMass()
	{
		return $this->total;
	}
	function getLastVar()
	{
		return $this->lastVar;
	}
	function getValues()
	{
		$out = array();

		for($i = 0; $i < count($this->contents); $i++) $out[] = $this->contents[$i]['term'];

		return $out;
	}
	function maxCount()
	{
		$max = 0;

		for($i = 0; $i < count($this->contents); $i++)
		{
			if($this->contents[$i]['count']>$max)
				$max = $this->contents[$i]['count'];
		}

		return $max;
	}
}
?>
