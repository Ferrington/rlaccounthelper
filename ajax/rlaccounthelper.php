<?php

require 'user.class.php';
$api_key = require 'api_key.php';
$config = require 'config.php';

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
    } else {
		die();
	}

    $user = new User($user_id, $config, $api_key);   
    echo $user->add_account($steam_id, $account_name);
	
} elseif ($_POST['method'] == 'update_ranks') {	

	$user = new User($user_id, $config, $api_key);   
    echo $user->update_all_accounts();
	
} elseif ($_POST['method'] == 'generate_user_id') {
	
    $user = new User('', $config, $api_key);
    echo $user->get_user_id();
	
} elseif ($_POST['method'] == 'get_all_accounts') {
	
	$user = new User($user_id, $config, $api_key);
    echo json_encode($user->get_all_accounts());
	
} elseif ($_POST['method'] == 'delete_account') {
	
	if (isset($_POST['steam_id'])) {
		$steam_id = $_POST['steam_id'];
	} else {
		die();
	}
	
	$user = new User($user_id, $config, $api_key);
    echo $user->delete_account($steam_id);
	
}

