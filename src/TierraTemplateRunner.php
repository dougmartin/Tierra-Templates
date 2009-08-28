<?php
	require_once dirname(__FILE__) . "/TierraTemplate.php";
	require_once dirname(__FILE__) . "/TierraTemplateRequest.php";
	
	class TierraTemplateRunner {
		
		private static $options = false;
		private static $startingUri = false;
		private static $finalUri = false;
		private static $request = false;
		private static $templatePath = false;
		
		public static function Render($uri, $options) {
			
			self::$startingUri = $uri;
			self::$finalUri = $uri;
			self::$options = $options;
			
			self::runHook("onStartup");
			
			if ($filteredOptions = self::runHook("filterOptions", self::$options))
				self::$options = $filteredOptions;
				  
			self::$request = new TierraTemplateRequest(self::$options);
				
			// validate the options
			if (!isset(self::$options["baseTemplateDir"]))
				self::showError("The 'baseTemplateDir' option is missing, cannot continue.");
				
			if ($filteredUri = self::runHook("filterUri", self::$startingUri))
				self::$finalUri = $filteredUri;  
				
			// find the template
			self::$templatePath = self::getRealpath(self::$options["baseTemplateDir"] . self::$finalUri);
			if (self::$templatePath && is_dir(self::$templatePath)) {
				if (substr(self::$finalUri, -1) == "/") {
					self::$templatePath = self::getRealpath(self::$options["baseTemplateDir"] . self::$finalUri . "index.html");
					if (self::$templatePath)
						self::$finalUri .= "index.html";	
				}
				else {
					header("Location: " . self::$finalUri . "/");
					exit;
				}
			}
			else if (!self::$templatePath) {
				self::$templatePath = self::getRealpath(self::$options["baseTemplateDir"] . self::$finalUri . ".html");
				if (self::$templatePath)
					self::$finalUri .= ".html";	
			}
			
			if ($filteredTemplatePath = self::runHook("filterTemplatePath", self::$templatePath))
				self::$templatePath = self::getRealpath($filteredTemplatePath);
			
			// if the template is not found look for the 404 template
			if (self::$templatePath === false) {
				if (!self::runHook("onSet404Header"))
					header("HTTP/1.0 404 Not Found");
				$fileNotFoundTemplate = isset(self::$options["fileNotFoundTemplate"]) ? self::$options["fileNotFoundTemplate"] : "/_404.html";
				if ($filteredFileNotFoundTemplate = self::runHook("filterFileNotFoundTemplate", $fileNotFoundTemplate))
					$fileNotFoundTemplate = $filteredFileNotFoundTemplate;
				self::$templatePath = self::getRealpath(self::$options["baseTemplateDir"] . $fileNotFoundTemplate);
				if (self::$templatePath)
					self::$finalUri = $fileNotFoundTemplate;
			}
			
			if (self::$templatePath !== false) {
				// make sure the template is within the template dir (eg, no .. in the passed uri moved us out of the template dir)
				if (strstr(self::$templatePath, self::$options["baseTemplateDir"]) != 0)
					self::showError("Requested template is outside the template directory.");
					
				self::runHook("onPreOutput");
					
				// render the template
				self::$options["requestObject"] = self::$request;
				self::$options["templateFile"] = self::$finalUri;
				$output = TierraTemplate::GetTemplateOutput(self::$options);
				if ($filteredOutput = self::runHook("filterOutput", $output))
					$output = $filteredOutput;
					
				// output the template
				if (!self::runHook("output", $output))
					echo $output;
				
				self::runHook("onPostOutput");
			}
			else {
				if (!self::runHook("onFileNotFound"))
					self::showError("File not found: " . self::$startingUri);
			}
			
			self::runHook("onShutdown");
		}
		
		public static function showError($error) {
			if (!self::runHook("error")) {
				echo $error;
				exit;
			}
		}
		
		public static function runHook($name) {
			if (isset(self::$options["runnerHooks"][$name])) {
				$params = func_get_args();
				$params[0] = (object) array(
					"startingUri" => self::$startingUri,
					"finalUri" => self::$finalUri,
					"request" => self::$request,
					"options" => self::$options,
					"templatePath" => self::$templatePath,
				);
				return @call_user_func_array(self::$options["runnerHooks"][$name], $params);
			}
			return false;
		}
		
		public static function getRealPath($path) {
			$realPath = realpath($path);
			return $realPath && file_exists($realPath) ? $realPath : false;
		}
		
	}