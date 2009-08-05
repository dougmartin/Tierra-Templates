<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateCodeGen.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class CodeGenTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function testEmptyAST() {
			$ast = TestHelpers::MakeAST();
			$generator = new TierraTemplateCodeGen($ast);
			$this->assertTrue($generator->emit() == "", "Test generate empty AST"); 
		}		
		
	}
	