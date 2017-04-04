<?php
require dirname(dirname(__FILE__)).'/commands.php';
require dirname(dirname(__FILE__)).'/classes/index.php';

require dirname(__FILE__).'/fztestbasic.php';

class FZTest extends FZTestBasic  {
	function testMirror() {
		emit(__FUNCTION__);

		emit("Verifying static mirrorize utilized in kernel_node::getName");

		$uS = kernel::mirrorize(3,1);
		$uO = kernel::mirrorize(0,1);
		$sS = kernel::mirrorize(1,1);
		$sO = kernel::mirrorize(1,2);

		$this->assertTrue($uS == 0);
		$this->assertTrue($uO == 3);
		$this->assertTrue($sS == 0);
		$this->assertTrue($sO == 3);
		
		emit("Processing initialization smoke query");

		$k = $this->init();
		$r = $k->process('(:| `#3|michael .%3|me .%0|you .#0|fadela)',$t);

		emit("Retrieving pronouns");

		$usrSelf = kernel_node::getName('%',3,1,2);
		$usrOther = kernel_node::getName('%',0,1,2);
		$sysSelf = kernel_node::getName('%',3,2,1);
		$sysOther = kernel_node::getName('%',0,2,1);

		emit("Verifying pronouns");

		$this->assertTrue($usrSelf == "me", "Expected 'me', got '$usrSelf'");
		$this->assertTrue($usrOther == "you", "Expected 'you', got '$usrOther'");
		$this->assertTrue($sysSelf == "me", "Expected 'me', got '$sysSelf'");
		$this->assertTrue($sysOther == "you", "Expected 'you', got '$sysOther'");

		emit("Retrieving names");

		$usrSelf = kernel_node::getName('#',3,1,2);
		$usrOther = kernel_node::getName('#',0,1,2);
		$sysSelf = kernel_node::getName('#',3,2,1);
		$sysOther = kernel_node::getName('#',0,2,1);

		emit("Verifying names");

		$this->assertTrue($usrSelf == "michael", "Expected 'michael', got '$usrSelf'");
		$this->assertTrue($usrOther == "fadela", "Expected 'fadela', got '$usrOther'");
		$this->assertTrue($sysSelf == "fadela", "Expected 'fadela', got '$sysSelf'");
		$this->assertTrue($sysOther == "michael", "Expected 'michael', got '$sysOther'");

		emit("Verifying smoke query output");

		$out_self = $r[0][0][1][1];
		$this->assertTrue($out_self == '#3|fadela', "Expected #3|fadela, got ".$out_self."");
	}

	function testSmartMirror() {
		emit(__FUNCTION__);
		emit("coming soon...");
	}

	function testPronoun() {
		emit(__FUNCTION__);
		$k = $this->init();
		$r = $k->process('(:| `hi (c force-mirror))',$t);
		$r = $k->process('(:| `#0|fadela .#3|michael)',$t);
		$nameLit = $r[0][0][1][1];
		$this->assertTrue($nameLit == '#0|michael','Name literal "#0|michael" expected, got "'.$nameLit.'"');
		$r = $k->process('(:| `hi)',$t);
	}

	function testLogicStatement() {
		emit(__FUNCTION__);

		$k = $this->init();
		$r = $k->process('(i _ `$#0|x .is "$#1|y)',$tree1);
		$this->assertTrue($r[0][0][0] == ':|');
		$r = $k->process('(? _ `@#0|x .is "@#1|y)',$tree2);
		$this->assertTrue($r[0][0][0] == 'i');
	}

