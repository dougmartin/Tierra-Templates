<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateParser.php";
	 
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
			return $this->assertTrue($isValid, $message);
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
			return $this->assertTrue($parser->getAST() == $ast, $message);
		}
		
		public function makeAST($nodes) {
			return new TierraTemplateAST(is_array($nodes) ? $nodes : array($nodes));
		}
		
		public function makeASTNode($type, $attributes=false) {
			return new TierraTemplateASTNode($type, $attributes);
		}
		
		public function checkBlockCommand($command, $testName, $blockName=false, $isString=false, $dump=false) {
			$srcBlockName = $blockName !== false ? ($isString ? "'" . $blockName . "'" : $blockName) : "";
			$src = "[@ {$command} {$srcBlockName} @]";
			self::checkSyntax($src, "{$testName} is valid syntax");
			if (($command == "extends") || ($command == "include"))
				$attributes = array("command" => $command, "templateName" => $blockName);
			else
				$attributes = array("command" => $command, "blockName" => $blockName);
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::BLOCK_NODE, $attributes));
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
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Single space is correct AST");
		}
			
		public function testMultipleSpaces() {
			$src = "     ";
			self::checkSyntax($src, "Multiple spaces are valid syntax");
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple spaces has correct AST");
		}	
		
		public function testSingleTab() {
			$src = "	";
			self::checkSyntax($src, "Single tab is valid syntax");
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Single tab has correct AST");
		}
			
		public function testMultipleTabs() {
			$src = "			";
			self::checkSyntax($src, "Multiple tabs are valid syntax");
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple tabs has correct AST");
		}
			
		public function testMultipleSpacesAndTabs() {
			$src = "  		  		  ";
			self::checkSyntax($src, "Multiple spaces and tabs are valid syntax");
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => $src)));
			self::checkAST($src, $ast, "Multiple spaces and tabs has correct AST");
		}	
		
		public function testCommentOnly() {
			$comment = " this is a comment ";
			$src = "[#{$comment}#]";
			self::checkSyntax($src, "Comment only is valid syntax");
			$ast = self::makeAST(self::makeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $comment)));
			self::checkAST($src, $ast, "Comment only has correct AST");
		}		
		
		public function testCommentWithSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#] ";
			self::checkSyntax($src, "Comment with spaces is valid syntax");
			$ast = self::makeAST(array(
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									self::makeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $comment)),
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Comment with spaces has correct AST");
		}

		public function testAdjoiningCommentWithSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#][#{$comment}#] ";
			self::checkSyntax($src, "Adjoining comments with spaces is valid syntax");
			$ast = self::makeAST(array(
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									self::makeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $comment)),
									self::makeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $comment)),
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
								));
			self::checkAST($src, $ast, "Adjoining comments with spaces has correct AST");
		}

		public function testAdjoiningCommentWithInteriorSpaces() {
			$comment = " this is a comment ";
			$src = " [#{$comment}#]  [#{$comment}#] ";
			self::checkSyntax($src, "Adjoining comments with interior spaces is valid syntax");
			$ast = self::makeAST(array(
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
									self::makeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $comment)),
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => "  ")),
									self::makeASTNode(TierraTemplateASTNode::COMMENT_NODE, array("comment" => $comment)),
									self::makeASTNode(TierraTemplateASTNode::HTML_NODE, array("html" => " ")),
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
		
	}