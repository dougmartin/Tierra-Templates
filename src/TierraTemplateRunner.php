<?php
	require_once dirname(__FILE__) . "/TierraTemplate.php";
	
	class TierraTemplateRunner {
		
		public static function Render($uri, $options) {
			
			// validate the options
			if (!isset($options["baseTemplateDir"]))
				self::showError("The 'baseTemplateDir' option is missing, cannot continue.");
			
			// find the template
			if ($uri == "/")
				$uri = "/index.html";
			$templatePath = realpath($options["baseTemplateDir"] . $uri);
			if ($templatePath === false) {
				$templatePath = realpath($options["baseTemplateDir"] . $uri . ".html");
				if ($templatePath === false) {
					$templatePath = realpath($options["baseTemplateDir"] . $uri . "/index.html");
					if ($templatePath) 
						$uri .= "/index.html";
				}
				else
					$uri .= ".html";	
			}
			
			// if the template is not found look for the 404 template
			if ($templatePath === false) {
				header("HTTP/1.0 404 Not Found");
				$fileNotFoundTemplate = isset($options["fileNotFoundTemplate"]) ? $options["fileNotFoundTemplate"] : "/_404.html";
				$templatePath = realpath($options["baseTemplateDir"] . $fileNotFoundTemplate);
				if ($templatePath)
					$uri = $fileNotFoundTemplate;
			}
			
			if ($templatePath !== false) {
				// make sure the template is within the template dir (eg, no .. in the passed uri moved us out of the template dir)
				if (strstr($templatePath, $options["baseTemplateDir"]) != 0)
					self::showError("Requested template is outside the template directory.");
					
				// render the template
				$options["templateFile"] = $uri;
				TierraTemplate::RenderTemplate($options);
			}
			else {
				self::showError("File not found: {$uri}");
			}
		}
		
		public static function showError($error) {
			echo $error;
			exit;
		}
	}