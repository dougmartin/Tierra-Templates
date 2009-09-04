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
	require_once dirname(__FILE__) . "/../src/TierraTemplateCodeGenerator.php";
	 
	class ASTTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function testEmptyAST() {
			$ast = new TierraTemplateAST();
			$this->assertEquals(count($ast->getNodes()), 0);
		}
		
		public function testASTWithNodes() {
			$nodes = array(
				"foo" => new TierraTemplateASTNode("foo"),
				"bar" => new TierraTemplateASTNode("bar")
			);
			$ast = new TierraTemplateAST($nodes);
			$this->assertEquals($ast->getNodes(), $nodes);
		}
		
		public function testEmptyASTNode() {
			$node = new TierraTemplateASTNode("foo");
			$this->assertEquals($node->type, "foo");
		}
		
		public function testEmptyASTWithAttributes() {
			$attributes = array(
				"foo" => 1,
				"bar" => 2
			);
			$ast = new TierraTemplateAST(array(), $attributes);
			foreach ($attributes as $name => $value)
				$this->assertEquals($ast->$name, $value);
		}

		public function testEmptyASTWithAstNodeAttributes() {
			$attributes = array(
				"foo" => new TierraTemplateASTNode("foo"),
				"bar" => new TierraTemplateASTNode("bar")
			);
			$ast = new TierraTemplateAST(array(), $attributes);
			foreach ($attributes as $name => $value)
				$this->assertEquals($ast->$name, $value);
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
			$this->assertEquals($ast->getNodes(), $nodes);
			foreach ($attributes as $name => $value)
				$this->assertEquals($ast->$name, $value);
		}
		
	}
	