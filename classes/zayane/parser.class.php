<?php
/*
 * parser.class.php - Parses "Zayane Lisp" code and converts it into a tree.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class parser
{
	//TODO: Incorporate the natural language inferrence algorithm into this.
	static function parse(&$str,$row,$preprocess = 1,$softspace = 0,&$kernel = null,$debug = 0,$ostr = null)
	{
		if(empty($ostr)) $ostr = $str;
		$out = [];
		$index = 0;
		while(strlen($str))
			//Whitespace Handling
			if(consume($str," ")||consume($str,"\t")||consume($str,chr(10))||consume($str,chr(13))) $index++;
			//Comment Handling
		else	if(consume($str,"//")) while(strlen($str) && !in_array(consume1($str),["\n",chr(10),chr(13)]));
			//Comment Block Handling
		else	if(consume($str,"[*")) while(strlen($str) && !consume($str,"*]")) consume1($str);
			//Jump Handling
		else	if(consume($str,"("))
			{
				$index++;
				$out[] = parser::parse($str,$row+1,$preprocess,$softspace,$kernel,$debug,$ostr);
			}
			//Return Handling
		else	if(consume($str,")")) break;
			//Escape Character Handling
		else	if(consume($str,"\\") && 0);
			//Holy Grail
		else	if ($row==0)
				do
				{
					$str = explode("\n",$str);
					$msg = consume1($str);
					$msg = explode(" ",trim($msg));
					$msg = remnull($msg);
					$str = implode("\n",$str);
					if(count($msg)<2||$msg[0] != "nl:") break;
					consume1($msg);
					$tokens[] = array_merge(["nl"],$msg);
					$index++;
				}
				while($row == 0);
		else	$out[$index] = (isset($out[$index])?$out[$index]:"").consume1($str);

		if(!empty($out)) {
			$preprocessed = interpreter::preprocess(array_values($out),($preprocess && $out[0] != 'c'),$row);
			return $preprocessed;
		} else {
			throw new Exception("Error: Nothing parsed.\nString: $ostr");
		}
	}
	static function split_chars($s,$softspace = 0)
	{
		if(!$softspace) $s = preg_split('//', trim(preg_replace("'\s+'", ' ', $s)), -1);
	else	$s = preg_split('//', trim(preg_replace("'\s+'", ' ', $s)), -1);
		$s = remnull($s);
		return $s;
	}
}
?>

