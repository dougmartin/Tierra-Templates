<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateParser.php";

	class TestHelpers {
		
		public static function MakeAST($nodes=array(), $attributes=false) {
			return new TierraTemplateAST(is_array($nodes) ? $nodes : array($nodes), $attributes);
		}
		
		public static function MakeASTNode($type, $attributes=false) {
			return new TierraTemplateASTNode($type, $attributes);
		}		
		
		public static function GetParsedAST($src, $options=array()) {
			$parser = new TierraTemplateParser($options, $src);
			return $parser->parse();
		}
	}