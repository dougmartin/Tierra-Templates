<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateOptimizer.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class OptimizerTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function testEmptyAST() {
			$ast = TestHelpers::MakeAST();
			$optimizer = new TierraTemplateOptimizer($ast);
			$this->assertTrue($optimizer->optimize() == $ast, "Test optimze empty AST"); 
		}
		
	}
	