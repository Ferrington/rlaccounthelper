<?php

define("SEASON", 8);

$api_key = include('api_key.php');
if (isset($_POST['steam_id'])) {
    $steam_id = $_POST['steam_id'];
}
if (isset($_POST['account_data'])) {
    $account_data = $_POST['account_data'];
}


//logic
if ($_POST['method'] == 'get_display_name') {
    echo get_display_name($steam_id, $api_key);
} elseif ($_POST['method'] == 'get_rank_info') {
    print_r(get_rank_info($account_data, $api_key));
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

function get_rank_info($account_data, $api_key) {
    $payload_arr = array();
    $batch = $i = 0;
    $game_modes = array(
        10 => 'solo_duel', 
        11 => 'doubles', 
        12 => 'solo_standard', 
        13 => 'standard'
    );
    foreach ($account_data as $key => $value) { 
        $payload_arr[$batch][] = array("platformId" => "1", "uniqueId" => $value['steam_id']);
        $i++;
        if ($i == 10) {
            $batch++;
            $i = 0;
        }
    }
    
    foreach ($payload_arr as $batch => $payload) {
        $curl = curl_init();
        $url = "https://api.rocketleaguestats.com/v1/player/batch";
        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array("Authorization: ". $api_key, 'Content-Type:application/json'),
            CURLOPT_POSTFIELDS => json_encode($payload)
        ));
        
        $response = json_decode(curl_exec($curl),true);
    
        foreach ($response as $i => $player) {
            $account_index = $batch * 10 + $i;
            
            foreach ($game_modes as $mode_number => $game_mode) {
                $account_data[$account_index][$game_mode]['mmr'] = $player['rankedSeasons'][SEASON][$mode_number]['rankPoints'];
                $account_data[$account_index][$game_mode]['tier'] = $player['rankedSeasons'][SEASON][$mode_number]['tier'];
                $account_data[$account_index][$game_mode]['division'] = $player['rankedSeasons'][SEASON][$mode_number]['division'];
            }
            
        }
    }
    
    return $account_data;
}
