<?php

	/*
	 * 
	 * The builtins are automatically searched first when no virtual dir or class name is given
	 * in a call.  This is accomplished with the following virtual dir setting which is prepended
	 * to the list of virtual dirs:
	 * 
	 * $virtualDirs["_"] = array(
	 *    "path" => dirname(__FILE__) . "/externals/builtin",
	 *    "classPrefix" => "TierraTemplateBuiltinExternals_",
	 *    "functionPrefix" => "TierraTemplateBuiltinExternals_",
	 * );
	 *
	 * The class name must match the classPrefix + filename.  Since no classname is used in a builtin call
	 * it defaults, like all other classless calls, to the class named "index" and then the classPrefix is prepended.  
	 * 
	 * Since the virtual dir for builtins is _ you can also call them like this "long test":_\truncate(7)
	 * but that is considered gauche and all your friends to point and laugh at you.
	 *
	 * As a sample the "link" function is in its own file to show how functions can be on their own.
	 * 
	 */

	class TierraTemplateBuiltinExternals_index {
	
		public static function Truncate($text, $max_length = -1, $more = "...") {
			if (($max_length > -1) && (strlen($text) > $max_length)) {
				$more_length = strlen($more);
				$text_length = $max_length - $more_length;
				$chars = str_split($text);
				while (    ($text_length > 0)
						&& ($chars[$text_length - 1] != " "))
					$text_length--;
				if ($text_length <= 0)
					$text_length = $max_length - $more_length;
				$text = implode("", array_slice($chars, 0, $text_length)) . $more;
			}
			return $text;
		}
		
		public static function TruncateHTML($text, $max_length = -1, $more = "...") {
			$input = self::Truncate($text, $max_length, $more);
	
			// from http://www.the-art-of-web.com/php/truncate/
			$opened = array(); 
			// loop through opened and closed tags in order  
			if (preg_match_all("/<(\/?[a-z]+)>?/i", $input, $matches)) { 
				foreach ($matches[1] as $tag) { 
					if (preg_match("/^[a-z]+$/i", $tag, $regs)) { 
						// a tag has been opened  
						if (strtolower($regs[0]) != 'br') 
							$opened[] = $regs[0]; 
					} 
					elseif (preg_match("/^\/([a-z]+)$/i", $tag, $regs)) { 
						// a tag has been closed  
						unset($opened[array_pop(array_keys($opened, $regs[1]))]); 
					} 
				}
			}
			// close tags that are still open  
			if($opened) { 
				$tagstoclose = array_reverse($opened); 
				foreach ($tagstoclose as $tag) 
					$input .= "</$tag>"; 
			} 
			
			return $input;
		}
		
		public static function DateFormat($date, $format) {
			return date($format, $date);
		}
		
		public static function EscapeQuotes($text) {
			return addcslashes($text, "'" . '"');
		}
	
		public static function EscapeSingleQuotes($text) {
			return addcslashes($text, "'");
		}
	
		public static function EscapeDoubleQuotes($text) {
			return addcslashes($text, '"');
		}
		
		public static function Replace($subject, $search, $replace) {
			return str_replace($search, $replace, $subject);
		}
	
		public static function UTF8($text) {
			// check if it is already unicode
			if ((mb_detect_encoding($text) == "UTF-8") && mb_check_encoding($text, "UTF-8"))
				return $text;
				
			// create a mapping of Windows-1252 codepoints to unicode
			$cp1252_map = array(
				"\xc2\x80" => "\xe2\x82\xac",
				"\xc2\x82" => "\xe2\x80\x9a",
				"\xc2\x83" => "\xc6\x92",    
				"\xc2\x84" => "\xe2\x80\x9e",
				"\xc2\x85" => "\xe2\x80\xa6",
				"\xc2\x86" => "\xe2\x80\xa0",
				"\xc2\x87" => "\xe2\x80\xa1",
				"\xc2\x88" => "\xcb\x86",
				"\xc2\x89" => "\xe2\x80\xb0",
				"\xc2\x8a" => "\xc5\xa0",
				"\xc2\x8b" => "\xe2\x80\xb9",
				"\xc2\x8c" => "\xc5\x92",
				"\xc2\x8e" => "\xc5\xbd",
				"\xc2\x91" => "\xe2\x80\x98",
				"\xc2\x92" => "\xe2\x80\x99",
				"\xc2\x93" => "\xe2\x80\x9c",
				"\xc2\x94" => "\xe2\x80\x9d",
				"\xc2\x95" => "\xe2\x80\xa2",
				"\xc2\x96" => "\xe2\x80\x93",
				"\xc2\x97" => "\xe2\x80\x94",
				"\xc2\x98" => "\xcb\x9c",
				"\xc2\x99" => "\xe2\x84\xa2",
				"\xc2\x9a" => "\xc5\xa1",
				"\xc2\x9b" => "\xe2\x80\xba",
				"\xc2\x9c" => "\xc5\x93",
				"\xc2\x9e" => "\xc5\xbe",
				"\xc2\x9f" => "\xc5\xb8"
			);
			
			// encode the text and then map the codepoints
			return strtr(utf8_encode($text), $cp1252_map);			
		}
		
		public static function nl2brmerge($text) {
			return preg_replace('/(<br[^>]*>\s*){2,}/', '<br/><br/>', nl2br($text));
		}
		
		public static function wrap($text, $start, $end, $find=false) {
			if ($find !== false)
				return preg_replace("/($find)/i", $start . '${1}' . $end, $text);
			else
				return $start . $text . $end;
		}
		
		public static function idify($text, $replaceSpace="_") {
			return strtolower(preg_replace("/[^0-9a-zA-Z\\{$replaceSpace}]/i", "", str_replace(" ", $replaceSpace, $text)));
		}
	
		public static function concat() {
			$args = func_get_args();
			return implode("", $args);
		}
	
		public static function Lorem($count) {
			$words = explode(" ", "lorem ipsum dolor sit amet consectetur adipisicing elit sed do eiusmod tempor incididunt ut labore et dolore magna aliqua ut enim ad minim veniam quis nostrud exercitation ullamco laboris nisi ut aliquip ex ea commodo consequat duis aute irure dolor in reprehenderit in voluptate velit esse cillum dolore eu fugiat nulla pariatur excepteur sint occaecat cupidatat non proident sunt in culpa qui officia deserunt mollit anim id est laborum.");
			shuffle($words);
			$result = array();
			for ($i=0; $i<$count; $i++) {
				$addPeriod = ($i > 0) && (mt_rand(0, 10) == 5);
				$addComma = !$addPeriod && ($i > 0) && (mt_rand(0, 10) == 5);
				$word = $words[mt_rand(0, count($words) - 1)];
				$result[] = $addPeriod ? (". " . ucfirst($word)) : ($addComma ? ", $word" : " $word");
			}
			$result[] = ".";
			return ucfirst(trim(implode("", $result)));
		}
		
		public static function AnyNonEmptyStrings() {
			$result = false;
			foreach (func_get_args() as $arg) {
				if ($arg !== false) {
					if (is_string($arg) && (strlen($arg) > 0)) {
						$result = true;
						break;
					}
				}
			}
			return $result;
		}
			
		public static function AnySet() {
			$result = false;
			foreach (func_get_args() as $arg) {
				$result = self::Set($arg);
				if ($result)
					break;
			}
			return $result;
		}
		
		public static function AllSet() {
			if (func_num_args() == 0)
				return false;
			$result = true;
			foreach (func_get_args() as $arg) {
				$result = self::Set($arg);
				if (!$result)
					break;
			}
			return $result;
		}
		
		public static function Set($var) {
			if (is_array($expression) || is_object($expression))
				return count($expression) > 0;
			if (is_string($expression))
				return strlen($expression) > 0;
			if (is_int($expression))
				return $expression != 0;
			return $expression;
		}
		
		public static function JSON($var) {
			return json_encode($var);
		}
		
		public static function Hashify($var, $index) {
			$hash = array();
			foreach ($var as $item) {
				if (is_array($item) && isset($item[$index]))
					$hash[$item[$index]] = $item;
				else if (is_object($item) && isset($item->$index))
					$hash[$item->$index] = $item;
			}
			return json_encode($hash);
		}
		
		public static function Total($var) {
			return is_array($var) || is_object($var) ? count($var) : 0;
		}
		
		public static function Sum() {
			$sum = 0;
			foreach (func_get_args() as $arg)
				$sum += is_array($arg) ? count($arg) : $arg;
			return $sum;
		}
		
		public static function RandomPick($array, $count=1) {
			if ($count > 1) {
				$count = min($count, count($array));
				$result = array();
				foreach (array_rand($array, $count) as $index)
					$result[] = $array[$index];
				return $result;
			}
			else
				return $array[array_rand($array)];
		}
		
		public static function slice($array, $offset, $length=false) {
			if (!is_array($array))
				return false;
			return $length !== false ? array_slice($array, $offset, $length) : array_slice($array, $offset);
		}	
		
		public static function select($val, $options) {
			return isset($options[$val]) ? $options[$val] : false;
		}
		
		public static function filter($vals, $on, $options) {
			$selected = array();
			foreach ($vals as $val) {
				if (isset($options[$val->$on])) {
					$selected[] = $val;
					foreach ($options[$val->$on] as $name => $value)
						$val->$name = $value;
				}
				else if (in_array($val->$on, $options))
					$selected[] = $val;
			}
			return $selected;
		}
		
		public static function find($val, $options) {
			foreach ($options as $name => $value) {
				if (strstr($val, $name) !== false)
					return $value;
			}
			return false;
		}
		
		public static function FlipACoin() {
			return mt_rand(0,99) <= 50;
		}
		
		public static function Coalesce() {
			$result = false;
			foreach (func_get_args() as $arg) {
				if ($arg) {
					$result = $arg;
					break;
				}
			}
			return $result;
		}
		
		public static function Pluralize($var, $forOne, $forMany) {
			return self::Total($var) == 1 ? $forOne : $forMany;
		}

		
	}

?>
