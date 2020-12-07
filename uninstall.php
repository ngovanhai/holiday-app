<?php

require 'conn-shopify.php';
 require 'help.php';
session_start();
unset($_SESSION['shop']);
$webhookContent = ""; 
$webhook = fopen('php://input', 'rb');
while (!feof($webhook)) {  $webhookContent .= fread($webhook, 4096); } 
fclose($webhook);
$webhookContent = json_decode($webhookContent);
if (isset($webhookContent->myshopify_domain)) {
    $shop = $webhookContent->myshopify_domain;  
} else if (isset($webhookContent->domain)) {
    $shop = $webhookContent->domain; 	
} 
if(isset($shop)){ 
    $db->query('delete from tbl_usersettings where store_name = "' . $shop . '" and app_id = ' . $appId); 
}


