<?php
/*
 * kernel.class.php - Association data and the methods that manipulate it.
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

class kernel extends kernel_basic
{
	use fitter;
	use builder;
	use inquiry;
	use evaluator;
	use kernel_static;

	//parent :: contents -> registry of concepts.
	public $heap;		//the heap. user-specified pointers to addresses within the registry.
	public $status;		//TODO estat info.
	public $scope;		//logical info.
	public $grammar;	//grammar processing module.
	public $sentences;	//exchange-count incrementor.
	public $last_state;	//previous state of the kernel.
	public $cache = [];	//working from the end to the start, resolve these...
	public $sender;		//user id of sent message
	public $receiver;	//user id of received message
	public $bayes;		//bayesian analysis object

	public $oats;		//registers -- short-term literal cache.
	public $stack = [];	//INSTR stack.
	public $stack_ptr = -1;	//INSTR stack pointer.

	public $status_heap = [];
	public $procedures = []; //strategic game plans

	public $last_conf_inq = []; //last conflict inquiry
	public $last_conf_count = 0; //last conflict count

	public $uuid;
	static $instances = [];
	public $log = [];

	function log($str) {
		$add = '['.date('Y-m-d h:i:s').substr(microtime(),1,7).'] '.trim($str);
		$this->log[] = $add;
		if(cli()) {
		#	echo "$add\n";
		}
	}
	function update_global() {
		kernel::set_global($this);
	}

	// please use kernel::initialize() factory;
	function __construct($uuid = null)
	{
		parent::__construct();

		// someday we will need to remodel everything as closures.  kernelception.
		if(empty($uuid)) {
			$this->uuid = uuid();
		} else {
			$this->uuid = $uuid;
		}
		$this->update_global();

		//Someday this will need to be able to support additional info being fitted in.
		$this->add(new kernel_node('_OTHER'));
		$this->add(new kernel_node('_USR'));
		$this->add(new kernel_node('_SYS'));
		$this->add(new kernel_node('_SELF'));

		for($i = 0; $i <= 3; $i++) {
			$this->get($i)->set_sender(1);
			$this->get($i)->set_receiver(2);
		}
		
		for($i = 1; $i <= 2; $i++) $this->contents[$i]->user = 1;
		$this->scope = new scope();
		$this->heap = array();
		$this->status = new estat();
		/*
		parameters to consider:
		amount, difference.
		:or: perhaps create a {multidimensional} j-table so that it can be allocated with respect to different concepts.
			be wary of possible future kernel compression, though.
		TODO You may want to handle this with a kernel_node-esque object type.
		exchange and session terminators in contents would be cool TODO + brainstorm.
		*/
		$this->grammar = new grammar();
		$this->oats = new register(32);
		$this->bayes = new bayes();

		$this->status_heap = [
			'reward' => new estat(0,1,0),
			'punish' => new estat(0,-1,0),
			'seq' => new estat(0,0,0,1),
			'nseq' => new estat(0,0,0,-1)
		];
	}
	function __destruct() {
		#kernel::remove_global($this);
	}

	static function ind2dir(int $addr, int $sender, int $receiver) {
		if($addr == 0) return $receiver;
		if($addr == 3) return $sender;
		return $addr;
	}
	
	static function mirrorize($addr,$sender) {
		if(!in_array($addr,[0,1,2,3])) return $addr;
		if($addr == 3) return 0;
		if($addr == 0) return 3;
		if($addr == $sender) return 0;
		if($addr != $sender) return 3;
	}
	function update_status()
	{
		$this->status->iterate();
	}
	function inc_status($c,$amt = 1)
	{
		$this->status->inc($c,$amt);
	}
	function dec_status($c,$amt = 1)
	{
		$this->status->dec($c,$amt);
	}
	// strictly for debugging purposes - do not use.
	function getContents() {
		return object_clone($this->contents);
	}
	function get_status($c)
	{
		return $this->status->get($c);
	}
	function get_status_exp($c)
	{
		return $this->get_status($c) * 0.1;
	}
	function addr2returntype($addr)
	{
		$x = $this->contents[$addr];
		if($x->logical >= 0) return $this->scope->get($x->logical);
	else	return new kaddr($addr);
	}
	function get_logical_parent(int $addr)
	{
		while(1)
		{
			$parent = $this->get($addr);
			if(in_array($parent->get_term()[0],['_','^','*']))
				return $parent;
			else	$addr = $this->get_parent($addr,1);
		}
	}
	function getnodesbyname($str,$exceptions = [] /* first is accepted to be the literal address */,$strictness = 3,$permit_ambig = 1)
	{
		/* strictness levels:
		 * 0 - identical
		 * 1 - without punctuation
		 * 2 - lower case
		 * 3 - without punctuation, lower case
		 */
		$out = [];
		foreach($this->contents as $i=>$v)
			if($i > $exceptions[0]) break;
		else	if(!$permit_ambig && $v->ambiguous) continue;
		else	if(!is_string($v->get_term()) || in_array($i,$exceptions)) continue;
		else	if(!$v->search($str,$strictness)) continue;
		else	{
				$parent = $this->get_parent($i);
				$lbase = $this->get_logical_parent($i);
				if(!isset($parent) || in_array($lbase->get_term()[0],['^','_']) && $parent->get_term()[0] == '.') continue;
				$out[$i] = $v;
			}
		return $out;
	}
	function rollback()
	{
		if($this->last_state == null) return;
		$vars = get_object_vars($this->last_state);
		foreach($vars as $i=>$v) {
			$this->$i = $v;
		}
		$this->last_state = null;
	}
	//Assigns sender and receiver values to the latest address addition, or the address given.
	function sender_receiver($sender,$receiver,$addr = null)
	{
		if($addr!=null) $end = $addr;
		else		$end = $this->size()-1;
		$this->contents[$end]->sender = $sender;
		$this->contents[$end]->receiver = $receiver;
	}
	function identify_registry_pointers($terminal,$map)
	{
		$parser = new literal_parser($terminal);

		if($parser->heap_flag == '@')
		{
			$ptrs = $this->get_heap_addr($parser->addressee);
		}
	else	if($parser->heap_flag == '$')
		{
			$ptrs = $parser->address;
			if(isset($parser->address))
				$this->heap[$parser->addressee] = $parser->address;
			else	$this->heap[$parser->addressee] = [$map];
			$ptrs = $this->heap[$parser->addressee];
		}
	else	if(count($parser->address)) $ptrs = $parser->address;
	else	$ptrs = [$map];

		return array_unique($ptrs);
	}
	function process_terminal_pointers(string &$terminal,$map,$parent,int $index,int $sender,int $receiver)
	{
		//Remember, terminals have only one pointer.  You can do an array search.
		//One pointer -- really? TODO review
		$key = $parent[0];

		$parser = new literal_parser($terminal);

		$flag = $parser->ref_type; //please remove the heapreftype flag
		$ptrs = $this->identify_registry_pointers($terminal,$map);
		$optrs = $ptrs;

		if($flag == null) return;
		$prs = $ptrs;
		foreach($ptrs as $i=>&$v) {
			if($flag == '#') $v = kernel::ind2dir((int)$v,$sender,$receiver);
		}

		//kernel_node::term -> established at instantiation. TODO you were clearly being lazy here.
		$term = $parser->literal;

		//kernel_node::pointer -> what does this terminal reference?
		foreach($ptrs as $i=>$v) {
			$this->get($map)->add_pointer($v);
		}
		$this->get($map)->set_pointers($ptrs);
		//kernel_node::backtrace -> we should assign this preemptively. TODO the kernel node may not even exist.
		foreach($ptrs as $i=>$v) {
			$this->get($v)->add_backtrace($map);
		}
		
		//kernel_node::flag
		$this->get($map)->$flag = $flag;
		//kernel_node::name
		//kernel_node::pronoun
		$reftypes = ['#' => 'name', '%' => 'pronoun', '&' => 'ambig']; //TODO standardization, standardization, standardization

		$terminal = $flag.implode(",",$optrs)."|".$term;

		$ptrs[] = $map;
		$ptrs = array_unique($ptrs);

	}
	function process_pointers(&$tree, $map,$parent = null,$sender,$receiver)
	{
		if(is_array($tree))
			foreach($tree as $i=>&$v)
				if($map == null) continue;
				//Is not a base flag, or is a block.
			else	if($i>0 && $v[0] != 'e' || is_array($v) && lang::is_base_flag($v[0]))
				{
					if(/*is_array($v) && */$v[0] == 'c') continue;
					$this->process_pointers($v,$map[$i],$map,$sender,$receiver);
				}
		if(!is_array($tree))	$this->process_terminal_pointers($tree,$map,$parent,$map,$sender,$receiver);
	}

	//Keeping it simple.  Generates addresses and returns a map for future work.
	function kernelize($tree,int $sender, $row = 0, int $receiver)
	{
		$out = [];
		$index;
		$estat = null;
		$estat_parser = null;

		//Block.  Recursively iterate contents.
		if(is_array($tree))
		{
			foreach($tree as $i => $statement) {
				// Command blocks should be disregarded.
				if(is_array($statement) && $statement[0] == 'c')
				{
					if(!empty($c)) {
						$out[] = null;
						continue;
					}
				}
			else	if(is_array($statement) && $statement[0] == 'e')
				{
					$estat = new estat();
					$estat_parser = $estat->parse($statement[1]);
					$l = $estat->l;
					$p = $estat->d;
					if($estat->l > 0 && $estat->p > 0) $this->inc_status('l');
					if($estat->l < 0 && $estat->p < 0) $this->dec_status('l');
				}
			else	{
					if(!$i && is_string($statement)) {
						$out[] = $statement;
					} else {
						$out_add = $this->kernelize($statement,$sender,$row+1,$receiver);
						if(!empty($out_add)) {
							$out[] = $out_add;
						}
					}
				}
			}
			if(is_array($tree[0])) {
				return $out;
			}
		}

		//Terminal literal.  Add new node and return address.
		if(is_string($tree))
		{
			$this->add(new kernel_node(lang::isolate_term($tree)));
			$index = $this->size()-1;
			$out = $index;
		}
		else
		{
			$out_simple = array_map
			(function($x) {
				return (is_array($x))?$x['block-id']:$x;
			},$out);
			$this->add(new kernel_node($out_simple));
			$index = $this->size()-1;
		}

		//Sentence block/base.
		if(is_array($tree)) {
			$out['block-id'] = $index;
		}

		//kernel_node var handling
		if(isset($index))
		{
			//kernel_node::root
			if(lang::is_base_flag($tree[0]) == 1 && is_array($tree)) $this->get($index)->root = 1;
			//kernel_node::block
			if(is_array($tree)) $this->get($index)->block = 1;
			//kernel_node::sender
			$this->get($index)->set_sender($sender);
			$this->get($index)->set_receiver($receiver);
			//kernel_node::flag
			if($this->get($index)->block == -1)
			{
				$flag = lang::isolate_reference_flag($tree);
				if(strlen($flag)) $this->get($index)->flag = $flag[strlen($flag)-1];
			}
			//kernel_node::estat
			if($estat != null)
			{
				if($estat_parser->addressee != null && $estat_parser->heap_flag == '$') $this->status_heap[$estat_parser->addressee] = $estat;
				if($estat_parser->addressee != null && $estat_parser->heap_flag == '@')
				{
					if(!isset($this->status_heap[$estat_parser->addressee])) throw new Exception('invalid status_heap address');
					$estat = $this->status_heap[$estat_parser->addressee];
				}
				$this->get($index)->estat = $estat;
			}
		}

		return $out;
	}

	function infer(&$msg)
	{
		if($msg[0]!="nl") return;
		consume1($msg);
		echo	"Natural language query detected.\n",
			"Attempting to process... ";
		$inference = $this->grammar->parse($msg);
		echo	"Done.\n",
			"Result:\n",
			$inference,
			"\n";
		echo	"Would you like to verify the inferred structure? (1:Yes,0:No)\n";
		$infer = intval(scan());
		passthru("clear");
		if($infer) $inference = prompt(null,null,strlen($inference)?$inference:implode(" ",$msg),"geany");
		if(strlen($inference)==0) throw new exception("Fatal Error: Empty verified query.");
		$out = parser::parse($inference,0,1,0);
		$out = interpreter::check($out,0);
		$this->grammar->process($out);
		$msg = $out[0];
	}

	//You may want to refer back to the old mirror for more potential features.
	function mirror(int $index,int $sender,int $receiver)
	{
		$term = $this->getTerm($index);
		$parent = $this->get_parent($index);

		$out = [];

		if(is_array($term))
		{
			foreach($term as $i=>$v)
				if(!$i) $out[] = $v;
				else if(!is_array($v))
				{
					$add = $this->mirror($v,$sender,$receiver);
					if(is_array($add) && $add[0]=='e') $add[0] = ','; //TODO replace this -- temporary
					$out[] = $add;
				}
				//else $out[] = $v; //Or throw an exception, that would probably be better.
		}
		else
		{
			$node = $this->get($index);
			$flag = $node->flag;
			$type;
			if($flag==null||$flag=="") return $term;

			$pointers = $node->get_pointers();
			
			foreach($pointers as &$v) {
				if(in_array($v,[1,2]))
				$v = self::mirrorize($v,$sender);
			}
			
			$terminal_head = $flag.implode(',',$pointers)."|";

			$name = kernel_node::getName($flag,$index,$sender,$receiver);

			$out = $terminal_head.$name;
		}
		return $out;
	}

	function list_interjection_keys()
	{
		$out = [];
		foreach($this->contents as $i=>$v)
		{
			if($i < 4) continue;
			$base = $this->get_base($i);
			if($base->get_term()[0] != ':|' || $v->get_sender() == 2 || !isset($v->estat) ) continue;
			$out[] = $i;
			//if($v->block == 1)
		}
		return $out;
	}

	function interject()
	{
		$keys = $this->list_interjection_keys();
		$nodes = $this->query_by_keys($keys);
		$vecs = array_map('n2::vectorize',$nodes,$keys);
		$vecs_mapped = [];
		foreach($keys as $i=>$v) $vecs_mapped[$v] = $vecs[$i];
		$me = [
		     'bf-0'    => 0,
		     'i-0'     => 0,
		     'h-0'     => 0,
		     'd-0'     => 0,
		     'blt-0'   => 0,
		     'flag'   => 0,
		     'el-0'    => $this->status->get('l'),
		     'ep-0'    => $this->status->get('p'),
		     'ed-0'    => $this->status->get('d'),
		     'usr'    => -1
		];
		$min_dist;
		$min_addr = -1;
		foreach($vecs_mapped as $i=>$v)
		{
			$dist = n2::compare_vectors($me,$v);
			if(!isset($min_dist) || $min_dist != null && $dist < $min_dist)
			{
				$min_dist = $dist;
				$min_addr = $i;
			}
		}
		if($min_addr >= 0)
		{
			$out = $this->build($min_addr);
			return $out;
		}
		return null;
	}

	function respond($returns,$sender,$receiver,$inquiry = null)
	{
		$out = [];

		// Balance is key.
		if($this->status->expressable("D") < 0 && $this->status->get_expression('D') < $this->status->get_min_threshold("D"))
		{
			$this->status->express('D');
			$selection = $this->interject();
			if($selection != null) return $selection;
			else return [':|',['`','under-thresh interjection']];
		}
		if($this->status->expressable("D") > 0 && $this->status->get_expression('D') > $this->status->get_max_threshold("D"))
		{
			$this->status->express('D'); // Silent fluctuation so that inquiry may occur... change process if this is not desirable
			//return [':|',['`','above-thresh interjection']];
		}
		if(isset($inquiry)) return $inquiry;
		switch($returns->type)
		{
			//Informal
			//opts "i","?",">","<","!",":|"
			case '>':
			case '<':
			case '!':
			case ':|':
				$this->log("Non-logical response designated.");
				$index = $returns->kaddr[0]->get(0);
				$out;
				//Fill this with all the lovely operation possibilities
				if(!isset($returns->meta['force-mirror']) && count($out = $this->smart_mirror($index,$sender,$receiver))) {
					//we're good
				}
				//Otherwise, mirror.
				else {
				     //$this->smart_mirror($index,$sender,$receiver,1);
				     $out = $this->mirror($index,$sender,$receiver);
				}
				break;
			case 'i':
			case '?':
				$this->log("Logical response designated.");
				//do nothing for now, argue later
				$base_key = (isset($returns->meta['force-mirror']))?
					$returns->type
					: lang::invert_logical_base_key($returns->type);
				$out[] = $base_key;
				$ir = [];
				if($sender == 2) {
					$ir = $this->instr_logic_reply($returns,$base_key);
				}

				if(count($ir) && lang::is_logical_stem_flag($ir[0]))
				{
					$fitted = kernel::logical_base_fit($ir,$base_key);
					if($fitted === 0) $out[] = $ir;
					else	$out = $fitted;
				}	else	$out = $ir;
				//respond generically for now, elaborate later
				break;
		}
		
		if(empty($out)) {
			throw new Exception("kernel::respond() - Empty result.");
		}

		return $out;
	}
	//Rudimentarily joins instr->logic based on commonalities
	function merge_logic_returns(&$returns)
	{
		$ops = 0;
		foreach($returns->logic as $i=>$v)
			foreach($returns->logic as $j=>$w)
				if($j <= $i) continue;
			else	if($returns->logic[$i]->merge($w,$this))
				{
					$ops = 1;
					unset($returns->logic[$j]);
					$returns->logic = array_values($returns->logic);
					break;
				}
			else	continue;
		if($ops) $this->merge_logic_returns($returns);
	}
	function get_first_truth_literal($t)
	{
		foreach($this->contents as $i=>$v)
			if($v->block != 1) continue;
		else	if($v->get_term()[0] == $t) {
				$res = $this->get($v->get_term()[1])->get_term();
				if(!empty($res)) return $res;
			}

		// just return the defaults otherwise
		switch($t) {
			case '=':
				return 'true';
				break;
			case '-':
				return 'false';
				break;
			case '~':
				return 'ptrue';
				break;
		}
	}
	function get_parent($index,$return_index = 0)
	{
		foreach($this->contents as $i=>$v)
		{
			if($i <= $index) continue;
			if($v->block == 1 && in_array($index,$v->get_term()))
				return ($return_index)?$i:$v;
		}
		return null;
	}

	function get_base($index,$return_index = 0)
	{
		$new_index = $index;
		while($new_index != null)
		{
			$new_index = $this->get_parent($new_index,1);
			if($new_index == null) break;
			$index = $new_index;
		}
		if(!$return_index) {
			return $this->get($index);
		} else {
			return $index;
		}
	}

	function select_ihd_block_template($logic,$type)
	{
		$champion = null;
		$champion_weight = -1;
		foreach($this->contents as $i=>$v)
		{
			$challenger_weight = 0;

			// skip if structure was not "human" generated, nonblock, and isn't logical
			if($v->get_sender() != 1 || $v->block != 1 || !lang::is_logical_stem_flag($v->get_term()[0])) continue;
			if($v->get_term()[0] == $logic->type) $challenger_weight++;
			//TODO: estat analysis/distance
			$tree = $this->build($i);
			$layout = interpreter::getLayout($tree);
			$base = $this->get_base($i);
			if($base != null && $base->get_term()[0] == $type) $challenger_weight++;
			$challenger = null;
			if($challenger = kernel::precise_fit_logical(object_clone($logic),$i)) $challenger_weight += 2;
		else	continue;
			kernel_lib::microballot($champion,$challenger,$champion_weight,$challenger_weight);
		}
		return $champion;
	}
	function logic2instr($logic,$type = 'i')
	{
		$out = new instr($type);
		$out->init_logical($logic);
		return $out;
	}
	function kaddr2instr($kaddr)
	{
		$addr = $kaddr->contents[0];
		if($this->get($addr)->flag == '&')
		{
			$out = new instr('?');
			$out->init_informal(new kaddr($addr));
			return $out;
		}
		$laddr = $this->get($addr)->logical;
		$logic = $this->scope->get($laddr);
		$out = new instr('i');
		$out->init_logical($logic);
		return $out;
	}
	function construct_ihd_block($v)
	{
		$result = [$v->type];
		$truth = $v->truth->getType(0);
		$subj1 = $this->build_from_kaddr($v->subj1,1);
		$act = $this->build_from_kaddr($v->act,1);
		$subj2 = $this->build_from_kaddr($v->subj2,1);
		$cond = $impl = null;

		// why was 0 the second argument for instr_logic_reply() below?

		if(isset($v->cond))
		{
			$t = $this->instr_logic_reply($this->kaddr2instr($v->cond),'i',1);
			$t2 = kernel::fit_ifthen('{',$t);
			if($t2 === 0) $cond = ['{',$t];
			else $cond = $t2;
			if(!count($t) || $t[0] == ':|') $cond = null;
		}
		if(isset($v->impl))
		{
			$t = $this->instr_logic_reply($this->kaddr2instr($v->impl),'i',1);
			$t2 = kernel::fit_ifthen('}',$t);
			if($t2 === 0) $impl = ['}',$t];
			else $cond = $t2;
		}

		$result = remnull (
				array_merge (
					$result,
					[
						$subj1,
						$act,
						$subj2,
						$cond,
						$impl,
						[
							$truth,
							interpreter::checkCommand($truth,$null,null)
						]
					]
				)
		);

		return $result;
	}
	function instr_logic_reply(instr $returns /*instr*/,$type = null,$fixed_truth = null)
	{
		// note: type refers to the rootkey to be used in response.

		$adds = [];
		$results = [];
		if(isset($type)) $returns->type = $type;
		else $type = $returns->type;

		$default = [':|', ['`','...']];

		if($type == '?')
		{
			$out;
			$subj1_index = $returns->logic[0]->subj1->get(0);
			$base_addr = $this->get_base($subj1_index,1);
			if(!isset($returns->meta['force-mirror']) && count($out = $this->smart_mirror($base_addr,1,2))) {
				$adds = $out;
			} else if(!empty($returns->meta['force-mirror'])) {
				$returns->type = 'i';
				$force_mirror = $this->instr_logic_reply($returns,null,true);
				$adds = $force_mirror;
			} else {
				$adds = $default;
			}
		}
		if($type == 'i')
		{
			foreach($returns->logic as $i=>$v)
			{
				$v = object_clone($v);
				$evaluation;

				$otruth = $v->truth;
				
				$returns->logic[$i] = object_clone($v);
				$completion = $this->scope->complete($returns->logic[$i]);
				// Case 1: No ambiguity.
				if(!$completion) {
					$evaluation = $this->scope->evaluate($v,$this);
				}
				// Case 2: Ambiguity, completed.
			else	if($completion == 1)
				{
					$evaluation = $this->scope->evaluate($returns->logic[$i],$this,1);
					if($evaluation > 2/3) {
						$evaluation = new truth($returns->logic[$i]->truth->getType(0));
					}
				else	if($evaluation > 1/3) $evaluation = new truth('~');
				else	if($evaluation > 0/3) $evaluation = new truth('-');
				}
				// Case 3: Ambiguity, unresolved.
			else	if($completion == -1)
				{
					$adds = $default;
					unset($returns->logic[$i]);
				}

				if($fixed_truth == 1) $evaluation = $otruth;

				if($completion != -1)
				{
					$evaluation_type = $evaluation->getType(0);
					$returns->logic[$i]->truth->setType($evaluation_type);
				}
			}
			$this->merge_logic_returns($returns);
			foreach($returns->kaddr as $i=>$v) {
				foreach($v->contents as $j => $w) {
					$node = $this->get($w);
					if(lang::is_ambiguous_flag($node->flag)) {
						print_r($node);
					}
				}
			}
			foreach($returns->logic as $i=>$v)
			{
				if(isset($fixed_truth) && $fixed_truth != 1) $v->truth->setType($fixed_truth,0);
				$w = object_clone($v);
				$struct = $this->select_ihd_block_template($w,$returns->type);
				if(isset($struct))
					$result = $struct;
				else	$result = $this->construct_ihd_block($v);
				$results[] = $result;
			}
			if(count($results)>1) $adds = array_merge(kernel::and_fit($results),$adds);
			else if(count($results)==1) $adds = $results[0];
			else ;
		}

		if(empty($adds)) {
			throw new Exception("kernel::instr_logic_reply() - Empty result.");
		}

		return $adds;
	}
	function sleep($its = 0)
	{
		// temporary - reset conflicts in scope before total sleep cycle to prevent redundancy
		if(!$its) {
			$this->scope->conflicts = [];
		}

		$iterations = 0;
		while($this->scope->sleep($this))
			if($its && $iterations >= $its) break;
			else $iterations++;
	}
	function pop()
	{
		return array_pop($this->stack);
	}
	function push($returns)
	{
		//if(count($returns->logic))
		array_push($this->stack,$returns);
	}
	function process($msg,&$tree)
	{
		$results = [];
		$eval_success = 0;
		//Prepare and build tree
		try
		{
			$this->log('Processing FZPL query: '.$msg);
			$this->prepare($msg,$tree);
			$this->log('Successfully processed FZPL query.');
			//Backup the kernel before attempting operations
			$this->archive();
			//Generate parsing grammar rules for NLP
			#$this->grammar->process($tree);
			//Evaluate parse tree
			$result = $this->evaluate($tree);
			$this->log("FZPL query evaluation complete.");
			$results[] = $result;
			$eval_success = 1;
		}
		catch (Exception $e)
		{
			if(cli()) {
				error_log("Tree:");
				error_log(print_r($tree,1));
				error_log("Log:");
				error_log(print_r(array_reverse($this->log,true),1));
			}
			error_log(jTraceEx($e));
			$this->rollback();
		}
	//	if($eval_success) $this->scope->sleep($this);
		//writef('kernel.dat',serialize($this));
		//writef('log.dat',serialize($hist));
		$this->log("End query processing sequence.");
		return $results;
	}
	function prepare($msg,&$tree)
	{
		//Rudimentary preparation
		//Convert into parse tree and run preprocessor routines
		$parse_success = 0;
		
		$tree = parser::parse($msg,0);
		//Verify syntax
		$tree = interpreter::check($tree,0);
		$parse_success = 1;
		
		return true;
	}
	function isolate_subj1($tree,$args,$map,$sender,$receiver)
	{
		$out = new kaddr(null);
		$layout = interpreter::getLayout($tree);
		foreach($layout as $i=>$v)
		{
			$de = interpreter::deep_eval($tree[$i]);
			$max = array_keys($de,max($de));
			$valid = intval(count($max)==1 && $max[0]==1);
			//formerly: ARG1 or !ARG1+ARG
			if
			(
				($v == "ARG1") ||
				($v == "ARG" && $valid) ||
				(!isset($args[1]) && $v=="ARG" && count($max)>1)
			)
			{
				$eval = $this->evaluate_block($tree[$i],$map[$i],$sender,$receiver);
				$out->merge($eval);
			}
		}
		$out->flag = '`';
		return $out;				
	}
	function isolate_action($tree,$args,$map,$sender,$receiver)
	{
		$out = new kaddr(null);
		$layout = interpreter::getLayout($tree);
		foreach($layout as $i=>$v)
		{
			$de = interpreter::deep_eval($tree[$i]);
			$max = array_keys($de,max($de));
			//formerly: CMT, nothing else
			if( ($v == "CMT") || ($v == "ARG" && count($max)==1 && $max[0]==0) )
				$out->merge($this->evaluate_block($tree[$i],$map[$i],$sender,$receiver));
		}
		$out->flag = '.';
		return $out;				
	}
	function isolate_subj2($tree,$args,$map,$sender,$receiver)
	{
		$out = new kaddr(null);
		$layout = interpreter::getLayout($tree);
		foreach($layout as $i=>$v)
		{
			$de = interpreter::deep_eval($tree[$i]);
			$max = array_keys($de,max($de));
			//formerly: ARG2 or !ARG2+ARG
			if
			(
				($v == "ARG2") ||
				($v == "ARG" && count($max)==1 && $max[0]==2)  ||
				(!isset($args[2]) && $v=="ARG" && count($max)>1)
			)
			{
				$eval = $this->evaluate_block($tree[$i],$map[$i],$sender,$receiver);
				$out->merge($eval);
			}
		}
		$out->flag = '"';
		return $out;
	}
	function isolate_truth($tree,$args,$map,$sender,$receiver)
	{	//TODO check limit to one truth
		$layout = interpreter::getLayout($tree);
		$out = [];
		if(!in_array("LOG",$layout)) {
			$out = [new truth("=")];
		}
		else foreach($tree as $i=>$v)
			if($layout[$i]=="LOG") {
				$out_add = new truth($v[0]);
				$out[] = $out_add;
			}
		return $out;
	}
	function isolate_if($tree,$args,$map,$sender,$receiver)
	{
		$layout = interpreter::getLayout($tree);
		$out = new kaddr(null);
		if(!in_array("IF",$layout)) return new kaddr(null);

		$tree_segment = $tree[array_search("IF",$layout)];
		$map_segment = $map[array_search("IF",$layout)];
		$k = $this->evaluate_block($tree_segment,$map_segment,$sender,$receiver);
		$k->validate_logical();
		return $k;
	}
	function isolate_then($tree,$args,$map,$sender,$receiver)
	{
		$layout = interpreter::getLayout($tree);
		if(!in_array("THEN",$layout)) return new kaddr(null);
		$k = $this->evaluate_block($tree[array_search("THEN",$layout)],$map[array_search("THEN",$layout)],$sender,$receiver);
		return $k;
	}

	function is_special_block($map)
	{
		$parent;
		do
		{
			$map = $this->get_parent($map,1);
			$parent = $this->get($map);
		}
		while(in_array($parent->get_term()[0],['+','/']));
		if(is_object($parent) && in_array($parent->get_term()[0],['i','?'])) return 1;
		return 0;
	}
	function store_logic(logic $logic)
	{
		$this->add(new kernel_node("LOG"));
		$this->get($this->size()-1)->logical = count($this->scope->contents);
		$this->scope->contents[] = $logic;
		$addr = $this->size()-1;
		$out = new kaddr($addr);
		$out->validate_logical();
		return $out;
	}
	function merge_into($x_ptrs,$y_ptrs)
	{
		$x_exp = kernel::extract_concept_roots_kaddr(new kaddr($x_ptrs));
		$y_exp = kernel::extract_concept_roots_kaddr(new kaddr($y_ptrs));
		$z = varbase2([$x_exp,$y_exp]);
		foreach($z as $i=>$v)
			$this->get($v[0])->merge($this->get($v[1]));
		array_map([$this,'clear_address'],$y_ptrs);
	}
	function extract_concept_roots_kaddr($kaddr)
	{
		$out = [];
		foreach($kaddr->get_contents() as $i=>$v) {
			$out = array_merge($out,$this->extract_concept_roots($v));
			
		}
		return $out;
	}
	function extract_concept_roots(int $index)
	{
		$out = [];
		$node = $this->get($index);
		if(strlen($node->flag) == 0) return [$index];
	else	if($node->flag == "%") return $node->get_pointers();
	else	if($node->flag == "#")
			foreach($node->get_pointers() as $i=>$v)
				if($v != $index) $out = array_merge($this->extract_concept_roots($v));
				else $out[] = $index;
	else	if($node->flag == "&") //amgibuous
			return [];
		return array_values($out);
	}
	function get_sentence_bases()
	{
		
	}
	function query_by_keys($keys)
	{
		$out = [];
		foreach($keys as $i=>$v) $out[$v] = $this->get($v);
		return $out;
	}
}
?>
