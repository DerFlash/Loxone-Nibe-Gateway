<?php

class NibeAPI {

	var $clientID;
	var $clientSecret;
	var $redirectURL;
	var $scopes;
	var $debugActive;

	function __construct($clientID, $clientSecret, $redirectURL, $scopes = "WRITESYSTEM+READSYSTEM", $debugActive = 0) {
		$this->clientID = $clientID;
		$this->clientSecret = $clientSecret;
		$this->redirectURL = $redirectURL;
		$this->scopes = $scopes;
		$this->debugActive = $debugActive;
	}
	
	function authorizationURL()
	{
		return "https://api.nibeuplink.com/oauth/authorize?response_type=code&client_id=" . $this->clientID . "&scope=" . $this->scopes . "&redirect_uri=" . $this->redirectURL . "&state=authorization";
	}
    
	function authorize($CODE, $isRefresh = false)
	{
		$ch = curl_init();

		if ($isRefresh)
		{
			$pf = "&refresh_token=" . urlencode($CODE);
			$grant_type = "refresh_token";
		}
		else
		{
			$pf = "&code=" . urlencode($CODE) . "&redirect_uri=" . $this->redirectURL . "&scope=" . urlencode($this->scopes);
			$grant_type = "authorization_code";
		}

		curl_setopt($ch, CURLOPT_URL,"https://api.nibeuplink.com/oauth/token");
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=" . $grant_type . "&client_id=" . urlencode($this->clientID) . "&client_secret=" . urlencode($this->clientSecret) . $pf);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded'));


		// receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		$token = false;
		if ($response === false)
		{
			echo 'Curl-Fehler: ' . curl_error($ch);
		}
		else
		{
			switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE))
			{
				case 200:
					$jsonResponse = json_decode($response);
					if ($jsonResponse != NULL || strlen(trim($token)) > 0)
					{
						$token = $jsonResponse;
					} else {
						echo 'Could not decode json response: ' . $response . '\n';
					}
				break;
			
				default:
					echo 'Unerwarter HTTP-Code: ' . $http_code . "\n";
			}
		}

		curl_close ($ch);
		return $token;
	}

	function readAPI($URI, $token, &$success = 'undefined')
	{

		$ch = curl_init();

		curl_setopt($ch, CURLOPT_URL,"https://api.nibeuplink.com/api/v1/" . $URI);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array("Authorization: Bearer " . $token->access_token));

		// receive server response ...
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$response = curl_exec($ch);

		$data = false;
		$success = false;
		if ($response === false)
		{
			echo 'Curl-Fehler: ' . curl_error($ch);
		}
		else
		{
			switch ($http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE))
			{
				case 200:
					$data = json_decode($response);
					if ($data !== false) $success = true;
				break;
			
				default:
					$data = "Unerwarter HTTP-Code: " . $http_code . "<br />\nResponse:<br />\n" . $response;
			}
		}

		curl_close ($ch);
		return $data;
	}
	
	function postAPI($URI, $postBody, $token, &$success = 'undefined')
	{
		return $this->postPutAPI($URI, "POST", $postBody, $token, $success);
	}
	
	function putAPI($URI, $postBody, $token, &$success = 'undefined')
	{
		return $this->postPutAPI($URI, "PUT", $postBody, $token, $success);
	}
	
	function postPutAPI($URI, $method, $postBody, $token, &$success = 'undefined')
	{
		$curl = curl_init();
		
		curl_setopt_array($curl, array(
		  CURLOPT_URL => "https://api.nibeuplink.com/api/v1/" . $URI,
		  CURLOPT_RETURNTRANSFER => true,
		  CURLOPT_CUSTOMREQUEST => $method,
		  CURLOPT_POSTFIELDS => $postBody,
		  CURLOPT_HTTPHEADER => array(
			"Authorization: Bearer " . $token->access_token,
			"Content-Type: application/json"
		  ),
		));

		$response = curl_exec($curl);

		$data = false;
		$success = false;
		if ($response === false)
		{
			echo 'Curl-Fehler: ' . curl_error($curl);
		}
		else
		{
			switch ($http_code = curl_getinfo($curl, CURLINFO_HTTP_CODE))
			{
				case 200:
					$data = json_decode($response);
					if ($data !== false) $success = true;
				break;
				
				case 204:
					$success = true;
					$data = "Success";
				break;
			
				default:
					$data = "Unerwarter HTTP-Code: " . $http_code . "<br />\nResponse:<br />\n" . $response;
			}
		}

		curl_close ($curl);
		return $data;
	}

	function save_token($token)
	{
		if (!file_put_contents("token", serialize($token)))
		{
			echo "Could not save token.";
			die();
		}

		return true;
	}

	function load_token()
	{
		$token = @file_get_contents("token");
		if ($token === false)
		{
			return false;
		}
	
		return @unserialize($token);
	}

	function clear_token()
	{
		if (!file_put_contents("token", (string)"\n"))
		{
			echo "Could not clear token.";
			die();
		}

		return true;
	}

	function last_token_update()
	{
		return filemtime("token");
	}

	function token_needs_update($token)
	{
		$diff = time() - $this->last_token_update();
		return ($diff >= $token->expires_in);
	}

	function checkToken()
	{
		$token = $this->load_token();
		if ($token === false)
		{
			return false;
		}
	
		if ($this->token_needs_update($token))
		{
			if ($this->debugActive) echo "Token needs update. Working on it...<br />\n";
			$token = $this->authorize($token->refresh_token, true);

			if ($token === false)
			{
				$this->clear_token();
				if ($this->debugActive) echo "Failed to refresh token.<br />\n";
				return false;
			}

			$this->save_token($token);
			if ($this->debugActive) echo "Successfully refreshed token.<br />\n";
		}

		return $token;
	}
}

?>