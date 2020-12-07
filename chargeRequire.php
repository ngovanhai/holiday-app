<?php require 'help.php';  ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>Recurring Application Charges</title>
</head>
<body>
<?php  
if (isset($_GET["shop"])) {
    $shop = $_GET["shop"];
    $shopify = shopifyInit($db, $shop, $appId);

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
    if ($chargeType == "one-time") {
        $recu = $shopify("POST", "/admin/application_charges.json", $charge);
        $confirmation_url = $recu["confirmation_url"];
    } else {
        $recu = $shopify("POST", "/admin/recurring_application_charges.json", $charge);
        $confirmation_url = $recu["confirmation_url"];
    }

?>

    <head>
        <script type="text/javascript" src="//code.jquery.com/jquery-2.1.4.min.js"></script>
        <link rel="stylesheet" type="text/css" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/css/bootstrap.min.css">
        <script type="text/javascript" src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.4/js/bootstrap.min.js"></script>
        <title>Active page</title>
    </head>
    <div class="container" style="padding:30px 0;margin-top: 50px;border: 5px solid #EC342E;max-width:600px;text-align:center;">
        <img src="https://cdn.shopify.com/s/files/applications/8086a640c26b0f1a50682c9334730f58.png?1497856221" style="max-width: 150px;">
        <h1 style="font-size:30px;text-transform:uppercase;margin-top:30px;">Charge now, pay later</h1>
        <p>To proceed with the installation, click below to activate the app and approve the charge.</p>
        <a class="btn btn-primary" target="_blank" href="<?php echo $confirmation_url; ?>">Activate App</a>
    </div>
    <?php include 'plugin/facebook-chat.html'; ?>
<?php
}
?>




</body>
</html>