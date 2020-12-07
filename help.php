<?php
date_default_timezone_set('UTC');
require 'vendor/autoload.php';

use sandeepshetty\shopify_api;

require 'conn-shopify.php';

// DATABASE 
function db_query($query_string)
{
    global $db;
    $result = mysqli_query($db, $query_string);
    if (!$result) {
        echo ('Query Error' . $query_string);
        die();
    }
    return $result;
}
function db_insert($table, $data)
{
    global $db;
    $fields = "(" . implode(", ", array_keys($data)) . ")";
    $values = "";

    foreach ($data as $field => $value) {
        if ($value === NULL) {
            $values .= "NULL, ";
        } elseif (is_numeric($value)) {
            $values .= $value . ", ";
        } elseif ($value == 'true' || $value == 'false') {
            $values .= $value . ", ";
        } else {
            $values .= "'" . addslashes($value) . "', ";
        }
    }
    $values = substr($values, 0, -2);
    db_query("
            INSERT INTO $table $fields
            VALUES($values)
        ");
    return mysqli_insert_id($db);
}
function db_update($table, $data, $where)
{
    global $db;
    $sql = "";
    foreach ($data as $field => $value) {
        if ($value === NULL) {
            $sql .= "$field=NULL, ";
        } elseif (is_numeric($value)) {
            $sql .= "$field=" . addslashes($value) . ", ";
        } elseif ($value == 'true' || $value == 'false') {
            $sql .= "$field=" . addslashes($value) . ", ";
        } else
            $sql .= "$field='" . addslashes($value) . "', ";
    }
    $sql = substr($sql, 0, -2);
    db_query("
        UPDATE `$table`
        SET $sql
        WHERE $where
    ");
    return mysqli_affected_rows($db);
}
function db_delete($table, $where)
{
    global $db;
    $query_string = "DELETE FROM " . $table . " WHERE $where";
    db_query($query_string);
    return mysqli_affected_rows($db);
}
function db_duplicate($table, $data, $content_duplicate)
{
    global $db;
    $fields = "(" . implode(", ", array_keys($data)) . ")";
    $values = "(";
    foreach ($data as $field => $value) {
        if ($value === NULL)
            $values .= "NULL, ";
        elseif ($value === TRUE || $value === FALSE)
            $values .= "$value, ";
        else
            $values .= "'" . addslashes($value) . "',";
    }
    $values = rtrim($values, ',');
    $values .= ")";
    $query = "INSERT INTO $table  $fields  VALUES $values ON DUPLICATE KEY UPDATE $content_duplicate ;";
    db_query($query);
    return  mysqli_insert_id($db);
}
function db_fetch_array($query_string)
{
    global $db;
    $result = array();
    $mysqli_result = db_query($query_string);
    while ($row = mysqli_fetch_assoc($mysqli_result)) {
        $result[] = $row;
    }
    mysqli_free_result($mysqli_result);
    if (!is_array($result)) {
        $result = array();
    }
    return $result;
}
function db_fetch_row($query_string)
{
    global $conn;
    $result = array();
    $mysqli_result = db_query($query_string);
    $result = mysqli_fetch_assoc($mysqli_result);
    mysqli_free_result($mysqli_result);
    if (!is_array($result)) {
        $result = array();
    }
    return $result;
}

// SHOPIFY
function addAppScriptToTheme($shop, $shopify, $rootLink, $file, $themeName)
{
    $id = $file["theme_id"];
    $value = $file["value"];
    $newText = '{% include "omega_multi_pixel" %} </head>';
    $pos = strpos($value,  $newText);
    if ($pos === false) {
        $value = str_replace("</head>", $newText, $value);
        if (!is_dir(UPLOAD_PATH . $shop)) {
            mkdir(UPLOAD_PATH . $shop, 0755, true);
        }
        $key_path = UPLOAD_PATH . $shop . '/' . $themeName . "/theme.liquid";

        file_put_contents($key_path, $value);
        $result = $shopify(
            'PUT',
            '/admin/themes/' . $id . '/assets.json',
            array(
                'asset' => array(
                    'key' => 'layout/theme.liquid',
                    'src' => $rootLink . '/client/theme/' . $shop . '/' . $themeName . '/theme.liquid'
                )
            )
        );
        unlink($key_path);
    }
}
function removeAppScriptToTheme($shop, $shopify, $rootLink, $file, $themeName)
{
    $id = $file["theme_id"];
    $value = $file["value"];
    $newText = '{% include "omega_multi_pixel" %} </head>';
    $pos = strpos($value,  $newText);
    if ($pos === true) {
        $value = str_replace($newText, "</head>", $value);
        if (!is_dir(UPLOAD_PATH . $shop)) {
            mkdir(UPLOAD_PATH . $shop, 0755, true);
        }
        $key_path = UPLOAD_PATH . $shop . '/' . $themeName . "/theme.liquid";

        file_put_contents($key_path, $value);
        $result = $shopify(
            'PUT',
            '/admin/themes/' . $id . '/assets.json',
            array(
                'asset' => array(
                    'key' => 'layout/theme.liquid',
                    'src' => $rootLink . '/client/theme/' . $shop . '/' . $themeName . '/theme.liquid'
                )
            )
        );
        unlink($key_path);
    }
}
function deleteWebhook($shopify, $id)
{
    $result = $shopify("DELETE", "/admin/webhooks/" . $id . ".json");
    return $result;
}
function createWebhook($shopify, $link)
{
    $webhook = array(
        "webhook" => array(
            "topic" => "products/create",
            "address" => $link,
            "format" => "json"
        )
    );
    $result = $shopify("POST", "/admin/webhooks.json", $webhook);
    return $result;
}
function editWebhook($shopify, $link, $id)
{
    $webhook = array(
        "webhook" => array(
            "id"    => $id,
            "topic" => "products/create",
            "address" => $link,
            "format" => "json"
        )
    );
    $result = $shopify("PUT", "/admin/webhooks.json", $webhook);
    return $result;
}
function getListWebhook($shopify)
{
    $result = $shopify("GET", "/admin/webhooks.json");
    return $result;
}
function shopifyInit($db, $shop, $appId)
{
    $select_settings = $db->query("SELECT * FROM tbl_appsettings WHERE id = $appId");
    $app_settings = $select_settings->fetch_object();
    $shop_data1 = $db->query("select * from tbl_usersettings where store_name = '" . $shop . "' and app_id = $appId");
    $shop_data = $shop_data1->fetch_object();
    if (!isset($shop_data->access_token)) {
        die("Please check the store: " . $shop . " seems to be incorrect access_token.");
    }
    $shopify = shopify_api\client(
        $shop,
        $shop_data->access_token,
        $app_settings->api_key,
        $app_settings->shared_secret
    );
    return $shopify;
}
function getProductInPage($shopify, $since_id = 0, $limit = 50, $fields = "id,title,handle")
{
    //if(!is_numeric($since_id)) $since_id = 0;
    $products = [];
    $products = $shopify("GET", "/admin/products.json?since_id=$since_id&limit=$limit&fields=$fields");
    if (!isset($products) || !is_array($products)) return [];
    return $products;
}
function getProductByCollectionID($shopify, $collectionID, $limit = 250, $fields = "id,title,handle,variants")
{
    if (!isset($collectionID)) return [];
    $products = $shopify("GET", "/admin/products.json?collection_id=$collectionID&limit=$limit&fields=$fields");
    if (!isset($products) || !is_array($products)) return [];
    return $products;
}
function getProductByProductID($shopify, $idProduct, $fields = "id,variants")
{
    if (!isset($idProduct)) return [];
    $product = $shopify("GET", "/admin/products/" . $idProduct . ".json?fields=$fields");
    return $product;
}
function getAllTag($shopify)
{
    $listAllTag = [];
    $result = $shopify('GET', '/admin/products/tags.json');
    if ($result['tags']) {
        $listAllTag = $result;
    }
    return $listAllTag;
}
function getAllCollection($shopify, $limit = 250, $fields = "id,title,handle")
{
    $collections = [];
    $collections_smart =  [];
    $collections_custom =  [];
    $smart  = $shopify("GET", "/admin/smart_collections.json?published_status=published&limit=$limit&fields=$fields");
    $custom = $shopify("GET", "/admin/custom_collections.json?published_status=published&limit=$limit&fields=$fields");
    if (is_array($smart)) $collections_smart = $smart;
    if (is_array($custom)) $collections_custom = $custom;
    $collections = array_merge($collections_smart, $collections_custom);
    return $collections;
}
function getVariantByProductID($shopify, $product_id)
{
    if (!isset($idProduct)) return [];
    $variants = $shopify("GET", "admin/products/" . $product_id . "/variants.json");
    if (!is_array($variants)) return [];
    return $variants;
}
function getCollectionByID($shopify, $collection_id, $limit = 250, $fields = "id,title,handle")
{
    if (!isset($collection_id)) return [];
    $infoCollectionByID = [];
    $infoCollectionByID = $shopify("GET", "/admin/custom_collections/" . $collection_id . ".json?fields=$fields&limit=$limit");
    if (is_array($infoCollectionByID) && count($infoCollectionByID) > 0) {
        return $infoCollectionByID;
    } else {
        $infoCollectionByID = $shopify("GET", "/admin/smart_collections/" . $collection_id . ".json?fields=$fields&limit=$limit");
        if (is_array($infoCollectionByID) && count($infoCollectionByID) > 0) {
            return $infoCollectionByID;
        }
    }
    return $infoCollectionByID;
}
function getCountProductByCollection($collection_id, $shopify)
{
    if (!isset($collection_id)) return 0;
    $countProduct  = $shopify("GET", "/admin/products/count.json?collection_id=" . $collection_id . "&fields=id");
    return $countProduct;
}
function getCountAllProduct($shopify)
{
    $counProduct  = $shopify("GET", "/admin/products/count.json");
    return $counProduct;
}
function getPriceRule($shopify)
{
    $result = [];
    $result = $shopify('GET', '/admin/price_rules.json');
    if (!is_array($result)) return [];
    return $result;
}
function getCustomColletionByProductID($shopify, $IDProduct)
{
    if (!isset($IDProduct)) return [];
    $collections = $shopify("GET", "/admin/custom_collections.json?product_id=$IDProduct");
    if (!is_array($collections)) return [];
    return $collections;
}
function getSmartColletionByProductID($shopify, $IDProduct)
{
    if (!isset($IDProduct)) return [];
    $collections = $shopify("GET", "/admin/smart_collections.json?product_id=$IDProduct");
    if (!is_array($collections)) return [];
    return $collections;
}
function postDataPriceRule($shopify, $data)
{
    if (!isset($data) || (!isset($shopify))) return [];
    $newDiscountRule = $shopify("POST", "/admin/price_rules.json", $data);
    return $newDiscountRule;
}
function postDiscountCode($shopify, $discountRuleID, $data)
{
    if (!isset($data) || (!isset($discountRuleID))) return [];
    $newDiscountCode = $shopify("POST", "/admin/price_rules/" . $discountRuleID . "/discount_codes.json", $data);
    return $newDiscountCode;
}
function postDataDraftOrder($shopify, $data)
{
    if (!isset($data) || (!isset($shopify))) return [];
    $response = $shopify("POST", "/admin/draft_orders.json", $data);
    return $response;
}
function getMoneyFormat($shopify)
{
    $shopInfo = $shopify("GET", "/admin/shop.json");
    $result = array();
    if (isset($shopInfo['money_format'])) {
        $result['money_format'] = $shopInfo['money_format'];
        $result['money_with_currency_format'] = $shopInfo['money_with_currency_format'];
    } else {
        $result['money_format'] = NULL;
        $result['money_with_currency_format'] = NULL;
    }
    return $result;
}
function getCustomer($shopify, $customerId)
{
    if (!isset($customerId)) return [];
    $result = $shopify('GET', "/admin/customers/{$customerId}.json");
    return $result;
}
// RULE FUNCTION
function getSetting($shop)
{
    $settings = db_fetch_row("SELECT * FROM fb_pixel_settings WHERE shop = '$shop'");
    return $settings;
}
// orther function
function checkExistArray($array1, $array2)
{
    if (is_array($array1) && is_array($array2)) {
        $check = array();
        foreach ($array1 as $v1) {
            array_push($check, $v1);
        }
        foreach ($array2 as $v2) {
            if (in_array($v2, $check)) {
                return $result = 1;
                break;
            } else {
                $result = 0;
            }
        }
    } else {
        return 0;
    }
    return $result;
}
function getmicrotime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float) $usec + (float) $sec);
}
function cvf_convert_object_to_array($data)
{
    if (is_object($data)) {
        $data = get_object_vars($data);
    }
    if (is_array($data)) {
        return array_map(__FUNCTION__, $data);
    } else {
        return $data;
    }
}
function remove_dir($dir = null)
{
    if (is_dir($dir)) {
        $objects = scandir($dir);
        foreach ($objects as $object) {
            if ($object != "." && $object != "..") {
                if (filetype($dir . "/" . $object) == "dir") remove_dir($dir . "/" . $object);
                else unlink($dir . "/" . $object);
            }
        }
        reset($objects);
        rmdir($dir);
    }
}
function creatSlug($string, $plusString)
{
    $search = array(
        '#(à|á|ạ|ả|ã|â|ầ|ấ|ậ|ẩ|ẫ|ă|ằ|ắ|ặ|ẳ|ẵ)#',
        '#(è|é|ẹ|ẻ|ẽ|ê|ề|ế|ệ|ể|ễ)#',
        '#(ì|í|ị|ỉ|ĩ)#',
        '#(ò|ó|ọ|ỏ|õ|ô|ồ|ố|ộ|ổ|ỗ|ơ|ờ|ớ|ợ|ở|ỡ)#',
        '#(ù|ú|ụ|ủ|ũ|ư|ừ|ứ|ự|ử|ữ)#',
        '#(ỳ|ý|ỵ|ỷ|ỹ)#',
        '#(đ)#',
        '#(À|Á|Ạ|Ả|Ã|Â|Ầ|Ấ|Ậ|Ẩ|Ẫ|Ă|Ằ|Ắ|Ặ|Ẳ|Ẵ)#',
        '#(È|É|Ẹ|Ẻ|Ẽ|Ê|Ề|Ế|Ệ|Ể|Ễ)#',
        '#(Ì|Í|Ị|Ỉ|Ĩ)#',
        '#(Ò|Ó|Ọ|Ỏ|Õ|Ô|Ồ|Ố|Ộ|Ổ|Ỗ|Ơ|Ờ|Ớ|Ợ|Ở|Ỡ)#',
        '#(Ù|Ú|Ụ|Ủ|Ũ|Ư|Ừ|Ứ|Ự|Ử|Ữ)#',
        '#(Ỳ|Ý|Ỵ|Ỷ|Ỹ)#',
        '#(Đ)#',
        "/[^a-zA-Z0-9\-\_]/",
    );
    $replace = array(
        'a',
        'e',
        'i',
        'o',
        'u',
        'y',
        'd',
        'A',
        'E',
        'I',
        'O',
        'U',
        'Y',
        'D',
        '-',
    );
    $string = preg_replace($search, $replace, $string);
    $string = preg_replace('/(-)+/', '-', $string);
    $string = strtolower($string);
    return $string . $plusString;
}
function pr($data)
{
    if (is_array($data)) {
        echo "<pre>";
        print_r($data);
        echo "</pre>";
    } else {
        var_dump($data);
    }
}
function redirect($data)
{
    header("Location: $data");
}
function getCurl($url)
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/4.0 (compatible; MSIE 6.0; Windows NT 5.1');
    $response = curl_exec($ch);
    if ($response === false) {
        $api_response = curl_error($ch);
    } else {
        $api_response = $response;
    }
    curl_close($ch);
    return $api_response;
}
function valaditon_get($data)
{
    $data = "";
    if ($data)  return $data;
    return $data;
}
function result_fetch_object($data)
{
    $result = $data->fetch_object();
    return $result;
}
function getTime($shopify)
{
    $shopInfo  = $shopify("GET", "/admin/shop.json");
    if (!isset($shopInfo['iana_timezone'])) $shopInfo['iana_timezone'] = 'UTC';
    date_default_timezone_set($shopInfo['iana_timezone']);
    $today = date("Y-m-d");
    $yesterday = date("Y-m-d", time() - 60 * 60 * 24);
    $week = date("Y-m-d", time() - 60 * 60 * 24 * 7);
    $month = date("Y-m-d", time() - 60 * 60 * 24 * 30);
    $lastmonth = date("Y-m-d", time() - 60 * 60 * 24 * 30 * 2);
    return [
        'today'     => $today,
        'yesterday' => $yesterday,
        'week'      => $week,
        'month'     => $month,
        'lastmonth' => $lastmonth,
    ];
}
function shopify_call($token, $shop, $api_endpoint, $query = array(), $method = 'GET', $request_headers = array())
{

    // Build URL
    $url = "https://" . $shop . $api_endpoint;
    if (!is_null($query) && in_array($method, array('GET',  'DELETE'))) $url = $url . "?" . http_build_query($query);

    // Configure cURL
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_HEADER, TRUE);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, TRUE);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, TRUE);
    curl_setopt($curl, CURLOPT_MAXREDIRS, 3);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, FALSE);
    // curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 3);
    // curl_setopt($curl, CURLOPT_SSLVERSION, 3);
    curl_setopt($curl, CURLOPT_USERAGENT, 'My New Shopify App v.1');
    curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, 30);
    curl_setopt($curl, CURLOPT_TIMEOUT, 30);
    curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);

    // Setup headers
    $request_headers[] = "";
    if (!is_null($token)) $request_headers[] = "X-Shopify-Access-Token: " . $token;
    curl_setopt($curl, CURLOPT_HTTPHEADER, $request_headers);

    if ($method != 'GET' && in_array($method, array('POST', 'PUT'))) {
        if (is_array($query)) $query = http_build_query($query);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $query);
    }

    // Send request to Shopify and capture any errors
    $response = curl_exec($curl);
    $error_number = curl_errno($curl);
    $error_message = curl_error($curl);

    // Close cURL to be nice
    curl_close($curl);

    // Return an error is cURL has a problem
    if ($error_number) {
        return $error_message;
    } else {

        // No error, return Shopify's response by parsing out the body and the headers
        $response = preg_split("/\r\n\r\n|\n\n|\r\r/", $response, 2);

        // Convert headers into an array
        $headers = array();
        $header_data = explode("\n", $response[0]);
        $headers['status'] = $header_data[0]; // Does not contain a key, have to explicitly set
        array_shift($header_data); // Remove status, we've already set it above
        foreach ($header_data as $part) {
            $h = explode(":", $part);
            $headers[trim($h[0])] = trim($h[1]);
        }

        // Return headers and Shopify's response
        return array('headers' => $headers, 'response' => $response[1]);
    }
}
