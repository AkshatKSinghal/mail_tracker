<?php

namespace Model;

class Token
{

	function __construct($id = null)
	{
		$this->db = new mysqli("localhost", "root", "");
		if ($this->db->connect_error || !mysqli_select_db($this->db, "mail_tracker")) {
			throw new Exception("Unable to connect to database", 101);
		}
		if (!empty($id)) {
			$this->id = $id;
		}
	}

	/**
	 * Function to generate Tracking Token
	 * @param int $groupId Group ID
	 * @param string $meta JSON String
	 * @return int Token ID
	 */
	public function generate($groupId = null, $meta = null)
	{
		$meta = $this->mysqlEscape($meta);
		$query = "INSERT INTO tokens (group_id, meta) VALUES ($groupId, '$meta')";
		$this->db->query($query);
		return mysqli_insert_id($this->db);
	}

	/**
	 * Function to record open event of token
	 * @param string $ip IP of client
	 * @param string $userAgent User Agent of client
	 * @param int $time Time of open event
	 * 
	 * @throws Exception In case the token is invalid/ not being tracked
	 * 
	 * @return void
	 */
	public function track($ip, $userAgent, $time)
	{	
		$uniqueUserString = $this->uniqueUserString($userAgent);
		$count = $this->incrementCache($this->id . $uniqueUserString . $time, 1);
		if ($count > 1) {
			throw new Exception("Duplicate open tracked");
		}
		$ip = $this->mysqlEscape($ip);
		$userAgent = $this->mysqlEscape($userAgent);
		$query = "INSERT INTO tracking_events(token_id, ip, user_agent, time) VALUES ($this->id, '$ip', '$userAgent', FROM_UNIXTIME($time))";
		$this->db->query($query);
	}

	/**
	 * Function to retrieve the stats of a token
	 * @param bool $details Flag to enable sending of details
	 * @param int $page Page number for the paginated details list
	 * @return array
	 */
	public function stats($details = false, $page = 0)
	{
		$query = "SELECT tokens.id AS token, tokens.created, tokens.meta, tokens.group_id, UNIX_TIMESTAMP(A.time) AS first_open_time, A.ip AS first_open_ip, A.user_agent AS first_open_ua, UNIX_TIMESTAMP(B.time) AS last_open_time, B.ip AS last_open_ip, B.user_agent AS last_open_ua, token_summaries.total_opens, token_summaries.unique_opens FROM tokens LEFT JOIN token_summaries ON tokens.id = token_summaries.token_id LEFT JOIN tracking_events A ON token_summaries.first_open = A.id LEFT JOIN tracking_events B ON token_summaries.last_open = B.id WHERE tokens.id = $this->id";
		$result = $this->db->query($query);
		if ($result->num_rows == 0) {
			throw new Exception("Token Not Found");
		}
		$response = $result->fetch_assoc();
		$response['first_open'] = [
			'ip' => $response['first_open_ip'],
			'time' => $response['first_open_time'],
			'user_agent' => $response['first_open_ua']
		];
		unset($response['first_open_ip']);
		unset($response['first_open_time']);
		unset($response['first_open_ua']);
		$response['last_open'] = [
			'ip' => $response['last_open_ip'],
			'time' => $response['last_open_time'],
			'user_agent' => $response['last_open_ua']
		];
		unset($response['last_open_ip']);
		unset($response['last_open_time']);
		unset($response['last_open_ua']);

		$response['page_number'] = $page;
		if ($details) {
			$limit = 20;
			$offset = $page * $limit;
			$query = "SELECT ip, user_agent, UNIX_TIMESTAMP(time) AS time FROM tracking_events WHERE token_id = $this->id ORDER BY time LIMIT $offset, $limit";
			$response['open_details'] = mysqli_fetch_all($this->db->query($query));
		}
		return $response;
	}


