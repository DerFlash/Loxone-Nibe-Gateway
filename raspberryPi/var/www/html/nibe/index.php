<?php

require_once('class.nibeAPI.php');
require_once('class.nibeGateway.php');

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// CONFIG (set up your own application on https://api.nibeuplink.com to get these things)
//==========

$CLIENT_ID = "<nibe_api_client_id>"; // Nibe Uplink API Application Identifier
$CLIENT_SECRET = "<nibe_api_client_secret>"; // Nibe Uplink API Application Secret
$REDIRECT_URL = "http://raspberrypi.fritz.box/nibe/"; // the URL on your raspberryPi to the folder containing this script (this can and should only be accessible from your LAN for security reasons!)

$nibeAPI = new NibeAPI($CLIENT_ID, $CLIENT_SECRET, $REDIRECT_URL);
$nibeGateway = new NibeGateway($nibeAPI);

if ($nibeAPI->debugActive)
{
	file_put_contents('/tmp/nibe.log', '['.date("c").'] '.$_SERVER['REQUEST_URI']."\n", FILE_APPEND);
}

$nibeGateway->main();

?>
