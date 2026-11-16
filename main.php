<?php
	$gene = "None";
	$datagroup = "None";
	
	// Only plain strings are accepted (gene[]=... is ignored). skyline.html must print these with skyline_js_string().
	if(isset($_GET['gene']) && is_string($_GET['gene'])){
		$gene = $_GET['gene'];
	}

	if(isset($_GET['datagroup']) && is_string($_GET['datagroup'])){
		$datagroup = $_GET['datagroup'];
	}

	// A value as a quoted JavaScript string literal that is also safe inside an HTML <script> block:
	// quotes, backslashes, <, >, & and line breaks are all escaped, so the visitor's text can't end the
	// string or the script tag (reflected XSS via ?gene= / ?datagroup=).
	function skyline_js_string($value) {
		$json = json_encode((string) $value, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
		return $json === false ? '""' : $json;
	}
?>
