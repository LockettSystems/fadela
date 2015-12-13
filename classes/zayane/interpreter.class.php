<?php
/*
 * interpreter.class.php - Language-related processing.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class interpreter //I think we're gonna need an interpreter_lib.class.php at some point.
{
	static function gkt() //Reads and parses keytypes list file -- probably obsolete
	{
		$keytypes = file('pdata/keytypes.txt');
		foreach($keytypes as $i => $row)
		{
			$row = explode("	",$row);
			$keytypes[str_replace(")","",str_replace("(","",$row[0]))] = strtolower($row[1]);
			unset($keytypes[$i]);
		}
	
				return $keytypes;
	}
	static function gwt() //Reads and parses wordtypes list file -- probably obsolete
	{
		$wordtypes = file('pdata/wordtypes.txt');
		foreach($wordtypes as $i => $row)
		{
			$row = explode("	",$row);
			$wordtypes[$row[0]] = strtolower($row[1]);
			unset($wordtypes[$i]);
		}
		return $wordtypes;
	}
	static function is_terminal($s)
	{
		$result = intval(in_array
			($s,[".",",","`","\"","=","~","-",";","e",":"])
		);
		if($result) return 1;
		else return 0;
	}
	static function extract_terminals($tree,$important = 0)
	{
		$out = [];
		if(is_array($tree[0]) && lang::is_base_flag($tree[0][0]))
			foreach($tree as $i=>$v) $out[] = interpreter::extract_terminals($v);
		else	foreach($tree as $i=>$v)
				if($i == 0) continue;
			else	if(lang::is_terminal_flag($v[0]) && (!$important || $v[0]!=",")) $out[] = $v[1];
			else	$out = array_merge($out,interpreter::extract_terminals($v));
		return $out;
	}
	static function simplify($tree,$prettyprint = 0,$row = 0,$cli = 0) //Flattens a response tree into a string of Zayane Lisp.
	{
		if($tree[0]=="nl")
		{
			$tree[0] = "(NL)";
			return implode(" ",$tree)."\n";
		}
		if(!is_array($tree)) return $tree;
		$otree = $tree;

		$delim = "<br/>";
		if($cli) $delim = "\n";

		foreach($tree as $i=>$v)
		{
			$tree[$i] = interpreter::simplify($v,$prettyprint,$row+1,$cli);
			if($prettyprint && $i==0 && !(interpreter::is_terminal($otree[0])) /*&& $row != 0*/) $tree[$i].=$delim;
		}
		$spaces = "";
		$spacesm = "";

		for($i = 0; $i < $row; $i++) $spaces = "    ".$spaces;
		for($i = 0; $i < $row-1; $i++) $spacesm = "  ".$spacesm;

		$spacesm = $spaces;

		if(!$prettyprint) $tree = "(".implode(" ",$tree).")";
	else	if($prettyprint && (interpreter::is_terminal($otree[0]))) $tree = $spaces."(".implode(" ",$tree).")$delim";
	else	if($prettyprint) $tree = $spaces."(".implode("",$tree)." ".$spacesm.")$delim";
		
		return $tree;
	}
	static function deep_extr($x,$level = 0)
	{
		$out = [0,0,0];
		if(!is_array($x)) return [0,0,0];
		foreach($x as $i=>$v)
		{
			if($i == 0) continue;
		else	if($v[0] == '.') $out[0][] = $v[1];
		else	if($v[0] == '`') $out[1][] = $v[1];
		else	if($v[0] == '"') $out[2][] = $v[1];
		else	if(!lang::is_terminal_flag($v[0]))
			{
				$de = interpreter::deep_extr($v);
				$out = array_map(function($x,$y){return array_unique(array_merge($x,$y));},$out,$de);
			}
		}
		return $out;
	}
	static function deep_eval($x,$level = 0)
	{
		$out = [0,0,0];
		if(!is_array($x)) return [0,0,0];
		foreach($x as $i=>$v)
		{
			if($i == 0) continue;
		else	if($v[0] == '.') $out[0]++;
		else	if($v[0] == '`') $out[1]++;
		else	if($v[0] == '"') $out[2]++;
		else	if(!lang::is_terminal_flag($v[0]))
			{
				$de = interpreter::deep_eval($v);
				$out = array_map(function($x,$y){return $x+$y;},$out,$de);
			}
		}
		return $out;
	}
	static function getLayout($x)
	{
		$layout = array();
		$layout[] = "CMD";
		for($i = 1; $i < count($x); $i++)
		{
			if(is_array($x[$i]))
			{
				if($x[$i][0]==".") $layout[] = "CMT";
				else if($x[$i][0]=="-") $layout[] = "LOG";
				else if($x[$i][0]=="=") $layout[] = "LOG";
				else if($x[$i][0]=="~") $layout[] = "LOG";
				else if($x[$i][0]=="`") $layout[] = "ARG1";
				else if($x[$i][0]=="\"") $layout[] = "ARG2";
				else if($x[$i][0]=="{") $layout[] = "IF";
				else if($x[$i][0]=="}") $layout[] = "THEN";
				else if($x[$i][0]==",") $layout[] = "UIP";
				else if($x[$i][0]==";") $layout[] = "SKIP";
				else $layout[] = "ARG"; //Miscellaneous argument.
			}
			else
			{
				$layout[] = "STR";
			}
		}
		return $layout;
	}
	static function getAtomType($tree,$layout,$atom_type,$num) //ARG1, ARG2, CMT, CMD, LOG, UIP, IF,THEN
	{
		$out = array();
		foreach($layout as $i => $atom)
		{
			if($atom==$atom_type.$num) $out[] = $tree[$i];
		}
		if(count($out)==0 && $atom_type=="LOG")
		{
			$out = array('=','');
		}
		return $out;
	}
	static function truthTest($t,$z) //t = truth layout, v = env for a particular item
	{
		$x = $t[0];
		$y = $z['truth'][0][0];
		return intval($x==$y);
	}
	//
	static function knormalize($ar)
	{
		if(count($ar)==0 || !is_array($ar)) return array();

		unset($ar[0]);
		$ar[1] = explode("|",$ar[1]);
		if(count($ar[1])>1) unset($ar[1][0]);
		$ar[1] = implode("",$ar[1]);
		$ar = implode(" ",$ar);
	
		return array($ar);
	}
	static function strip_pointer($str)
	{
		if(!lang::has_reference_flag($str)) return $str;
		if($str=="") return "";
		$str_split = explode("|",$str);
		if(count($str_split)==2) return $str_split[1];
		else if($str[0]=="$") return substr($str,1);
		else if(count($str_split)>2)
		{
			$str_split = decapitate($str_split,1);
			$str_split = implode("|",$str_split);
			return $str_split;
		}
		else return $str_split[0];
	}
	static function isolate_spointers($str)
	{
		return array($str);
	}
	static function isolate_pointers($str)
	{
		$str_split = explode("|",$str);
		$head = $str_split[0];
		if(consume($head,"#s")) return interpreter::isolate_spointers("s".$head);
		if(count($str_split)>=2)
		{
			$ref = "";
			$str_splitter = parser::split_chars($head);
			while(count($str_splitter)>0 && !is_numeric($str_splitter[0]))
			{
				$ref.=$str_splitter[0];
				$str_splitter = decapitate($str_splitter,1);
			}
			$head = implode("",$str_splitter);
			$raw_pointers = $head;
			$out = explode(",",$raw_pointers);
		
			return $out;
		}
		else return array();
	}
	// TODO: Please do this like you did prefix processing, and document the syntax too.  This is an abomination.
	static function parse_estat($ar)
	{
	}
	static function naturalize($ar)
	{
		$out = array();
		$add = "";
		if($ar[0]=="nl")
			$ar[0] = "(nl)";
		if($ar[0]=="e")
		{
			$ar[1] = explode(" ",$ar[1]);
			$ar = array_merge([$ar[0]],$ar[1]);

			$ptr = parser::split_chars($ar[count($ar)-1]);

			unset($ptr[0]); $ptr = array_values($ptr); $ptr = implode("",$ptr);
			$ptr = explode("|",$ptr);
			$lit = lang::isolate_term($ptr[1]);
			$ptr = explode(":",$ptr[0]);

			$ptr_from = "";
			$ptr_to = "";

			if(count($ptr)==2)
			{
				$ptr_from = $ptr[0];
				$ptr_to = $ptr[1];
			}
			else if(count($ptr)==1)
			{
				$ptr_to = $ptr[0];
			}
			else return;

			$add.="[e|";
			if(strlen($ptr_from)>0)
			{
				if($ptr_from=="3") $add.="user";
				else if($ptr_from=="0") $add.="system";
				else $add.="concepts (".$ptr_from.")";
				$add.=" acting-on ";
			}
			if($ptr_to=="0") $add.="system:";
			else if($ptr_to=="3") $add.="user:";
			else $add.="concepts (".$ptr_to."):";

			for($i = 1; $i < count($ar)-1; $i++)
			{
				if($ar[$i][0]=="L") $add.="love";		// general positive sentiment towards a concept
				else if($ar[$i][0]=="P") $add.="pleasure";	// reward/punishment dimension
				else if($ar[$i][0]=="D") $add.="interest";	// how interesting is this concept?  could technically be tied into the former category...
				else if($ar[$i][0]=="A") $add.="relevance";	// is this semantically appropriate?

				$indicator = $ar[count($ar)-1][0];

				if($indicator=="%")
				{
					if($ar[$i][1]=="+"||strlen($ar[$i])==3 && $ar[$i][2]=="+")$add.="++";
					if($ar[$i][1]=="-"||strlen($ar[$i])==3 && $ar[$i][2]=="-")$add.="--";
					if($ar[$i][1]=="~"||strlen($ar[$i])==3 && $ar[$i][2]=="~")$add.="-nochange ";
					$add.=", when=";
					if($ar[$i][1]=="'")$add.="future";
					else if(strlen($ar[$i])==3 && $ar[$i][2]=="'")$add.="past";
					else $add.="present";
				}
				else if($indicator=="#")
				{
					if($ar[$i][1]=="+") $add.="++";
					if($ar[$i][1]=="-") $add.="--";
					if($ar[$i][1]=="~") $add.="-nochange";
					$add.=" {inflicted}";
				}
				$add .= '; ';
			}
			$add.="]";
			$out[] = $add;
			return interpreter::stripEscapes($lit." ".implode(" ",$out));
		}
		if(count($ar)==1 && is_array($ar)) return interpreter::naturalize($ar[0]);
		for($i = 1; $i < count($ar); $i++)
		{
			if(is_array($ar[$i]))
			{
				$out[] = interpreter::naturalize($ar[$i]);
			}
			else
			{
				$out[] = interpreter::strip_pointer($ar[$i]);
			}
		}
		$out = implode(" ",$out);
		//$GLOBALS['nstack']--;
	
		return interpreter::stripEscapes($out);
	}
	static function checkCommand($cmd,&$tree,$layout)
	{
		if(is_array($layout)) $flat_layout = implode(" ",$layout);
		switch($cmd)
		{
			//keytypes
			case 'i':
				if(!in_array("ARG",$layout) && !in_array("LOG",$layout))
					throw new Exception("syntax error: no primary arguments. ($flat_layout)");

				return 'informational';
			break;

			case '?':
				if(!in_array("ARG",$layout))
				throw new Exception("syntax error: no primary arguments. ($flat_layout)");

				return 'question';
			break;

			case '>':
				return 'greeting';
			break;

			case '<':
				return 'goodbye';
			break;

			case '!':
				return 'interjection';
			break;

			case ':|':
				return 'derp';
			break;

			//argtypes
			case '`':
				if(!in_array("STR",$layout))
				throw new Exception('syntax error: no string.');

				if(array_in_array($tree)) throw new Exception('syntax error: inappropriate arguments in (` section).');

				$tree = array($tree[0],implode(" ",decapitate($tree,1)));
				return 'arg1';
			break;

			case '"':
				if(!in_array("STR",$layout))
				throw new Exception('syntax error: no string.');

				if(array_in_array($tree)) throw new Exception('syntax error: inappropriate arguments in (" section).');

				$tree = array($tree[0],implode(" ",decapitate($tree,1)));
				return 'arg2';
			break;

			case '.':
				if(!in_array("STR",$layout))
				throw new Exception('syntax error: no string.');

				if(array_in_array($tree)) throw new Exception('syntax error: inappropriate arguments in (. section).');

				$tree = array($tree[0],implode(" ",decapitate($tree,1)));
				return 'ips/action';
			break;

			case ',':
				if(!in_array("STR",$layout))
				throw new Exception('syntax error: no string.');

				if(array_in_array($tree)) throw new Exception('syntax error: inappropriate arguments in (, section).');

				$tree = array($tree[0],implode(" ",decapitate($tree,1)));
				return 'uip';
			break;

			//declarations
			case '#':
				return 'direct';
			break;

			case '%':
				return 'indirect';
			break;

			case '&':
				return 'ambiguous';
			break;

			case '$#':
				return 'heap-direct-decl';
			break;

			case '$%':
				return 'heap-indirect-decl';
			break;

			case '@#':
				return 'heap-direct-ref';
			break;

			case '@%':
				return 'heap-indirect-ref';
			break;

			//ihds
			case '_':
				if(all_not_in_array(array("ARG1","ARG2","ARG"),$layout)) throw new Exception("syntax error: no subjects. ($flat_layout)");
				if(all_in_array(array("ARG1","ARG2","ARG"),$layout)) throw new Exception('syntax error: too many arguments.');
				if(count(array_keys($layout,'LOG'))>1) throw new Exception('syntax error: too many truth arguments.');
				if(count(array_keys($layout,'IF'))>1) throw new Exception('syntax error: too many if arguments.');
				if(count(array_keys($layout,'THEN'))>1) throw new Exception('syntax error: too many then arguments.');
				if(count(array_keys($layout,'ARG'))>1) throw new Exception('syntax error: too many supplementary arguments.');
				return 'isa';
			break;

			case '^':
				$args = [0,0,0];
				foreach($tree as $i=>$v)
				{
					$de = interpreter::deep_eval($v);
					$max = array_keys($de,max($de));
					if(count($max)==1) $args[$max[0]] = 1;
				}
				if(!isset($args[1]) && !isset($args[2])) throw new Exception('syntax error: invalid arguments (HASA)');
				return 'hasa';

				if(all_not_in_array(array("ARG1","ARG2","ARG"),$layout)) throw new Exception("syntax error: no subjects. ($flat_layout)");
				if(all_in_array(array("ARG1","ARG2","ARG"),$layout)) throw new Exception('syntax error: too many arguments.');
				if(count(array_keys($layout,'LOG'))>1) throw new Exception('syntax error: too many truth arguments.');
				if(count(array_keys($layout,'IF'))>1) throw new Exception('syntax error: too many if arguments.');
				if(count(array_keys($layout,'THEN'))>1) throw new Exception('syntax error: too many then arguments.');
				if(count(array_keys($layout,'ARG'))>1) throw new Exception('syntax error: too many supplementary arguments.');
				return 'hasa';
			break;

			case '*':
				if(all_not_in_array(["ARG1","ARG2","ARG"],$layout)) throw new Exception("syntax error: no subjects. ($flat_layout)");
				if(all_in_array(["ARG1","ARG2","ARG"],$layout)) throw new Exception('syntax error: too many arguments.');
				if(count(array_keys($layout,'LOG'))>1) throw new Exception('syntax error: too many truth arguments.');
				if(count(array_keys($layout,'IF'))>1) throw new Exception('syntax error: too many if arguments.');
				if(count(array_keys($layout,'THEN'))>1) throw new Exception('syntax error: too many then arguments.');
				if(count(array_keys($layout,'ARG'))>1) throw new Exception('syntax error: too many supplementary arguments.');
				return 'doesa';
			break;

			case '>>':
				if(all_in_array(["ARG1","ARG2"],$layout)); //Valid -- Kernel compression
			else	if(all_in_array(["ARG","LOG"],$layout)); //Valid -- Closure truth
			else	throw new Exception("syntax error: no subjects. ($flat_layout)");
				return 'tran';

			case 'e':
				return 'estat';
			break;

			//truth
			case '=':
				return 'true';
			break;

			case '-':
				return 'false';
			break;

			case '~':
				return 'ptrue';
			break;

			//conjunctions
			case '+':
				return 'and';
			break;

			case '/':
				return 'or';
			break;

			//conditional
			case '{':
				return 'if';
			break;

			case '}':
				return 'then';
			break;

			case ';':
				return 'skip';
			break;
			case 'nl':
				return 'nl';
			break;
			case 'c':
				return 'command';
				break;

			default: throw new Exception('syntax error: unknown command-type ('.print_r($cmd,1).')');
			break;
		}
	}
	static function check(&$tree,$level)
	{
		$layout = interpreter::getLayout($tree);
		if($level==0)
		{
			foreach($tree as $i=>&$v) interpreter::check($v,$level+1);
		}
		else
		{
			interpreter::checkCommand($tree[0],$tree,$layout);
			for($i = 1; $i < count($tree); $i++)
			{
				if(is_array($tree[$i])) interpreter::check($tree[$i],$level+1);
			}
		}
		return $tree;
	}
	static function preprocess($tree,$preprocess = 1) //Hardcoded pre-processing routines.
	{	//You may want to create a preprocessor class at some point.
		if(lang::is_terminal_flag($tree[0]))
		{
			$new_tree = [consume1($tree)];
			array_map(function($x){if(!is_string($x)) throw new Exception('Syntax Error: Invalid string literal contents.');},$tree);
			$tree = implode(" ",$tree);
			$new_tree[] = $tree;
			$tree = $new_tree;
		}
		if(count($tree)==0) throw new Exception("syntax error: empty block");
		$tree = remnull($tree);
		if($preprocess)
		{
			$tree = preprocessor::lazyArgs($tree);
			$tree = preprocessor::lazyPlus($tree);
			$tree = preprocessor::lazyIHD($tree);
			$tree = preprocessor::lazyClosure($tree); //what is this
		}
		return $tree;
	}
	//static function hasValidHead($str)
	//replaced with lang::has_reference_flag($str)
	static function addEscapes($str)
	{
		//$str = str_replace("(","\(",$str);
		//$str = str_replace(")","\)",$str);
		return $str;
	}
	static function stripEscapes($str)
	{
		$str = str_replace('\\(','(',$str);
		$str = str_replace('\\)',')',$str);
		return $str;
	}
	static function isIHD($s)
	{
		if($s=="_"||$s=="^"||$s=="*") return 1;
		return 0;
	}
	static function isConditional($s)
	{
		if($s=="{"||$s=="}") return 1;
		return 0;
	}
}
?>
