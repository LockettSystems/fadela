<?php
/*
 * register.class.php - A tool for concept disambiguation.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

// At some point, it would be a lovely idea to
//     do storage/withdrawal based not only on
//     address, but especially:
//         (1) Expanded concept roots
//         (2) Partial matching of those expanded roots on query-time.

class register
{
	public $size;
	public $contents = [];
	function __construct($size)
	{
		$this->size = $size;
	}
	function add($str,$addr,$flag = null,$prefix = null)
	{
		register::prepare($str,$addr);
		foreach($this->contents as $i=>$v)
		{
			if($v['addr'] == $addr)
			{
				$this->contents[$i]['count']++;
				return;
			}			
		}
		$this->contents[] = ['term' => $str,'addr' => $addr,'count' => 1,'flag' => $flag,'prefix' => $prefix];
		if(count($this->contents) > $this->size) $this->reduce();
	}
	function reduce()
	{
		if(!count($this->contents)) return;
		foreach($this->contents as $i=>$v)
			if($v['count'] <= 1)
			{
				unset($this->contents[$i]);
				$this->contents = array_values($this->contents);
				return;
			}
		$this->contents[0]['term']--;
		$this->reduce();
	}
	function suppress($term,$addr)
	{
		register::prepare($term,$addr);
		foreach($this->contents as $i=>$v)
		{
			if($v['term'] == $term && $v['addr'] == $addr) $this->contents[$i]['weight'] = 0;
		}
	}
	function infer($str,$flag = null,&$prefix = null)
	{
		// In the future, you may want to consider factoring relevance
		//     into the equation to account for mis-spelled words.
		// Also, please devise a means of flag support here.
		register::prepare($str);
		$addr = -1;
		$max = -1;
		foreach($this->contents as $i=>$v)
		{
			if($v['term'] == $str && $v['count'] > $max)
			{
				$max = $v['count'];
				$addr = $v['addr'];
				$prefix = $v['prefix'];
			}
		}
		return explode(",",$addr);
	}
	static function prepare(&$term,&$addr = null)
	{
		$term = strtolower($term);
		if(isset($addr) && is_array($addr)) $addr = implode(",",$addr);
	}
}
/*
$reg3 = new register(3);
$reg3->add('you',[0]);
$reg3->add('me',[3]);
$reg3->add('you',[0]);
$reg3->add('_USR',[1]);
$reg3->add('_SELF',[2]);
print_r($reg3);
*/
?>
