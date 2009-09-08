<!DOCTYPE html PUBLIC "-//W3C//DTD HTML 4.01//EN"
   "http://www.w3.org/TR/html4/strict.dtd">

<html lang="en">
<head>
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8">
	<title>{@ pageTitle ? $ else "Untitled" @}</title>
	<meta name="generator" content="TextMate http://macromates.com/">
	<meta name="author" content="Gerard Gualberto">
	<style>
	[@ prepend pageStyle @]
	
		body {
			color: #000;
		}
		
	[@ end pageStyle @]
	</style>
</head>
<body>
	[# this is the main block that the children of this template will override #]
	[@ start content @]
		this is the default content
	[@ end content @]
</body>
</html>
