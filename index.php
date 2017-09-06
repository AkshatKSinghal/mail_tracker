<?php


try {
	return json_encode(handleRequest());
} catch (Exception $ex) {
	if ($ex->getCode() == 101) {
		// Handling of MySQL Error, Return 503 error
		// Log into error log
		http_response_code(503);
	} else {
		http_response_code(400);
		$response = ['message' => $ex->getMessage()];
	}
}

/**
 * Router function
 * @return array | void
 */

#TODO Change the routing mechanism, this is a temporary hack
function handleRequest() {
	$uri = $_SERVER['REQUEST_URI'];
	if ($uri == "/token/new" || strpos($uri, "/stats/") === 0) {
		try {
			$this->authorise();
		} catch (Exception $ex) {
			http_response_code(401);
			exit();
		}
		$token = new \Controller\Token();

		if ($uri == "/token/new") {
			$meta = $_POST['meta'];
			$groupId = $_POST['group_id'];
			$response = $token->generate($groupId, $meta);
		} else if ($uri == "/stats/all") {
			$startTime = $_GET['start_time'];
			$endTime = $_GET['end_time'];
			$bucketPeriod = $_GET['bucket_period'];
			$filters = $_GET['meta_filters'];
			$pageNo = $_GET['page_no'];
			$groupId = $_GET['group_id'];
			$response = $token->summary($startTime, $bucketPeriod, $endTime, $filters, $groupId, $pageNo);
		} else {
			$tokenId = str_replace("/stats/", "", $uri);
			$response = $token->stats($tokenId);
		}
		return $response;
	} else if (strpos($uri, "/token/track/") === 0) {
		$tokenString = str_replace("/token/track/", "", $uri);
		$token = new \Controller\Token();
		try {
			$responseCode = $token->track($tokenString, $auth, $version) ? 204 : 202;
		} catch (Exception $ex) {
			if ($ex->getCode() == 101) {
				throw new Exception($ex->getMessage(), $ex->getCode());
			}
			$responseCode = 401;
		} finally {
			http_response_code($responseCode);
		}
	} else {
		http_response_code(404);
	}
}

/**
 * Function to authorise the user using auth token etc
 * @throws Exception In case authorisation fails
 * @return void
 */
 function authorise()
{
	#TODO Authorise user, throw exception in case of failure
}