<?php
/*
 * preprocessor.class.php - Converts FZPL shorthand to more "formal" form.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class preprocessor
{
	static function lazyPlus($tree) // (k arg + arg) => (k (+ arg arg) )
	{
		if($tree[0]=="`" || $tree[0]=="\"" || $tree[0]=="." || $tree[0]==",") return $tree;

		$pluses = array();
		$newtree = array();
		$present = 0;
		for($i = 1; $i < count($tree); $i++) //Identifying all '+' tokens, caching whatever terms are connected to them
		{
			if(!is_array($tree[$i]) && $tree[$i][0]=='+')
			{
				$present = 1;
				$temp = $tree[$i];
				consume1($temp);
				$pluses[] = $temp;
				$tree[$i] = '+';
				if(strlen($temp)>0) $newtree[] = array(',',$temp);
			}
		//	else if(is_array($tree[$i]))
		//	{
		//		$newtree[] = interpreter::lazyPlus($tree[$i]);
		//	}
			else
			{
				$newtree[] = $tree[$i];
			}
		}	if(!$present) return $tree;
		$newlayer = array($tree[0],array_merge(array('+'),$newtree));
		return $newlayer;
	}
	static function lazyIHD($tree) //You may want to do more with this later...
	{
		$out = array($tree[0]);
		for($i = 1; $i < count($tree); $i++)
		{
			if(!is_array($tree[$i]) && (interpreter::isIHD($tree[$i]) || interpreter::isConditional($tree[$i])/**/))
			{
				$out[] = array($tree[$i]);
				$i++;
				while($i < count($tree))
				{
					$out[count($out)-1][] = $tree[$i];
					$i++;
				}
				$i--;
			}
			else $out[] = $tree[$i];
		}
		for($i = 1; $i < count($out); $i++)
		{
			if(is_array($out[$i])) $out[$i] = preprocessor::lazyIHD($out[$i]);
		}

		return $out;
	}
	static function lazyArgs($tree) // (k `arg arg arg .arg arg arg) => (k (` arg arg arg) (. arg arg arg) )
	{//As usual, verify that this is stable.
	        if(lang::is_terminal_flag($tree[0])) return $tree;
		$out = array($tree[0]);
		for($i = 1; $i < count($tree); $i++)
		{
			if(!is_array($tree[$i]) && lang::is_terminal_flag($tree[$i][0]) && $tree[$i][0] != 'e')
			{
				$k = consume1($tree[$i]);
				$v = $tree[$i];
				$i++;
				while($i < count($tree) && !is_array($tree[$i]) && $tree[$i][0]!="`" && $tree[$i][0]!="\"" && $tree[$i][0]!="." && $tree[$i][0]!="," && $tree[$i][0]!="=" && $tree[$i][0]!="~" && $tree[$i][0]!="-" && $tree[$i][0]!="{" && $tree[$i][0]!="}" && $tree[$i][0]!=':' /**/)
				{
					$v .= " ".$tree[$i];
					$i++;
				}
				$i--;

				if($k == ':') // estat shorthand/reference
				{
					$k = 'e';
					$v = explode("|",$v);
					$v = "@${v[0]} %3|${v[1]}";
				}
				$out[] = array($k,$v);
			}
			else $out[] = $tree[$i];
		}
		return $out;
	}
	static function lazyClosure($tree)
	{
		return $tree;
	}
}
?>
