<?php

	// Credentials (../config/db_config.php or SKYLINE_DB_* env vars) and the query helpers; see db_connect.php.
	require_once __DIR__ . '/db_connect.php';

	$gene = skyline_post_string('gene');
	$datagroup = skyline_post_string('datagroup');
	$populations = skyline_post_string('populations');
	$key_population = skyline_post_string('key_population');
	$search_type = skyline_post_string('search_type');
	skyline_log_request_ok($gene);

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

	$query_populations = ($key_population == 'Reference populations') ? 'Reference' : $populations;
	$sql = "INSERT INTO `Skyline_query_logs` (`query_gene`, `query_populations`, `datagroup`, `search_type`, `ip_address`, `host_name`) VALUES (?, ?, ?, ?, ?, ?)";
	skyline_query($con, $sql, array($gene, $query_populations, $datagroup, $search_type, (string) $ip, (string) $hostname), 'Can not insert!');
	mysqli_close($con);
?>
