<?php

	class TierraTemplateTokenizer {
		
		const UNKNOWN_TOKEN = "UNKNOWN_TOKEN";
		const EOF_TOKEN = "EOF_TOKEN";
		const HTML_TOKEN = "HTML_TOKEN";
		
		const HTML_MODE = 1;
		const COMMENT_MODE = 2;
		const BLOCK_MODE = 3;
		const GENERATOR_MODE = 4;
		
		public $src;
		public $nextLexeme;
		public $nextToken;
		public $lineNumber;
		public $columnNumber;
		private $stream;
		private $streamLength;
		private $streamIndex;
		private $mode;
		private $eof;
		private $commentCloser;
				
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
			
			$this->advance();			
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
			$this->streamIndex < $this->streamLength ? $this->stream[$this->streamIndex] : "";
		} 
		
		public function nextChar() {
			$this->streamIndex + 1 < $this->streamLength ? $this->stream[$this->streamIndex + 1] : "";
		}
		
		public function advanceChar($count = 1) {
			$curChar = $this->curChar();
			for ($i = 0; ($i < $count) && !$this->eof; $i++) {
				$this->streamIndex++;
				$this->eof = $this->streamIndex >= $this->streamLength;
				if ($this->curChar() == "\n")
					$this->lineNumber++;
			}
			return $this->curChar();
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
						$chars = array();
						$curChar = $this->curChar();
						$nextChar = $this->nextChar();
						while ((!$this->eof) && !((($curChar == '[') || ($curChar == '{')) && (($nextChar == '#') || ($nextChar == '@')))) {
							$chars[] = $curChar;
							$curChar = $this->advanceChar();
							$nextChar = $this->nextChar();
						}
						
						$this->nextLexeme = implode("", $chars);
						$this->nextToken = self::HTML_TOKEN;
												
						if (!$this->eof) {
							if ($nextChar == '#') {
								$this->mode = self::COMMENT_MODE;
								$this->commentCloser = $curChar == "[" ? "]" : "}";
							}
							else if ($curChar == '[')
								$this->mode = self::BLOCK_MODE;
							else if ($curChar == '{')
								$this->mode = self::BLOCK_MODE;
								
							$this->advanceChar(2);
						}						
						break;
				}
			}
			
			return $lexeme;
		}		
		
	}
	
	class TierraTemplateTokenizerException extends Exception {
		
	}