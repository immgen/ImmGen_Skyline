<?php
	session_start ();

	// Credentials (../config/db_config.php or SKYLINE_DB_* env vars) and the query helpers; see db_connect.php.
	require_once __DIR__ . '/db_connect.php';

	$chatgpt_user_id = skyline_post_string('chatgpt_user_id');
	$user_rating = skyline_post_string('user_rating');
	$suggestion = skyline_post_string('suggestion');
	// Without an id, "WHERE chatgpt_user_id = ''" would overwrite every row that has no id.
	skyline_log_request_ok($chatgpt_user_id);

	$con = skyline_db_connect();

	$sql = "UPDATE `Skyline_ChatGPT_rating_logs` SET `user_rating` = ?, `suggestion` = ? WHERE `chatgpt_user_id` = ?";
	skyline_query($con, $sql, array($user_rating, $suggestion, $chatgpt_user_id), 'Can not update!');

	mysqli_close($con);
?>
