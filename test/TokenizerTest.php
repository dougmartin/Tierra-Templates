<?php

	require_once 'PHPUnit/Framework.php';
	require_once dirname(__FILE__) . "/../src/TierraTemplateTokenizer.php";
	 
	class TokenizerTest extends PHPUnit_Framework_TestCase {
		
		private $tokenizer;
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkMatches($src, $tokens, $testMessage) {
			$tokenizer = new TierraTemplateTokenizer($src);
			try {
				foreach ($tokens as $token)
					$tokenizer->match($token);
			}
			catch (TierraTemplateTokenizerException $e) {
				$this->assertTrue(false, $testMessage . " / " . $e->getMessage());
			}
		}
		
		public function testEmpty() {
			self::checkMatches("", array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty string is valid syntax");
		}
	
		public function testSingleSpace() {
			self::checkMatches(" ", array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Single space is valid syntax");
		}
			
		public function testMultipleSpaces() {
			self::checkMatches("     ", array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Multiple spaces are valid syntax");
		}	
		
		public function testSingleTab() {
			self::checkMatches("	", array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Single tab is valid syntax");
		}
			
		public function testMultipleTabs() {
			self::checkMatches("			", array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Multiple tabs are valid syntax");
		}
			
		public function testMultipleSpacesAndTabs() {
			self::checkMatches("  		  		  ", array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Multiple spaces and tabs are valid syntax");
		}	
		
		
	}