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
		
		public function testspecialIdentifiers() {
			$frame = new TierraTemplateStackFrame(array("foo", "bar", "baz"));
			$this->assertEquals($frame->specialIdentifier("count"), 3);
			$this->assertEquals($frame->specialIdentifier("this is a garbage value"), false);
			
			// foo
			$this->assertEquals($frame->specialIdentifier(""), "foo");
			$this->assertEquals($frame->specialIdentifier("0"), 0);
			$this->assertEquals($frame->specialIdentifier("1"), 1);
			$this->assertEquals($frame->specialIdentifier("key"), 0);
			$this->assertTrue($frame->specialIdentifier("first"));
			$this->assertFalse($frame->specialIdentifier("last"));
			$this->assertTrue($frame->specialIdentifier("even"));
			$this->assertFalse($frame->specialIdentifier("odd"));
			
			// bar
			$frame->loop();
			$this->assertEquals($frame->specialIdentifier(""), "bar");
			$this->assertEquals($frame->specialIdentifier("0"), 1);
			$this->assertEquals($frame->specialIdentifier("1"), 2);
			$this->assertEquals($frame->specialIdentifier("key"), 1);
			$this->assertFalse($frame->specialIdentifier("first"));
			$this->assertFalse($frame->specialIdentifier("last"));
			$this->assertFalse($frame->specialIdentifier("even"));
			$this->assertTrue($frame->specialIdentifier("odd"));

			// baz
			$frame->loop();
			$this->assertEquals($frame->specialIdentifier(""), "baz");
			$this->assertEquals($frame->specialIdentifier("0"), 2);
			$this->assertEquals($frame->specialIdentifier("1"), 3);
			$this->assertEquals($frame->specialIdentifier("key"), 2);
			$this->assertFalse($frame->specialIdentifier("first"));
			$this->assertTrue($frame->specialIdentifier("last"));
			$this->assertTrue($frame->specialIdentifier("even"));
			$this->assertFalse($frame->specialIdentifier("odd"));
		}
		
	}
	