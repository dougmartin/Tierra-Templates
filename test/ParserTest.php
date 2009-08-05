<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateParser.php";
	require_once dirname(__FILE__) . "/TestHelpers.php";
	 
	class ParserTest extends PHPUnit_Framework_TestCase {
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkSyntax($src, $message) {
			try {
				$isValid = true;
				$parser = new TierraTemplateParser($src);
				$parser->parse();
			} 
			catch (TierraTemplateParserException $e) {
				$isValid = false;
			}
			$this->assertTrue($isValid, $message);
		}
		
		public function checkAST($src, $ast, $message, $dump = false) {
			try {
				$parser = new TierraTemplateParser($src);
				$parser->parse();
			} 
			catch (TierraTemplateParserException $e) {}
			if ($dump) {
				var_dump($parser->getAST());
				var_dump($ast);
			}
			$this->assertTrue($parser->getAST() == $ast, $message);
		}
		
		public function checkBlockCommand($command, $testName, $blockName=false, $isString=false, $dump=false) {
			$srcBlockName = $blockName !== false ? ($isString ? "'" . $blockName . "'" : $blockName) : "";
			$src = "[@ {$command} {$srcBlockName} @]";
			self::checkSyntax($src, "{$testName} is valid syntax");
			if ($command == "extends") {
				$ast = TestHelpers::MakeAST(array(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => ""))), array("parentTemplateName" => $blockName));
			}
			else {
				if ($command == "include")
					$nodeAttributes = array("command" => $command, "templateName" => $blockName);
				else
					$nodeAttributes = array("command" => $command, "blockName" => $blockName);
				$ast = TestHelpers::MakeAST(array(
										TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
										TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, $nodeAttributes)
									));
			}
			self::checkAST($src, $ast, "{$testName} has correct AST", $dump);
		}
		
		public function testEmpty() {
			self::checkSyntax("", "Empty string is valid syntax");
			$ast = new TierraTemplateAST();
			self::checkAST("", $ast, "Empty string is correct AST");
		}
	
		public function testSingleSpace() {
			$src = " ";
			self::checkSyntax($src, "Single space is valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Single space is correct AST");
		}
			
		public function testMultipleSpaces() {
			$src = "     ";
			self::checkSyntax($src, "Multiple spaces are valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple spaces has correct AST");
		}	
		
		public function testSingleTab() {
			$src = "	";
			self::checkSyntax($src, "Single tab is valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Single tab has correct AST");
		}
			
		public function testMultipleTabs() {
			$src = "			";
			self::checkSyntax($src, "Multiple tabs are valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple tabs has correct AST");
		}
			
		public function testMultipleSpacesAndTabs() {
			$src = "  		  		  ";
			self::checkSyntax($src, "Multiple spaces and tabs are valid syntax");
			$ast = TestHelpers::MakeAST(TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple spaces and tabs has correct AST");
		}	
		
		public function testCommentOnly() {
			$comment = " this is a comment ";
			$src = "[#{$comment}#]";
			self::checkSyntax($src, "Comment only is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment))
								));
			self::checkAST($src, $ast, "Comment only has correct AST");
		}		
		
		public function testCommentWithSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#] ";
			self::checkSyntax($src, "Comment with spaces is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Comment with spaces has correct AST");
		}

		public function testAdjoiningCommentWithSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#][#{$comment}#] ";
			self::checkSyntax($src, "Adjoining comments with spaces is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Adjoining comments with spaces has correct AST");
		}

		public function testAdjoiningCommentWithInteriorSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#]  [#{$comment}#] ";
			self::checkSyntax($src, "Adjoining comments with interior spaces is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "  ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("start" => "[#", "end" => "#]", "comment" => $comment)),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Adjoining comments with interior spaces has correct AST");
		}			
		
		public function testUnnamedBlockOnly() {
			foreach (array("start", "else", "end") as $command)
				self::checkBlockCommand($command, "Unnamed {$command} block");
		}		
				
		public function testUnnamedBlockOnlyForException() {
			$this->setExpectedException('TierraTemplateTokenizerException');
			foreach (array("extends", "include", "prepend", "append", "replace") as $command)
				self::checkBlockCommand($command, "Unnamed {$command} block");
		}
				
		public function testNamedBlockOnly() {
			foreach (array("start", "else", "end", "extends", "include", "prepend", "append", "replace") as $command)
				self::checkBlockCommand($command, "Named {$command} block", "foo");
		}		
				
		public function testNamedStringBlockOnly() {
			foreach (array("start", "else", "end", "extends", "include", "prepend", "append", "replace") as $command)
				self::checkBlockCommand($command, "Named string {$command} block", "foo", true);
		}	

		public function testStartAndEndBlockWithHTML() {
			$src = " [@ start test @] foo [@ end test @] ";
			self::checkSyntax($src, "Start and end blocks with html is valid syntax");
			$ast = TestHelpers::MakeAST(array(
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "start", "blockName" => "test")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " foo ")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::BLOCK_NODE, array("command" => "end", "blockName" => "test")),
									TestHelpers::MakeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Start and end blocks with html has correct AST");
		}	

		public function testDuplicateExtendsBlock() {
			$this->setExpectedException('TierraTemplateTokenizerException');
			$src = " [@ extends test @] foo [@ extends foo @] ";
			self::checkSyntax($src, "Duplicate extends blocks with html is valid syntax");
		}
		
	}