<?php
	session_start();
	$chatgpt_all_outputs = $_POST['chatgpt_all_outputs'] ?? array();

	// HUIT Harvard API Portal key: env HUIT_BEDROCK_API_KEY, else ../config/ai_api_keys.php; see api_keys.php.
	require_once __DIR__ . '/api_keys.php';
	$bedrock_api_key = skyline_api_key('claude_api_key');

	$model_id = 'us.anthropic.claude-sonnet-4-6';

	// Build messages in Bedrock Claude format.
	// The page sends the history as a flat list that alternates question/answer and always ends with the new
	// question. Roles are counted back from the end, so the last entry is always 'user': Claude Sonnet 4.6
	// rejects a conversation ending with an assistant message ("does not support assistant message prefill"),
	// which is what happened when an older page pushed two answers per question in "Both" mode.
	// Claude also needs the first message to be 'user' and every text block to be non-empty.
	$messages = [];
	$history = is_array($chatgpt_all_outputs) ? array_values(array_filter($chatgpt_all_outputs, 'is_string')) : array();
	$n = count($history);
	foreach ($history as $i => $value) {
		$is_user = (($n - 1 - $i) % 2 == 0);
		if (count($messages) === 0 && !$is_user) { continue; }
		if (trim($value) === '') { $value = '(no answer)'; }
		$messages[] = [
			'role' => $is_user ? 'user' : 'assistant',
			'content' => [
				['type' => 'text', 'text' => $is_user ? $value . ' Please summarize within 200 words in one paragraph.' : $value]
			]
		];
	}

	$payload = [
		'anthropic_version' => 'bedrock-2023-05-31',
		'max_tokens' => 800,
		'messages' => $messages
	];

	$postData = json_encode($payload);

	if (json_last_error() !== JSON_ERROR_NONE) {
		echo "JSON Encoding Error: " . json_last_error_msg() . "\n";
		echo "Data: " . print_r($chatgpt_all_outputs, true);
		exit;
	}

	$base_url = 'https://apis.huit.harvard.edu/ais-bedrock-llm/v2/model/';
	$url = $base_url . $model_id . '/invoke';
	list($response, $httpCode, $curlError) = bedrock_invoke($url, $bedrock_api_key, $postData);

	// Some gateways reject the colon in the model id; retry once with it encoded.
	if (!$curlError && $httpCode === 404) {
		$url = $base_url . str_replace(':', '%3A', $model_id) . '/invoke';
		list($response, $httpCode, $curlError) = bedrock_invoke($url, $bedrock_api_key, $postData);
	}

	if ($curlError) {
		echo "cURL Error: " . $curlError;
		exit;
	}
	if ($httpCode !== 200) {
		echo "HTTP Error: " . $httpCode . "\n";
		echo "Response: " . $response;
		exit;
	}

	$d = json_decode($response, true);
	if (json_last_error() !== JSON_ERROR_NONE) {
		echo "JSON Error: " . json_last_error_msg() . "\n";
		echo "Raw Response: " . $response;
		exit;
	}

	// Anthropic returns content as an array of blocks; concatenate text
	$outputText = '';
	if (isset($d['content']) && is_array($d['content'])) {
		foreach ($d['content'] as $block) {
			if (isset($block['type']) && $block['type'] === 'text' && isset($block['text'])) {
				$outputText .= $block['text'];
			}
		}
	}
	if ($outputText === '') {
		echo "Unexpected response structure:\n";
		echo $response;
		exit;
	}

	echo $outputText;

	function bedrock_invoke($url, $api_key, $postData) {
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
		curl_setopt($ch, CURLOPT_ENCODING, '');
		curl_setopt($ch, CURLOPT_HTTPHEADER, [
			'Content-Type: application/json',
			'x-api-key: ' . $api_key
		]);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

		$response = curl_exec($ch);
		$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlError = curl_error($ch);
		curl_close($ch);

		return [$response, $httpCode, $curlError];
	}
?>
