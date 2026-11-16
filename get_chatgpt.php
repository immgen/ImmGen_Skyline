<?php
	session_start();    
	//$genesymbol = $_POST['probeset_id'];
	//$followup_question = $_POST['followup_question'];
	$chatgpt_all_outputs = $_POST['chatgpt_all_outputs'] ?? array();
	
	// HUIT Harvard API Portal key: env HUIT_OPENAI_API_KEY, else ../config/ai_api_keys.php; see api_keys.php.
	require_once __DIR__ . '/api_keys.php';
	$openai_api_key = skyline_api_key('openai_api_key');
	
	$ch = curl_init();
	// HUIT v2 endpoint (v1 /ais-openai-direct-limited-schools/v1 is retired on 2026-11-30); same API key.
	curl_setopt($ch, CURLOPT_URL, 'https://go.apis.huit.harvard.edu/ais-openai-direct-comdev/v2/chat/completions');
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
	// Enable automatic decompression (handles gzip/deflate responses)
	curl_setopt($ch, CURLOPT_ENCODING, '');
	curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $openai_api_key,
	]);	
	
	// Build messages array properly with JSON encoding
	$messages = [
		[
			"role" => "system",
			"content" => "You are a helpful assistant."
		]
	];
	
	$count = 0;
	if(is_array($chatgpt_all_outputs)) {
		foreach ($chatgpt_all_outputs as $value) {
			// Ensure proper escaping by using json_encode for content
			if($count % 2 == 0){
				$messages[] = [
					"role" => "user",
					"content" => $value . " Please summarize within 200 words in one paragraph."
				];
			} else {
				$messages[] = [
					"role" => "assistant", 
					"content" => $value
				];
			}
			$count = $count + 1;
		}
	}
	
	$payload = [
		"model" => "gpt-4o-mini",
		"messages" => $messages,
		"top_p" => 0.5,
		"max_tokens" => 500,
		"frequency_penalty" => 0.1,
		"presence_penalty" => 0.1
	];
	
	$postData = json_encode($payload);
	
	// Check if JSON encoding was successful
	if (json_last_error() !== JSON_ERROR_NONE) {
		echo "JSON Encoding Error: " . json_last_error_msg() . "\n";
		echo "Data: " . print_r($chatgpt_all_outputs, true);
		exit;
	}
	
	curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);

	$response = curl_exec($ch);
	$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$curlError = curl_error($ch);
	curl_close($ch);
	
	// Debug output
	if ($curlError) {
		echo "cURL Error: " . $curlError;
		exit;
	}
	
	if ($httpCode !== 200) {
		echo "HTTP Error: " . $httpCode . "\n";
		echo "Response: " . $response;
		exit;
	}
	
	$d = json_decode($response);
	
	if (json_last_error() !== JSON_ERROR_NONE) {
		echo "JSON Error: " . json_last_error_msg() . "\n";
		echo "Raw Response: " . $response;
		exit;
	}
	
	if (!isset($d->choices[0]->message->content)) {
		echo "Unexpected response structure:\n";
		echo "Response: " . $response;
		exit;
	}
	
	echo($d->choices[0]->message->content);
?>
