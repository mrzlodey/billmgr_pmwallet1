<?php

error_reporting(0);

$log_file = fopen("/usr/local/mgr5/var/". __MODULE__ .".log", "a");

function tmErrorHandler($errno, $errstr, $errfile, $errline) {
	global $log_file;
	fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ERROR: ". $errno .": ". $errstr .". In file: ". $errfile .". On line: ". $errline ."\n");
	return true;
}

$default_xml_string = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n<doc/>\n";

set_error_handler("tmErrorHandler");

function Debug($str) {
	global $log_file;
	fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ". __MODULE__ ." [DEBUG] ". $str ."\n");
}

function Error($str) {
	global $log_file;
	fwrite($log_file, date("M j H:i:s") ." [". getmypid() ."] ". __MODULE__ ." [ERROR] ". $str ."\n");
}

function LocalQuery($function, $param, $auth = NULL) {
	$cmd = "/usr/local/mgr5/sbin/mgrctl -m billmgr -o xml " . escapeshellarg($function) . " ";
	foreach ($param as $key => $value) {
		$cmd .= escapeshellarg($key) . "=" . escapeshellarg($value);
	}

	if (!is_null($auth)) {
		$cmd .= " auth=" . escapeshellarg($auth);
	}

	$out = array();
	exec($cmd, $out);
	$out_str = "";
	foreach ($out as $value) {
		$out_str .= $value . "\n";
	}

	Debug("mgrctl out: ". $out_str);

	return simplexml_load_string($out_str);
}

function CgiInput($skip_auth = false) {
	if ($_SERVER["REQUEST_METHOD"] == 'POST'){
		$fp = fopen('php://stdin','r');
		stream_set_timeout($fp,500);
		$input = fread($fp, 4096);
		fclose($fp);
	} elseif ($_SERVER["REQUEST_METHOD"] == 'GET'){
		$input = $_SERVER["QUERY_STRING"];
	}

	$param = array();
	parse_str($input, $param);

	if ($skip_auth == false && (!array_key_exists("auth", $param) || $param["auth"] == "")) {
		if (array_key_exists("billmgrses5", $_COOKIE)) {
			$cookies_bill = $_COOKIE["billmgrses5"];
			$param["auth"] = $cookies_bill;
		} elseif (array_key_exists("HTTP_COOKIE", $_SERVER)) {
			$cookies = explode("; ", $_SERVER["HTTP_COOKIE"]);
			foreach ($cookies as $cookie) {
				$param_line = explode("=", $cookie);
				if (count($param_line) > 1 && $param_line[0] == "billmgrses5") {
					$cookies_bill = explode(":", $param_line[1]);
					$param["auth"] = $cookies_bill[0];
				}
			}
		}

		Debug("auth: " . $param["auth"]);
	}

	if ($skip_auth == false) {
		Debug("auth: " . $param["auth"]);
	}
	return $param;
}

function ClientIp() {
	$client_ip = "";

	if (array_key_exists("HTTP_X_REAL_IP", $_SERVER)) {
		$client_ip = $_SERVER["HTTP_X_REAL_IP"];
	}
	if ($client_ip == "" && array_key_exists("REMOTE_ADDR", $_SERVER)) {
		$client_ip = $_SERVER["REMOTE_ADDR"];
	}

	Debug("client_ip: " . $client_ip);

	return $client_ip;
}

class Error extends Exception
{
	private $m_object = "";
	private $m_value = "";
	private $m_param = "";
	function __construct($message, $object = "", $value = "", $param = array()) {
		parent::__construct($message);
		$this->m_object = $object;
		$this->m_value = $value;
		$this->m_param = $param;
		$error_msg = "Error: ". $message;
		if ($this->m_object != "")
		{
			$error_msg .= ". Object: ". $this->m_object;
		}
		if ($this->m_value != "")
		{
			$error_msg .= ". Value: ". $this->m_value;
		}
		Error($error_msg);
	}
}

?>
