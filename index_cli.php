<?php
/*
 * index_cli.php - Command line frontend.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

function cli_display($v, $fz_ind, $fz = 1, $nl = 1)
{
			echo "====================\n",
			     $v[0].":\n",
			     "~~~~~~~~~~~~~~~~~~~~\n";

			if($fz_ind)
			echo "FZPL\n",
			     "--------------------\n";
			if($fz)
			echo $v[1];

			if($fz && $nl)
			echo "--------------------\n";

			if($nl)
			echo "NL\n",
			     "--------------------\n",
			     $v[2]."\n";
}
if(PHP_SAPI=="cli")
{
	if($header)
	{
		echo "FadelaBOT v".$version."\n",
		     "(c) '.$year.' Lockett Analytical Systems\n",
		     "Running in command line mode.\n";
	}
	$desc = "\n".file_get_contents("docs/fzpl_guide.txt");
	$desc = "";

	$debug = 0;
	$noclear = 0;
	$nosave = 0;
	$interactive = 0;

	if(in_array("-debug",$argv))
		$debug = 1;
	if(in_array("-noclear",$argv))
		$noclear = 1;
	if(in_array("-nosave",$argv))
		$nosave = 1;
	if(in_array("-interactive",$argv))
		$interactive = 1;

	$kernel = kernel::load('kernel.dat');
	if(empty($kernel)) $kernel = new kernel();

	$choice;
	$hist = [];
	while(1)
	{
		$msg;

		if(!$debug || $interactive)
		{
			echo	"Options:\n\n",
				"1	Enter Text\n",
				"2	Language Reference\n",
				'';
				$choice = scan();
		}
		else
		{
			$msg = scan();
			if(empty($msg)) die();
			$choice = 1;
		}
		if(!$noclear) passthru("clear");
		if($choice==1)
		{
			if(!$debug)
			{
				$msg = prompt(null,"fadela-in",$desc);
				if(empty($msg)) continue;
				if(!$noclear) passthru("clear");
			}

			$results = $kernel->process($msg,$tree);
			$results = $results[0];
			foreach($results as $i=>$v)
			{
				$hist[] = [
					'USR',
					interpreter::simplify($tree[$i],1,0,1),
					interpreter::naturalize($tree[$i])
				];
				array_pop($v);
				$hist[] = [
					'SYS',
					interpreter::simplify($v,1,0,1),
					interpreter::naturalize($v)
				];
			}

			//if(!$nosave) writef('kernel.dat',serialize($kernel));
			//if(!$nosave) writef('log.dat',serialize($hist));
		}
		else if($choice == 2)
		{
			prompt(null,null,file_get_contents("fzpl_guide.txt"),"geany");
		}
		else break;

		foreach($hist as $i=>$v)
		{
			if($i < count($v)-3) continue;
			cli_display($v,0,1,0);
		}	echo "====================\n";
		//printDat($kernel->bayes);
	}
	die();
} else ;//ob_clean();
?>
