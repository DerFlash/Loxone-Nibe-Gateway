<?php

// CONFIG (set up your own application on https://api.nibeuplink.com to get these things)
//==========

$CLIENT_ID = "<nibe_api_client_id>"; // Nibe Uplink API Application Identifier
$CLIENT_SECRET = "<nibe_api_client_secret>"; // Nibe Uplink API Application Secret
$REDIRECT_URL = "http://raspberrypi.fritz.box/nibe/"; // the URL on your raspberryPi to the folder containing this script (this can and should only be accessible from your LAN for security reasons!)


// DO NOT EDIT THE CODE BELOW UNLESS YOU KNOW WHAT YOU'RE DOING ;-)
//==========

$DEBUG = 0;
$SCOPES = "READSYSTEM";

if ($DEBUG)
{
	ini_set('display_errors', 1);
	ini_set('display_startup_errors', 1);
	error_reporting(E_ALL);
}

function authorize($CODE, $isRefresh = false)
{
    global $CLIENT_ID, $CLIENT_SECRET, $SCOPES, $REDIRECT_URL;

    $ch = curl_init();

    if ($isRefresh)
    {
		$pf = "&refresh_token=" . urlencode($CODE);
		$grant_type = "refresh_token";
    }
    else
    {
		$pf = "&code=" . urlencode($CODE) . "&redirect_uri=" . $REDIRECT_URL . "&scope=" . urlencode($SCOPES);
		$grant_type = "authorization_code";
    }

    curl_setopt($ch, CURLOPT_URL,"https://api.nibeuplink.com/oauth/token");
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=" . $grant_type . "&client_id=" . urlencode($CLIENT_ID) . "&client_secret=" . urlencode($CLIENT_SECRET) . $pf);
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
				$token = json_decode($response);
			break;
    	    
    	    default:
				echo 'Unerwarter HTTP-Code: ', $http_code, "\n";
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
    $token = file_get_contents("token");
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
    $diff = time() - last_token_update();
    return ($diff >= $token->expires_in);
}

function baseURL()
{
    return $_SERVER['PHP_SELF'];
}

function checkToken()
{
    global $DEBUG;

    $token = load_token();
    if ($token === false)
    {
		return false;
    }
    
    if (token_needs_update($token))
    {
		if ($DEBUG) echo "Token needs update. Working on it...<br />\n";
		$token = authorize($token->refresh_token, true);

		if ($token === false)
		{
	    	clear_token();
	    	if ($DEBUG) echo "Failed to refresh token.<br />\n";
    	    return false;
        }

        save_token($token);
        if ($DEBUG) echo "Successfully refreshed token.<br />\n";
    }

    return $token;
}

function checkStatus()
{
    global $CLIENT_ID, $SCOPES, $REDIRECT_URL;

    $token = checkToken();
    if ($token === false)
    {
        $URL = "https://api.nibeuplink.com/oauth/authorize?response_type=code&client_id=$CLIENT_ID&scope=$SCOPES&redirect_uri=$REDIRECT_URL&state=authorization";
		echo "You're not authorized yet.<br /><br />\n";
		echo "<b>Important:</b> If you haven't done that yet, create an application on <a href=\"https://api.nibeuplink.com\">https://api.nibeuplink.com</a> first and update the config section in the index.php (this file).<br ><br />\n";
		echo "If you think you're ready to connect this bridge to the Nibe API, click <a href=\"$URL\">here</a>.";
		die();
    }

    if (isset($_GET["autoUpdate"]) && $_GET["autoUpdate"] == "true")
    {
		header("refresh:5;url=" . baseURL() . "?autoUpdate=true");
		echo "<center><a href=\"" . baseURL() . "?autoUpdate=false\">Disable auto refresh</a></center><br /><br />\n";
    }
    else
    {
		echo "<center><a href=\"" . baseURL() . "?autoUpdate=true\">Enable auto refresh</a></center><br /><br />\n";
    }

    echo "<h2>Status</h2>";
    echo "Current status: authorized<br /><br />\n";
    echo "Access-Token:<br />" . $token->access_token . "<br /><br />\n";
    echo "Current Time: " . time() . "<br />\n";
    echo "Last update: " . last_token_update() . "<br />\n";
    echo "Token expire time: " . $token->expires_in . "<br />\n";
    echo "Remaining seconds: " . ($token->expires_in - (time() - last_token_update()) . "<br /><br />\n");

    echo "<h2>Status response</h2>";
    $response = readAPI("systems", $token, $success);
    if (!$success)
    {
        echo "FAILED:<br />\n";
        print_r($response);
    }
    else
    {
        echo "<pre>" . json_encode($response, JSON_PRETTY_PRINT) . "</pre>";
    }
    
    ?>
    <h2>Query</h2>
    <div><form>
   		<input type="hidden" name="mode" value="raw" />
      	<p>
      		Output format: <input type="radio" name="format" value="json" checked>json&nbsp;<input type="radio" name="format" value="pretty">pretty print
      	</p>
    	<p>
    		Function:
    		<input type="text" name="exec" value="systems">&nbsp;
      		<input type="submit" value="Submit">
      	</p>
    </form></div>
    <div>
    <a href="https://api.nibeuplink.com/docs/v1/Functions">Nibe Uplink API Documentation</a><br />
    </div>
    <?php
}

function unauthorized()
{
	header("HTTP/1.0 401 Unauthorized");
	$URL = baseURL();
	echo "Not authorized yet. Please setup the required token by opening the following URL in your browser from without your LAN:<br />\n";
	echo "<a href=\"$URL\">" . (isset($_SERVER['HTTPS']) ? "https" : "http") . "://" . $_SERVER['HTTP_HOST'] . explode("?",$_SERVER['REQUEST_URI'])[0] . "</a>";
	die();
}

// handle API callback
if (isset($_GET["state"]) && $_GET["state"] == "authorization")
{
    if (!isset($_GET["code"]))
    {
		echo "Missing code!";
		die();
    }

    $CODE = $_GET["code"];

    $token = authorize($CODE);

    header("refresh:5;url=" . baseURL());
    if ($token === false)
    {
		clear_token();
		echo "Failed to authorize! Redirecting to <a href=\"" . baseURL()  . "\">status page</a> ...";
    }
    else
    {
		save_token($token);
        echo "Successfully authorized! Redirecting to <a href=\"" . baseURL()  . "\">status page</a> ...";
    }
    die();
}

else if (isset($_GET["exec"]))
{
    $token = checkToken();
    if ($token === false)
    {
    	unauthorized();
    }
    $exec = $_GET["exec"];

	if (isset($_GET["mode"]) && $_GET["mode"] == "raw")
	{
		$response = readAPI(urlencode($exec), $token, $success);
		if (!$success)
		{
			header("HTTP/1.0 400 Bad Request");
			print_r($response);
			die(1);
		}

		$output = json_encode($response, JSON_PRETTY_PRINT);
		if (isset($_GET["format"]) && $_GET["format"] == "pretty") $output = "<pre>" . $output . "</pre>";

		echo $output;
	}
}

// handle default access
else
{
    checkStatus();
}

?>