<?php

	require_once dirname(__FILE__) . "/../src/TierraTemplateAST.php";
	
	class TierraTemplateCodeGen {
		
		public static function emit($ast) {

			// get the html and code chunks
			$chunks = array();
			foreach ($ast->getNodes() as $node) {
				switch ($node->type) {
					case TierraTemplateASTNode::HTML_NODE:
						$chunks[] = new TierraTemplateCodeGenChunk(TierraTemplateCodeGenChunk::HTML_CHUNK, $node->html);
						break;
						
					case TierraTemplateASTNode::BLOCK_NODE:
						$chunks[] = new TierraTemplateCodeGenChunk(TierraTemplateCodeGenChunk::PHP_CHUNK, self::emitBlock($node));
						break;
						
					case TierraTemplateASTNode::GENERATOR_NODE:
						$chunks[] = new TierraTemplateCodeGenChunk(TierraTemplateCodeGenChunk::PHP_CHUNK, self::emitGenerator($node));
						break;
				}
			}
			
			// merge the like chunks together
			$lastChunk = false;
			$code = array();
			foreach ($chunks as $chunk) {
				$startOfFileOrInHtml = (($lastChunk === false) || ($lastChunk->type == TierraTemplateCodeGenChunk::HTML_CHUNK));
				if ($chunk->type == TierraTemplateCodeGenChunk::HTML_CHUNK)
					$prefix = $startOfFileOrInHtml ? "" : " ?>";
				else
					$prefix = $startOfFileOrInHtml ? "<?php " : " "; 
				$code[] = $prefix . $chunk->contents;
				$lastChunk = $chunk;
			}
			
			return implode("", $code);
		}
		
		public static function emitBlock($blockNode) {
			// TODO: implement
			return "";
		}
		
		public static function emitGenerator($generatorNode) {
			// TODO: implement
			return "";
		}
	}	
	
	
	class TierraTemplateCodeGenChunk {
		
		const HTML_CHUNK = "HTML_CHUNK";
		const PHP_CHUNK = "PHP_CHUNK";
		
		public $type;
		public $contents;
		
		public function __construct($type, $contents) {
			$this->type = $type;
			$this->contents = $contents;
		}
	}