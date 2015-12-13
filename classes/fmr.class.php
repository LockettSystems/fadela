<?php
/*
 * fmr.class.php - A simple base class for map/reduce/filter/pipeline operations.
 * Not good for operations of larger scale... And not particularly stable yet.
 * TODO support parallelism
 *
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/
class fmr
{
	protected $contents;
	function __construct(array $in = [])
	{
		$this->contents = $in;
	}
	static function validate($function)
	{
		return ud_validate($function);
	}
	static function static_pipeline()
	{
		$args = func_get_args();
		$input = consume1($args);

		foreach($args as $i => $arg)
		{
			/* index types:
			* 0 - map/reduce/filter
			* 1 - function
			*/
			$arg[1] = fmr::validate($arg[1]);
			foreach($input as $j => $val)
				if(!is_array($val)) $input[$j] = [$val];
			switch($arg[0])
			{
				case 'map':
					$input = [call_user_func_array('array_map',array_merge([$arg[1]],$input))];
					break;
				case 'reduce':
					$input = call_user_func_array('array_reduce',array_merge($input,[$arg[1]]));
					break;
				case 'filter':
					$input = [call_user_func_array('array_filter',array_merge($input,[$arg[1]]))];
					break;
			}
		}

		return $input;
	}
	function pipeline(/* can we even */)
	{
		$args = func_get_args();
		$input; $output;
		if(!isset($this))
			return call_user_func_array('fmr::static_pipeline',func_get_args());
		else	{
				$input = consume1($args);
				foreach($args as $i=>$v)
				{
					$input = call_user_func_array([$this,'fmr'],array_merge([$v[0],$v[1]],$input));
					$output = $input;
					$input = [$input];
				}
			}
		return $output;
	}
	function map_local()
	{
		return call_user_func_array([$this,'fmr_local'],array_merge(['map'],func_get_args()));
	}
	function reduce_local()
	{
		return call_user_func_array([$this,'fmr_local'],array_merge(['reduce'],func_get_args()));
	}
	function filter_local()
	{
		return call_user_func_array([$this,'fmr_local'],array_merge(['filter'],func_get_args()));
	}
	function map()
	{
		return call_user_func_array([$this,'fmr'],array_merge(['map'],func_get_args()));
	}
	function reduce()
	{
		return call_user_func_array([$this,'fmr'],array_merge(['reduce'],func_get_args()));
	}
	function filter()
	{
		return call_user_func_array([$this,'fmr'],array_merge(['filter'],func_get_args()));
	}
	function fmr_local()
	{
		// Not advised for reduce operations
		$modification = call_user_func_array([$this,'fmr'],func_get_args());
		$this->contents = $modification;
	}
	function fmr(/* $type, $func */)
	{
		$out = null;
		$args = func_get_args();
		$type = consume1($args);
		$func = fmr::validate(consume1($args));
		if(is_string($func) && method_exists($this,$func))
			$func = [$this,$func];
		if(!count($args)) $args = ($type=='map')?[object_clone($this->contents),array_keys($this->contents)]:[object_clone($this->contents)];
		switch($type)
		{
			case 'map':
				$out = call_user_func_array(
					'array_map',
					array_merge([$func],$args)
				);
				break;
			case 'reduce':
				$out = call_user_func_array('array_reduce',array_merge($args,[$func]));
				break;
			case 'filter':
				$out = call_user_func_array('array_filter',array_merge($args,[$func]));
				break;
			default:
				throw new Exception('fmr error');
				break;
		}
		return $out;
	}
}
?>
