<?php
/*
 * kernel_static.class.php - Static kernel functions
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

trait kernel_static
{
	static function neuter($b)
	{
		for($i = 0; $i < count($b); $i++)
		{
			unset($b[$i]['object']);
			foreach($b[$i]['args'] as $j=>$w)
				if(is_object($w) && get_class($w)=='kernel') unset($b[$i]['args'][$j]);
		}
		return $b;
	}

	static function load($f)
	{
		if(!file_exists($f)) return new kernel();
		$a = file_get_contents($f);
		if(strlen($a)==0) return new kernel();
		else return unserialize($a);
	}

	static function set_global($x) {
#		echo "SET: ".u_md5($x)."\n";
		kernel::$instances[$x->uuid] = $x;
	}
	static function get_global(&$x)
	{
		$static_keys = array_keys(kernel::$instances);
		if(!count($static_keys)) {
			throw new Exception('Global kernel does not exist.');
		} else {
			$a = end(kernel::$instances);
			$x = $a;
		}
#		echo "GET: ".u_md5($x)."\n";
	}
	static function remove_global($x) {
		unset(kernel::$instances[$x->uuid]);
	}
	static function initialize() {
		$uuid = uuid();
		kernel::set_global(new kernel($uuid));
		return kernel::$instances[$uuid];
	}
}
?>
