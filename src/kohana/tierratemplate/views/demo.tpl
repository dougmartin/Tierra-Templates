[@ extends "/_page.tpl" @]

<@ pageTitle = "Test Page" @>

[@ prepend pageStyle @]

	div#message {
		color: #f00;
	}
	
[@ end pageStyle @]


[@ start content @]
	{@ message ? `<div id='message'>{$}</div>` @}
[@ end content @]
