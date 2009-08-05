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
		
		public function testEmpty() {
			self::checkSyntax("", "Empty string is valid syntax");
		}
	
		public function testSingleSpace() {
			self::checkSyntax(" ", "Single space is valid syntax");
		}
			
		public function testMultipleSpaces() {
			self::checkSyntax("     ", "Multiple spaces are valid syntax");
		}	
		
		public function testSingleTab() {
			self::checkSyntax("	", "Single tab is valid syntax");
		}
			
		public function testMultipleTabs() {
			self::checkSyntax("			", "Multiple tabs are valid syntax");
		}
			
		public function testMultipleSpacesAndTabs() {
			self::checkSyntax("  		  		  ", "Multiple spaces and tabs are valid syntax");
		}	
		
		
	}