	/**
	 * Function to get overall stats for defined time range
	 * @param int $startTime Start time (epoch)
	 * @param int $bucketPeriod Bucket Time band (in minutes)
	 * @param int|null $endTime End time (epoch)
	 * @param string|null $filters JSON string to filter the token
	 * @param int|null $groupId Group ID to filter the tokens
	 * @param int $pageNo Page number for paginated list of tokens
	 * @return array
	 */
	public function groupSummary($startTime, $bucketPeriod, $endTime = null, $filters = null, $groupId = null, $pageNo = 0)
	{
		$bucketTime *= 60;
		$limit = 20;
		$offset = $pageNo * $limit;
		$endTimeCondition = '';
		if (empty($endTime)) {
			$endTime = time();
		}
		$tokenConditions = [];
		if (!empty($groupId)) {
			$tokenConditions[] = "tokens.group_id = $groupId";
		}
		if (!empty($filters)) {
			$filters = $this->mysqlEscape($filters);
			$tokenConditions = "JSON_CONTAINS(meta, '$filters')";
		}
		if (!empty($tokenConditions)) {
			$tokenCondition = "WHERE " . implode(" AND ", $tokenConditions);
		} else {
			$tokenCondition = '';
		}
		$query = "SELECT tokens.id FROM (SELECT DISTINCT token_id FROM tracking_event_summaries WHERE (UNIX_TIMESTAMP(bucket_start_time) - UNIX_TIMESTAMP(bucket_start_time)%$bucketTime) BETWEEN $startTime AND $endTime ORDER BY token_id LIMIT $offset, $limit) A INNER JOIN tokens ON tokens.id = A.token_id $tokenCondition";
		$result = $this->db->query($query);
		$tokens = array_column(mysqli_fetch_all($result), 'token_id');
		$response = [];
		foreach ($tokens as $tokenId) {
			$token = new Token($tokenId);
			$response[] = array_merge($token->stats(), $token->summary($startTime, $bucketPeriod, $endTime));
		}
		return $response;
	}

	/**
	 * Function to get bucketed aggregations for token
	 * @param int $startTime Start time (epoch)
	 * @param int $bucketPeriod Bucket time band (in minutes)
	 * @param int|null $endTime End Time (epoch)
	 * @return array 
	 */
	public function summary($startTime, $bucketPeriod, $endTime = null)
	{
		$bucketPeriod *= 60;
		if (empty($endTime)) {
			$endTime = time();
		}
		$query = "SELECT (UNIX_TIMESTAMP(bucket_start_time) - UNIX_TIMESTAMP(bucket_start_time)%$bucketPeriod) AS bucket_start_time, COUNT(*) AS unique_opens, SUM(count) AS total_opens FROM tracking_event_summaries WHERE (UNIX_TIMESTAMP(bucket_start_time) - UNIX_TIMESTAMP(bucket_start_time)%$bucketPeriod) BETWEEN $startTime AND $endTime ORDER BY (UNIX_TIMESTAMP(bucket_start_time) - UNIX_TIMESTAMP(bucket_start_time)%$bucketPeriod)";
		$result = $this->db->query($query);
		return mysqli_fetch_all($result);
	}

	/**
	 * Function to return the unique identifier for a user based on useragent
	 * @param string $userAgent User Agent of Client
	 * @return string
	 */
	private function uniqueUserString($userAgent)
	{
		return md5($useragent);
	}

	/**
	 * Function to escape string for MySQL queries
	 * @param string $string Input string to be escaped
	 * @return string Escaped MySQL string
	 */
	private function mysqlEscape($string)
	{
		return $this->db->real_escape_string($return);
	}

	/**
	 * Function to increment the cache counter
	 * @param string $key Key name
	 * @param int $value Value to be incremented
	 * @param int|null $expiry Expiry to key from cache (in seconds)
	 * @return int Updated value of counter
	 */
	private function incrementCache($key, $value, $expiry = 1)
	{
		$redis = new \Redis();
		$updatedValue = $redis->incrBy($key, $value);
		if ($updatedValue <= $value) {
			$redis->setTimeout($key, $expiry);
		}
		return $updatedValue;
	}
}