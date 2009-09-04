<?php
	/*
	 * Tierra Templates - %VERSION%
	 * 
	 * http://tierratemplates.com/
	 *
	 * Copyright (c) 2009 Tierra Innovation (http://tierra-innovation.com)
	 * 
 	 * This project is available for use in all personal or commercial projects under both MIT and GPL2 licenses. 
 	 * This means that you can choose the license that best suits your project, and use it accordingly.
	 * 
	 * MIT License: http://www.tierra-innovation.com/license/MIT-LICENSE.txt
	 * GPL2 License: http://www.tierra-innovation.com/license/GPL-LICENSE.txt
	 * 
	 */

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateRuntime.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateRequest.php";
	 
	class RuntimeTest extends PHPUnit_Framework_TestCase {
		
		protected $request;
		protected $runtime;
		
		protected function setUp() {
			$this->request = new TierraTemplateRequest();
			$this->runtime = new TierraTemplateRuntime($this->request, array());
		}
		
		protected function tearDown() {
		}
		
		public function testAttr() {
			$this->assertEquals($this->runtime->attr(array("foo" => "bar", "bam" => "boom"), "foo"), "bar");
		}
		
		public function testLimit() {
			$value = array("foo", "bar", "bam", "boom");
			$this->assertEquals($this->runtime->limit($value, 0), array("foo", "bar", "bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, 1), array("bar", "bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, 2), array("bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, 3), array("boom"));
			$this->assertEquals($this->runtime->limit($value, 4), array());
			
			$this->assertEquals($this->runtime->limit($value, -1), array("boom"));
			$this->assertEquals($this->runtime->limit($value, -2), array("bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, -3), array("bar", "bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, -4), array("foo", "bar", "bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, -5), array("foo", "bar", "bam", "boom"));
			
			$this->assertEquals($this->runtime->limit($value, 0, 1), array("foo"));
			$this->assertEquals($this->runtime->limit($value, 1, 1), array("bar"));
			$this->assertEquals($this->runtime->limit($value, 2, 1), array("bam"));
			$this->assertEquals($this->runtime->limit($value, 3, 1), array("boom"));
			$this->assertEquals($this->runtime->limit($value, 4, 1), array());
			
			$this->assertEquals($this->runtime->limit($value, 0, 2), array("foo", "bar"));
			$this->assertEquals($this->runtime->limit($value, 1, 2), array("bar", "bam"));
			$this->assertEquals($this->runtime->limit($value, 2, 2), array("bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, 3, 2), array("boom"));
			$this->assertEquals($this->runtime->limit($value, 4, 2), array());
			
			$this->assertEquals($this->runtime->limit($value, -1, 1), array("boom"));
			$this->assertEquals($this->runtime->limit($value, -2, 1), array("bam"));
			$this->assertEquals($this->runtime->limit($value, -3, 1), array("bar"));
			$this->assertEquals($this->runtime->limit($value, -4, 1), array("foo"));
			$this->assertEquals($this->runtime->limit($value, -5, 1), array("foo"));
			
			$this->assertEquals($this->runtime->limit($value, -1, 2), array("boom"));
			$this->assertEquals($this->runtime->limit($value, -2, 2), array("bam", "boom"));
			$this->assertEquals($this->runtime->limit($value, -3, 2), array("bar", "bam"));
			$this->assertEquals($this->runtime->limit($value, -4, 2), array("foo", "bar"));
			$this->assertEquals($this->runtime->limit($value, -5, 2), array("foo", "bar"));
		}
		
		public function testMissingIdentifier() {
			$this->assertFalse($this->runtime->identifier("foo"));
		}
		
		public function testIdentifier() {
			$this->runtime->startConditerator(array(array("foo" => "bar", "bam" => "boom")));
			$this->assertEquals($this->runtime->identifier("foo"), "bar");
			$this->assertEquals($this->runtime->identifier("bam"), "boom");
		}

		public function testAssign() {
			$this->request->setVar("foo", "bar");
			$this->assertEquals($this->request->foo, "bar");
			$this->assertEquals($this->request->baz, false);
		}
		
		public function testSpecialValues() {
			$this->runtime->startConditerator(array("foo", "bar", "baz"));
			$this->assertEquals($this->runtime->specialIdentifier("count"), 3);
			$this->assertEquals($this->runtime->specialIdentifier("this is a garbage value"), false);
			
			// foo
			$this->assertEquals($this->runtime->specialIdentifier(""), "foo");
			$this->assertEquals($this->runtime->specialIdentifier("0"), 0);
			$this->assertEquals($this->runtime->specialIdentifier("1"), 1);
			$this->assertEquals($this->runtime->specialIdentifier("key"), 0);
			$this->assertTrue($this->runtime->specialIdentifier("first"));
			$this->assertFalse($this->runtime->specialIdentifier("last"));
			$this->assertTrue($this->runtime->specialIdentifier("even"));
			$this->assertFalse($this->runtime->specialIdentifier("odd"));
			
			// bar
			$this->runtime->loop();
			$this->assertEquals($this->runtime->specialIdentifier(""), "bar");
			$this->assertEquals($this->runtime->specialIdentifier("0"), 1);
			$this->assertEquals($this->runtime->specialIdentifier("1"), 2);
			$this->assertEquals($this->runtime->specialIdentifier("key"), 1);
			$this->assertFalse($this->runtime->specialIdentifier("first"));
			$this->assertFalse($this->runtime->specialIdentifier("last"));
			$this->assertFalse($this->runtime->specialIdentifier("even"));
			$this->assertTrue($this->runtime->specialIdentifier("odd"));

			// baz
			$this->runtime->loop();
			$this->assertEquals($this->runtime->specialIdentifier(""), "baz");
			$this->assertEquals($this->runtime->specialIdentifier("0"), 2);
			$this->assertEquals($this->runtime->specialIdentifier("1"), 3);
			$this->assertEquals($this->runtime->specialIdentifier("key"), 2);
			$this->assertFalse($this->runtime->specialIdentifier("first"));
			$this->assertTrue($this->runtime->specialIdentifier("last"));
			$this->assertTrue($this->runtime->specialIdentifier("even"));
			$this->assertFalse($this->runtime->specialIdentifier("odd"));			
		}
		
		public function testStartEmptyConditerators() {
			$this->assertFalse($this->runtime->startConditerator(array()));
			$this->assertFalse($this->runtime->startConditerator(""));
			$this->assertFalse($this->runtime->startConditerator(0));
			$this->assertFalse($this->runtime->startConditerator(false));
		}
		
		public function testStartNonEmptyConditerators() {
			$this->assertTrue($this->runtime->startConditerator(array("foo")));
			$this->assertTrue($this->runtime->startConditerator("foo"));
			$this->assertTrue($this->runtime->startConditerator(1));
			$this->assertTrue($this->runtime->startConditerator(true));
		}

		public function testMultipleConditerators() {
			$this->assertFalse($this->runtime->identifier("foo"));
			$this->assertFalse($this->runtime->identifier("bam"));
			$this->runtime->startConditerator(array(array("foo" => "bar", "bam" => "boom")));
				$this->assertEquals($this->runtime->identifier("foo"), "bar");
				$this->assertEquals($this->runtime->identifier("bam"), "boom");
				$this->runtime->startConditerator(array(array("foo" => "bar2")));
					$this->assertEquals($this->runtime->identifier("foo"), "bar2");
					$this->assertEquals($this->runtime->identifier("bam"), "boom");
					$this->runtime->startConditerator(array(array("bam" => "boom2")));
						$this->assertEquals($this->runtime->identifier("foo"), "bar2");
						$this->assertEquals($this->runtime->identifier("bam"), "boom2");
					$this->runtime->endConditerator();
					$this->assertEquals($this->runtime->identifier("foo"), "bar2");
					$this->assertEquals($this->runtime->identifier("bam"), "boom");
				$this->runtime->endConditerator();
				$this->assertEquals($this->runtime->identifier("foo"), "bar");
				$this->assertEquals($this->runtime->identifier("bam"), "boom");
			$this->runtime->endConditerator();
			$this->assertFalse($this->runtime->identifier("foo"));
			$this->assertFalse($this->runtime->identifier("bam"));
		}
		
		public function testAssignWithConditeratorStack() {
			$this->request->setVar("foo", "bar");
			$this->assertEquals($this->request->foo, "bar");
			$this->assertEquals($this->runtime->identifier("foo"), "bar");
			
			$this->runtime->startConditerator(array(array("foo" => "baz2", "bam" => "boom")));
				$this->assertEquals($this->request->foo, "bar");
				$this->assertEquals($this->runtime->identifier("foo"), "baz2");
				
				$this->runtime->startConditerator(array(array("foo" => "baz3")));
					$this->assertEquals($this->request->foo, "bar");
					$this->assertEquals($this->runtime->identifier("foo"), "baz3");
				
					$this->assertEquals($this->runtime->identifier("bam"), "boom");
					$this->request->setVar("bam", "boomboom");
					$this->assertEquals($this->request->bam, "boomboom");
					$this->assertEquals($this->runtime->identifier("bam"), "boom");
				$this->runtime->endConditerator();
				
				$this->assertEquals($this->request->foo, "bar");
				$this->assertEquals($this->runtime->identifier("foo"), "baz2");
				$this->assertEquals($this->request->bam, "boomboom");
				$this->assertEquals($this->runtime->identifier("bam"), "boom");
				
			$this->runtime->endConditerator();
			
			$this->assertEquals($this->request->foo, "bar");
			$this->assertEquals($this->runtime->identifier("foo"), "bar");
			$this->assertEquals($this->request->bam, "boomboom");
			$this->assertEquals($this->runtime->identifier("bam"), "boomboom");
		}
	}		
		