<?php
$apiKey = "99e421f55d6359329d4981f8596a2b08";
$appId = "1";
$rootLink = "https://hailocal.omegatheme.com/holiday-app";
$trialTime = 30;
$chargeType = "monthly";
$price = 7.99;
$version = time();
//true or null
$appName = "Holiday app";
$testMode = "true";
$dateUse  = '2019-02-15 04:03:09';
$chargeTitle = "Sample app";
$host = "localhost";
$username = "root";
$password = "";
$database = "shopify_hai";
$db = new Mysqli($host, $username, $password, $database);
if ($db->connect_errno) {
  die('Connect Error: ' . $db->connect_errno);
}
