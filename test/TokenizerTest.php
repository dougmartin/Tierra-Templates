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
	require_once dirname(__FILE__) . "/../src/TierraTemplateTokenizer.php";
	 
	class TokenizerTest extends PHPUnit_Framework_TestCase {
		
		private $tokenizer;
		
		protected function setUp() {
		}
		
		protected function tearDown() {
		}
		
		public function checkMatches($src, $tokens, $testMessage, $dump=false) {
			if ($dump) {
				echo "\n{$testMessage}\n";
				echo "src:\n";
				var_dump($src);
				echo "tokens:\n";
				var_dump($tokens);
				echo "found tokens:\n";
			}
			$tokenizer = new TierraTemplateTokenizer($src);
			try {
				foreach ($tokens as $token) {
					if ($dump)
						var_dump($tokenizer->getNextToken());
					$tokenizer->match($token);
				}
			}
			catch (TierraTemplateException $e) {
				$this->assertTrue(false, $testMessage . " / " . $e->getMessage());
			}
			return $tokenizer;
		}
		
		public function checkLexemes($src, $lexemes, $testMessage, $dump=false) {
			$tokenizer = new TierraTemplateTokenizer($src);
			foreach ($lexemes as $lexeme) {
				$actualLexeme = $tokenizer->advance();
				if ($dump)
					echo "expected: '{$lexeme}', found '{$actualLexeme}'\n";
				$this->assertEquals($lexeme, $actualLexeme, $testMessage . " / Expected '{$lexeme}' found '{$actualLexeme}'");
			} 
			$this->assertTrue(true, $testMessage);
			return $tokenizer;
		}
		
		public function testEmpty() {
			$src = "";
			self::checkMatches($src, array(TierraTemplateTokenizer::EOF_TOKEN), "Empty string is HTML + EOF");
			$tokenizer = self::checkLexemes($src, array(""), "Empty string lexeme check");
			$this->assertEquals($tokenizer->getLineNumber(), 1, "Empty string line number");
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
			$this->assertEquals($tokenizer->getLineNumber(), 8, "Muliline HTML line number");
		}
		
		public function testCommentBlockOnly() {
			$comment = " this is a comment ";
			$src = "[#{$comment}#]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Comment block only is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "[#", $comment, "#]", ""), "Comment block only lexeme check");
		}

		public function testCommentConditeratorOnly() {
			$comment = "@ this is a comment ";
			$src = "{#{$comment}@}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Commented conditerator only is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "{#", $comment, "@}", ""), "Commented conditerator only lexeme check");
		}

		public function testEmptyCommentBlock() {
			$comment = "";
			$src = "[#{$comment}#]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty comment block is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "[#", "#]", ""), "Empty comment block lexeme check");
		}
		
		public function testEmptyCommentConditerator() {
			$comment = "";
			$src = "{#{$comment}@}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Empty commented conditerator is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "{#", "@}", ""), "Empty commented conditerator lexeme check");
		}		
		
		public function testUnterminatedCommentBlock() {
			$comment = " this is a comment ";
			$src = "[#{$comment}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Unterminated comment block is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "[#", $comment, ""), "Unterminated comment block lexeme check");
		}

		public function testUnterminatedCommentConditerator() {
			$comment = " this is a comment ";
			$src = "{#{$comment}";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::COMMENT_START_TOKEN, TierraTemplateTokenizer::COMMENT_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Unterminated commented conditerator is HTML + COMMENT + EOF");
			self::checkLexemes($src, array("", "{#", $comment, ""), "Unterminated commented conditerator lexeme check");
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

		public function testBlockWithOneIdentifierNoSpaces() {
			$commands = "command";
			$src = "[@{$commands}@]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 1 identifier with no spaces is HTML + BLOCK_START + TEXT + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", $commands, "@]", ""), "Block with 1 identifier with no spaces lexeme check");
		}

		public function testBlockWithOneIdentifierWithSpaces() {
			$commands = "command";
			$src = "[@ {$commands} @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 1 identifier with spaces is HTML + BLOCK_START + TEXT + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", $commands, "@]", ""), "Block with 1 identifier with spaces lexeme check");
		}

		public function testBlockWithTwoIdentifiersWithSpaces() {
			$commands = "command1 command2";
			$src = "[@ {$commands} @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 2 identifiers with spaces is HTML + BLOCK_START + TEXT + TEXT + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", "command1", "command2", "@]", ""), "Block with 2 identifiers with spaces lexeme check");
		}	

		public function testBlockWithOneDoubleQuotedStringNoSpaces() {
			$commands = "command";
			$src = "[@\"{$commands}\"@]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 1 double quoted string with no spaces is HTML + BLOCK_START + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", $commands, "@]", ""), "Block with 1 double quoted string with no spaces lexeme check");
		}

		public function testBlockWithOneDoubleQuotedStringWithSpaces() {
			$commands = "command";
			$src = "[@ \"{$commands}\" @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 1 double quoted string with spaces is HTML + BLOCK_START + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", $commands, "@]", ""), "Block with 1 double quoted string with spaces lexeme check");
		}

		public function testBlockWithTwoDoubleQuotedStringsWithSpaces() {
			$commands = '"command1" "command2"';
			$src = "[@ {$commands} @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 2 identifiers with spaces is HTML + BLOCK_START + STRING + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", "command1", "command2", "@]", ""), "Block with 2 double quoted strings with spaces lexeme check");
		}		

		public function testBlockWithOneSingleQuotedStringNoSpaces() {
			$commands = "command";
			$src = "[@'{$commands}'@]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 1 single quoted string with no spaces is HTML + BLOCK_START + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", $commands, "@]", ""), "Block with 1 single quoted string with no spaces lexeme check");
		}

		public function testBlockWithOneSingleQuotedStringWithSpaces() {
			$commands = "command";
			$src = "[@ '{$commands}' @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 1 single quoted string with spaces is HTML + BLOCK_START + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", $commands, "@]", ""), "Block with 1 single quoted string with spaces lexeme check");
		}

		public function testBlockWithTwoSingleQuotedStringsWithSpaces() {
			$commands = "'command1' 'command2'";
			$src = "[@ {$commands} @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with 2 single quoted strings with spaces is HTML + BLOCK_START + STRING + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", "command1", "command2", "@]", ""), "Block with 2 single quoted strings with spaces lexeme check");
		}			
		
		public function testBlockWithIdentifierAndDoubleQuotedString() {
			$commands = "command1 \"command2\"";
			$src = "[@ {$commands} @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with identifier and double quoted string is HTML + BLOCK_START + TEXT + STRING + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", "command1", "command2", "@]", ""), "Block with identifier and double quoted string lexeme check");
		}

		public function testBlockWithDoubleQuotedStringAndIdentifier() {
			$commands = "\"command1\" command2";
			$src = "[@ {$commands} @]";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with double quoted string and identifier is HTML + BLOCK_START + STRING + TEXT + BLOCK_END + EOF");
			self::checkLexemes($src, array("", "[@", "command1", "command2", "@]", ""), "Block with double quoted string and identifier lexeme check");
		}		

		public function testBlockWithSimpleConditional() {
			$src = "[@ include foo if true @]";	
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Block with simple conditional");
			self::checkLexemes($src, array("", "[@", "include", "foo", "if", "true", "@]", ""), "Block with simple conditional lexeme check");
		}
		
		public function testBlockWithFunctionCallNoParams() {
			$src = "[@ include foo if foo() @]";	
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::FUNCTION_CALL_TOKEN, TierraTemplateTokenizer::LEFT_PAREN_TOKEN, TierraTemplateTokenizer::RIGHT_PAREN_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Function call no params");
			self::checkLexemes($src, array("", "[@", "include", "foo", "if", "foo", "(", ")", "@]", ""), "Function call no params lexeme check");
		}

		public function testBlockWithOutputTemplateWithBraces() {
			$src = "[@ include foo if `foo{bar}` @]";	
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::BACKTICK_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::LEFT_BRACE_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::RIGHT_BRACE_TOKEN, TierraTemplateTokenizer::BACKTICK_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Output block");
			self::checkLexemes($src, array("", "[@", "include", "foo", "if", "`", "foo", "{", "bar", "}", "`", "@]", ""), "Output template lexeme check");
		}
		
		public function testBlockWithOutputTemplateWithStartConditerators() {
			$src = "[@ include foo if `foo{@ bar @}` @]";	
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::BACKTICK_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::Conditerator_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::Conditerator_END_TOKEN, TierraTemplateTokenizer::BACKTICK_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Output block");
			self::checkLexemes($src, array("", "[@", "include", "foo", "if", "`", "foo", "{@", "bar", "@}", "`", "@]", ""), "Output template lexeme check");
		}
		
		public function testBlockWithStrictOutputTemplateWithBraces() {
			$src = "[@ include foo if ~foo{bar}~ @]";	
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::TILDE_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::TILDE_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Strict output block");
			self::checkLexemes($src, array("", "[@", "include", "foo", "if", "~", "foo{bar}", "~", "@]", ""), "Strict output block lexeme check");
		}
					
		public function testBlockWithStrictOutputTemplateWithStartConditerators() {
			$src = "[@ include foo if ~foo{@ bar @}~ @]";	
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::BLOCK_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::IF_TOKEN, TierraTemplateTokenizer::TILDE_TOKEN, TierraTemplateTokenizer::STRING_TOKEN, TierraTemplateTokenizer::Conditerator_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::Conditerator_END_TOKEN, TierraTemplateTokenizer::TILDE_TOKEN, TierraTemplateTokenizer::BLOCK_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Output block");
			self::checkLexemes($src, array("", "[@", "include", "foo", "if", "~", "foo", "{@", "bar", "@}", "~", "@]", ""), "Output template lexeme check");
		}
		
		public function testCodeBlock() {
			$src = "<@ foo @>";
			self::checkMatches($src, array(TierraTemplateTokenizer::HTML_TOKEN, TierraTemplateTokenizer::CODE_START_TOKEN, TierraTemplateTokenizer::IDENTIFIER_TOKEN, TierraTemplateTokenizer::CODE_END_TOKEN, TierraTemplateTokenizer::EOF_TOKEN), "Code block");
			self::checkLexemes($src, array("", "<@", "foo", "@>", ""), "Code block lexeme check");
		}
		
	}