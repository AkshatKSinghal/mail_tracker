<?php

namespace Controller;

class Token
{
	public function generate($params)
	{
		$token = new \Model\Token();
		$tokenId = $token->generate($params);
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

	public function track($token, $auth, $version)
	{
		$tokenId = $this->validateTracker($token, $auth);
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
		$response = $token->stats($details, $pageNo)
		$response['first_open'] = $this->formatTrackingEvents($response['first_open']);
		$response['last_open'] = $this->formatTrackingEvents($response['last_open']);
		if ($details) {
			$response['allOpenEventsData'] = array_map([self, 'formatTrackingEvents'], $response['allOpenEventsData']);
		}
		return $response;
	}

	public function summary($startTime, $bucketPeriod, $endTime, $filters, $pageNo)
	{

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