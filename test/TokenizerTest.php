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
			self::checkLexemes($src, array($src), "Only HTML lexeme check");
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
		
		public function testCommentBlockOnly() {
			$comment = " this is a comment ";
			$src = "[#{$comment}#]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Comment block only is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "[#", $comment, "#]", ""), "Comment block only lexeme check");
		}

		public function testCommentGeneratorOnly() {
			$comment = "@ this is a comment ";
			$src = "{#{$comment}@}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Commented generator only is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "{#", $comment, "@}", ""), "Commented generator only lexeme check");
		}

		public function testEmptyCommentBlock() {
			$comment = "";
			$src = "[#{$comment}#]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty comment block is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "[#", "#]", ""), "Empty comment block lexeme check");
		}
		
		public function testEmptyCommentGenerator() {
			$comment = "";
			$src = "{#{$comment}@}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty commented generator is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "{#", "@}", ""), "Empty commented generator lexeme check");
		}		
		
		public function testUnterminatedCommentBlock() {
			$comment = " this is a comment ";
			$src = "[#{$comment}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Unterminated comment block is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "[#", $comment, ""), "Unterminated comment block lexeme check");
		}

		public function testUnterminatedCommentGenerator() {
			$comment = " this is a comment ";
			$src = "{#{$comment}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Unterminated commented generator is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "{#", $comment, ""), "Unterminated commented generator lexeme check");
		}		

		public function testCommentBlockWithHTML() {
			$before = " this is before ";
			$after = " this is after ";
			$comment = " this is a comment ";
			$src = "{$before}[#{$comment}#]{$after}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Comment block with html is HTML + COMMENT + HTML + EOF");
			self::checkLexemes($src, array($before, "[#", $comment, "#]", $after, ""), "Comment block with html lexeme check");
		}		
		
		public function testCommentBlockWithBracketsInHTML() {
			$before = " [this is before] ";
			$after = " [this is after] ";
			$comment = " this is a comment ";
			$src = "{$before}[#{$comment}#]{$after}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Comment block with brackets in html is HTML + COMMENT + HTML + EOF");
			self::checkLexemes($src, array($before, "[#", $comment, "#]", $after, ""), "Comment block with brackets in html lexeme check");
		}

		public function testCommentBlockWithBracketsAndSpaceBeforeSigilsInHTML() {
			$before = " [ @ this is before] ";
			$after = " { @ this is after @ } ";
			$comment = " this is a comment ";
			$src = "{$before}[#{$comment}#]{$after}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Comment block with brackets with space before sigils in html is HTML + COMMENT + HTML + EOF");
			self::checkLexemes($src, array($before, "[#", $comment, "#]", $after, ""), "Comment block with brackets with space before sigils in html lexeme check");
		}

		public function testEmptyBlock() {
			$commands = "";
			$src = "[@{$commands}@]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty block is HTML + BLOCK_START + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", "@]", ""), "Empty block lexeme check");
		}		
	}