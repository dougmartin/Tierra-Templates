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
			return $tokenizer;
		}
		
		public function checkLexemes($src, $lexemes, $testMessage) {
			$tokenizer = new TierraTemplateTokenizer($src);
			foreach ($lexemes as $lexeme) {
				$actualLexeme = $tokenizer->advance();
				$this->assertTrue($lexeme == $actualLexeme, $testMessage . " / Expected '{$lexeme}' found '{$actualLexeme}'");
			} 
			$this->assertTrue(true, $testMessage);
			return $tokenizer;
		}
		
		public function testEmpty() {
			$src = "";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty string is HTML + EOF");
			$tokenizer = self::checkLexemes($src, array($src), "Empty string lexeme check");
			$this->assertTrue($tokenizer->getLineNumber() == 1, "Empty string line number");
		}
	
		public function testSingleSpace() {
			$src = " ";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Single space is HTML + EOF");
			self::checkLexemes($src, array($src), "Single space lexeme check");
		}
			
		public function testMultipleSpaces() {
			$src = "     ";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Multiple spaces is HTML + EOF");
			self::checkLexemes($src, array($src), "Multiple spaces lexeme check");
		}	
		
		public function testSingleTab() {
			$src = "	";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Single tab is HTML + EOF");
			self::checkLexemes($src, array($src), "Single tab lexeme check");
		}
			
		public function testMultipleTabs() {
			$src = "			";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Multiple tabs is HTML + EOF");
			self::checkLexemes($src, array($src), "Multiple tabs lexeme check");
		}
			
		public function testMultipleSpacesAndTabs() {
			$src = "  		  		  ";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Multiple spaces is HTML + EOF");
			self::checkLexemes($src, array($src), "Multiple spaces lexeme check");
		}	
		
		public function testAllHTML() {
			$src = "<html><head><title>test</title></head><body>test</body></html>";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Only HTML is HTML + EOF");
			$tokenizer = self::checkLexemes($src, array($src), "Only HTML lexeme check");
			print $tokenizer->getLineNumber();
		}
			
		public function testMultiLineAllHTML() {
			$src = <<<HTML
				<html>
					<head>
						<title>test</title>
					</head>
					<body>
						test
					</body>
				</html>
HTML;
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Only HTML is HTML + EOF");
			$tokenizer = self::checkLexemes($src, array($src), "Muliline HTML only lexeme check");
			$this->assertTrue($tokenizer->getLineNumber() == 8, "Muliline HTML line number");
		}	
		
	}