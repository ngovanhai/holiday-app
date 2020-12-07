<?php
ini_set('display_errors', TRUE);
error_reporting(E_ALL);
header('Access-Control-Allow-Origin: *');
date_default_timezone_set('UTC');
header('Content-Type: application/json');
require_once('help.php');
require 'vendor/autoload.php';

use sandeepshetty\shopify_api;

$shop = $_GET['shop'];
$user = db_fetch_row("SELECT * FROM tbl_usersettings WHERE store_name = '$shop'");
$token = $user['access_token'];
$colectionList = shopify_call($token, $shop,  '/admin/api/2020-10/products.json', array(), 'GET');
// $colectionList = json_encode($colectionList['response'], JSON_PRETTY_PRINT);
echo $res = $colectionList['response'];
