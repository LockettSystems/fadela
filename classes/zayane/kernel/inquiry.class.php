<?php
/*
 * inquiry.class.php - Functions related to ambiguity / conflict handling
 * Copyright (c) Lockett Analytical Systems <lockettanalytical@gmail.com>
 *
 * This file is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published
 * by the Free Software Foundation; either version 2, or (at your
 * option) any later version.
 *
*/

trait inquiry
{
	////////////////////////////////////////////////////////////////////////
	// Inquiry Functions
	////////////////////////////////////////////////////////////////////////

	function inquire_set($set,$compr = 1)
	{
		// get all logics corresponding to each ambiguous address in set
		$logs = array_map([$this,'inquire_addr'],$set);
		// reduce superset to unique logics
		$logs_unique = [];
		foreach($logs as $i=>$v)
			foreach($v as $j=>$w)
				if(!in_array($j,array_keys($logs_unique)))
					$logs_unique[$j] = $w;

		// keys corresponding to unique list
		$x = array_values($logs_unique);

		//TODO for each k, whether here or in a more relevant spot, verify whether or not this logic has already been addressed with respect to this literal 
		$k = array_keys($x);
		$y = $set; // initial address set being inquired about
		if(!$compr)
		{
			// alter y, filter anything not in first logic 
			$y = remnull(
				array_map( 
					function($log0,$setv,$logv)
					{
						return ($logv==$log0)?$setv:null;
					},
					array_fill(0,count($logs),$logs[0]),
					$set,
					$logs
				)
			);
			// first address, within an array
			$x = [$x[$k[0]]];
		}
		if(!count($x)) return [];
		// create HAS-A logic
		$z = new logic();
		$z->init('^',new kaddr($x),new kaddr(null),new kaddr($y),new truth('='));
		$instr = $this->logic2instr($z);
		// generate output from instr
		$zz = $this->instr_logic_reply($instr,'i','=');
		//TODO this calls for deep layout analysis (for reversal of this type of inquiry)
		//TODO it might be a good idea to incrementally work one's way instead of dumping all this on the poor user
		//TODO why not keep cache disiambiguations universally assigned for the duration of the session until perhaps a new one is introduced or ignore them completely granted one has been referenced initially during the course of this session -- server-side OATS, so to speak for simplicity of fadela's comprehension
		return $zz;
	}

	// attempts to disambiguate and thus filter set
	function disambiguate(&$ambiguities)
	{
		// Iterate set of ambiguous addresses
		foreach($ambiguities as $i=>$v)
		{
			// If addresses have been resolved, reflect this in kernel nodes and remove from master set
			
			$node = $this->get($v);
			$term = $node->get_term();
						$inference = $this->oats->infer($term,null,$prefix);
			if($inference[0] == -1) $inference = [];
			$x;
			if(!count($inference)) $x = $this->scope->disambiguate($v,$this);

			if(count($inference) || $x >= 0)
			{
				if(count($inference)) $x = $inference;
				$this->get($v)->set_pointers(array_merge($this->get($v)->get_pointers(),$x));
				$this->get($v)->ambiguous = 0;
				$this->get($v)->flag = (isset($prefix))?$prefix:null;
				foreach($x as $j=>$w)
				{
					$this->get((int)$w)->add_backtrace($v);
					$this->get((int)$w)->set_backtrace(array_values($this->get((int)$w)->get_backtrace()));
				}
				unset($ambiguities[$i]);
			}
		}

		// Renumber and return by reference.
		$ambiguities = array_values($ambiguities);
	}

	function has_conflict_inquiry()
	{
		return intval(count($this->get_latest_ambiguity()));
	}

	function get_latest_unresolved_ambiguity($returns)
	{
		$ambiguities;
		$cptr;
		do {
			$ambiguities = $this->get_ambiguous_addresses($returns);
			// If there are ambiguities remaining, break the loop.
			if(isset($returns->logic) && count($ambiguities)) break;
			// We assume there are no ambiguous pointers in returns
			// and move to the last index in the cache to check it

			// If cache pointer is set, remove that index from cache
			// Indicate that a deflation has occurred
			// Eventually get rid of deflation and use more change
			//  detection mechanisms
			if(isset($cptr)) $cptr--;
			$keys = array_keys($this->stack);
			// If array keys are empty, break the loop.
			if(isset($cptr) && $cptr < 0) break;
			// Index of last (most recent) address in cache
			if(!isset($cptr)) $cptr = $keys[count($keys)-1];
			// Set returns to cache item at that index
			$returns = object_clone($this->stack[$cptr]);
			// summary: looking for most recent unresolved ambiguity
		} while(1);
		return $ambiguities;
	}

