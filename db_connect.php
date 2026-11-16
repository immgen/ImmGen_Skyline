<?php
	// Shared database helpers for the Skyline scripts. No credentials are stored in this folder.
	// As in Gene Constellation, environment variables (e.g. Apache SetEnv) win; otherwise the PHP file named by
	// SKYLINE_DB_CONFIG, or ../config/db_config.php, is loaded. That file must return
	// array('host' => ..., 'user' => ..., 'pass' => ..., 'db' => ...).
	// Every value that comes from a visitor is passed to MySQL as a bound parameter (skyline_query), and
	// population names used as column names are checked against the table's real columns first.
	// Requesting this file directly only defines the functions below and prints nothing.

	function skyline_env_value($name) {
		$value = getenv($name);
		if ($value === false && isset($_SERVER[$name])) { $value = $_SERVER[$name]; }
		return $value;
	}

	function skyline_load_db_config() {
		if (skyline_env_value('SKYLINE_DB_USER') !== false) {
			return array('host' => skyline_env_value('SKYLINE_DB_HOST'), 'user' => skyline_env_value('SKYLINE_DB_USER'), 'pass' => skyline_env_value('SKYLINE_DB_PASS'), 'db' => skyline_env_value('SKYLINE_DB_NAME'));
		}
		$config_file = skyline_env_value('SKYLINE_DB_CONFIG');
		if ($config_file === false || $config_file === '') { $config_file = dirname(__DIR__) . '/config/db_config.php'; }
		if (!is_readable($config_file)) { return null; }
		$config = include $config_file;
		return is_array($config) ? $config : null;
	}

	// Stop the request with a short JSON error. $log_message (if any) goes to the server log only, never to the browser.
	function skyline_fail($status, $public_message, $log_message = null) {
		if ($log_message !== null) {
			error_log('Skyline ' . basename($_SERVER['SCRIPT_NAME'] ?? 'script') . ': ' . $log_message);
		}
		http_response_code($status);
		header('Content-Type: application/json');
		echo json_encode(array('error' => $public_message));
		exit;
	}

	function skyline_db_fail($log_message) {
		skyline_fail(500, 'Can not connect.', $log_message);
	}

	// Open the connection, or stop the request with a 500.
	function skyline_db_connect() {
		$db_config = skyline_load_db_config();
		if ($db_config === null) {
			skyline_db_fail('no database configuration (set SKYLINE_DB_* env vars or create ../config/db_config.php)');
		}
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
		try {
			return mysqli_connect($db_config['host'], $db_config['user'], $db_config['pass'], $db_config['db']);
		} catch (mysqli_sql_exception $e) {
			skyline_db_fail($e->getMessage());
		}
	}

	// Read a POST field as a string. Missing gives ''; anything else (e.g. gene[]=...) is rejected.
	function skyline_post_string($name) {
		if (!isset($_POST[$name])) { return ''; }
		if (!is_string($_POST[$name])) { skyline_fail(400, "Invalid parameter: $name"); }
		return $_POST[$name];
	}

	// Logging scripts only: ignore anything that is not the page's POST, or that lacks the value identifying the
	// entry (gene, chatgpt_user_id). Crawlers and bots opening save_logs.php left ~480 empty rows in
	// Skyline_query_logs in 2026 alone. Answers 204 (no content) and writes nothing.
	function skyline_log_request_ok($required_value) {
		if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' || trim($required_value) === '') {
			http_response_code(204);
			exit;
		}
	}

	// Run $sql with every value in $params bound as a string. Returns a buffered mysqli_result for statements
	// that return rows, true otherwise. Any MySQL error is logged and answered with a 500.
	function skyline_query($con, $sql, $params = array(), $public_message = 'Query failed.') {
		try {
			$stmt = mysqli_prepare($con, $sql);
			if (count($params) > 0) {
				$params = array_map('strval', array_values($params));
				mysqli_stmt_bind_param($stmt, str_repeat('s', count($params)), ...$params);
			}
			mysqli_stmt_execute($stmt);
			$result = mysqli_stmt_field_count($stmt) > 0 ? mysqli_stmt_get_result($stmt) : true;
			mysqli_stmt_close($stmt);
			return $result;
		} catch (mysqli_sql_exception $e) {
			skyline_fail(500, $public_message, $e->getMessage());
		}
	}

	// "(?, ?, ...)" for an IN list, plus the values to bind. An empty list becomes IN ('') (matches nothing),
	// which is what the old string-built queries did for an empty population parameter.
	function skyline_in_list($values) {
		$values = array_values($values);
		if (count($values) === 0) { $values = array(''); }
		if (count($values) > 5000) { skyline_fail(400, 'Too many populations'); }
		return array('(' . implode(', ', array_fill(0, count($values), '?')) . ')', $values);
	}

	// Backtick-quote a table or column name. Only use it on names that come from this code or were checked
	// against skyline_table_columns().
	function skyline_ident($name) {
		return '`' . str_replace('`', '``', $name) . '`';
	}

	// Column names of a table in the current database, in table order.
	function skyline_table_columns($con, $table) {
		$result = skyline_query($con, "SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ? ORDER BY ORDINAL_POSITION", array($table));
		$columns = array();
		while ($r = mysqli_fetch_row($result)) { $columns[] = $r[0]; }
		return $columns;
	}

	// Prepared statements return INT/FLOAT/DOUBLE columns as PHP numbers, while the old mysqli_query() calls
	// returned every column as a string. Convert back so the JSON and CSV output stay the same
	// (same conversion as Gene Constellation's get_data.php).
	function skyline_float_to_text($v) {
		if ($v == 0) { return '0'; }
		if (!is_finite($v)) { return (string) $v; }
		// Shortest mantissa that round-trips to the same double.
		for ($p = 0; $p <= 16; $p++) {
			$s = sprintf('%.' . $p . 'e', $v);
			if ((float) $s == $v) { break; }
		}
		if (!preg_match('/^(-?)(\d)(?:\.(\d*))?e([+-]?\d+)$/i', $s, $m)) { return $s; }
		$sign = $m[1];
		$digits = rtrim($m[2] . (isset($m[3]) ? $m[3] : ''), '0');
		if ($digits === '') { $digits = '0'; }
		$exp = intval($m[4]);
		if ($exp < -6 || $exp >= 15) {
			return $sign . $digits[0] . (strlen($digits) > 1 ? '.' . substr($digits, 1) : '') . 'e' . $exp;
		}
		if ($exp < 0) {
			return $sign . '0.' . str_repeat('0', -$exp - 1) . $digits;
		}
		$int_part = substr(str_pad($digits, $exp + 1, '0'), 0, $exp + 1);
		$frac_part = substr($digits, $exp + 1);
		return $sign . $int_part . ($frac_part !== '' && $frac_part !== false ? '.' . $frac_part : '');
	}

	// One number as MySQL's text protocol would print it, using the column's metadata: FLOAT(M,D)/DOUBLE(M,D)
	// keep D decimals (1.50, not 1.5) and ZEROFILL columns keep their leading zeros. Plain FLOAT/DOUBLE report
	// 31 decimals ("not fixed") and use the shortest round-trip form.
	function skyline_number_text($v, $field) {
		if (is_int($v)) {
			$s = (string) $v;
		} else if ($field !== null && $field->decimals > 0 && $field->decimals < 31 && is_finite($v)) {
			$s = sprintf('%.' . $field->decimals . 'F', $v);
		} else {
			$s = skyline_float_to_text($v);
		}
		if ($field !== null && ($field->flags & MYSQLI_ZEROFILL_FLAG) && $s !== '' && $s[0] !== '-') {
			$s = str_pad($s, $field->length, '0', STR_PAD_LEFT);
		}
		return $s;
	}

	// mysqli_fetch_array() with numbers turned back into strings. $mode is MYSQLI_NUM, MYSQLI_ASSOC or MYSQLI_BOTH.
	function skyline_fetch_text_row($result, $mode) {
		$row = mysqli_fetch_array($result, $mode);
		if (!is_array($row)) { return null; }
		$fields = mysqli_fetch_fields($result);
		$fields_by_name = array();
		foreach ($fields as $f) { $fields_by_name[$f->name] = $f; }   // duplicate names: last wins, as in the row
		foreach ($row as $k => $v) {
			if (is_int($v) || is_float($v)) {
				$field = is_int($k) ? ($fields[$k] ?? null) : ($fields_by_name[$k] ?? null);
				$row[$k] = skyline_number_text($v, $field);
			}
		}
		return $row;
	}
	// No closing tag on purpose: stray whitespace after it would break the CSV download headers.
