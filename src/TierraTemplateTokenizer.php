<?php

	class TierraTemplateTokenizer {
		
		const UNKNOWN_TOKEN = "unknown";
		const EOF_TOKEN = "end of file";
		const HTML_TOKEN = "html";
		const COMMENT_TOKEN = "comment";
		const COMMENT_START_TOKEN = "comment start";
		const COMMENT_END_TOKEN = "comment end";
		const STRING_TOKEN = "string";
		const FUNCTION_CALL_TOKEN = "function call";
		const IDENTIFIER_TOKEN = "identifier";
		const FLOAT_TOKEN = "float";
		const INTEGER_TOKEN = "integer";
		
		const IF_TOKEN = "if";
		const DO_TOKEN = "do";
		
		const BLOCK_START_TOKEN = "[@";
		const BLOCK_END_TOKEN = "@]";
		const GENERATOR_START_TOKEN = "{@";
		const GENERATOR_END_TOKEN = "@}";
		
		const COLON_TOKEN = ":";
		const COMMA_TOKEN = ",";
		const QUESTION_MARK_TOKEN = "?";
		const SEMICOLON_TOKEN = ";";
		const EQUAL_TOKEN = "=";
		const LEFT_PAREN_TOKEN = "(";
		const RIGHT_PAREN_TOKEN = ")";
		const LEFT_BRACKET_TOKEN = "[";
		const RIGHT_BRACKET_TOKEN = "]";
		const ASTERISK_TOKEN = "*";
		const LEFT_BRACE_TOKEN = "{";
		const RIGHT_BRACE_TOKEN = "}";
		const BACKTICK_TOKEN = "`";
		const HASH_TOKEN = "#";
		const AT_TOKEN = "@";
		const XOR_TOKEN = "xor";
		const LOGICAL_OR_TOKEN = "||";
		const OR_TOKEN = "or";
		const LOGICAL_AND_TOKEN = "&&";
		const AND_TOKEN = "and";
		const LOGICAL_EQUAL_TOKEN = "==";
		const NOT_EQUAL_TOKEN = "!=";
		const ALT_NOT_EQUAL_TOKEN = "<>";
		const LESS_THAN_TOKEN = "<";
		const LESS_THAN_OR_EQUAL_TOKEN = "<=";
		const GREATER_THAN_TOKEN = ">";
		const GREATER_THAN_OR_EQUAL_TOKEN = ">=";
		const NOT_TOKEN = "!";
		const PIPE_TOKEN = "|";
		const PLUS_TOKEN = "+";
		const MINUS_TOKEN = "-";
		const DIVIDE_TOKEN = "/";
		const MODULUS_TOKEN = "%";
		const DOT_TOKEN = "."; 		
		
		const HTML_MODE = "HTML_MODE";
		const COMMENT_MODE = "COMMENT_MODE";
		const BLOCK_MODE = "BLOCK_MODE";
		const BLOCK_CONDITIONAL_MODE = "BLOCK_CONDITIONAL_MODE";
		const GENERATOR_MODE = "GENERATOR_MODE";
		
		private $src;
		private $lineNumber;
		private $nextLexeme;
		private $nextToken;
		private $stream;
		private $streamLength;
		private $streamIndex;
		private $startStreamIndex;
		private $eof;
		private $commentOpener;
		private $commentCloser;
		private $startSelectionIndices;
		
		private $singleTokens;
		private $doubleTokens;
		private $outputTemplateSingleTokens;
		private $tokenPatterns;

		private $modeStack;
				
		public function __construct($src) {
			$this->src = $src;
			$this->stream = str_split($src);
			$this->streamIndex = 0;
			$this->streamLength = $src != "" ? count($this->stream) : 0;
			
			$this->doubleTokens = array(
				self::LOGICAL_OR_TOKEN,
				self::LOGICAL_AND_TOKEN,
				self::LOGICAL_EQUAL_TOKEN,
				self::NOT_EQUAL_TOKEN,
				self::ALT_NOT_EQUAL_TOKEN,
				self::LESS_THAN_OR_EQUAL_TOKEN,
				self::GREATER_THAN_OR_EQUAL_TOKEN,
				self::BLOCK_START_TOKEN,
				self::BLOCK_END_TOKEN,
				self::GENERATOR_START_TOKEN,
				self::GENERATOR_END_TOKEN,
			);
			
			$this->singleTokens = array(
				self::QUESTION_MARK_TOKEN,
				self::COLON_TOKEN,
				self::SEMICOLON_TOKEN,
				self::EQUAL_TOKEN,
				self::LEFT_PAREN_TOKEN,
				self::RIGHT_PAREN_TOKEN,
				self::LEFT_BRACKET_TOKEN,
				self::RIGHT_BRACKET_TOKEN,
				self::COMMA_TOKEN,
				self::ASTERISK_TOKEN,
				self::LEFT_BRACE_TOKEN, 
				self::RIGHT_BRACE_TOKEN, 
				self::BACKTICK_TOKEN,
				self::AT_TOKEN,
				self::LESS_THAN_TOKEN,
				self::GREATER_THAN_TOKEN,
				self::NOT_TOKEN,
				self::PIPE_TOKEN,
				self::HASH_TOKEN,
				self::PLUS_TOKEN,
				self::MINUS_TOKEN,
				self::DIVIDE_TOKEN,
				self::MODULUS_TOKEN, 		
				self::DOT_TOKEN
			);
				
			$this->outputTemplateSingleTokens = array(
				self::LEFT_BRACE_TOKEN, 
				self::RIGHT_BRACE_TOKEN, 
				self::BACKTICK_TOKEN,
			);
			
			// order of patterns matter - we want to match the largest lexemes first
			$this->tokenPatterns = array(
				self::IF_TOKEN => '/(' . self::IF_TOKEN . ')/',
				self::DO_TOKEN => '/(' . self::DO_TOKEN . ')/',
				self::XOR_TOKEN => '/(' . self::XOR_TOKEN . ')/',
				self::OR_TOKEN => '/(' . self::OR_TOKEN . ')/',
				self::AND_TOKEN => '/(' . self::AND_TOKEN . ')/',			
				self::FUNCTION_CALL_TOKEN => '/([A-Za-z_]([A-Za-z_\\\\]*)?)\(/',
				self::IDENTIFIER_TOKEN => '/([$A-Za-z_]([A-Za-z_0-9]*)?)/',
				self::FLOAT_TOKEN => '/((\d+\.\d+))/',
				self::INTEGER_TOKEN => '/(\d+)/',
			);
					
			$this->lineNumber = 1;
			
			$this->nextLexeme = "";
			$this->nextToken = self::UNKNOWN_TOKEN;
			
			$this->eof = false;
			$this->modeStack = array(self::HTML_MODE);
			
			$this->startSelectionIndices = array();
			
			$this->advance();			
		}
		
		public function getLineNumber() {
			return $this->lineNumber;
		}
		
		public function getNextToken() {
			return $this->nextToken;
		}
		
		public function getNextLexeme() {
			return $this->nextToken;
		}		
		
		public function getStreamIndex() {
			return $this->streamIndex;
		}
		
		public function eof() {
			return $this->eof;
		}
		
		public function match($token, $message = false) {
			if ($token != $this->nextToken) {
				if (!$message)
					$message = "Expected {$token} found {$this->nextToken}";
				$this->matchError($message);
			}
			return $this->advance();	
		}
		
		public function matches($tokens, $message = false) {
			foreach ($tokens as $token) {
				if ($token == $this->nextToken)
					return $this->advance();
			}
			if (!$message)
				$message = "Expected " . implode(" or ", $tokens) . " found {$this->nextToken}";
			$this->matchError($message);			
		}
		
		public function nextIs($token) {
			return ($token == $this->nextToken);
		}
		
		public function nextIn($tokens) {
			return in_array($this->nextToken, $tokens);
		}		
	
		public function matchIf($token) {
			return $token == $this->nextToken ? $this->advance() : false;
		}
		
		public function matchElse($token, $elseLexeme=false) {
			return $token == $this->nextToken ? $this->advance() : $elseLexeme;
		}		
	
		public function matchesElse($tokens, $elseLexeme=false) {
			foreach ($tokens as $token) {
				if ($token == $this->nextToken)
					return $this->advance();
			}
			return $elseLexeme;
		}		
	
		public function matchError($message, $streamIndex=false) {
			$message = "{$message} on line {$this->lineNumber}.";
			
			$streamIndex = ($streamIndex !== false ? $streamIndex: $this->startStreamIndex);
			if ($streamIndex - 100 < 0) {
				$start = 0;
				$offset = $streamIndex;
				$length = $streamIndex;
			}
			else {
				$start = $streamIndex - 100;
				$offset = 100;
				$length = 100;
			}
			$message .= " <p>Context: <pre>" . htmlspecialchars(substr($this->src, $start, $length)) . "<font color='#ff0000'>*</font>" . htmlspecialchars(substr($this->src, $start + $offset, 100)) . "</pre></p>";
			throw new TierraTemplateTokenizerException($message);
		}
		
		public function curChar() {
			return $this->streamIndex < $this->streamLength ? $this->stream[$this->streamIndex] : "";
		} 
		
		public function nextChar() {
			return $this->streamIndex + 1 < $this->streamLength ? $this->stream[$this->streamIndex + 1] : "";
		}
		
		public function advanceChar($count = 1) {
			for ($i = 0; ($i < $count) && !$this->eof; $i++) {
				$this->streamIndex++;
				$this->eof = $this->streamIndex >= $this->streamLength;
				if ($this->curChar() == "\n")
					$this->lineNumber++;
			}
			return $this->curChar();
		}
		
		public function startSelection() {
			$this->startSelectionIndices[] = $this->streamIndex;
		}
		
		public function endSelection() {
			if (count($this->startSelectionIndices) == 0)
				throw new TierraTemplateTokenizerException("endSelection() called without matching startSelection()");
			$startIndex = array_pop($this->startSelectionIndices);
			return substr($this->src, $startIndex, $this->streamIndex - $startIndex);
		} 
		
		public function skipWhitespace() {
			$curChar = $this->curChar();
			while (!$this->eof && (($curChar == ' ') || ($curChar == "\t") || ($curChar == "\r") || ($curChar == "\n"))) {
				$curChar = $this->advanceChar();
			}
			return $curChar;			
		}
		
		public function advanceToken() {
			$curChar = $this->skipWhitespace();
			
			if (!$this->eof) {
				$nextChar = $this->nextChar();
				
				if (($curChar == '"') || ($curChar == "'")) {
					$quoteDelimiter = $curChar;
					$curChar = $this->advanceChar();
					$this->startSelection();
					while (!$this->eof && ($curChar != $quoteDelimiter)) {
						if ($curChar == "\\")
							$this->advanceChar();
						$curChar = $this->advanceChar();
					}
					$this->nextLexeme = $this->endSelection();
					$this->nextToken = self::STRING_TOKEN;
					
					// get past the closing quote
					$this->advanceChar();
				}
				else if (in_array($curChar . $nextChar, $this->doubleTokens)) {
					$this->nextToken = $curChar . $nextChar;
					$this->nextLexeme = $curChar . $nextChar;
					$this->advanceChar(2);
				}
				else if (in_array($curChar, $this->singleTokens)) {
					$this->nextToken = $curChar;
					$this->nextLexeme = $curChar;
					$this->advanceChar();
				}
				else {
					// newlines are throwing off the matching if we pass in the streamIndex offset to preg_match so build a string each time for now (this makes me sad)
					// TODO: find a better way to do this instead of building the string every time!
					$restOfSrc = implode("", array_slice($this->stream, $this->streamIndex));
					
					foreach ($this->tokenPatterns as $token => $pattern) {
						preg_match($pattern, $restOfSrc, $matches, PREG_OFFSET_CAPTURE);
						if ($matches && ($matches[0][1] == 0)) {
							$this->nextToken = $token;
							$this->nextLexeme = $matches[1][0];
							$this->advanceChar(strlen($this->nextLexeme));
							break;
						}
					}
					
				}
			}
		}
		
		public function advance() {
			// save where we started for match context errors
			$this->startStreamIndex = $this->streamIndex;
			
			$lexeme = $this->nextLexeme;
			$this->nextLexeme = "";
			
			$this->eof = $this->streamIndex >= $this->streamLength;
			if ($this->eof) {
				$this->nextLexeme = "";
				$this->nextToken = self::EOF_TOKEN;
			}
			else {
				$this->nextToken = self::UNKNOWN_TOKEN;
				$startIndex = $this->streamIndex;

				// the top of the modeStack contains the current mode
				switch ($this->modeStack[count($this->modeStack) - 1]) {
					case self::HTML_MODE:
						$this->startSelection();
						
						// get everything up to the next comment, block or generator start 
						$curChar = $this->curChar();
						$nextChar = $this->nextChar();
						while (!$this->eof && !((($curChar == '[') || ($curChar == '{')) && (($nextChar == '#') || ($nextChar == '@')))) {
							$curChar = $this->advanceChar();
							$nextChar = $this->nextChar();
						}
						
						$this->nextLexeme = $this->endSelection();
						$this->nextToken = self::HTML_TOKEN;
						
						if (!$this->eof) {
							if ($nextChar == '#') {
								$this->modeStack[] = self::COMMENT_MODE;
								$this->commentOpener = $curChar;
								$this->commentCloser = $curChar == "[" ? "]" : "}";
							}
							else if ($curChar == '[')
								$this->modeStack[] = self::BLOCK_MODE;
							else if ($curChar == '{')
								$this->modeStack[] = self::GENERATOR_MODE;
						}						
						break;
						
					case self::COMMENT_MODE:
						$this->startSelection();
						
						$curChar = $this->curChar();
						$nextChar = $this->nextChar();
						
						if ($curChar == $this->commentOpener) {
							$this->advanceChar(2);
							$this->nextLexeme = $this->endSelection();
							$this->nextToken = self::COMMENT_START_TOKEN;
						}
						else if ($nextChar == $this->commentCloser) {
							$this->advanceChar(2);
							$this->nextLexeme = $this->endSelection();
							$this->nextToken = self::COMMENT_END_TOKEN;
							
							array_pop($this->modeStack);
						}
						else {
							while (!$this->eof && !((($curChar == '@') || ($curChar == '#')) && ($nextChar == $this->commentCloser))) {
								$curChar = $this->advanceChar();
								$nextChar = $this->nextChar();
							}
						
							$this->nextLexeme = $this->endSelection();
							$this->nextToken = self::COMMENT_TOKEN;
						}
						break;
						
					case self::BLOCK_MODE:
						$this->advanceToken();
						if ($this->nextToken == self::BLOCK_END_TOKEN)
							array_pop($this->modeStack);
						break;
						
					case self::GENERATOR_MODE:
						$this->advanceToken();
						if ($this->nextToken == self::GENERATOR_END_TOKEN)
							array_pop($this->modeStack);
						break;
				}
			}
			
			return $lexeme;
		}		
		
	}
	
	class TierraTemplateTokenizerException extends Exception {
		
	}