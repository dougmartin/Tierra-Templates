<?php

	require_once dirname(__FILE__) . "/TierraTemplateTokenizer.php";

	class TierraTemplateParser {
		
		private $tokenizer;
		
		public function __construct($src) {
			$this->tokenizer = new TierraTemplateTokenizer($src);
		}
		
		public function parse() {
			throw new TierraTemplateParserException("Parser not implemented");
		}
	}
	
	class TierraTemplateParserException extends Exception {
		
	}