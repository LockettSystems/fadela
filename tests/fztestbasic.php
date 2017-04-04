<?php
if(!class_exists('phpunit_framework_testcase')) {

	class phpunit_framework_testcase {
		static $assertions = 0;
		static $failures = 0;
		static $tests = 0;
		function assertTrue($x,$m = '') {
			self::$assertions++;
			if($x === false) {
				self::$failures++;
				throw new Exception($m);
			}
		}
		function assertFalse($x,$m = '') {
			$this->assertTrue(!$x,$m);
		}
	}
}

class FZTestBasic extends phpunit_framework_testcase {
	function testSmoke() {
		emit(__FUNCTION__);
		$this->smoke();
	}
	function smoke() {
		$this->assertTrue(true);
	}
	function testReadTest() {
		emit(__FUNCTION__);
		$this->readTest();
	}
	function readTest($test = '000') {
		$file = dirname(__FILE__)."/$test.txt";
		$data = file_get_contents($file);
		$this->assertFalse($data === false);
		return $data;
	}
	function testProcess() {
		emit(__FUNCTION__);
		$this->process();
	}
	function process(&$k = null, $data = null) {
		if($k === null) {
			$k = $this->init();
			if($data == null) {
				$data = '(:| `test)';
			}
		} else {
			$this->assertTrue(is_object($k));
		}
		$res = $k->process($data,$tree);
		if(!empty($data)) {
			$this->assertTrue(!empty($res));
			$this->assertTrue(!empty($tree));
		} else {
			$this->assertTrue(empty($res));
			$this->assertTrue(empty($tree));
		}
		return array('result'=>$res,'tree'=>$tree);
	}
	function testRunTest() {
		emit(__FUNCTION__);
		$this->runTest();
	}
	function runTest($test = '000') {
		$data = $this->readTest($test);
		$k = $this->init();
		$this->process($k,$data);
	}

	function testFiles() {
		emit(__FUNCTION__);
		$this->files();
	}
	function files() {
		for($i = 0; $i <= 24; $i++) {
			$test = sprintf('%03d',$i);
			emit("Test $test");
			$this->runTest($test);
		}
	}
	function testScript() {
		emit(__FUNCTION__);
		$this->script();
	}
	function script(&$k = null,$file = "script.txt") {
		$filename = dirname(dirname(__FILE__))."/$file";
		$data = file_get_contents($filename);
		$res = $this->process($k,$data);
		return $res;
	}
	function testInit() {
		emit(__FUNCTION__);
		$this->init();
	}
	function init() {
		kernel::$instances = [];
		$this->smoke();
		$k = kernel::initialize();
		$this->assertTrue(get_class($k) == "kernel");
		return $k;
	}
}
?>
