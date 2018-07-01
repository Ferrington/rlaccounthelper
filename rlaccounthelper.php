<?php

$api_key = include('api_key.php');
//$steam_id = $_POST['steam_id'];

$steam_id = "imatwig";
//logic
/*
if ($_POST['method'] == 'validate') {
    echo validate_steam_id($steam_id);
}
*/
//functions
function validate_steam_id($id, $api_key) {
    $fp = fopen(dirname(__FILE__).'/errorlog.txt', 'w');
    $curl = curl_init();
    
    curl_setopt_array($curl, array(
        CURLOPT_RETURNTRANSFER => 1,
        CURLOPT_URL => "https://api.rocketleaguestats.com/v1/player?unique_id=". $id ."&platform_id=1",
    ));
    curl_setopt($curl, CURLOPT_USERPWD, $api_key);
    curl_setopt($curl, CURLOPT_VERBOSE, 1);
    curl_setopt($curl, CURLOPT_STDERR, $fp);
    
    $response = curl_exec($curl);
    
    return $response;
    
}

echo validate_steam_id($steam_id, $api_key);