<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateCodeGenerator.php";
	 
	class ASTTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function testEmptyAST() {
			$ast = new TierraTemplateAST();
			$this->assertTrue(count($ast->getNodes()) == 0);
		}
		
		public function testASTWithNodes() {
			$nodes = array(
				"foo" => new TierraTemplateASTNode("foo"),
				"bar" => new TierraTemplateASTNode("bar")
			);
			$ast = new TierraTemplateAST($nodes);
			$this->assertTrue($ast->getNodes() == $nodes);
		}
		
		public function testEmptyASTNode() {
			$node = new TierraTemplateASTNode("foo");
			$this->assertTrue($node->type == "foo");
		}
		
		public function testEmptyASTWithAttributes() {
			$attributes = array(
				"foo" => 1,
				"bar" => 2
			);
			$ast = new TierraTemplateAST(array(), $attributes);
			foreach ($attributes as $name => $value)
				$this->assertTrue($ast->$name == $value);
		}

		public function testEmptyASTWithAstNodeAttributes() {
			$attributes = array(
				"foo" => new TierraTemplateASTNode("foo"),
				"bar" => new TierraTemplateASTNode("bar")
			);
			$ast = new TierraTemplateAST(array(), $attributes);
			foreach ($attributes as $name => $value)
				$this->assertTrue($ast->$name == $value);
		}

		public function testASTWithNodesAndAttributes() {
			$nodes = array(
				"foo" => new TierraTemplateASTNode("foo"),
				"bar" => new TierraTemplateASTNode("bar")
			);
			$attributes = array(
				"foo" => 1,
				"bar" => 2
			);
			$ast = new TierraTemplateAST($nodes, $attributes);
			$this->assertTrue($ast->getNodes() == $nodes);
			foreach ($attributes as $name => $value)
				$this->assertTrue($ast->$name == $value);
		}
		
	}
	