	function testBaseNod() {
		emit(__FUNCTION__);

		emit("Initializing...");
		$k = $this->init();

		emit("<usr> x is y.");
		$r0 = $this->process($k,'(i _ `$#0|x "$#1|y =true)');

		emit("<sys> ...");
		$this->assertTrue(
			lang_tree::getBaseFlag($r0['result']) == ':|',
			"Verify ':|' base flag\n".print_r($r0,true)
		);

		emit("<usr> is x y?");
		$r1 = $this->process($k,'(? _ `@#0|x "@#1|y)');

		emit("<sys> x is y.");
		$this->assertTrue(
			lang_tree::getBaseFlag($r1['result']) == 'i',
			"Verify 'i' base flag\n".print_r($r1,true)
		);

		emit("<usr> nah.");
		$r2 = $this->process($k,'(- nah)');

		emit("<sys> is x y or is not x y?");

		$scope = $k->scope;
		$total = 0;
		$roots = 0;
		foreach($k->scope->contents as $log) {
			if($log->root == 1 && $log->truth->getType() == '=') $total++;
			if($log->root == 1 && $log->truth->getType() == '-') $total--;
		}
		$this->assertTrue($total == 0,"Verify ambiguous logic");
		$this->assertTrue(
			lang_tree::getBaseFlag($r2['result']) == '?',
			"Verify '?' base flag\n".print_r($r2,true)
		);
		$this->assertTrue($r2['result'][0][0][1][0] == '/');

		// <usr> yah.

		$r3 = $this->process($k,'(= yah)');

		// <sys> [interjection]

		$this->assertTrue(
			lang_tree::getBaseFlag($r3['result']) == ':|',
			"Verify ':|' root flag\n".print_r($r3,true)
		);

		// <usr> x is not y.

		$r4 = $this->process($k,'(i _ `@#0|x "@#1|y -false)');

		// <sys> ...

		$this->assertTrue(
			lang_tree::getBaseFlag($r4['result']) == ':|',
			"Verify ':|' base flag\n".print_r($r4,true)
		);

		// <usr> is x y?
		
		$r5 = $this->process($k,'(? _ `@#0|x "@#1|y)');
		$this->assertTrue(
			lang_tree::getBaseFlag($r5['result']) == 'i',
			"Verify '?' base flag\n".print_r($r5,true)
		);
		$this->assertTrue($r5['result'][0][0][1][3][0] == '-',print_r($r5['result'],true));
	}
	function testPartScript0() {
return;
		emit(__FUNCTION__);
		$this->testScriptOutput("script_part0.txt");
	}

	function testScriptOutput($file = "script.txt") {
	
		emit(__FUNCTION__);
		$k = $this->init();
		$empty = null;
		$res = $this->script($empty,$file);
		foreach($res['tree'] as $i => $tree) {
#			if($i < 17) continue;
			$result = $res['result'][0][$i];
			
			unset($result[count($result)-1]);
			echo "########$i########\n\n<usr> ".interpreter::naturalize($tree,true)."\n\n<sys> ".interpreter::naturalize($result,true)."\n\n";

			if($i == 17) {
				kernel::get_global($kk);
				foreach($kk->get_contents() as $n) {
					if(in_array(0,$n->get_pointers())) {
						#print_r($n);
					}
				}
				
				$this->assertTrue($result[2][1] == '#0|michael', "Name literal should be '#0|michael'.  Got '{$result[2][1]}' instead.\n",1);
			}
		}
		kernel::get_global($kk);
		die();
	}
	function testBlockNod() {
		emit(__FUNCTION__);
		$k = $this->init();
		$r0 = $this->process($k,'(i _ `$#0|x "$#1|y -false)');
		$this->assertTrue(lang_tree::getBaseFlag($r0['result']) == ':|');
		$r1 = $this->process($k,'(? _ `@#0|x "@#1|y)');
		$this->assertTrue(lang_tree::getBaseFlag($r1['result']) == 'i');
		$r2 = $this->process($k,'(? (= come again))');
		$this->assertTrue(lang_tree::getBaseFlag($r2['result']) == 'i');
	}
}

if(!class_exists('phpunit_framework_testcase') || !empty($argv)) {
	$t = new FZTest();
	$f = get_class_methods('FZTest');
	foreach($f as $v) {
		try {
			phpunit_framework_testcase::$tests++;
			$t->$v();
		} catch (Exception $e) {
			$msg = "Assertion failed";
			if(strlen($e->getMessage())) {
				$msg .= ": '{$e->getMessage()}'";
			}
			$trace = $e->getTrace();
			$trace = $trace[count($trace)-2];
			$msg .= " in {$trace['file']} line {$trace['line']}";
			emit($msg);
			break;
		}
	}
	#echo "Tests: \nAssertions: \nFailed: \n";
} else {
}
?>
