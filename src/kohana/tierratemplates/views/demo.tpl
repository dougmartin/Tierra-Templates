[# this is how you specify the parent template #]
[@ extends "/_page.tpl" @]

[# <@ ... @> tags are used to delimit one or more statements, seperated by semi-colons #] 
<@ pageTitle = "Test Page" @>

[# this will add the style to the parent pageStyle block #]
[@ prepend pageStyle @]

	div#message {
		color: #f00;
	}
	
[@ end pageStyle @]


[@ start content @]
	[# this is a "conditerator"  #]
	{@ message ? `<div id='message'>{$}</div>` @}
[@ end content @]
