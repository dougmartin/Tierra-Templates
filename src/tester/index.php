<?php
	include "../TierraTemplate.php";
	
	$output = "";
	$code = isset($_POST["code"]) ? $_POST["code"] : false;
	if ($code !== false) {
		$output = "<div id='results-body'>" . TierraTemplate::GetDynamicTemplateOutput($code) . "</div>";
	}
?>
<html>
	<head>
		<title>Tierra Templates Tester</title>
		<style>
			body {
				margin: 0;
				padding: 0;
				font-family: "Lucida Grande", "Lucida Sans Unicode", Arial, sans-serif;
				font-size: 14px;
			}
			#header {
				margin: 10px 0 0 10px;
			}
			#input,
			#output {
				margin: 10px;
			}
			textarea#code {
				height: 700px;
				width: 100%;
			}
			#results-body {
				border: 1px solid #000;
				background: #eee;
				padding: 10px;
			}
		</style>
	</head>
	<body>
		<form name="template" method="post">
			<table width="100%" height="100%">
				<tr>
					<td width="50%" valign="top">
						<div id="header">
							Enter your template code and click <input type="submit" name="run" value="run"/> to see the results on the right.
						</div>
					</td>
				</tr>
				<tr>
					<td valign="top">
						<div id="input">
							<textarea id="code" name="code"><?php echo $code ?></textarea>
						</div>
					</td>
					<td valign="top">
						<div id="output">
							<?php echo $output ?>
						</div>
					</td>
				</tr>
			</table>
		</form>
		<script>
			document.template.code.focus();
		</script>
	</body>
</html>