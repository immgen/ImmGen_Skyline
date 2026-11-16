<?php
	// API keys for the Skyline AI summaries. No key is stored in this folder.
	// Order: an environment variable (e.g. Apache SetEnv) wins; otherwise the PHP file named by
	// SKYLINE_API_KEYS_CONFIG, or ../config/ai_api_keys.php, is loaded. That file must return
	// array('openai_api_key' => ..., 'claude_api_key' => ...).
	// Requesting this file directly only defines the functions below and prints nothing.

	// Environment variable checked first for each key name.
	function skyline_api_key_env_names() {
		return array('openai_api_key' => 'HUIT_OPENAI_API_KEY', 'claude_api_key' => 'HUIT_BEDROCK_API_KEY');
	}

	function skyline_api_key($name) {
		$env_names = skyline_api_key_env_names();
		if (isset($env_names[$name])) {
			$value = getenv($env_names[$name]);
			if (($value === false || $value === '') && isset($_SERVER[$env_names[$name]])) { $value = $_SERVER[$env_names[$name]]; }
			if (is_string($value) && $value !== '') { return $value; }
		}
		$config_file = getenv('SKYLINE_API_KEYS_CONFIG');
		if ($config_file === false || $config_file === '') { $config_file = dirname(__DIR__) . '/config/ai_api_keys.php'; }
		if (is_readable($config_file)) {
			$config = include $config_file;
			if (is_array($config) && isset($config[$name]) && is_string($config[$name]) && $config[$name] !== '') {
				return $config[$name];
			}
		}
		// No key: log it (never the key itself) and stop with a short message for the page.
		error_log('Skyline ' . basename($_SERVER['SCRIPT_NAME'] ?? 'script') . ": no $name (set the env var or create ../config/ai_api_keys.php)");
		http_response_code(500);
		echo 'AI service is not configured.';
		exit;
	}
	// No closing tag on purpose: nothing may be printed before the scripts' own output.
