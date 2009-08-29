<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateException.php";
	
	class TierraTemplateTokenizer {
		
		const UNKNOWN_TOKEN = "unknown";
		const EOF_TOKEN = "end of file";
		const HTML_TOKEN = "html";
		const COMMENT_TOKEN = "comment";
		const PHP_TOKEN = "php code";
		const STRING_TOKEN = "string";
		const FUNCTION_CALL_TOKEN = "function call";
		const IDENTIFIER_TOKEN = "identifier";
		const FLOAT_TOKEN = "float";
		const INTEGER_TOKEN = "integer";
		
		const IF_TOKEN = "if";
		const ELSE_TOKEN = "else";
		const DO_TOKEN = "do";
		
		const BLOCK_START_TOKEN = "[@";
		const BLOCK_END_TOKEN = "@]";
		const GENERATOR_START_TOKEN = "{@";
		const GENERATOR_END_TOKEN = "@}";
		const CODE_START_TOKEN = "<@";
		const CODE_END_TOKEN = "@>";
		const COMMENT_START_TOKEN = "comment start";
		const COMMENT_END_TOKEN = "comment end";
		
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
		const TILDE_TOKEN = "~";
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
		const GENERATOR_MODE = "GENERATOR_MODE";
		const CODE_MODE = "CODE_MODE";
		const OUTPUT_TEMPLATE_MODE = "OUTPUT_TEMPLATE_MODE";
		const STRICT_OUTPUT_TEMPLATE_MODE = "STRICT_OUTPUT_TEMPLATE_MODE";
		
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
				self::CODE_START_TOKEN,
				self::CODE_END_TOKEN,
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
				self::TILDE_TOKEN,
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

			
			// order of patterns matter - we want to match the largest lexemes first
			$this->tokenPatterns = array(
				self::IF_TOKEN => '/(' . self::IF_TOKEN . ')/',
				self::ELSE_TOKEN => '/(' . self::ELSE_TOKEN . ')/',
				self::DO_TOKEN => '/(' . self::DO_TOKEN . ')/',
				self::XOR_TOKEN => '/(' . self::XOR_TOKEN . ')/',
				self::OR_TOKEN => '/(' . self::OR_TOKEN . ')/',
				self::AND_TOKEN => '/(' . self::AND_TOKEN . ')/',			
				self::FUNCTION_CALL_TOKEN => '/([A-Za-z_]([A-Za-z_0-9\\\\]*(::)*[A-Za-z_0-9\\\\]*)?)\(/',
				self::IDENTIFIER_TOKEN => '/([$A-Za-z_]([$A-Za-z_0-9\\\\]*(::)*[A-Za-z_0-9\\\\]*)?)/',
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
			throw new TierraTemplateException($message);
		}
		
		public function curChar($skipSlash=false) {
			$char = $this->streamIndex < $this->streamLength ? $this->stream[$this->streamIndex] : "";
			if ($skipSlash && ($char == "\\"))
				return $this->advanceChar(2);
			return $char;
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
		
		// skips over the slash if present and adds the character to the buffer
		public function skipSlash($curChar, &$chars) {
			if ($curChar == "\\") {
				$chars[] = $this->advanceChar();
				$curChar = $this->advanceChar();
			}
			return $curChar;
		} 
		
		public function startSelection() {
			$this->startSelectionIndices[] = $this->streamIndex;
		}
		
		public function endSelection() {
			if (count($this->startSelectionIndices) == 0)
				throw new TierraTemplateException("endSelection() called without matching startSelection()");
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
					$chars = array();
					while (!$this->eof && ($curChar != $quoteDelimiter)) {
						if ($curChar == "\\")
							$curChar = $this->advanceChar();
						$chars[] = $curChar;
						$curChar = $this->advanceChar();
					}
					$this->nextLexeme = implode("", $chars);
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
			else {
				$this->nextLexeme = "";
				$this->nextToken = self::EOF_TOKEN;				
			}
		}
		
		public function pushMode($mode) {
			$this->modeStack[] = $mode;
		}
		
		public function popMode() {
			array_pop($this->modeStack);
		}
		
		public function getMode() {
			return $this->modeStack[count($this->modeStack) - 1];
		}
		
		public function getPreviousMode() {
			return count($this->modeStack) > 1 ? $this->modeStack[count($this->modeStack) - 2] : false;
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

				$mode = $this->getMode();
				switch ($mode) {
					case self::HTML_MODE:
						$this->startSelection();
												
						// get everything up to the next comment, block, generator, php or code start token
						$curChar = $this->curChar(); 
						$nextChar = $this->nextChar();
						while (!$this->eof && !((($curChar == '[') || ($curChar == '{') || ($curChar == '<')) && (($nextChar == '#') || ($nextChar == '@')))) {
							$this->advanceChar();
							$curChar = $this->curChar();
							$nextChar = $this->nextChar();
						}
						
						$this->nextLexeme = $this->endSelection();
						$this->nextToken = self::HTML_TOKEN;
						
						if (!$this->eof) {
							if ($nextChar == '#') {
								$this->pushMode(self::COMMENT_MODE);
								$this->commentOpener = $curChar;
								$closers = array("[" => "]", "{" => "}", "<" => ">");
								$this->commentCloser = $closers[$curChar];
							}
							else if ($curChar == '[')
								$this->pushMode(self::BLOCK_MODE);
							else if ($curChar == '{')
								$this->pushMode(self::GENERATOR_MODE);
							else if ($curChar == '<')
								$this->pushMode(self::CODE_MODE);
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
							
							$this->popMode();
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
						
					// these two modes are combined to share the common code for switching to the output template modes
					case self::BLOCK_MODE:
					case self::GENERATOR_MODE:
						$this->advanceToken();
						
						switch ($this->nextToken) {
							case self::BLOCK_END_TOKEN:
								if ($mode == self::BLOCK_MODE)
									$this->popMode();
								break;
								
							case self::GENERATOR_END_TOKEN:
								if ($mode == self::GENERATOR_MODE)
									$this->popMode();
								break;
								
							// normal output templates can use {...} or {@...@} to delimit generators
							// strict output templates can only use {@...@}
							case self::RIGHT_BRACE_TOKEN:
								if (($mode == self::GENERATOR_MODE) && ($this->getPreviousMode() == self::OUTPUT_TEMPLATE_MODE))
									$this->popMode();
								break;
								
							case self::BACKTICK_TOKEN:
								$this->pushMode(self::OUTPUT_TEMPLATE_MODE);
								break;
							
							case self::TILDE_TOKEN:
								$this->pushMode(self::STRICT_OUTPUT_TEMPLATE_MODE);
								break;
						}
						break;
						
					case self::CODE_MODE:
						$this->advanceToken();
						
						switch ($this->nextToken) {
							case self::CODE_END_TOKEN:
								$this->popMode();
								break;
								
							case self::BACKTICK_TOKEN:
								$this->pushMode(self::OUTPUT_TEMPLATE_MODE);
								break;
							
							case self::TILDE_TOKEN:
								$this->pushMode(self::STRICT_OUTPUT_TEMPLATE_MODE);
								break;
						}
						break;

					// the difference in the two modes is the delimiters `...` for normal and ~...~ for strict
					// and the generator delimiters {...} and {@...@} for normal but just {@...@} for strict
					// strict is used when including javascript with braces in a output template so you don't have to escape all the {} characters
					case self::OUTPUT_TEMPLATE_MODE:
						
						$chars = array();
						$curChar = $this->skipSlash($this->curChar(), $chars);
						
						if ($curChar == self::BACKTICK_TOKEN) {
							$this->nextToken = self::BACKTICK_TOKEN;
							$this->nextLexeme = self::BACKTICK_TOKEN; 
							$this->advanceChar();
							$this->popMode();
						}
						else if ($curChar == self::LEFT_BRACE_TOKEN) {
							$nextChar = $this->nextChar();
							if ($curChar . $nextChar == self::GENERATOR_START_TOKEN) {
								$this->nextToken = self::GENERATOR_START_TOKEN;
								$this->nextLexeme = $curChar . $nextChar;
								$this->advanceChar(2);
							}
							else {
								$this->nextToken = self::LEFT_BRACE_TOKEN;
								$this->nextLexeme = self::LEFT_BRACE_TOKEN; 
								$this->advanceChar();
							}
							
							$this->pushMode(self::GENERATOR_MODE);
						}
						else {
							while (!$this->eof && ($curChar != self::BACKTICK_TOKEN) && ($curChar != self::LEFT_BRACE_TOKEN)) {
								$chars[] = $curChar;
								$curChar = $this->skipSlash($this->advanceChar(), $chars);
							}
							$this->nextLexeme = implode("", $chars);
							$this->nextToken = self::STRING_TOKEN;							
						}
						break;
						
					case self::STRICT_OUTPUT_TEMPLATE_MODE:
						
						$chars = array();
						$curChar = $this->skipSlash($this->curChar(), $chars);
						$nextChar = $this->nextChar();
						
						if ($curChar == self::TILDE_TOKEN) {
							$this->nextToken = self::TILDE_TOKEN;
							$this->nextLexeme = self::TILDE_TOKEN; 
							$this->advanceChar();
							$this->popMode();
						}
						else if ($curChar . $nextChar == self::GENERATOR_START_TOKEN) {
							$this->nextToken = self::GENERATOR_START_TOKEN;
							$this->nextLexeme = self::GENERATOR_START_TOKEN;
							$this->advanceChar(2);
							
							$this->pushMode(self::GENERATOR_MODE);
						}
						else {
							while (!$this->eof && ($curChar != self::TILDE_TOKEN) && ($curChar . $nextChar != self::GENERATOR_START_TOKEN)) {
								$chars[] = $curChar;
								$curChar = $this->skipSlash($this->advanceChar(), $chars);
								$nextChar = $this->nextChar();
							}
							$this->nextLexeme = implode("", $chars);
							$this->nextToken = self::STRING_TOKEN;							
						}
						break;
				}
			}
			
			return $lexeme;
		}		
		
	}
	
