<?php
/*
 * gparse.php - Script prototyping grammar inference engine.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

set_time_limit(15);
/*
Track references.  "They" is suddenly disambiguated.
Track grammar terminals by ID.  Query default name.  Eh?

What if we were to substitute the kernel with a monolithic FZPL tree representing every input there has ever been?
What would we gain?  What would we lose?
*/
include 'commands.php';
include 'classes/index.php';

$rules = new grammar();

$input = '
(i (_ `x .is "y) ,.)
(? ,how (_ ` .are "you) ,?)
(i (* `i -do not .understand "you) ,.)
[*
(i (* `i -do not .receive ,any "questions) ,.)
(i (_ `y .is "x) ,.)
(? ,how (_ `.. .is "your mother) ,.)
(i (_ `y .is (^ `your "mom)) ,.)
(? (_ .is `x "y) ,?)
(i (* `i -do not .understand ,your "question) ,.)
(i (* `i -do not .understand ,the fact that (^ `you .have` "questions)) ,.)
(:| (. blah) ,.)
*]
';

echo "Processing FZPL queries...\n";
$trees = parser::parse($input,1);

foreach($trees as $i => $v)
{
	echo multi("-",80)."\n";
	echo "Training input:\n".interpreter::simplify($v,1,null,1);
	$rules->process($v);
}
$tests = array
(
//Given examples
//	"x is y .",
//	"y is x .",
//Simple tests
//	"x is x .",
//	"y is y .",
//Intermediate tests
//	"blah .",
/*
//Blah terminator
//	"i do not get no questions .",
*/
	"i do not understand how x is y .",
//	"hello ."
);

foreach($tests as $i=>$v)
{
	echo multi("-",80)."\n";
	echo "Testing input:\n$v\n".multi("-",80)."\n";
	$v = explode(" ",trim($v));
	$v = remnull($v);
	$inference = $rules->parse($v);
	echo "Inferred structure:\n".$inference;
}
echo multi("-",80)."\n*DONE*\n";
?>
