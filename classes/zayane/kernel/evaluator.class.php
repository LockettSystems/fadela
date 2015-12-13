<?php
/*
 * evaluator.class.php - Functions related to structure evaluation
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

trait evaluator
{
	function evaluate_test($block,$test)
	{
		if(isset($test[3]))
		{
			$t3 = $test[3];
			unset($test[3]);
			$test = array_values($test);
			return $this->evaluate_test($block[$t3],$test);
		}
		switch($test[1])
		{
			case 'block':
				return ($block[0] == $test[2])?'1':'0';
				break;
			case 'flag':
				return (lang::has_reference_flag($block[1],$test[2]))?'1':'0';
				break;
			case 'has_ptr':
				$parsed = new literal_parser($block[1]);
				return in_array($test[2],$parsed->address)?'1':'0';
			case 'literal':
				$parsed = new literal_parser($block[1]);
				return ($test[2] == $parsed->literal)?'1':'0';
				break;
			case 'default':
				return -1;
		}
		return -2;
	}
	function evaluate_command($base,$sender,$receiver,&$base_key)
	{
		$out = [];
		switch($base[1])
		{
			case 'inq-trigger':
				$base_key = '?';
				return [['_',['`','x'],['.','y'],['"','z']]];
				break;
			case 'autonuke';
				$this->last_state = new kernel();
				$this->rollback();
				return [['`','autonuke complete']];
				break;
			case 'force-mirror':				
				break;
			default:
				$out[] = ['`','invalid command'];
				break;
		}
		return $out;
	}
	function strategic_interact()
	{
		//output
		$procedural_response = null;
		//base keys
		$keys = $this->list_base_keys();
		//iterate active strategic interaction routines -- prioritize this someday
		printDat($this->procedures);
		foreach($this->procedures as $procno=>$procedure)
		{
			//last base address
			$lptr = $keys[count($keys)-1];
			//vector, user's last response
			$usr_real = n2::vectorize($this->get($lptr),$lptr);
			//vector, user's expected response
			if($procedure['step'] >= count($procedure['litmus'])) continue; //TODO implement a proper solution
			$usr_goal = $procedure['litmus'][$procedure['step']];
			//vector distance
			$diff = n2::compare_vectors($usr_goal,$usr_real);
			//reward threshold -- should be fuzzy
			$thresh = 2;
			if($diff < $thresh)
			{
				// eventually every monkey in the cage needs to get water on it
				$this->dec_status('p',($procedure['step']+1)/count($procedure['program']));
				unset($this->procedures[$procno]);
				continue;
			}
			else
			{
				// eventually every monkey in the cage needs to get fabulous prizes
				$this->inc_status('p',($procedure['step']+1)/count($procedure['program']));
				$procedural_response = $this->build($procedure['program'][$procedure['step']]['ptr']); //do something sensible with this
			}
			$this->procedures[$procno]['step']++;
			if($procedure['step'] >= count($procedure['litmus']))
			{
				unset($this->procedures[$procno]);
				break;
			}
		}
		return $procedural_response;
	}
	// Evaluates parse tree from rock bottom.
	function evaluate($tree,$sender = 1,$receiver = 2)
	{
		$this->sender = $sender;
		$this->receiver = $receiver;

		// Output, set of FZPL parse trees
		$out = [];

		// Pointer to cache address being analyzed -> kernel::stack_ptr

		// Inquiry
		$conf_inq = [];

		//Evaluate each base statement in parse tree, use results to generate a response, and return it.
		foreach($tree as $i=>$base)
		{
			// Previous inquiry
			$last_conf_inq = $this->last_conf_inq;
			$last_conf_count = $this->last_conf_count;

			// Adjust addresser/addressee
			$this->sender = $sender;
			$this->receiver = $receiver;

			//Generate a map of addresses
			$map = $this->kernelize($base,$sender);

			foreach($base as $k=>$v) {
				if(is_array($v) && $v[0] == 'c') {
					$this->get($map['block-id'])->has_command = 1;
				}
			}

			//Do all the necessary pointer work
			$this->process_pointers($base,$map,null,$sender,$receiver);

			//Store prior status for reporting
			$last_status = serialize($this->status);

			//Strategic interaction management
			//$procedural_response = $this->strategic_interact();

			//"Instr" object summing up evaluation process
			if($base[0] == 'c')
			{
				$cmd_key = 'cout';
				$eval = $this->evaluate_command($base,$sender,$receiver,$cmd_key);
				$cout = array_merge([$cmd_key],$eval);
				if($cmd_key != 'cout')
				{
					$ret = $this->evaluate([$cout],$receiver,$sender);
				}
				$cout[] = ["`", s64($last_status), s64($this->status)];
				$out[] = $cout;
				continue;
			}
		else	$returns = $this->evaluate_base($base,$map,$sender,$receiver);

			$this->log('Successfully evaluated FZPL: '."\n".interpreter::simplify($base,1,0,1));

			//Push to the stack
			$this->push($returns);

			//Contemplate logical knowledge base
			$this->sleep();

			//Skip command handling -- do more with this
			if(in_array("SKIP",interpreter::getLayout($tree)))
			{
				$out[] = [];
				$this->update_status();
				continue;
			}

			// Generate conflict resolution inquiry prior to response-time

			// Generate an inquiry about ambiguities.
			$conf_inq = $this->generate_ambiguity_inquiry($returns);

			// If there are no ambiguities, generate an inquire about logical conflicts.
			if(!count($conf_inq)) {
				$conf_inq = $this->generate_conflict_inquiry();
			}
			// If there are no logical conflicts, clear the stack.
			if(!count($conf_inq)) {
				$this->stack = []; //again, modularity.  please.  this is a disorganized mess.
			}

			// React to potential inquiry dismissals

			// Count the number of remaining conflicts on the stack
			$confkeys = array_keys($this->stack);
			$conf_count = count($confkeys);
/**
			printDat([
				// keys of stack contents
				'confkeys' => $confkeys,
				// last conflict inquiry tree
				'last_conf_inq' => $last_conf_inq,
				// current conflict inquiry
				'conf_inq' => $conf_inq,
				// current scope conflicts
				'scope conflicts' => $this->scope->conflicts,
			]);
/**/
			// If remaining conflicts, and the system just asked about it, react adversely.
			if(count($confkeys))
			{
				if(in_array($this->stack_ptr,$confkeys) && !empty($last_conf_inq))
				{
					$this->dec_status('D');
				}
				$this->stack_ptr = $confkeys[count($confkeys)-1];
			}
			// Otherwise, react positively.
		else	if($conf_count < $last_conf_count)
			{
				$this->inc_status('D');
				$this->stack_ptr = -1;
			}

			//Generate response to "instr" object, format it
			$reply = $this->respond($returns,$sender,$receiver);
			
			//Kernelize system response
			$resp_map = $this->kernelize($reply,$receiver);

			//Do all the necessary pointer work
			$this->process_pointers($reply,$resp_map,null,$receiver,$sender);

			if(count($conf_inq) && !$this->status->expressable('D') && $this->status->d >= 0) $reply = $conf_inq;
			else $conf_inq = null;

			// Preprocess the response parse tree generated
			if(count($reply))
				$reply = interpreter::preprocess($reply,1);

			// Post-processing tag -- status info
			$status_generated = $this->status->generate();
			if($status_generated != null) $reply[] = $status_generated;

			$reply[] = ["`", s64($last_status), s64($this->status)];

			// Cache last inquiry so we know it happened
			$last_conf_inq = $conf_inq;
			$last_conf_count = $conf_count;
			$this->last_conf_inq = $last_conf_inq;
			$this->last_conf_count = $last_conf_count;

			// Update the system status
			$this->update_status();

			// Add reply to set
			$out[] = $reply;
		}
		//Please make this entire 'ordeal' more modular rather than just hacking in a quick fix for avatar verification please

		//Return results
		return $out;
	}
	function evaluate_terminal($tree,$map,$sender,$receiver)
	{
		$ptrs = null;
		$lit_ptr = null;
		$key = $tree[0];
		$block_id = $map['block-id'];
		$parent = $this->get_parent($block_id);
		switch($key)
		{

			case '`':
			case '"':
			case '.':
				$ptrs = [$map[1]];
				$lit_ptr = $map[1];
				if(!count($this->get($map[1])->pointer))
				{
					// Mark literal entries as ambiguous if no entries with such literals already exist.
					$opts = $this->getnodesbyname($tree[1],[$map[1]]);
					if(count($opts) && !($key == '.' && $parent->term[0] == '_'))
					{
						$this->get($map[1])->ambiguous = 1;
					}
					$ar_keys = array_keys($opts);
				}
				else	$ptrs = $this->get($map[1])->pointer;
				$out = new kaddr($ptrs);
				break;

			case '=':
			case '-':
			case '~':
				$out = new truth($key);
				break;

			case 'e': //TODO
			     	$out = null;
				break;

			default: //error
				$out = null;
				throw new exception("Error: Unrecognized terminal flag '$key'");
				break;
		}
		//$ptrs = $lit_ptr;
		if($key != ',' && !$this->get($lit_ptr)->ambiguous && !($key == '.' && $parent->term[0] == '_'))
		{
			$this->oats->add(lang::isolate_string_literal($tree[1]), $ptrs, $key, lang::isolate_reference_flag($tree[1]));
		}
		return $out;
	}
	function evaluate_block($tree,$map,$sender,$receiver)
	{
		$key = $tree[0];
		$special_logic = $this->is_special_block($map['block-id']);

		if(lang::is_terminal_flag($key) && !$special_logic) return $this->evaluate_terminal($tree,$map,$sender,$receiver);
		$layout = interpreter::getLayout($tree);
		$out;

		switch($key)
		{
			//logic
			case '=':
			case '~':
			case '-':
				$out = new logic();
				// First, track down the previous instr.
				$prev = null;
				for($i = count($this->stack)-1; $i >= 0; $i--)
				{
					if($this->stack[$i]->owner == $receiver) $prev = object_clone($this->stack[$i]);
				}
				if($prev == null) throw new Exception('TODO: Revision of interpretation of explicit types and responding accordingly');
				// Second, assign truth to it (^ ARG LOG) style.
				$out = new kaddr(null);
				for($i = 0; $i < count($prev->logic); $i++)
				{
					$new_key = scope::invert($prev->logic[$i]->truth->getType(0),$key);
					$prev->logic[$i]->truth = new truth($new_key);
					$kaddr = $this->store_logic($prev->logic[$i]);
					$out->merge($kaddr);
				}
				// Third, tag it as addressed . . . or not . . .
				return $out;
				break;
			case '_':
			case '^':
			case '*';
				$out = new kaddr(null);
				$args = kernel_lib::arg_presence($layout);
				//TODO: integrate deep evaluation
				//if(!isset($args[1]) && !isset($args[2])) throw new exception('Indeterminable logical subjects.');
					//TODO expand when possible -- that one x did that one y with that one z
					//TODO or perhaps do deeper analysis in generation of layouts

				$logic_data = 
				[
					$key,
					//TODO: Implicit actions, and implicit other types -- "that thing you did yesterday"
					$this->isolate_subj1($tree,$args,$map,$sender,$receiver)->split(),
					$this->isolate_action($tree,$args,$map,$sender,$receiver)->split(),
					$this->isolate_subj2($tree,$args,$map,$sender,$receiver)->split(),
					$this->isolate_truth($tree,$args,$map,$sender,$receiver),
					$this->isolate_if($tree,$args,$map,$sender,$receiver)->split(),
					$this->isolate_then($tree,$args,$map,$sender,$receiver)->split()
				];
				$logic_sets = varbase2($logic_data);
				foreach($logic_sets as $i=>$v)
				{
					// convert indirect to direct references
					$v = array_map(function($ob) use ($sender,$receiver){
						if(!is_object($ob) || get_class($ob) != 'kaddr') return $ob;
						foreach($ob->contents as $i => $v) {
							if($v == 3) {
								$ob->contents[$i] = $sender;
							}
							if($v == 0) {
								$ob->contents[$i] = $receiver;
							}
						}
						return $ob;
					},$v);

					$add = new logic();
					$add->init($v[0],$v[1],$v[2],$v[3],$v[4],$v[5],$v[6]);
					$kaddr = $this->store_logic($add);
					$out->merge($kaddr);
				}
				break;

			case '{': //If
			case '}': //Then
				$out = new kaddr(null);
				foreach($tree as $i=>$v)
					if($i == 0) continue;
				else	if($layout[$i] == "ARG" || $layout[$i] == "ARG1" || $layout[$i] == "ARG2")
						$out->merge($this->evaluate_block($v,$map[$i],$sender,$receiver));
				break;
			case '+':
			case '/':
				$rvals = [];
				foreach($tree as $i=>$v)
				{
					if($i == 0) continue;
					if(lang::is_terminal_flag($v[0]) && lang::is_significant_flag($v[0]))
						$rvals[] = $this->evaluate_terminal($v,$map[$i],$sender,$receiver);
					else if(lang::is_stem_flag($v[0]))
						$rvals[] = $this->evaluate_block($v,$map[$i],$sender,$receiver);
				}
				$out = $rvals[0];
				foreach($rvals as $i=>$v)
					if($i == 0) continue;
					else $out->merge($v);
				break;
			case '>>': //Acts like a base, but it isn't. Ever.

				//Scenario B: Closure Truth TODO maybe pass this stuff down too
				if(in_array("ARG",$layout))
				{
					$index = array_search("ARG",$layout);
					$rval = $this->evaluate_block($tree[$index],$map[$index],$sender,$receiver);
					foreach($rval->contents as $i=>$v)
					{
						$laddr = $this->get($v)->logical;
						$parent_block = $this->get_parent($map['block-id']);
						$parent_key = $parent_block->term[0];
						if($parent_key == 'i')
						{
							$laddr_type = $this->scope->contents[$laddr]->truth->getType(0);
							$operator = $tree[array_search('LOG',$layout)][0];
							$new_laddr_type = scope::invert($laddr_type,$operator);
							$this->scope->contents[$laddr]->truth->setType($new_laddr_type);
							$this->scope->contents[$laddr]->root = 1;
							$this->scope->conform($laddr,$this); //TODO: QA
							//TODO: This applies to a hypothetical logical entry.  Make it the law of the land.
						}
						else	throw new Exception("Error: Unsupported ($parent_key (>> args))");
					}
					$out = $rval;
				}
				else
				//Scenario A: Kernel Compression
				{
					$parent_block = $this->get_parent($map['block-id']);
					$parent_key = $parent_block->term[0];
					if($parent_key == 'i') //TODO:maybe this should be on a lower row, or maybe not
					{
						$x = $tree[array_search("ARG1",$layout)][1];
						$y = $tree[array_search("ARG2",$layout)][1];
						$x_ptrs = lang::isolate_pointers_head($x);
						$y_ptrs = lang::isolate_pointers_head($y);
						$this->merge_into($x_ptrs,$y_ptrs);
						$out = new kaddr(null);
					}
					else	throw new Exception("Error: Unsupported ($parent_key (>> args))");
				}
				break;
			default:
				break;
		}
		return $out;
	}
	function evaluate_base($tree,$map,$sender,$receiver)
	{
		//Basic structure: (cmd arg)

		/* Identifying argument type */
		$key = $tree[0];

		/* Identifying arguments and comments */
		$layout = interpreter::getLayout($tree); //(> (" sup) (. derp)) == array(CMD,ARG,CMT)

		$out = new instr($key);
		$out->owner = $sender;

		foreach($tree as $i=>$v)
			if($v[0] == 'c' && $v[1] == 'force-mirror') $out->meta['force-mirror'] = true;

		/* From here, we must think about return types. */
		switch($key) //i ? > < ! c :|
		{
			//Logical.  Recursive.
			case 'i': //return logic -- to argue with it if need be, and digest it otherwise
			case '?': //return logic -- to answer the question
				$index = array_search("ARG",$layout);
				if($index === false) $index = array_search("LOG",$layout);
				if($index === false) throw new Exception('Error in kernel::evaluate_base()');
				$rval = $this->evaluate_block($tree[$index],$map[$index],$sender,$receiver);
				foreach($rval->contents as $i=>$v)
				{
					$laddr = $this->get($v)->logical;
					if($key == "i")
					{
						$log = $this->scope->contents[$laddr];
						if(	$this->get($log->subj1->contents[0])->flag != '&' &&
							(in_array($log->type,['^','_']) || $this->get($log->act->contents[0])->flag != '&') &&
							$this->get($log->subj2->contents[0])->flag != '&' &&
							(!isset($log->cond) || $this->get($log->cond->contents[0])->flag != '&')
						)
						{
							$this->scope->contents[$laddr]->root = 1;
						}
					}
					$lval = $this->scope->contents[$laddr];
					$out->init_logical($lval);
				}
				break;
			//Conversational.  No complex evaluation required at this point.
			case '>':
			case '<':
			case '!':
			case ':|':
				$ival = new kaddr($map['block-id']);
				$out->init_informal($ival);
				break;
			//is this even still relevant
			//maybe we can use this to fizzbuzz
			case 'c':
				if($c[1] == 'checkin') ; //change user
				return null;
				break;
			default:
				throw new Exception('error -- invalid key');
				break;
		}
		return $out;
	}
}
