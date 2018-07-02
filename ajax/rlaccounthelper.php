<?php

$api_key = include('api_key.php');
$steam_id = $_POST['steam_id'];

//logic
if ($_POST['method'] == 'get_display_name') {
    echo get_display_name($steam_id, $api_key);
}

//functions
function get_display_name($id, $api_key) {
    $curl = curl_init();
    $url = "https://api.rocketleaguestats.com/v1/player?unique_id=". $id ."&platform_id=1";

    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url,
        CURLOPT_HTTPHEADER => array("Authorization: ". $api_key)
    ));

    $response = json_decode(curl_exec($curl),true);

    if (isset($response['displayName'])) {
        return $response['displayName'];
    } else {
        return 'false';
    }
}
