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
		
		
	}