	// given an instr, 
	function generate_ambiguity_inquiry($returns)
	{
		// First, identify ambiguous terms and inquire about them.
		$ambiguities = $this->get_latest_unresolved_ambiguity($returns);

		// If none, return nothing.
		if(!count($ambiguities)) return [];

		// generate new item to potentially go to the stack . . . why?
		$cached = object_clone($returns);
		$cached->type = 'i';
		// track number of ambiguous addresses to identify deflation
		$cached->meta['ambigs'] = count($ambiguities);
		// We need to eliminate redundancy.
		// Here's a first-gen safeguard against it.
		$match = 0;
		// iterate stack from the top
		foreach(array_reverse($this->stack,True) as $i=>$v)
		{
			// if it's not logical, skip it -- interjection support
			if(!count($v->logic)) continue;
			// compare logics -- are they equivalent, scopewise? TODO kernel leak in said function
			$match = $v->logic[0]->matches($cached->logic[0]);
			if($match == 1)
			{
				$diff = 1; // estat fluctuation coefficient
				// if deflation, fluctuate
				if(isset($cached->meta['ambigs']) && isset($cached->meta['ambigs']) && $cached->meta['ambigs'] < $v->meta['ambigs'])
					$this->inc_status('D',$diff);
				// replace stack item
				$this->stack[$i] = $cached;
			}
		}
		// if there is no equivalent item on the stack, add it -- TODO potentially obsolete
		if(!$match) $this->stack[] = $cached;
		// generate inquiry based on list of ambiguous addresses
		$inq_set = $this->inquire_set($ambiguities);
		$adds = (!count($inq_set))?[]:['?',$inq_set];
		return $adds;
	}

	// returns ambiguous addresses, filtered after attempting disambiguation
	function get_ambiguous_addresses($returns)
	{
		// Generate set of ambiguous items from returns
		$ambiguities = $this->iso_ambig($returns->logic);
		// Remove disambiguated ambiguous items
		$this->disambiguate($ambiguities);
		return $ambiguities;
	}

	// if scope has logical conflict, generates and returns inquiry message
	function generate_conflict_inquiry()
	{
		$out = [];
		
		if(count($this->scope->conflicts)) {
		
			// Identify first conflict in the scope
			$x = $this->scope->conflicts[0];
			
			// Build parse tree of first conflicting logic
			$y1 = $this->build_from_logic($x[0],$x[0]->truth->getType(0));
			
			// Build parse tree of second conflicting logic
			$y2 = $this->build_from_logic($x[1],$x[1]->truth->getType(0));
			
			// Fit both parse trees into an OR-block
			$z = $this->or_fit([$y1,$y2]);
			
			// Fit the OR-block into a logical base
			$out = $this->logical_base_fit($z,'?');
			
			// Clear conflict queue until redundancy safeguards are in place
			$this->scope->conflicts = [];
			
			// If no good structural fit, return in simple FZPL
			if($out === 0) $out = ['?',$z];
			
			// Otherwise, return the final fitted product
		}
		
		return $out;
	}

	// given a logic object, returns set of all ambiguous addresses
	function iso_ambig($logic)
	{
		// Master set of ambiguous addresses in logic set.
		$out = [];

		// Iterate logic set.
		foreach($logic as $i=>$v)
		{
			// Assemble a set of all addresses in the logic object.
			$set = array_merge (
				$v->subj1_addr(),
				$v->act_addr(),
				$v->subj2_addr()
			);

			// Isolate unique addresses of that set.
			$set = array_unique($set);

			// Isolate ambiguous addresses of that subset.
			foreach($set as $j=>$w)
				if($this->get($w)->ambiguous) continue;
			else	unset($set[$j]);
			$set = array_values($set);
			if(!count($set)) continue;

			// Merge set into master collection.
			$out = array_merge($out, $set);
			//TODO: account for IF/THEN.  Making this a recursive function would be very wise.
			//TODO: process for delaying a response instruction a few rounds while waiting on an answer.
			//TODO: patience vs just answering as is.
			//there are a lot of different factors that could influence a response over several exchanges.
		}

		// Return.
		return $out;
	}

	// find register addresses corresponding to a set of logical addresses
	function getlogicalnodesbyset($keys)
	{
		$out = []; //TODO filtering based on past inquiries
		foreach($this->contents as $i=>$v)
		{
			foreach($keys as $j=>$w)
				if($v->logical == $w)
				{
					$out[$w] = $i;
					break;
				}
			if(count($out) == count($keys)) break;
		}
		return $out;
	}

	// Given an address, identify the kernel contents of logic objects
	// that reference kernel nodes with the literal of that address
	function inquire_addr($addr)
	{
		// basic terminology for given registry address
		$literal = $this->get($addr)->term;
		// addresses of all concepts with similar terms/names
		$opts = array_keys($this->getnodesbyname($literal,[$addr],3,0));
		// all logical items containing aforementioned addresses
		$logs = $this->scope->getnodesbyidset($opts);
		// addresses of aforementioned logical items
		$keys = array_keys($logs);
		// register addresses corresponding to logical addresses
		$kaddrs = $this->getlogicalnodesbyset($keys);
		return $kaddrs;
	}
}
?>
