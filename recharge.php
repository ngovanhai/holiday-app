<?php
require 'help.php'; 

$shop = $_GET['shop']; //shop name 

$app_settings = db_fectch_row("SELECT * FROM tbl_appsettings WHERE id = $appId");
$shop_data = db_fectch_row("select * from tbl_usersettings where store_name = '" . $shop . "' and app_id = $appId"); 
$shopify = shopify_api\client($shop, $shop_data['access_token'], $app_settings['api_key'], $app_settings['shared_secret']);

//charge fee
$charge = array(
    "recurring_application_charge" => array(
        "name" => $chargeTitle,
        "price" => $price,
        "return_url" => "$rootLink/charge.php?shop=$shop",
        "test" => $testMode,
        "trial_days" => $trialTime
    )
);

if($chargeType == "one-time"){
    $recu = $shopify("POST", "/admin/application_charges.json", $charge);
    $confirmation_url = $recu["confirmation_url"];
} else {
    $recu = $shopify("POST", "/admin/recurring_application_charges.json", $charge);
    $confirmation_url = $recu["confirmation_url"];
}
$db->query("update tbl_usersettings set confirmation_url = '$confirmation_url' where store_name = '$shop' and app_id = $appId"); 

/* 
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */
