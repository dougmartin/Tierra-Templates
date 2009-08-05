<?php

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
			
			$this->assertTrue($optimizedAST == $astAfter, $message); 
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
			$astBefore = TestHelpers::GetParsedAST("[@ extends bar @] foo");
			$astAfter = TestHelpers::MakeAST(array(), array("parentTemplateName" => "bar"));
			$this->assertTrue(TierraTemplateOptimizer::optimize($astBefore) == $astAfter, "Test extends with html");
		}

		public function testHTMLWithExtendsAtEnd() {
			$astBefore = TestHelpers::GetParsedAST("foo [@ extends bar @]");
			$astAfter = TestHelpers::MakeAST(array(), array("parentTemplateName" => "bar"));
			$this->assertTrue(TierraTemplateOptimizer::optimize($astBefore) == $astAfter, "Test html with extends at end");
		}	

		public function testExtendsWithBlockWithHTML() {
			$astBefore = TestHelpers::GetParsedAST("[@ extends bar @] foo [@ start bam @] boom bim [@ end bam @] boom ");
			$astAfter = TestHelpers::MakeAST(array(
												TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => "bam")),
												TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " boom bim ")),
												TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => "bam")),
											), array("parentTemplateName" => "bar"));
			$optimizedAST = TierraTemplateOptimizer::optimize($astBefore);
			$this->assertTrue($optimizedAST == $astAfter, "Test extends with block with html");
		}		
		
	}
	