<?php

	class TierraTemplateTokenizer {
		
		const UNKNOWN_TOKEN = "UNKNOWN_TOKEN";
		const EOF_TOKEN = "EOF_TOKEN";
		const HTML_TOKEN = "HTML_TOKEN";
		const COMMENT_TOKEN = "COMMENT_TOKEN";
		const STRING_TOKEN = "STRING_TOKEN";
		const TEXT_TOKEN = "TEXT_TOKEN";
		
		const HTML_MODE = "HTML_MODE";
		const COMMENT_MODE = "COMMENT_MODE";
		const BLOCK_MODE = "BLOCK_MODE";
		const GENERATOR_MODE = "GENERATOR_MODE";
		
		private $src;
		private $lineNumber;
		private $nextLexeme;
		private $nextToken;
		private $stream;
		private $streamLength;
		private $streamIndex;
		private $mode;
		private $eof;
		private $commentCloser;
		private $startSelectionIndices;
				
		public function __construct($src) {
			$this->src = $src;
			$this->stream = str_split($src);
			$this->streamIndex = 0;
			$this->streamLength = count($this->stream);
					
			$this->lineNumber = 1;
			
			$this->nextLexeme = false;
			$this->nextToken = self::UNKNOWN_TOKEN;
			
			$this->eof = false;
			$this->mode = self::HTML_MODE;
			
			$this->startSelectionIndices = array();
			
			$this->advance();			
		}
		
		public function getLineNumber() {
			return $this->lineNumber;
		}
		
		public function match($token, $message = false) {
			if ($token != $this->nextToken) {
				if (!$message)
					$message = "Expected {$token} found {$this->nextToken}";
					
				// build context around the error
				if ($this->streamIndex - 100 < 0) {
					$start = 0;
					$offset = $this->streamIndex;
					$length = $this->streamIndex;
				}
				else {
					$start = $this->streamIndex - 100;
					$offset = 100;
					$length = 100;
				}
				$message .= " <br><p>Context: <pre>" . htmlspecialchars(substr($this->src, $start, $length)) . "<font color='#ff0000'>*</font>" . htmlspecialchars(substr($this->src, $start + $offset, 100)) . "</pre></p>";
				$this->matchError($message);
			}
			return $this->advance();	
		}
		
		public function nextIs($token) {
			return ($token == $this->nextToken);
		}
	
		public function matchIf($token) {
			return $token == $this->nextToken ? $this->advance() : false;
		}
	
		public function matchError($message) {
			throw new TierraTemplateTokenizerException("{$message} @ line: {$this->lineNumber}");
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
			$lexeme = $this->nextLexeme;
			$this->nextLexeme = "";
			
			$this->eof = $this->streamIndex >= $this->streamLength;
			if ($this->eof) {
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
								$this->commentCloser = $curChar == "[" ? "]" : "}";
							}
							else if ($curChar == '[')
								$this->mode = self::BLOCK_MODE;
							else if ($curChar == '{')
								$this->mode = self::GENERATOR_MODE;
								
							$this->advanceChar(2);
						}						
						break;
						
					case self::COMMENT_MODE:
						$this->startSelection();
						
						$curChar = $this->curChar();
						$nextChar = $this->nextChar();
						while (!$this->eof && !((($curChar == '@') || ($curChar == '#')) && ($nextChar == $this->commentCloser))) {
							$curChar = $this->advanceChar();
							$nextChar = $this->nextChar();
						}
						
						$this->nextLexeme = $this->endSelection();
						$this->nextToken = self::COMMENT_TOKEN;

						$this->mode = self::HTML_MODE;
						$this->advanceChar(2);
						break;
						
					case self::BLOCK_MODE:
						$curChar = $this->skipWhitespace();
						
						if (!$this->eof) {
							if (($curChar == '"') || ($curChar == "'")) {
								$this->nextLexeme = $this->advanceString($curChar);
								$this->nextToken = self::STRING_TOKEN;
							}
							else {
								$this->startSelection();
								$curChar = $this->curChar();
								while (!$this->eof && !(($curChar == ' ') || ($curChar == "\t") || ($curChar == "\r") || ($curChar == "\n"))) {
									$curChar = $this->advanceChar();
								}
								$this->nextLexeme = $this->endSelection();
								$this->nextToken = self::TEXT_TOKEN;
							}
						}
						
						if (($curChar == "@") && ($this->nextChar() == "]")) {
							$this->mode = self::HTML_MODE;
							$this->advanceChar(2);
						}
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