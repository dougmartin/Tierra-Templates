<?php
	require_once dirname(__FILE__) . "/TierraTemplateParser.php";
	require_once dirname(__FILE__) . "/TierraTemplateOptimizer.php";
	require_once dirname(__FILE__) . "/TierraTemplateRequest.php";
	require_once dirname(__FILE__) . "/TierraTemplateRuntime.php";
	require_once dirname(__FILE__) . "/TierraTemplateException.php";
	
	class TierraTemplate {
		
		public $__options;
		public $__templateFile;
		public $__baseTemplateDir;
		public $__cachedTemplatePath;
		public $__runtime;
		public $__request;
		
		public function __construct($options=array()) {
			
			// add the request object if not present to the options so it can be shared with parent templates
			if (!isset($options["request"]))
				$options["request"] = new TierraTemplateRequest();
				
			// add the built in externals
			if (!isset($options["virtualDirs"]["_"])) {
				// rebuild the array so our builtin is first when we loop on it
				$virtualDirs["_"] = array(
					"path" => dirname(__FILE__) . "/externals/builtin",
					"classPrefix" => "TierraTemplateBuiltinExternals_",
					"functionPrefix" => "TierraTemplateBuiltinExternals_",
				);
				if (isset($options["virtualDirs"])) {
					foreach ($options["virtualDirs"] as $virtualDir => $dirInfo)
						$virtualDirs[$virtualDir] = $dirInfo;
				}
				$options["virtualDirs"] = $virtualDirs;
			}
			$this->__options = $options;
			
			$templateFile = $this->getOption("templateFile");
			if ($templateFile === false)
				throw new TierraTemplateException("Missing templateFile option");
			$baseTemplateDir = $this->getOption("baseTemplateDir");
			if ($baseTemplateDir === false)
				throw new TierraTemplateException("Missing baseTemplateDir option");
			$baseTemplateDir = self::AddTrailingDirectorySeparator($baseTemplateDir);
				
			$this->__request = $this->getOption("request");
			$this->__runtime = new TierraTemplateRuntime($this->__request, $options);
			$this->__templateFile = $templateFile;
			$this->__baseTemplateDir = $baseTemplateDir;
			$this->__cachedTemplatePath = false;
			
			$rawTemplatePath = $baseTemplateDir . $templateFile;
			$rawTemplateInfo = @stat($rawTemplatePath);
			$this->__cachedTemplatePath = self::GetCacheDir($options) . $templateFile . ".php";
			$cachedTemplateInfo = @stat($this->__cachedTemplatePath);
			
			$useCachedTemplate = $this->getOption("readFromCache", true) && ($cachedTemplateInfo !== false) && ($cachedTemplateInfo['mtime'] > $rawTemplateInfo['mtime']);
						
			if (!$useCachedTemplate) {
				if (!$rawTemplateInfo)
					throw new TierraTemplateException("Template not found: {$rawTemplatePath}");
				$templateContents = @file_get_contents($rawTemplatePath);
				if ($templateContents === false)
					throw new TierraTemplateException("Cannot read template: {$rawTemplatePath}");
					
				$parser = new TierraTemplateParser($templateContents, $templateFile);
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
		}
		
		public static function LoadTemplate($options) {
			return new TierraTemplate($options);
		}
		
		public static function RenderTemplate($options) {
			$template = self::LoadTemplate($options);
			$template->render();
		}
		
		public static function RenderDynamicTemplate($templateContents, $options=array()) {
			list($options["templateFile"], $options["baseTemplateDir"]) = self::SaveDynamicTemplate($templateContents, $options);
			self::RenderTemplate($options);
		}
		
		public static function GetDynamicTemplateOutput($templateContents, $options=array()) {
			list($options["templateFile"], $options["baseTemplateDir"]) = self::SaveDynamicTemplate($templateContents, $options);
			$template = self::LoadTemplate($options);
			return $template->getOutput();
		}
		
		public static function SaveDynamicTemplate($templateContents, $options) {
			$cacheDir = self::GetCacheDir($options);
			
			$templateFile = self::AddTrailingDirectorySeparator(self::StaticGetOption($options, "dynamicTemplateDir", "_dtt")) . "dtt_" . sha1($templateContents) . ".html";
			$templatePath = $cacheDir . $templateFile;
			@mkdir(dirname($templatePath), self::StaticGetOption($options, "cacheDirPerms", 0777), true);
			if (!file_exists($templatePath)) {
				$handle = @fopen($templatePath, "w");
				if ($handle) {
					fwrite($handle, $templateContents);
					fclose($handle);
					@chmod($templatePath,  self::StaticGetOption($options, "cachedTemplatePerms", 0666));
				}
				else
					throw new TierraTemplateException("Cannot create dynamic template: {$templatePath}");			
			}
			
			return array($templateFile, $cacheDir);
		}
		
		public static function StaticGetOption($options, $name, $default=false) {
			return isset($options[$name]) ? $options[$name] : $default;
		}
		
		public static function AddTrailingDirectorySeparator($path) {
			if (substr($path, -1) != DIRECTORY_SEPARATOR)
				$path .= DIRECTORY_SEPARATOR;
			return $path;
		}
		
		public static function GetCacheDir($options) {
			$cacheDir = self::AddTrailingDirectorySeparator(self::StaticGetOption($options, "cacheDir", function_exists("sys_get_temp_dir") ? sys_get_temp_dir() : DIRECTORY_SEPARATOR . "tmp" . DIRECTORY_SEPARATOR));
			if (!is_dir($cacheDir)) {
				@mkdir($cacheDir, self::StaticGetOption($options, "cacheDirPerms", 0777), true);
				if (!is_dir($cacheDir))
					throw new TierraTemplateException("Cache directory cannot be created: {$cacheDir}");
			}
			return $cacheDir;
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
			return self::StaticGetOption($this->__options, $name, $default);
		}
		
		public function render($bufferOutput=true) {
			if ($this->__cachedTemplatePath) {
				if ($bufferOutput)
					ob_start();
				include $this->__cachedTemplatePath;
				if ($bufferOutput) {
					$output = ob_get_contents();
					ob_end_clean();
					echo $output;
				}
			}
		}
		
		public function getOutput() {
			if ($this->__cachedTemplatePath) {
				ob_start();
				include $this->__cachedTemplatePath;
				$output = ob_get_contents();
				ob_end_clean();
				return $output;
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
				
			$this->__options["templateFile"] = $templateFile;
			
			$includedTemplate = self::LoadTemplate($this->__options);
			$includedTemplate->render(false);
		}			
	}
	
	