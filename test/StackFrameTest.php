<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateStackFrame.php";
	 
	class StackFrameTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function testSingleVariable() {
			$frame = new TierraTemplateStackFrame("foo");
			$this->assertEquals($frame->currentValue(), "foo");
		}
		
		public function testArray() {
			$frame = new TierraTemplateStackFrame(array("foo", "bar", "baz"));
			$this->assertEquals($frame->currentValue(), "foo");
			$frame->loop();
			$this->assertEquals($frame->currentValue(), "bar");
			$frame->loop();
			$this->assertEquals($frame->currentValue(), "baz");
		}
		
		public function testArrayIdentifier() {
			$frame = new TierraTemplateStackFrame(array(array("foo" => "bar", "baz" => "boom")));
			$this->assertEquals($frame->identifier("foo"), "bar");
			$this->assertEquals($frame->identifier("baz"), "boom");
			
			$this->assertTrue($frame->hasIdentifier("foo"));
			$this->assertTrue($frame->hasIdentifier("baz"));
			$this->assertFalse($frame->hasIdentifier("bar"));
			$this->assertFalse($frame->hasIdentifier("boom"));
		}
		
		public function testSpecialValues() {
			$frame = new TierraTemplateStackFrame(array("foo", "bar", "baz"));
			$this->assertEquals($frame->specialValue("count"), 3);
			$this->assertEquals($frame->specialValue("this is a garbage value"), false);
			
			// foo
			$this->assertEquals($frame->specialValue(""), "foo");
			$this->assertEquals($frame->specialValue("0"), 0);
			$this->assertEquals($frame->specialValue("1"), 1);
			$this->assertEquals($frame->specialValue("key"), 0);
			$this->assertTrue($frame->specialValue("first"));
			$this->assertFalse($frame->specialValue("last"));
			$this->assertTrue($frame->specialValue("even"));
			$this->assertFalse($frame->specialValue("odd"));
			
			// bar
			$frame->loop();
			$this->assertEquals($frame->specialValue(""), "bar");
			$this->assertEquals($frame->specialValue("0"), 1);
			$this->assertEquals($frame->specialValue("1"), 2);
			$this->assertEquals($frame->specialValue("key"), 1);
			$this->assertFalse($frame->specialValue("first"));
			$this->assertFalse($frame->specialValue("last"));
			$this->assertFalse($frame->specialValue("even"));
			$this->assertTrue($frame->specialValue("odd"));

			// baz
			$frame->loop();
			$this->assertEquals($frame->specialValue(""), "baz");
			$this->assertEquals($frame->specialValue("0"), 2);
			$this->assertEquals($frame->specialValue("1"), 3);
			$this->assertEquals($frame->specialValue("key"), 2);
			$this->assertFalse($frame->specialValue("first"));
			$this->assertTrue($frame->specialValue("last"));
			$this->assertTrue($frame->specialValue("even"));
			$this->assertFalse($frame->specialValue("odd"));
		}
		
	}
	