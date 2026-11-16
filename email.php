<?php
	// "Send Current Plot by Email" on skyline.html. The page POSTs imgdata (base64 PNG), from_email, to_email,
	// msg and gene, and shows "Failed!" when the JSON answer has an "error" field.
	//
	// This used to be an open mail relay: From, To (any number) and the raw HTML body all came from the POST.
	// Now the message always comes from a fixed Skyline sender (the visitor is only the Reply-To), goes to at
	// most SKYLINE_MAIL_MAX_RECIPIENTS checked addresses, carries the visitor's text escaped and size-capped,
	// must contain a real PNG plot, and is rate-limited per IP address and per day.
	use PHPMailer\PHPMailer\PHPMailer;
	use PHPMailer\PHPMailer\Exception;

	require __DIR__ . '/resources/PHPMailer/src/Exception.php';
	require __DIR__ . '/resources/PHPMailer/src/PHPMailer.php';

	// Sender of every Skyline email. Use an address in a domain the web server is allowed to send for;
	// the env var SKYLINE_MAIL_FROM overrides it without editing this file.
	const SKYLINE_MAIL_FROM_DEFAULT = 'no-reply@rstats.immgen.org';
	const SKYLINE_MAIL_FROM_NAME = 'ImmGen Gene Skyline';
	const SKYLINE_MAIL_MAX_RECIPIENTS = 3;
	const SKYLINE_MAIL_MAX_MESSAGE = 2000;          // characters of the visitor's message
	const SKYLINE_MAIL_MAX_IMAGE = 10485760;        // bytes of decoded PNG (10 MB)
	const SKYLINE_MAIL_PER_IP_PER_HOUR = 10;
	const SKYLINE_MAIL_PER_DAY = 200;               // all visitors together

	header('Content-Type: application/json');

	// Answer the page and stop. The page only looks at whether "error" is present, and it has no handler
	// for HTTP errors, so refusals are sent as 200 + {"error": ...} to make it show "Failed!".
	function email_reply($error, $log_message = null) {
		if ($log_message !== null) { error_log('Skyline email.php: ' . $log_message); }
		echo json_encode($error === null ? array('message' => 'Message sent!') : array('error' => $error));
		exit;
	}

	function email_post($name) {
		return (isset($_POST[$name]) && is_string($_POST[$name])) ? $_POST[$name] : '';
	}

	function email_valid_address($address) {
		return strlen($address) <= 254 && filter_var($address, FILTER_VALIDATE_EMAIL) !== false;
	}

	// Count one email for this IP; false when the hourly per-IP or the daily total limit is reached.
	// Only a hash of the IP is kept, in the server's temp folder, for at most a day.
	function email_rate_ok($ip) {
		$fh = @fopen(sys_get_temp_dir() . '/skyline_email_rate.json', 'c+');
		if ($fh === false) { return false; }          // fail closed: no counter, no email
		flock($fh, LOCK_EX);
		$data = json_decode(stream_get_contents($fh), true);
		if (!is_array($data)) { $data = array(); }
		$now = time();
		foreach ($data as $key => $times) {
			$data[$key] = array_values(array_filter((array) $times, function ($t) use ($now) { return is_int($t) && $t > $now - 86400; }));
			if (count($data[$key]) === 0) { unset($data[$key]); }
		}
		$key = hash('sha256', 'skyline-email|' . $ip);
		$ip_last_hour = count(array_filter($data[$key] ?? array(), function ($t) use ($now) { return $t > $now - 3600; }));
		$total_today = array_sum(array_map('count', $data));
		$ok = $ip_last_hour < SKYLINE_MAIL_PER_IP_PER_HOUR && $total_today < SKYLINE_MAIL_PER_DAY;
		if ($ok) {
			$data[$key][] = $now;
			ftruncate($fh, 0);
			rewind($fh);
			fwrite($fh, json_encode($data));
		}
		flock($fh, LOCK_UN);
		fclose($fh);
		return $ok;
	}

	if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { http_response_code(405); email_reply('POST only'); }

	// A browser always sends Origin on a cross-site POST; refuse pages on other sites using this form.
	$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
	$origin_host = is_string($origin) ? parse_url($origin, PHP_URL_HOST) : null;
	$own_host = preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? ''));
	if ($origin !== '' && (!is_string($origin_host) || strcasecmp($origin_host, $own_host) !== 0)) {
		http_response_code(403);
		email_reply('Forbidden');
	}

	// Sender (Reply-To only) and recipients.
	$reply_to = trim(email_post('from_email'));
	if (!email_valid_address($reply_to)) { email_reply('Invalid sender address'); }
	$recipients = array();
	foreach (explode(',', email_post('to_email')) as $address) {
		$address = trim($address);
		if ($address === '') { continue; }
		if (!email_valid_address($address)) { email_reply('Invalid recipient address'); }
		$recipients[strtolower($address)] = $address;
	}
	if (count($recipients) === 0) { email_reply('No recipient'); }
	if (count($recipients) > SKYLINE_MAIL_MAX_RECIPIENTS) { email_reply('At most ' . SKYLINE_MAIL_MAX_RECIPIENTS . ' recipients'); }

	// Gene (subject) and message (plain text, escaped into the HTML body).
	$gene = substr(preg_replace('/[^A-Za-z0-9_.()\-\/ ]/', '', email_post('gene')), 0, 60);
	$msg = email_post('msg');
	if (function_exists('mb_substr')) { $msg = mb_substr($msg, 0, SKYLINE_MAIL_MAX_MESSAGE, 'UTF-8'); } else { $msg = substr($msg, 0, SKYLINE_MAIL_MAX_MESSAGE); }
	$msg_html = nl2br(htmlspecialchars($msg, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));

	// The plot: must decode to a real PNG of sensible size. Kept in memory (no temp file any more).
	$imgdata = email_post('imgdata');
	if (strlen($imgdata) > SKYLINE_MAIL_MAX_IMAGE * 4 / 3 + 4) { email_reply('Image too large'); }
	$png = base64_decode($imgdata, true);
	if ($png === false || strlen($png) === 0 || strlen($png) > SKYLINE_MAIL_MAX_IMAGE || strncmp($png, "\x89PNG\r\n\x1a\n", 8) !== 0) {
		email_reply('Invalid image');
	}
	$info = @getimagesizefromstring($png);
	if ($info === false || $info[2] !== IMAGETYPE_PNG) { email_reply('Invalid image'); }

	// REMOTE_ADDR, not X-Forwarded-For: forwarded headers are set by the client and would bypass the limit.
	if (!email_rate_ok($_SERVER['REMOTE_ADDR'] ?? '')) {
		email_reply('Too many emails; please try again later', 'rate limit reached for ' . ($_SERVER['REMOTE_ADDR'] ?? '?'));
	}

	$from = getenv('SKYLINE_MAIL_FROM');
	if (!is_string($from) || !email_valid_address($from)) { $from = SKYLINE_MAIL_FROM_DEFAULT; }

	try {
		$mail = new PHPMailer(true);
		$mail->CharSet = 'UTF-8';
		$mail->setFrom($from, SKYLINE_MAIL_FROM_NAME);
		$mail->addReplyTo($reply_to);
		foreach ($recipients as $address) { $mail->addAddress($address); }
		$mail->isHTML(true);
		$mail->Subject = 'ImmGen Gene Skyline plot for ' . $gene;
		$sender_html = htmlspecialchars($reply_to, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
		$mail->Body = '<p>' . $msg_html . '</p>'
			. '<p style="color:#666;font-size:12px">Sent by ' . $sender_html . ' using the ImmGen Gene Skyline '
			. '(https://rstats.immgen.org/Skyline/skyline.html). Reply to this email to answer the sender.</p>'
			. '<br><img src="cid:image_cid" alt="Gene Skyline plot">';
		$mail->AltBody = $msg . "\n\nSent by " . $reply_to . " using the ImmGen Gene Skyline (https://rstats.immgen.org/Skyline/skyline.html). The plot is attached as an image.";
		$mail->addStringEmbeddedImage($png, 'image_cid', 'Gene_Skyline.png', 'base64', 'image/png');
		$mail->send();
	} catch (Exception $e) {
		email_reply('Mailer error', $e->getMessage());
	}
	email_reply(null);
