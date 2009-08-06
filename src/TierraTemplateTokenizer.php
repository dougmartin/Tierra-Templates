<?php

	class TierraTemplateTokenizer {
		
		const UNKNOWN_TOKEN = "UNKNOWN_TOKEN";
		const EOF_TOKEN = "EOF_TOKEN";
		const HTML_TOKEN = "HTML_TOKEN";
		const COMMENT_TOKEN = "COMMENT_TOKEN";
		const COMMENT_START_TOKEN = "COMMENT_START_TOKEN";
		const COMMENT_END_TOKEN = "COMMENT_END_TOKEN";
		const BLOCK_START_TOKEN = "BLOCK_START_TOKEN";
		const BLOCK_END_TOKEN = "BLOCK_END_TOKEN";
		const STRING_TOKEN = "STRING_TOKEN";
		const TEXT_TOKEN = "TEXT_TOKEN";
		const GENERATOR_START_TOKEN = "GENERATOR_START_TOKEN";
		const GENERATOR_END_TOKEN = "GENERATOR_END_TOKEN";
		const IF_TOKEN = "IF_TOKEN";
		const DO_TOKEN = "DO_TOKEN";
		const COLON_TOKEN = "COLON_TOKEN";
		const COMMA_TOKEN = "COMMA_TOKEN";
				
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
		private $mode;
		private $eof;
		private $commentOpener;
		private $commentCloser;
		private $startSelectionIndices;
				
		public function __construct($src) {
			$this->src = $src;
			$this->stream = str_split($src);
			$this->streamIndex = 0;
			$this->streamLength = $src != "" ? count($this->stream) : 0;
					
			$this->lineNumber = 1;
			
			$this->nextLexeme = "";
			$this->nextToken = self::UNKNOWN_TOKEN;
			
			$this->eof = false;
			$this->mode = self::HTML_MODE;
			
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
		
		public function advanceString($quoteDelimiter) {
			$curChar = $this->advanceChar();
			$this->startSelection();
			while (!$this->eof && ($curChar != $quoteDelimiter)) {
				if ($curChar == "\\")
					$this->advanceChar();
				$curChar = $this->advanceChar();
			}
			$string = $this->endSelection();
			
			// get past the closing quote
			$this->advanceChar();
			
			return $string;
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

				switch ($this->mode) {
					case self::HTML_MODE:
						$this->startSelection();
						
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
								$this->mode = self::COMMENT_MODE;
								$this->commentOpener = $curChar;
								$this->commentCloser = $curChar == "[" ? "]" : "}";
							}
							else if ($curChar == '[')
								$this->mode = self::BLOCK_MODE;
							else if ($curChar == '{')
								$this->mode = self::GENERATOR_MODE;
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
							
							$this->mode = self::HTML_MODE;
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
						$this->startSelection();
						
						$curChar = $this->curChar();
						$nextChar = $this->nextChar();
						
						if (($curChar == "[") && ($nextChar == "@")) {
							$this->advanceChar(2);
							$this->nextLexeme = $this->endSelection();
							$this->nextToken = self::BLOCK_START_TOKEN;
						}
						else if (($curChar == "@") && ($nextChar == "]")) {
							$this->advanceChar(2);
							$this->nextLexeme = $this->endSelection();
							$this->nextToken = self::BLOCK_END_TOKEN;
							
							$this->mode = self::HTML_MODE;
						}
						else {						
							$curChar = $this->skipWhitespace();
							
							if (!$this->eof) {
								if (($curChar == '"') || ($curChar == "'")) {
									$this->nextLexeme = $this->advanceString($curChar);
									$this->nextToken = self::STRING_TOKEN;
								}
								else {
									$this->startSelection();
									
									$curChar = $this->curChar();
									while (!$this->eof && !(($curChar == '@') || ($curChar == ' ') || ($curChar == "\t") || ($curChar == "\r") || ($curChar == "\n"))) {
										$curChar = $this->advanceChar();
									}
									
									$this->nextLexeme = $this->endSelection();
									
									if (strtolower($this->nextLexeme) == "if") {
										$this->nextToken = self::IF_TOKEN;
										$this->mode = TierraTemplateTokenizer::BLOCK_CONDITIONAL_MODE;	
									}
									else
										$this->nextToken = self::TEXT_TOKEN;
								}
								
								// skip to the next text or end of the block
								$this->skipWhitespace();
							}
						}
						break;
						
					case self::BLOCK_CONDITIONAL_MODE:
						break;
						
					case self::GENERATOR_MODE:
						break;
				}
			}
			
			return $lexeme;
		}		
		
	}
	
	class TierraTemplateTokenizerException extends Exception {
		
	}