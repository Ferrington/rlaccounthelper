<?php
require '../resources/simple_html_dom.php';

class User {
	const PLAYLISTS = [
//		'0_' => 'Un-Ranked',
		'1_' => 'Ranked Duel 1v1',
		'2_' => 'Ranked Doubles 2v2',
		'3_' => 'Ranked Standard 3v3',
		'3s_' => 'Ranked Solo Standard 3v3'
	];
	const DIVS = [
		0 => ' I ',
		1 => ' II ',
		2 => ' III ',
		3 => ' IV'
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
		
		$stmt = $this->db->prepare("UPDATE users SET
										display_name = :display_name, avatar = :avatar,
										_1_mmr = :1_mmr, _1_tier = :1_tier, _1_division = :1_division, 
										_2_mmr = :2_mmr, _2_tier = :2_tier, _2_division = :2_division, 
										_3s_mmr = :3s_mmr, _3s_tier = :3s_tier, _3s_division = :3s_division, 
										_3_mmr = :3_mmr, _3_tier = :3_tier, _3_division = :3_division
									WHERE guid = :guid AND steam_id = :steam_id");
									
		foreach ($steam_ids as $steam_id) {
			$update_arr = $this->get_single_account_info($steam_id);
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
		$html = file_get_html('https://rocketleague.tracker.network/profile/steam/'.$steam_id);
	
		if (!$table = $html->find('.card-table', 1))
			return false;

		$tbody = $table->find('tbody', 0);
		
		$account_data = [];
		foreach ($tbody->find('tr') as $row) {
			$playlist = $this->get_playlist_id($row->find('td', 1)->plaintext);
			if ($playlist === false)
				continue;
			
			$account_data[$playlist.'tier'] = str_replace('.png','',substr($row->find('td', 0)->find('img', 0)->src, 21));
			$mmr = explode(' ', $row->find('td', 3)->plaintext, 2);
			$account_data[$playlist.'mmr'] = str_replace(',', '', $mmr[0]);
			$account_data[$playlist.'division'] = $this->get_div($row->find('td', 1)->plaintext);
		}
		
		$this->fill_in_the_blanks($account_data);
		
		$steam64id = $this->get_steam64id($steam_id);
		$avatar_and_displayname = $this->get_avatar_and_displayname($steam64id);
		$account_data['avatar'] = $avatar_and_displayname['avatar'];
		$account_data['display_name'] = $avatar_and_displayname['displayname'];
		
		return $account_data;
    }
	
	private function fill_in_the_blanks(&$account_data)
	{
		foreach (self::PLAYLISTS as $id => $str) {
			if (!in_array($id.'tier', array_keys($account_data))) {
				$blanks = [
					$id.'tier' => 0,
					$id.'mmr' => 0,
					$id.'division' => 0
				];
				$account_data = array_merge($account_data, $blanks);
			}
		}
	}
	
	private function get_playlist_id($txt)
	{
		foreach (self::PLAYLISTS as $id => $str)
		{
			if (strpos($txt, $str) !== false)
				return $id;		
		}

		return false;
	}
	
	private function get_div($txt)
	{
		$pos = strpos($txt, 'Division');
		foreach (self::DIVS as $id => $str)
		{
			if (strpos($txt." ", $str, $pos) !== false)
				return $id;
		}
		
		return false;
	}
	
	private function get_avatar_and_displayname($steam64id)
	{
		$url = "http://api.steampowered.com/ISteamUser/GetPlayerSummaries/v0002/?key=".$this->api_key."&steamids=".$steam64id;
		$response = json_decode(file_get_html($url)->plaintext, true);
		return ['avatar' => $response['response']['players'][0]['avatarfull'], 'displayname' => $response['response']['players'][0]['personaname']];
	}

	private function get_steam64id($vanity)
	{
		$url = "https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?key=".$this->api_key."&vanityurl=".$vanity;
		$response = json_decode(file_get_html($url)->plaintext, true);
		if ($response['response']['message'] == 'No match')
			return $vanity;
		return $response['response']['steamid'];
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