<?php
	require_once dirname(__FILE__) . "/../src/TierraTemplateParser.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateOptimizer.php";
	require_once dirname(__FILE__) . "/../src/TierraTemplateException.php";
	
	class TierraTemplate {
		
		private $__options;
		private $__templateFile;
		private $__baseTemplateDir;
		private $__cachedTemplatePath;
		private $__runtime;
		private $__request;
		
		public function __construct($templateFile, $baseTemplateDir, $options=array(), $request=false) {
			
			$this->__options = $options;
			$this->__templateFile = $templateFile;
			$this->__baseTemplateDir = $this->addTrailingDirectorySepatator($baseTemplateDir);
			$this->__request = $request !== false ? $request : new TierraTemplateRequest();
			$this->__runtime = new TierraTemplateRuntime($this->__request);
			
			$rawTemplatePath = $baseTemplateDir . $templateFile;
			$rawTemplateInfo = @stat($rawTemplatePath);
			$this->__cachedTemplatePath = $this->getCacheRoot() . $templateFile . ".php";
			$cachedTemplateInfo = @stat($this->__cachedTemplatePath);
			
			$useCachedTemplate = $this->getOption("readFromCache", true) && ($cachedTemplateInfo !== false) && ($cachedTemplateInfo['mtime'] > $rawTemplateInfo['mtime']);
						
			if (!$useCachedTemplate) {
				if (!$rawTemplateInfo)
					throw new TierraTemplateException("Template not found: {$rawTemplatePath}");
				$templateContents = @file_get_contents($rawTemplatePath);
				if ($templateContents === false)
					throw new TierraTemplateException("Cannot read template: {$rawTemplatePath}");
				$this->parseAndCache($templateContents, $this->__cachedTemplatePath);
			}
		}
		
		public static function Load($templateFile, $baseTemplateDir, $options=array(), $request=false) {
			return new TierraTemplate($templateFile, $baseTemplateDir, $options, $request);
		}
		
		public static function Run($templateFile, $baseTemplateDir, $options=array(), $request=false) {
			$template = self::Load($templateFile, $baseTemplateDir, $options, $request);
			$template->render();
		}
		
		public static function RunDynamic($templateContents, $options=array(), $request=false, $bufferOutput=true) {
			
			$cachedTemplatePath = $this->getCacheRoot() . "dtt_" . md5($templateContents) . ".html.php";
			if (!file_exists($cachedTemplatePath))
				$this->parseAndCache($templateContents, $cachedTemplatePath);
				
			if ($bufferOutput)
				ob_start();
			require_once $cachedTemplatePath;
			if ($bufferOutput) {
				echo ob_get_contents();
				ob_end_clean();
			}
		}
		
		public function __set($name, $value) {
			$this->__request->$name = $value;
		}
		
		public function __get($name) {
			return $this->__request->$name;
		}
		
		public function __isset($name) {
			return isset($this->__request->$name);
		}
		
		public function __unset($name) {
			unset($this->__request->$name);
		}
		
		public function getOption($name, $default=false) {
			return isset($this->__options[$name]) ? $this->__options[$name] : $default;
		}
		
		public function addTrailingDirectorySepatator($path) {
			if (substr($path, -1) != DIRECTORY_SEPARATOR)
				$path .= DIRECTORY_SEPARATOR;
			return $path;
		}
		
		public function getCacheRoot() {
			$cacheRoot = $this->addTrailingDirectorySepatator($this->getOption("cacheRoot", function_exists("sys_get_temp_dir") ? sys_get_temp_dir() : DIRECTORY_SEPARATOR . "tmp" . DIRECTORY_SEPARATOR));
			if (!is_dir($cacheRoot)) {
				@mkdir(dirname($cacheRoot), $this->getOption("cacheDirPerms", 0777), true);
				if (!is_dir($cacheRoot))
					throw new TierraTemplateException("Cache directory cannot be created: {$cacheRoot}");
			}
			return $cacheRoot;
		}
		
		public function parseAndCache($templateContents, $cacheTemplatePath) {
			$parser = new TemplateTemplateParser($templateContents);
			$parser->parse();
			$src = TierraTemplateCodeGenerator::emit(TierraTemplateOptimizer::optimize($parser->getAST()));
			
			@mkdir(dirname($this->__cachedTemplatePath), $this->getOption("cacheDirPerms", 0777), true);
			$handle = @fopen($this->__cachedTemplatePath, "w");
			if ($handle) {
				fwrite($handle, $src);
				fclose($handle);
				@chmod($this->__cachedTemplatePath,  $this->getOption("cachedTemplatePerms", 0666));
			}
			else
				throw new TierraTemplateException("Cannot create cached template: {$this->__cachedTemplatePath}");			
		}
		
		public function render($bufferOutput=true) {
			if ($bufferOutput)
				ob_start();
			require_once $this->__cachedTemplatePath;
			if ($bufferOutput) {
				echo ob_get_contents();
				ob_end_clean();
			}
		}
		
		public function includeTemplate($templateFile) {
			if (($templateFile === false) || ($templateFile == ""))
				throw new TierraTemplateException("No template name given in include");
				
			if (substr($templateFile, 0, 1) == "/")
				$templateFile = substr($templateFile, 1);
			else 
				$templateFile = dirname($this->$templateFile) . "/" . $templateFile;
				
			$info = pathinfo($templateFile);
			if (!isset($info["extension"]) && $this->getOption("autoAddHtmlExtension", true))
				$templateFile .= ".html";
			
			$includedTemplate = Template::Load($templateFile, $this->__baseTemplateDir, $this->__options, $this->__request);
			$includedTemplate->render(false);
		}			
	}
	
	