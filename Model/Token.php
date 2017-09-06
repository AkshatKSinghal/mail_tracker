<?php

namespace Model;

class Token
{

	function __construct($id = null)
	{
		$this->db = new mysqli("localhost", "root", "");
		if ($this->db->connect_error || !mysqli_select_db($this->db, "mail_tracker")) {
			throw new Exception("Unable to connect to database", 1);
		}
		if (!empty($id)) {
			$this->id = $id;
		}
	}

	public function generate($params)
	{
		$query = "INSERT INTO tokens (group_id, meta) VALUES ($groupId, $meta)";
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
		$count = $this->cache->inc($this->id . $uniqueUserString . $time, 1);
		if ($count > 1) {
			throw new Exception("Duplicate open tracked");
		}
		#TODO MySQL Escape
		$query = "INSERT INTO tracking_events(token_id, ip, user_agent, time) VALUES ($this->id, '$ip', '$userAgent', FROM_UNIXTIME($time))";
		$this->db->query($query);
	}

	/**
	 * Function to retrieve the stats of a token
	 * @param bool $details Flag to enable sending of details
	 * @param int $page Page number for the paginated details list
	 * @return array
	 */
	public function stats($details, $page)
	{
		$query = "SELECT tokens.id, tokens.created, tokens.meta, tokens.group_id, UNIX_TIMESTAMP(A.time) AS first_open_time, A.ip AS first_open_ip, A.user_agent AS first_open_ua, UNIX_TIMESTAMP(B.time) AS last_open_time, B.ip AS last_open_ip, B.user_agent AS last_open_ua, token_summaries.total_opens, token_summaries.unique_opens FROM tokens LEFT JOIN token_summaries ON tokens.id = token_summaries.token_id LEFT JOIN tracking_events A ON token_summaries.first_open = A.id LEFT JOIN tracking_events B ON token_summaries.last_open = B.id WHERE tokens.id = $this->id";
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

	public function summary()
	{


	}


	/**
	 * Function to return the unique identifier for a user based on useragent
	 * @param string $userAgent User Agent of Client
	 * @return string
	 */
	private function uniqueUserString($userAgent)
	{
		#TODO Generate unique user string
		return md5($useragent);
	}
}