<?php
/*
 * lang.class.php - Functions related to the FZPL language.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class literal_parser
{
	public $heap_flag = null; //$ or #
	public $ref_type = null; //# or % or &
	public $addressee = null; //THIS:x,y,z
	public $address = null; //x:THIS,THIS,THIS
	public $subjects = null; //1,2,3:THIS,THIS,THIS
	public $literal = null; //The actual terminal string.
	public $estat = [];

	function __construct(string $str)
	{
		$str_init = $str;
		$this->parse($str);
	}
	function parse(string $str)
	{
		if($this->parse_heap_ref($str)) return;
	else	if($this->parse_registry_ref($str)) return;
	else	if($this->parse_estat($str)) return;
	else	if($this->parse_literal($str)) return;
	else	throw new Exception('string literal parser error');
	}
	function parse_estat_atom(string $v)
	{
		// initialization
		$node = ['char' => null, 'val' => 0, 'when' => 0];
		// "past" indicator
		if(consume($v,"'")) $node['when'] = -1;
		$c = consume1($v);
		// check for category code
		if(in_array(strtolower($c),['l','p','d'])) $node['char'] = strtolower($c);
		// check for stack reference address
	else	if(in_array($c,['%','#'])) return null;
		// check for heap reference address 
	else	if(in_array($c,['$','@']))
		{
			$this->heap_flag = $c;
			$this->addressee = $v;
			return 1;
		}
		// skip if invalid syntax
	else	return 0;
		$weights = [
			 '+++' => 1.5,
			 '++'  => 1.0,
			 '+'   => 0.5,
			 '-'   => -0.5,
			 '--'  => -1.0,
			 '---' => -1.5
		];
		$success = 0;
		// parse weight, skip if no match
		// TODO consider ambivalence/neutral sentiment (~) maybe
		foreach($weights as $sym => $weight)
			if(consume($v,$sym))
			{
				$node['val'] = $weight;
				$success = 1;
			}
		if(!$success) return 0;
		// "future" indicator
		if(consume($v,"'")) $node['when'] = 1;
		// if no type code or prefix still has remnants, skip.
		if(strlen($v) || $node['char'] == null) return 0;
		return $node;
	}
	function parse_estat($str)
	{
		$info = explode("|",$str);
		$prefix = consume1($info);
		$literal = implode("|",$info);
		$prefix = explode(" ",$prefix);
		foreach($prefix as $i=>$v)
		{
			$node = $this->parse_estat_atom($v);
			if($node === null) $this->parse_heap_ref("$v|$literal",1); // TODO the nomenclature of these functions are horribly wrong fix them
		else	if($node === 0) return 0;
		else	if($node === 1) continue;
		else	{
				$c = $node['char'];
				unset($node['char']);
				$this->estat[$c] = $node;
			}
		}
		return 1;
	}
	function parse_literal($str)
	{
		$this->literal = $str;
		return 1;
	}
	function parse_heap_ref($str,$estat = 0)
	{
		//Reference type?
		$flag = consume1($str);
		if(!in_array($flag,['#','%','&'])) return 0;

		$ptr_str = '';
		while($str[0] != '|' && strlen($str))
			$ptr_str .= consume1($str); 
		if(!strlen($str)) return 0;
		consume1($str);

		//Heap variable name?
		$ptr_split = explode(":",$ptr_str);
		$ptr_split = array_map(function($x){return explode(",",$x);},$ptr_split);

		if(count($ptr_split) == 1)
		{
			$ptr_str = explode(',',$ptr_str);
			foreach($ptr_str as $i=>&$v) {
				if(intval($v) != $v) return 0;
				$v = intval($v);
			}
			$this->address = $ptr_str;
		}
	else	if(count($ptr_split) == 2 && $estat)
		{
#			echo ">";
			$this->address = $ptr_split[0];
			$this->subjects = $ptr_split[1];
		}
	else	return 0;

		$this->literal = $str;
		$this->ref_type = $flag;
		return 1;
	}
	function parse_registry_ref($str)
	{
		//1. Is it a heap reference?
		$heap_prefix = consume1($str);
		if(!in_array($heap_prefix,['$','@'])) return 0;

		//2. Reference type?
		$flag = consume1($str);
		if(!in_array($flag,['#','%','&'])) return 0;

		$ptr_str = '';
		while($str[0] != '|' && strlen($str))
			$ptr_str .= consume1($str); 
		if(!strlen($str)) return 0;
		consume1($str);

		//3. Heap variable name?
		$ptr_info;
		$ptr_info_split = explode(':',$ptr_str);
		if(count($ptr_info_split)==2 && $heap_prefix != '$') return 0;
	else	if(count($ptr_info_split)==2) $ptr_info = ['name'=>$ptr_info_split[0],'info'=>$ptr_info_split[1]];
	else	if(count($ptr_info_split)==1) $ptr_info = ['name'=>$ptr_info_split[0]];
	else	if(count($ptr_info_split)>2) return 0;

		//4. Split pointers if still possible.
		if(isset($ptr_info['info']))
		{
			$ptr_info['info'] = explode(',',$ptr_info['info']);
			foreach($ptr_info['info'] as $i=>&$v) {
				if(intval($v) != $v) return 0;
				$v = intval($v);
			}
			$this->address = $ptr_info['info'];
		}

		$this->heap_flag = $heap_prefix;
		$this->ref_type = $flag;
		$this->addressee = $ptr_info['name'];

		$this->literal = $str;

		return 1;
	}
}
class lang //Static only, please.
{
	static	$baseflags = array("i","?",">","<","!",":|","c");
	static	$stemflags = array("_","^","*","{","}","+","/");
	static	$termflags = array("`","\"",".",",",";","=","-","~","e",":");
	static	$reference_flags = array('#','%','$#','$%','@#','@%','&','$&','@&'); //please account for ambiguity

	static function is_ambiguous_flag($s) {
		return in_array($s,['&','$&','@&']);
	}

	static function is_ambiguous($s) {
		$prefix = self::isolate_reference_flag($s);
		return in_array($prefix,['&','$&','@&']);
	}

	static function is_significant_flag($s)
	{
		if($s != ",") return 1;
		else return 0;
	}
	//TODO a non-lazy version of this and other functions. perhaps a comprehensive parser.
	static function isolate_term($s)
	{
		$parser = new literal_parser($s);
		return $parser->literal;
	}
	static function is_whitespace($char)
	{
		return in_array($char,[" ","\t","\n"]);
	}
	static function construct_reference($flag,$ptrs)
	{
		return $flag.implode(",",$ptrs);
	}
	static function construct_strlit($flag,$ptrs,$terminal)
	{
		return $flag.implode(",",$ptrs)."|".$terminal;
	}
	static function isolate_pointers_head($x)
	{
		foreach(lang::$reference_flags as $i=>$v) consume($x,$v);
		$x = explode("|",$x);
		if(count($x)>1) return explode(",",$x[0]);
		else return [];
	}
	static function isolate_pointers_nohead($x)
	{
		while(strlen($x)>0 && !is_alphanumeric($x[0])) consume1($x);
		return explode(",",$x);
	}
	static function isolate_heap_pointers($x,$name = 0,$heap = 1)
	{
		//Colon delimits name:pointers
		$a = explode(":",$x);
		//There should only be one colon
		if(count($a)>2) throw new Exception('syntax error: too many \':\'s used in heap-declaration pointer.'); //you should move this somewhere else.
		//If name is requested, return name (always on the left)
		if($name) return $a[0];
	else	if(count($a)==2) return explode(",",$a[1]);
	else	return ($heap)?[]:explode(",",$a[0]);
	}
	static function is_heap_declaration($k)
	{
		if(strlen($k)==0) return;
		return intval($k[0]=="$" && strlen($k)==2);
	}
	static function is_heap_reference($k)
	{
		return intval($k[0]=="@" && strlen($k)==2);
	}
	static function is_ihd_flag($flag)
	{
		return in_array($flag,['_','^','*']);
	}
	static function is_subject_flag($flag)
	{
		return in_array($flag,['`','"']);
	}
	static function is_reference($strlit)
	{
		if(!lang::has_reference_flag($strlit)) return 0;
		$strlit = explode("|",$strlit);
		if(count($strlit)>1) return 1;
		else return 0;
	}
	static function isolate_reference_flag($strlit,$remainder = 0)
	{
		if(lang::isolate_term($strlit) === $strlit) return '';
		$result = "";
		foreach(lang::$reference_flags as $i=>$v)
			if(consume($strlit,$v))
			{
				$result = $v;
				break;
			}
		if(!$remainder) return $result;
		else return $strlit;
	}
	static function isolate_reference($strlit)
	{
		$strlit = explode("|",$strlit);
		if(count($strlit)>1)
			return $strlit[0];
		else return "";
	}
	static function isolate_string_literal($strlit)
	{
		$strlit = explode("|",$strlit);
		if(count($strlit)>1)
			return implode("|",decapitate($strlit,1));
		else return $strlit[0];
	}
	static function has_reference_flag($strlit,$flag = null)
	{
		foreach(lang::$reference_flags as $i=>$v)
			if(consume($strlit,$v) && (!isset($flag) || $v == $flag)) return 1;
		return 0;
	}

	static function is_logical_terminal_flag($flag)
	{
		return intval(in_array($flag,['=','~','-']));
	}
	static function is_terminal_flag($flag,$important = 0)
	{
		if($important && $flag == ',') return 0;
		return intval(in_array($flag,lang::$termflags));
	}
	static function is_stem_flag($flag)
	{
		return intval(in_array($flag,lang::$stemflags));
	}
	static function is_informal_base_flag($flag) {
		return !lang::is_logical_base_flag($flag) && lang::is_base_flag($flag);
	}

	static function is_logical_base_flag($flag) {
		return intval(in_array($flag,['i','?']));
	}
	static function is_base_flag($flag)
	{
		return intval(in_array($flag,lang::$baseflags));
	}
	static function is_logical_stem_flag($flag)
	{
		return intval(in_array($flag,['_','^','*']));
	}
	static function is_conjunction_flag($flag) {
		return in_array($flag,['+','/']);
	}
	static function invert_logical_base_key($key) {
		switch($key) {
			case '?': return 'i'; break;
			case 'i': return '?'; break;
			default: throw new Exception('lang error: invalid logical base key'); break;
		}
	}
}
class lang_tree {
	static function getBaseFlag($tree) {
		return $tree[0][0][0];
	}
}
?>
