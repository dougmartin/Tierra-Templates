<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateRequest.php";
	 
	class RequestTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function testEmpty() {
			$request = new TierraTemplateRequest();
			$this->assertEquals($request->getParams(), array(), "Params are empty on startup");
			$this->assertEquals($request->getVars(), array(), "Vars are empty on startup");
		}
		
		public function testSettings() {
			foreach (array("server", "get", "post", "files", "request", "session", "env", "cookie") as $setting) {
				$request = new TierraTemplateRequest(array($setting => array("foo" => $setting)));
				$this->assertEquals($request->getParam("foo", false, $setting), $setting, "Test settings for $setting");
			}
		}

		public function testSetSetting() {
			$request = new TierraTemplateRequest();
			$this->assertEquals($request->getSetting("foo"), false, "Set setting - foo doesn't exist");
			$request->setSetting("foo", "bar");
			$this->assertEquals($request->getSetting("foo"), "bar", "Set setting - foo set");
			$request->setSetting("foo", "baz");
			$this->assertEquals($request->getSetting("foo"), "baz", "Set setting - foo changed");
		}
		
		public function testAddSetting() {
			$request = new TierraTemplateRequest();
			$this->assertEquals($request->getSetting("foo"), false, "Add setting - foo doesn't exist");
			$request->addSetting("foo", "bar");
			$this->assertEquals($request->getSetting("foo"), "bar", "Add setting - foo added");
			$request->addSetting("foo", "baz");
			$this->assertEquals($request->getSetting("foo"), "bar", "Add setting - foo did not change");
		}
		
		public function testSetParam() {
			foreach (array("server", "get", "post", "files", "request", "session", "env", "cookie") as $setting) {
				$request = new TierraTemplateRequest();
				$request->setParam("foo", "bar", $setting);
				$this->assertEquals($request->getParam("foo", false, $setting), "bar", "Test set param for $setting");
			}			
		}
				
		public function testOverloadedSetVar() {
			$request = new TierraTemplateRequest();
			$request->foo = "bar";
			$this->assertEquals($request->foo, "bar", "Test overloaded set var and overloaded get var");
			$this->assertEquals($request->getVar("foo"), "bar", "Test overloaded set var and getVar()");
		}
		
		public function testSetVar() {
			$request = new TierraTemplateRequest();
			$request->setVar("foo", "bar");
			$this->assertEquals($request->foo, "bar", "Test setVar() and overloaded get var");
			$this->assertEquals($request->getVar("foo"), "bar", "Test setVar() and getVar()");
		}
		
		public function testOverloadedIssetAndUnset() {
			$request = new TierraTemplateRequest();
			$request->foo = "bar";
			$this->assertTrue(isset($request->foo), "Overloaded isset");
			unset($request->foo);
			$this->assertFalse(isset($request->foo), "Overloaded unset");
		}
		
		public function testSetVars() {
			$request = new TierraTemplateRequest();
			$request->setVars(array("foo" => "bar", "baz" => "bam"));
			$this->assertEquals($request->foo, "bar", "Test setVars() for foo");
			$this->assertEquals($request->baz, "bam", "Test setVars() for baz");
		}

	}