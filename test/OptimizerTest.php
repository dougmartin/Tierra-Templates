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
	require_once dirname(__FILE__) . "/../src/TierraTemplateOptimizer.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class OptimizerTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkOptimizer($srcBefore, $srcAfter, $message, $dump=false) {
			$astBefore = TestHelpers::GetParsedAST($srcBefore);
			$astAfter = TestHelpers::GetParsedAST($srcAfter);
			
			$optimizedAST = TierraTemplateOptimizer::optimize($astBefore);
			
			if ($dump) {
				echo "\nTest: $message\n";
				echo "before:\n";
				var_dump($astBefore);
				echo "after:\n";
				var_dump($astAfter);
				echo "optimized:\n";
				var_dump($optimizedAST);
			}
			
			$this->assertEquals($optimizedAST, $astAfter, $message); 
		}
		
		public function testEmptyAST() {
			self::checkOptimizer("", "", "Test optimze empty AST");
		}
		
		public function testStripOneCommentNoSpaces() {
			self::checkOptimizer("[# this is a comment #]", "", "Test optimize 1 comment no space AST");
		}		
		
		public function testStripOneCommentWithSpaceBefore() {
			self::checkOptimizer(" [# this is a comment #]", " ", "Test optimize 1 comment with space before AST");
		}		
		
		public function testStripOneCommentWithSpaceAfter() {
			self::checkOptimizer("[# this is a comment #] ", " ", "Test optimize 1 comment with space after AST");
		}		
		
		public function testStripOneCommentWithSpacesAround() {
			self::checkOptimizer(" [# this is a comment #] ", "  ", "Test optimize 1 comment with spaces around comment AST");
		}		
		
		public function testExtendsWithHTML() {
			$astBefore = TestHelpers::GetParsedAST("[@ extends 'bar' @] foo");
			$astAfter = TestHelpers::MakeAST(array(), array("parentTemplateName" => TestHelpers::MakeASTNode(TierraTemplateASTNode::LITERAL_NODE, array("tokenType" => "string", "value" => "bar"))));
			$this->assertEquals(TierraTemplateOptimizer::optimize($astBefore), $astAfter, "Test extends with html");
		}

		public function testHTMLWithExtendsAtEnd() {
			$astBefore = TestHelpers::GetParsedAST("foo [@ extends 'bar' @]");
			$astAfter = TestHelpers::MakeAST(array(), array("parentTemplateName" => TestHelpers::MakeASTNode(TierraTemplateASTNode::LITERAL_NODE, array("tokenType" => "string", "value" => "bar"))));
			$this->assertEquals(TierraTemplateOptimizer::optimize($astBefore), $astAfter, "Test html with extends at end");
		}	

		public function testExtendsWithBlockWithHTML() {
			$astBefore = TestHelpers::GetParsedAST("[@ extends 'bar' @] foo [@ start bam @] boom bim [@ end bam @] boom ");
			$astAfter = TestHelpers::MakeAST(array(
												TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => "bam")),
												TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " boom bim ")),
												TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => "bam")),
											), array("parentTemplateName" => TestHelpers::MakeASTNode(TierraTemplateASTNode::LITERAL_NODE, array("tokenType" => "string", "value" => "bar"))));
			$optimizedAST = TierraTemplateOptimizer::optimize($astBefore);
			$this->assertEquals($optimizedAST, $astAfter, "Test extends with block with html");
		}		
		
	}
	