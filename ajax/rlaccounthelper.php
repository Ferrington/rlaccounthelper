<?php

$api_key = include('api_key.php');
$config = include('config.php');

class User {
    private const SEASON = 8;
    private $api_key;
    private $db;
    private $user_id;
    private $user_data;
    
    public function __construct($user_id, $config, $api_key) 
    {
        $this->user_id = $user_id ?: $this->generate_user_id();
        $this->api_key = $api_key;
        
        if ($user_id) {
            $this->db = new PDO("mysql:host=".$config['host'].";dbname=".$config['db'].";charset=".$config['charset'], $config['user'], $config['pass']);
            $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);        
        }
    }
    
    
    //public methods
    public function get_user_id() 
    {
        return $this->user_id;
    }
    
    public function add_account($steam_id, $account_name) 
    {
        if ($this->get_number_of_accounts() >= 20) {
            return "too many accounts";
        } elseif ($this->account_exists($steam_id)) {
            return "account exists";
        }
        
        $account_info = $this->get_single_account_info($steam_id);
        $account_info['guid'] = $this->user_id;
        $account_info['steam_id'] = $steam_id;
        $account_info['account_name'] = $account_name;
        
             
        $stmt = $this->db->prepare("INSERT INTO users (guid, steam_id, account_name, display_name, 1_mmr, 1_tier, 1_division, 2_mmr, 2_tier, 2_division, 3s_mmr, 3s_tier, 3s_division, 3_mmr, 3_tier, 3_division) 
                                    VALUES (:guid, :steam_id, :account_name, :display_name, :1_mmr, :1_tier, :1_division, :2_mmr, :2_tier, :2_division, :3s_mmr, :3s_tier, :3s_division, :3_mmr, :3_tier, :3_division)");
        if ($stmt->execute($account_info)) {
            return "success";
        } else {
            return "failure";
        }
    }
    
    
    //private methods
    private function account_exists($steam_id)
    {
        $stmt = $this->db->prepare("SELECT count(*) FROM users WHERE guid LIKE ? AND steam_id LIKE ?");
        $stmt->execute([$this->user_id, $steam_id]);
        $count = $stmt->fetchColumn();
        
        return $count;
    }
       
    private function get_number_of_accounts() 
    {
        $stmt = $this->db->prepare("SELECT count(*) FROM users WHERE guid LIKE ?");
        $stmt->execute([$this->user_id]);
        $count = $stmt->fetchColumn();
        
        return $count;
    }
    
    private function generate_user_id() 
    {
        return $this->guidv4(openssl_random_pseudo_bytes(16));
    }
    
    private function get_single_account_info($steam_id) {
        $game_modes = array(
            10 => '1_', 
            11 => '2_', 
            12 => '3s_', 
            13 => '3_'
        );
        $data_categories = array(
            'rankPoints' => 'mmr',
            'tier' => 'tier',
            'division' => 'division'
        );
        $curl = curl_init();
        $url = "https://api.rocketleaguestats.com/v1/player?unique_id=". $steam_id ."&platform_id=1";

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array("Authorization: ". $this->api_key)
        ));

        $response = json_decode(curl_exec($curl),true);

        if (isset($response['displayName'])) {
            foreach ($game_modes as $mode_number => $game_mode) {
                foreach ($data_categories as $theirs => $mine) {
                    $account_data[$game_mode.$mine] = $response['rankedSeasons'][self::SEASON][$mode_number][$theirs];
                }
            }
            $account_data['display_name'] = $response['displayName'];
            return $account_data;
        } else {
            return false;
        }
    }
  
    private function guidv4($data) 
    {
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));    
    }
}
/*
$user = new User('abcd', $config, $api_key);
echo $user->get_number_of_accounts();
die();
*/

//logic
if (!isset($_POST['method'])) {
    die();
}
if (isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
} else {
    die();
}

if ($_POST['method'] == 'add_account') {

    if (isset($_POST['steam_id']) and isset($_POST['account_name'])) {
        $steam_id = $_POST['steam_id'];
        $account_name = $_POST['account_name']; 
    }

    
    $user = new User($user_id, $config, $api_key);
    
    print_r($user->add_account($steam_id, $account_name));

} elseif ($_POST['method'] == 'generate_user_id') {
    $user = new User('', $config, $api_key);
    echo $user->get_user_id();
}

//functions

function get_batch_rank_info($account_data, $api_key) {
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
    
        print_r($account_data);
        die();
        foreach ($response as $i => $player) {
            $account_index = $batch * 10 + $i;
            die($account_index);
            foreach ($game_modes as $mode_number => $game_mode) {
                $account_data[$account_index][$game_mode]['mmr'] = $player['rankedSeasons'][SEASON][$mode_number]['rankPoints'];
                $account_data[$account_index][$game_mode]['tier'] = $player['rankedSeasons'][SEASON][$mode_number]['tier'];
                $account_data[$account_index][$game_mode]['division'] = $player['rankedSeasons'][SEASON][$mode_number]['division'];
            }
            
        }
    }
    
    return json_encode($account_data);
}
