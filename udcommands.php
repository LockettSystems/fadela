<?php
/*
 * udcommands.php - Helper functions for fmr.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/

function ud_validate($function)
{
	if(!is_string($function)) return $function;
	if(function_exists("ud_$function"))
		return "ud_$function";
	else	return $function;
}

function make_lambda($f)
{
	$f = ud_validate($f);
	return function() use ($f)
	{
		return call_user_func_array($f,func_get_args());
	};
}

function ud_echo() {
	foreach(func_get_args() as $arg)
		echo $arg;
}

function ud_isset($arg) {
	return isset($arg);
}

function ud_empty($arg) {
	return empty($arg);
}

function ud_array()
{
	return func_get_args();
}
?>
