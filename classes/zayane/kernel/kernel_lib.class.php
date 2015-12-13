<?php
/*
 * kernel_lib.class.php - "Complex" non-central methods for the kernel.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/
//TODO traits where more appropriate
class kernel_lib
{

	static function microballot(&$champion,$challenger,&$champion_weight,$challenger_weight)
	{
		if($challenger_weight > $champion_weight)
		{
			$champion = $challenger;
			$champion_weight = $challenger_weight;
		}	
	}
	static function arg_presence($layout)
	{
		return array_map
		(	function($x)
			{
				return ($x===false)?null:$x;
			},
			[in_array('ARG',$layout), in_array('ARG1',$layout), in_array('ARG2',$layout),]
		);
	}
	static function getName($kernel,$id,$type,$bef,$aft,$sender,$receiver)
	{	//Types: 'name', 'pronoun'
		$result = "";

		if($id==3 && $type=="name") return kernel_lib::getName($kernel,$sender,$type,$bef,$aft,$sender,$receiver);
		if($id==0 && $type=="name") return kernel_lib::getName($kernel,$receiver,$type,$bef,$aft,$sender,$receiver);

		return $kernel->contents[$id]->getName($type,$bef,$aft);
	}
	static function getNeighbors($kernel,$parent,$index)
	{
		//I dont think this is necessary.
		//TODO maybe it is, implement in kernel_basic
	}
	static function list_terms($k)
	{
		$out = array();
		foreach($k->contents as $i=>$v)
		{
			if(!is_array($v->term)) $out[$i] = $v->term;
		}
		return $out;
	}
	static function list_heap_terms($k)
	{
		$out = array();
		foreach($k->heap as $i=>$v)
		{
			if(!is_array($k->contents[$v[0]]['term'])) $out[$i] = $k->contents[$v[0]]['term'];
		}
		return $out;
	}
	static function list_flat_items($k)
	{
		$out = array();
		foreach($k->contents as $i=>$v)
		{
			$out[] = $v->term;
		}
		return $out;
	}
	static function getKeys($sentence,$kernel)
	{
		$out = array();
		if(!is_array($sentence->term))
		{
			if($sentence->flag != null) $out[] = array
			(	//Stuff
				'flag' => $sentence->flag,
				'pointer' => $sentence->pointer,
				'term' => $sentence->term
			);
			return $out;
		}

		for($i = 1; $i < count($sentence->term); $i++)
		{
			if($sentence->term[$i][0]==":" & is_numeric($sentence->term[$i][1]))
			{
				$num = substr($sentence->term[$i],1);
				$out = array_merge($out,kernel_lib::getKeys($kernel->contents[$num],$kernel));
			}
		}
		return $out;
	}
	static function build_tree($kernel,$sentence)
	{
			$out = array();
			
	}
	static function getUips($sentence,$kernel)
	{
		$out = array();
		if(!is_array($sentence->term))
		{
			if(!isset($sentence->flag)) $out[] = array
			(	//Stuff
				'term' => $sentence->term
			);
			return $out;
		}

		if($sentence->term[0]==',') return array(); //"Terminal" -- ignore.  At some point, disregard ',' statements completely. 

		for($i = 1; $i < count($sentence->term); $i++)
		{
			if($sentence->term[$i][0]==":" & is_numeric($sentence->term[$i][1]))
			{
				$num = substr($sentence->term[$i],1);
				$out = array_merge($out,kernel_lib::getUips($kernel->contents[$num],$kernel));

			}
		}
		return $out;
	}
	static function specialSigmoid($x)
	{
		$sigmoid = math::sigmoid($x);
		//echo "-->".$sigmoid."<br/>";
		if(in_range($sigmoid,2/3,1)) return 1;
	else	if(in_range($sigmoid,1/3,2/3)) return 0.5;
	else 	return 0;

//		$seed = rnd();
//		$result = round(intval($seed<$sigmoid));
//		return $result;
	}

	static function dump_logical($strict = 0) {
		kernel::get_global($kernel);
		echo '<table width=100%>';
		foreach($kernel->scope->contents as $i=>$v)
		{
			if($strict && !($v->root >= 1)) continue;
			
			$type = $v->type;
			$w1 = $v->subj1->contents[0];
			$act = @$v->act->contents[0];
			$w2 = $v->subj2->contents[0];

			$truth = $v->truth->getType(0);
			$cond = $v->cond;
			$scond = '';
			if(!empty($cond) && !empty($cond->contents)) {
				$addr = $cond->contents[0];
				$laddr = $kernel->get($addr)->logical;
				if($laddr != -1) {
					$lnode = $kernel->scope->contents[$laddr];
					$scond = "({ L$laddr)";
				}
			}
			$impl = $v->impl;

			if($i%10 == 0) echo '<tr>';
			if($v->root >= 1) echo "<td><b>( $type $w1 $act $w2 $truth $scond )</b></td>";
		else	if($v->root==0)echo "<td>( $type $w1 $act $w2 $truth $scond )</td>";
			if($i%10 == 0 && $i) echo '</tr>';
		}
		echo '</table>';
	}
	static function dump()
	{
		kernel::get_global($kernel);
		echo '<table width="100%">';
		echo '<tr><td bgcolor="b0b0ff"><b>kernel dump</b></td></tr>';

		echo '<tr><td bgcolor="d0d0ff"><b>heap contents</b></td></tr>';
		echo '<tr><td bgcolor="f0f0ff">';
		foreach($kernel->heap as $i=>$v)
			echo "<div style=\"display:inline; margin:10px;\">@<b>$i</b> => [ ".implode(",",$v)." ]</div>";
		echo '</td></tr>';

		echo '<tr><td bgcolor="d0d0ff"><b>registry contents</b></td></tr>';
		echo '<tr><td bgcolor="f0f0ff"><table width="100%">';
		$kernel->map(function($v,$i){
			if($v==null) continue;
			if(!($i%10) && $i>0) echo '</tr>';
			if(!($i%10)) echo '<tr>';
			echo '<td>';
			if(is_string($v->term))
				echo "<div style=\"display:inline; color:#000080;\">@<b>$i</b> => [ ".$v->term.((count($v->pointer))?' >> '.$v->flag.implode(",",$v->pointer):'')." ]</div> ";
		else	if($v->logical == -1)
			{
				echo "<div style=\"display:inline; color:#008000;\">@<b>$i</b> => { ".implode(" ",$v->term)." }</div> ";
			}
		else		echo "<div style=\"display:inline; color:#800000;\">@<b>$i</b> => [ L".$v->logical." ]</div> ";
			echo '</td>';
		});

		echo '</table></td></tr>';

		//echo '<tr><td bgcolor="d0d0ff"><b>estat info</b></td></tr>';
		echo '<tr><td bgcolor="d0d0ff"><b>logical info</b></td></tr>';
		echo '<tr><td bgcolor="f0f0ff">';
		kernel_lib::dump_logical();
		echo '</td></tr>';
/*
    [subj1] => kaddr Object
    [act] => kaddr Object
    [subj2] => kaddr Object
    [truth] => truth Object
    [cond] => 
    [impl] => 
    [type] => _
    [root] => 1
*/
		echo '<tr><td bgcolor="d0d0ff"><b>parsing grammar rules</b></td></tr>';
		echo '</table>';
	}
}
