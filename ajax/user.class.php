<?php

class User {
    const SEASON = 8;
	const GAME_MODES = [
		10 => '1_', 
		11 => '2_', 
		12 => '3s_', 
		13 => '3_'
	];
    const DATA_CATEGORIES = [
		'rankPoints' => 'mmr',
		'tier' => 'tier',
		'division' => 'division'
	];
    private $api_key;
    private $db;
    private $user_id;


    public function __construct($user_id, $config, $api_key) 
    {
        $this->user_id = $user_id ?: $this->generate_user_id();
        $this->api_key = $api_key;
		$this->db = new PDO("mysql:host=".$config['host'].";dbname=".$config['db'].";charset=".$config['charset'], $config['user'], $config['pass']);
		$this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);        
    }
    
    
	
    //public methods
    public function get_user_id() 
    {
        return $this->user_id;
    }
		
	public function get_all_accounts() 
	{
		$stmt = $this->db->prepare("SELECT steam_id, account_name, display_name, avatar, _1_mmr, _1_tier, _1_division, _2_mmr, _2_tier, _2_division, _3s_mmr, _3s_tier, _3s_division, _3_mmr, _3_tier, _3_division FROM users WHERE guid LIKE ?");
		$stmt->execute([$this->user_id]);
		
		$account_data = $stmt->fetchALL(PDO::FETCH_ASSOC);
		
		if (count($account_data) == 0) {
			$this->user_id = "sample_account";
			return $this->get_all_accounts();
		}
		
		return $account_data;
	}	
    
    public function add_account($steam_id, $account_name) 
    {
		$safe_user_id = filter_var($this->user_id, FILTER_SANITIZE_STRING);
		$steam_id = filter_var($steam_id, FILTER_SANITIZE_STRING);
		$account_name = filter_var($account_name, FILTER_SANITIZE_STRING);		
        if ($this->get_number_of_accounts() >= 20) {
            return "too many accounts";
        } elseif ($this->account_exists($steam_id)) {
            return "account exists";
        }
        
        if (!$account_info = $this->get_single_account_info($steam_id)) {
			return "invalid steam id";
		};
        $account_info['guid'] = $safe_user_id;
        $account_info['steam_id'] = $steam_id;
        $account_info['account_name'] = $account_name;
        
             
        $stmt = $this->db->prepare("INSERT INTO users (guid, steam_id, account_name, display_name, avatar, _1_mmr, _1_tier, _1_division, _2_mmr, _2_tier, _2_division, _3s_mmr, _3s_tier, _3s_division, _3_mmr, _3_tier, _3_division) 
                                    VALUES (:guid, :steam_id, :account_name, :display_name, :avatar, :1_mmr, :1_tier, :1_division, :2_mmr, :2_tier, :2_division, :3s_mmr, :3s_tier, :3s_division, :3_mmr, :3_tier, :3_division)");
        if ($stmt->execute($account_info)) {
            return "success";
        } else {
            return "failure";
        }
    }
	
	public function update_all_accounts() 
	{
		$steam_ids = $this->get_all_steam_ids();
		if (count($steam_ids) == 0) {
			sleep(1);
			die();
		}
		
		$account_info = $this->get_batch_rank_info($steam_ids);
		
		$stmt = $this->db->prepare("UPDATE users SET
										display_name = :display_name, avatar = :avatar,
										_1_mmr = :1_mmr, _1_tier = :1_tier, _1_division = :1_division, 
										_2_mmr = :2_mmr, _2_tier = :2_tier, _2_division = :2_division, 
										_3s_mmr = :3s_mmr, _3s_tier = :3s_tier, _3s_division = :3s_division, 
										_3_mmr = :3_mmr, _3_tier = :3_tier, _3_division = :3_division
									WHERE guid = :guid AND steam_id = :steam_id");
									
		foreach ($account_info as $steam_id => $account) {
			$update_arr = $account;
			$update_arr['steam_id'] = $steam_id;
			$update_arr['guid'] = $this->user_id;
			
			$stmt->execute($update_arr);
		}
	}

	public function delete_account($steam_id) 
	{
		$stmt = $this->db->prepare("DELETE FROM users WHERE guid LIKE ? AND steam_id LIKE ?");
		if ($stmt->execute([$this->user_id, $steam_id])) {
			return "success";
		} else {
			return "failure";
		}
	}
    
    
	
    //private methods
	private function get_all_steam_ids() 
	{
		$stmt = $this->db->prepare("SELECT steam_id FROM users WHERE guid LIKE ?");
		$stmt->execute([$this->user_id]);
		
		return $stmt->fetchALL(PDO::FETCH_COLUMN);	
	}
	
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

    private function get_single_account_info($steam_id) 
	{
		$this->check_rate_limit();

        $curl = curl_init();
        $url = "https://api.rocketleaguestats.com/v1/player?unique_id=". $steam_id ."&platform_id=1";

        curl_setopt_array($curl, array(
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_URL => $url,
            CURLOPT_HTTPHEADER => array("Authorization: ". $this->api_key)
        ));

        $response = json_decode(curl_exec($curl),true);

        if (isset($response['displayName'])) {
            foreach (self::GAME_MODES as $mode_number => $game_mode) {
				if (!isset($response['rankedSeasons'][self::SEASON][$mode_number])) {
					foreach (self::DATA_CATEGORIES as $theirs => $mine) {
						$account_data[$game_mode.$mine] = 0;
					}
					continue;
				}
                foreach (self::DATA_CATEGORIES as $theirs => $mine) {
                    $account_data[$game_mode.$mine] = $response['rankedSeasons'][self::SEASON][$mode_number][$theirs] ?? 0;
                }
            }
            $account_data['display_name'] = $response['displayName'];
			$account_data['avatar'] = $response['avatar'];
            return $account_data;
        } else {
            return false;
        }
    }
	
	private function get_batch_rank_info($steam_ids) 
	{
		$this->check_rate_limit();
		
		$payload_arr = [];
		$batch = $i = 0;

		foreach ($steam_ids as $id) { 
			$payload_arr[$batch][] = ["platformId" => "1", "uniqueId" => $id];
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
				CURLOPT_HTTPHEADER => array("Authorization: ". $this->api_key, 'Content-Type:application/json'),
				CURLOPT_POSTFIELDS => json_encode($payload)
			));
			
			$response = json_decode(curl_exec($curl),true);
		
			foreach ($response as $i => $player) {
				$account_index = $batch * 10 + $i;
				foreach (self::GAME_MODES as $mode_number => $game_mode) {
					if (!isset($player['rankedSeasons'][self::SEASON][$mode_number])) {
						foreach (self::DATA_CATEGORIES as $theirs => $mine) {
							$account_data[$steam_ids[$account_index]][$game_mode.$mine] = 0;
						}
						continue;
					}
					foreach (self::DATA_CATEGORIES as $theirs => $mine) {
						$account_data[$steam_ids[$account_index]][$game_mode.$mine] = $player['rankedSeasons'][self::SEASON][$mode_number][$theirs];
					}
				}
				$account_data[$steam_ids[$account_index]]['display_name'] = $player['displayName'];
				$account_data[$steam_ids[$account_index]]['avatar'] = $player['avatar'];
			}
		}
		
		return $account_data;
	}
	
	//add delay if request as been made recently
	private function check_rate_limit() 
	{
		$diff = $this->db->query("SELECT ROUND(TIMESTAMPDIFF(MICROSECOND, last_request, NOW(3))) FROM rate_limit WHERE id = 1")->fetchColumn();
		
		$sleepy_time = $diff < 1000000 ? 1000000 - $diff : 0;
		
		$this->db->query("UPDATE rate_limit SET last_request = SUBSTRING(DATE_ADD(NOW(3), INTERVAL ". $diff ." MICROSECOND),1,23) WHERE id = 1");
		
		if ($diff < 1000000) {
			usleep($sleepy_time);			
		}
	}
  
    private function generate_user_id() 
    {
        return $this->guidv4(openssl_random_pseudo_bytes(16));
    }
    
    private function guidv4($data) 
    {
        assert(strlen($data) == 16);

        $data[6] = chr(ord($data[6]) & 0x0f | 0x40); // set version to 0100
        $data[8] = chr(ord($data[8]) & 0x3f | 0x80); // set bits 6-7 to 10

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));    
    }
}