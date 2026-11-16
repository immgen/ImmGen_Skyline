<?php
	session_start ();

	// Credentials (../config/db_config.php or SKYLINE_DB_* env vars) and the query helpers; see db_connect.php.
	require_once __DIR__ . '/db_connect.php';

	$gene = skyline_post_string('gene');
	$prompt = skyline_post_string('prompt');
	$chatgpt_output = skyline_post_string('chatgpt_output');
	$chatgpt_user_id = skyline_post_string('chatgpt_user_id');
	skyline_log_request_ok($chatgpt_user_id);

	$con = skyline_db_connect();

	// All four values are bound parameters (prompt and output used to be escaped by hand; the stored text is the same).
	$sql = "INSERT INTO `Skyline_ChatGPT_chat_logs` (`query_gene`, `prompt`, `ChatGPT_output`, `chatgpt_user_id`) VALUES (?, ?, ?, ?)";
	skyline_query($con, $sql, array($gene, $prompt, $chatgpt_output, $chatgpt_user_id), 'Can not insert!');

	mysqli_close($con);
?>
