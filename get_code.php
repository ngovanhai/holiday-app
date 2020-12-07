<?php    
require 'vendor/autoload.php'; 
use sandeepshetty\shopify_api;  
require 'help.php';
 
if (!empty($_GET['shop']) && !empty($_GET['code'])) {  
    $shop = $_GET['shop'];   
    $app_settings = db_fetch_row("SELECT * FROM tbl_appsettings WHERE id = $appId"); 
    $access_token = shopify_api\oauth_access_token($_GET['shop'], $app_settings['api_key'], $app_settings['shared_secret'], $_GET['code']);     
    $installed = checkInstalled($db, $shop, $appId);
    if($installed["installed"]){
        $date_installed = $installed["installed_date"];
        db_insert("tbl_usersettings",[
            'access_token' => $access_token,
            'store_name' => $shop,
            'app_id' => $appId,
            'installed_date' => $date_installed,
            'confirmation_url' => ''
        ]);
        $date1 = new DateTime($installed["installed_date"]);
        $date2 = new DateTime("now");
        $interval = date_diff($date1, $date2);
        $diff = (int)$interval->format('%R%a');
        $trialTime = $trialTime - $diff;
        if($trialTime < 0){  $trialTime = 0;  } 
    } else {
        db_insert("tbl_usersettings",[
            'access_token' => $access_token,
            'store_name' => $shop,
            'app_id' => $appId,
            'installed_date' => date("Y-m-d H:i:s"),
            'confirmation_url' => ''
        ]);
        db_insert("shop_installed",[ 
            'shop' => $shop,
            'app_id' => $appId,
            'date_installed' => date("Y-m-d H:i:s")
        ]);  
    } 
    $shopify= shopifyInit($db, $shop, $appId);

    //---- CHARGE FEE ----
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
    } else {
        $recu = $shopify("POST", "/admin/recurring_application_charges.json", $charge);
       
    }
    if(isset($recu["confirmation_url"])){
        $confirmation_url = $recu["confirmation_url"];
    }else{
        $confirmation_url =  NULl;
    }
    db_update("tbl_usersettings",['confirmation_url' => $confirmation_url ],"store_name = '$shop' and app_id = $appId");
   
    
    //hook when user remove app
    $webhook = $shopify('POST', '/admin/webhooks.json', array('webhook' => array('topic' => 'app/uninstalled', 'address' => $rootLink.'/uninstall.php', 'format' => 'json')));     
    if($chargeType == "free"){
        db_update("tbl_usersettings",['confirmation_url' => $confirmation_url ],"store_name = '$shop' and app_id = $appId");   
        header('Location: '.$rootLink.'/admin.php?shop='.$shop);
    } else {
        header('Location: ' . $confirmation_url);
    }  
}
function checkInstalled($db, $shop, $appId) { 
    $shop_installled = db_fetch_row("select * from shop_installed where shop = '$shop' and app_id = $appId");
    if(count($shop_installled) > 0){
        $date_instaled = $shop_installled["date_installed"];
        $result = array(
            "installed_date" => $date_instaled,
            "installed" => true
        );
        return $result;
    }else{
        $result = array(
            "installed" => false
        );
        return $result;
    }
}

?>
