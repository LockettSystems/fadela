<?php
/*
 * itsess.php - Will someday handle iteration persistence.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
*/
class iteration
{
    static $sessid;
    static $itval;
    static function init($sessid = null,$instance = null)
    {
        if($sessid == null)
                iteration::$sessid = rand(0,999999);
	else	iteration::$sessid = $sessid;
        iteration::$itval = 0;

	if(isset($instance))
	{
		iteration::$sessid = $instance['sessid'];
		iteration::$itval = $instance['itval'];
	}
    }
    static function iterate()
    {
        iteration::$itval++;
    }
    static function status()
    {
	return ['sessid'=>iteration::$sessid,'itval'=>iteration::$itval];
    }
}

iteration::init('a95');
print_r(iteration::status());
?>
