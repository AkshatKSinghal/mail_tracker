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
		$query = "SELECT tokens.*, COUNT(DISTINCT tracking_event_summaries.unique_user_string) AS unique_count, SUM(tracking_event_summaries.count) AS total_count FROM tokens LEFT JOIN tracking_event_summaries ON tracking_event_summaries.token_id = tokens.id WHERE tokens.id = '$this->id' GROUP BY tokens.id;"
		$tokenData = $this->db->query($query);
		$openEvents = array_filter([$tokenData['first_open'], $tokenData['last_open']]);
		$query = "SELECT * FROM tracking_events WHERE id IN (" . implode(",", $openEvents) . ")";
		$openEventsData = $this->db->query($query);
		$response = [
			'tokenData' => $tokenData,
			'openEventsData' => $openEventsData
			];
		if ($details) {
			$limit = 20;
			$offset = $page * $limit;
			$query = "SELECT * FROM tracking_events WHERE token_id = $this->id ORDER BY time LIMIT $offset, $limit";
			$response['allOpenEventsData'] = $this->db->query($query);
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