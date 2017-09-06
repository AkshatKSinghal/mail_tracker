<?php

namespace Controller;

class Token
{
	/**
	 * Function to generate a new tracking token
	 * @param int $groupId Group Id
	 * @param string $meta JSON String
	 * @return array Containing token and tracking link
	 */
	public function generate($groupId, $meta)
	{
		$token = new \Model\Token();
		$this->validateInt($groupId, 'Group ID');
		$this->validateJson($meta, "Meta");
		$tokenId = $token->generate($groupId, $meta);
		return [
			'token' => $tokenId,
			'track_link' => $this->getTrackLink($tokenId)
			];
	}

	private function getTrackLink($tokenId)
	{
		$host = $_SERVER['SERVER_NAME'];
		$version = 1;
		return $host . "/token/track/" . dechex($tokenId) . "?version=$version&auth=" . $this->getAuthParameter($tokenId, $version);
	}

	public function track($tokenString, $auth, $version)
	{
		$tokenId = $this->validateTracker($tokenString, $auth);
		$token = new \Model\Token($tokenId);
		$userAgent = $_SERVER ['HTTP_USER_AGENT'];
		$time = $_SERVER['REQUEST_TIME'];
		$ip = $_SERVER['REMOTE_ADDR'];
		try {
			$token->track($ip, $userAgent, $time);
		} catch (Exception $ex) {
			return false;
		}
		return true;
	}


	public function stats($tokenId, $details = false, $pageNo = 0)
	{
		$token = new \Model\Token($tokenId);
		$this->validateInt($pageNo, 'Page number');
		$response = $token->stats($details, $pageNo);
		$response['first_open'] = $this->formatTrackingEvents($response['first_open']);
		$response['last_open'] = $this->formatTrackingEvents($response['last_open']);
		if ($details) {
			$response['open_details'] = array_map([self, 'formatTrackingEvents'], $response['open_details']);
		}
		return $response;
	}

	public function groupSummary($startTime, $bucketPeriod, $endTime, $filters, $groupId, $pageNo)
	{
		$this->validateInt($startTime, 'Start Time', false);
		$this->validateInt($bucketPeriod, 'Bucket period');
		$this->validateInt($endTime, 'End Time', false);
		$this->validateInt($groupId, 'Group Id', false);
		$this->validateInt($pageNo, 'Page no', false);
		$this->validateJson($meta, 'Meta Filters');

		if (!in_array($bucketPeriod, [5,30])) {
			throw new Exception("Bucket period allowed to be 5 or 30 only", 102);
		}

		$token = new \Model\Token();
		return $token->summary($startTime, $bucketPeriod, $endTime, $filters, $groupId, $pageNo);
	}

	private function validateInt($value, $name, $allowEmpty = true)
	{
		if (empty($value)) {
			if (!$allowEmpty) {
				throw new Exception("$name cannot be empty", 102);
			}
		} else if (!is_numeric($value)) {
			throw new Exception("$name invalid", 102);
			
		}
	}

	private function validateJson($value, $name, $allowEmpty = true)
	{
		if (empty($value)) {
			if (!$allowEmpty) {
				throw new Exception("$name cannot be empty", 102);
			}
		} else if (!is_array(json_decode($value, true))) {
			throw new Exception("$name invalid", 102);
		}
	}
	/**
	 * Function to format the tracking event data from DB into required response format
	 * @param array $data Data from DB
	 * @return array
	 */
	private function formatTrackingEvents($data)
	{
		return [
			'time' => $data['time'],
			'ip' => $data['ip'],
			'user_agent' => $data['user_agent'],
			'location' => $this->getLocation($data['ip'])
		];
	}

	private function salt($version)
	{
		switch ($version) {
			case 1:
				return 'zw`^c`<ua&/{+`"x6r]h<VRA9,"U9_.h';
				break;
			
			default:
				return '';
				break;
		}
	}

	private function validateTracker($token, $auth, $version)
	{
		$this->id = hexdec($token);
		if ($auth != $this->getAuthParameter($this->id, $version)) {
			throw new Exception("Token authorisation failed");
		}
	}

	private function getAuthParameter($tokenId, $version)
	{
		return md5($tokenId . $this->salt($version));
	}
}