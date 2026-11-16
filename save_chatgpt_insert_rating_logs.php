<?php

	session_start ();

	// Credentials (../config/db_config.php or SKYLINE_DB_* env vars) and the query helpers; see db_connect.php.
	require_once __DIR__ . '/db_connect.php';

	$chatgpt_user_id = skyline_post_string('chatgpt_user_id');
	skyline_log_request_ok($chatgpt_user_id);

	function get_ip_address(){
	    foreach (array('HTTP_CLIENT_IP', 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_FORWARDED', 'HTTP_X_CLUSTER_CLIENT_IP', 'HTTP_FORWARDED_FOR', 'HTTP_FORWARDED', 'HTTP_X_REAL_IP', 'REMOTE_ADDR') as $key){
	        if (array_key_exists($key, $_SERVER) === true){
	            foreach (explode(',', $_SERVER[$key]) as $ip){
	                $ip = trim($ip); // just to be safe

	                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false){
	                    return $ip;
	                }
	            }
	        }
	    }
	    return null;
	}
	$ip = get_ip_address();
	// The reverse-DNS name is chosen by whoever controls the IP's PTR record, so it is bound like any other input.
	$hostname = ($ip !== false && $ip !== null) ? gethostbyaddr($ip) : '';

	$con = skyline_db_connect();

	$sql_user = "INSERT INTO `Skyline_ChatGPT_rating_logs` (`chatgpt_user_id`, `user_rating`, `suggestion`, `ip_address`, `host_name`) VALUES (?, '', '', ?, ?)";
	skyline_query($con, $sql_user, array($chatgpt_user_id, (string) $ip, (string) $hostname), 'Can not insert!');

	mysqli_close($con);
?>
