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