<?php

$api_key = include('api_key.php');
$steam_id = $_POST['steam_id'];

//logic
if ($_POST['method'] == 'validate') {
    echo validate_steam_id($steam_id);
}

//functions
function validate_steam_id($id, $api_key) {
    $curl = curl_init();
    $url = "https://api.rocketleaguestats.com/v1/player?unique_id=". $id ."&platform_id=1&apikey=". $api_key;

    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => $url
    ));

    $response = json_decode(curl_exec($curl),true);

    if (isset($response['displayName'])) {
        return $response['displayName'];
    } else {
        return 'false';
    }
}
