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
		#TODO Get time, IP, User Agent from HTTP Header
		$time = time();
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
		$response['openEventsData'] = $this->formatTrackingEvents($response['openEventsData']);
		if ($details) {
			$response['allOpenEventsData'] = $this->formatTrackingEvents($response['allOpenEventsData']);
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