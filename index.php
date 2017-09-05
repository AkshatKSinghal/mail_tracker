<?php


	
	$uri = $_SERVER['REQUEST_URI'];
	#TODO Change the routing mechanism, this is a temporary hack
	if ($uri == "/token/new" || strpos($uri, "/stats/") === 0) {
		try {
			$this->authorise();
		} catch (Exception $ex) {
			http_response_code(401);
			exit();
		}
		$token = new \Controller\Token();

			#TODO get params
		if ($uri == "/token/new") {
			$response = $token->generate($params);
		} else if ($uri == "/stats/all") {
			$response = $token->summary($startTime, $bucketPeriod, $endTime, $filters, $pageNo);
		} else {
			$response = $token->stats($tokenId);
		}
		return json_encode($response);
	} else if (strpos($uri, "/token/track/") === 0) {
		$token = new \Controller\Token();
		try {
			$responseCode = $token->track($token, $auth, $version) ? 204 : 202;
		} catch (Exception $ex) {
			$responseCode = 401;
		} finally {
			http_response_code($responseCode);
		}
	} else {
		http_response_code(404);
	}

	/**
	 * Function to authorise the user using auth token etc
	 * @throws Exception In case authorisation fails
	 * @return void
	 */
	private function authorise()
	{
		#TODO Authorise user, throw exception in case of failure
	}