//set global
var user_id;
var account_data = {};
var ajax_url = "ajax/rlaccounthelper.php";

//on load events
$(function() {
	load_user_id();
	tippy('.fa-info-circle', {
		allowTitleHTML: true
	})
	$("#account-table").tablesorter({
		headers: {
			0: {sorter: false},
			4: {sorter: "digit"},
			5: {sorter: "digit"},
			6: {sorter: "digit"},
			7: {sorter: "digit"},
			8: {sorter: false}
		},
		textExtraction: {
			4: function(node, table, cellIndex) { return $(node).find(".td-mmr").text(); },
			5: function(node, table, cellIndex) { return $(node).find(".td-mmr").text(); },
			6: function(node, table, cellIndex) { return $(node).find(".td-mmr").text(); },
			7: function(node, table, cellIndex) { return $(node).find(".td-mmr").text(); }
		}
	}); 
});

//triggers
$('#add-account').click(function() {  
	add_account();
	$(this).blur();
});
$('#steam-id').keyup(clear_error);
$('#account-table tbody').on('click', '.remove-account', function() {
	var steam_id = $(this).closest('tr').find('td').eq(1).text();
	remove_account(steam_id);
});
$('.add-account input').keypress(function(e) {
	var key = e.which;
	if (key ==13) {
		$('#add-account').click();
	}
});
$('#update-ranks').click(update_ranks);

//functions        
function update_ranks() {
	start_loading_animation($('#update-ranks'));
	$.post(ajax_url, {user_id: user_id, method: 'update_ranks'}).then(function(data) {
		console.log(data);
		draw_table();
		stop_loading_animation($('#update-ranks'));
	}, function(data) {
		//error
		stop_loading_animation($('#update-ranks'));
	});
	//
}
function add_account() {
	clear_error();
	if (missing_required_field()) {
		$('#steam-id').focus();
		return;
	}
	
	start_loading_animation($('#add-account'));
	
	var steam_id = $('#steam-id').val();
	var account_name = $('#account-name').val();
 
	//attempt to add account
	$.post(ajax_url, {user_id: user_id, steam_id: steam_id, account_name: account_name, method: 'add_account'}).then(function(data) {
		add_account_return_action(data);
	}, function(data) {
		$('.bad-things').show();
		stop_loading_animation($('#add-account'));
	});
}
function add_account_return_action(data) {
	if (data == 'success') {
		clear_fields();
		draw_table();
	} else if (data =='too many accounts') {
		$('.too-many-accounts').show();
		$('#steam-id').addClass('is-invalid');
	} else if (data == 'invalid steam id') {
		$('.invalid-steam-id').show();
		$('#steam-id').addClass('is-invalid');
	} else if (data == 'account exists') {
		$('.account-exists').show();
		$('#steam-id').addClass('is-invalid');
	} else {
		$('.bad-things').show();
	}
	stop_loading_animation($('#add-account'));		
}
function draw_table() {
	$.post(ajax_url, {user_id: user_id, method: 'get_all_accounts'}, function(data) {
		var account_data = JSON.parse(data);
		account_data.sort(sort_by_steam_id);
		
		$('#account-table tbody').html('');
		
		$.each(account_data, function(key, row) {
			let html = "<tr><td class='avatar-td'><div class='avatar-container'><img src='"+ row.avatar +"' class='img-fluid' alt='steam avatar'></div></td>";
			html += "<td class='long-word'>"+ row.steam_id +"</td>";
			html += "<td class='long-word'>"+ row.account_name +"</td>";
			html += "<td class='long-word'>"+ row.display_name +"</td>";
			html += "<td class='rank-td'>"+ format_ranks("_1_", row) + "</td>";
			html += "<td class='rank-td'>"+ format_ranks("_2_", row) + "</td>";
			html += "<td class='rank-td'>"+ format_ranks("_3s_", row) + "</td>";
			html += "<td class='rank-td'>"+ format_ranks("_3_", row) + "</td>";
			html += "<td><button type='button' class='btn btn-default btn-sm remove-account'><span class='fas fa-times' aria-hidden='true'></span></button></td></tr>";				
			$('#account-table tbody').append(html);
		});
		
		$("#account-table").trigger("update");
	});
}
function format_ranks(playlist, row) {
	var rank_images = {
		0: "unranked",
		1: "bronze1",
		2: "bronze2",
		3: "bronze3",
		4: "silver1",
		5: "silver2",
		6: "silver3",
		7: "gold1",
		8: "gold2",
		9: "gold3",
		10: "plat1",
		11: "plat2",
		12: "plat3",
		13: "diamond1",
		14: "diamond2",
		15: "diamond3",
		16: "champ1",
		17: "champ2",
		18: "champ3",
		19: "gc"		
	}	
	
	var mmr = parseInt(row[playlist + "mmr"]) || "&nbsp;";
	if (mmr < 1000) {
		mmr = "&nbsp;&nbsp;" + mmr;
	}
	if (row[playlist + "tier"] == 0) {
		var division = "&nbsp;";
	} else {
		var division = "Div. " + (1 + parseInt(row[playlist + "division"]));
	}
	
	var output = "<div class='rank-image-div'><img src='imgs/"+ rank_images[row[playlist + "tier"]] + ".png' class='img-fluid' alt='rank image'></div>";
	output += "<div class='rank-span-div'><span class='td-mmr'>"+ mmr +"</span><span class='td-division'>"+ division +"</span></div>"
	return output;
	
}
function clear_fields() {
	$('.add-account input').val('');
}
function load_user_id() {
	var cookie = Cookies.getJSON('rlaccounthelper');
	if (cookie === undefined) {
		$.post(ajax_url, {user_id: '', method: 'generate_user_id'}, function(user_id) {
			Cookies.set('rlaccounthelper', user_id, {expires: 365});
			load_user_id();
		});
	} else {
		user_id = cookie;
		draw_table();
	}
}
function missing_required_field() {
	if (!$('#steam-id').val()) {
		$('.lacking-steam-id').show();
		$('#steam-id').addClass('is-invalid');
		return true;
	}
	return false;
}
function start_loading_animation($this) {
	var loading_text = '<i class="fas fa-spinner fa-spin"></i> Loading';
	
	$this.prop('disabled', true);
   
	if ($this.html() !== loading_text) {
		$this.data('original-text', $this.html());
		$this.html(loading_text);
	}
}
function stop_loading_animation($this) {
	$this.prop('disabled', false);
	$this.html($this.data('original-text'));
	if ($this.attr('id') == 'add-account') {
		$('#steam-id').focus();
	}
}
function clear_error() {
	$('.invalid-feedback').hide();
	$('#steam-id').removeClass('is-invalid');
}
function remove_account(steam_id) {
	$.post(ajax_url, {user_id: user_id, steam_id: steam_id, method: 'delete_account'}, function(data) {
		if (data == 'success') {
			draw_table();
		} else {
			$('.bad-things').show(); //replace this with another error text
		}
	});
}
function sort_by_steam_id(a, b){
	var a = a.steam_id.toLowerCase();
	var b = b.steam_id.toLowerCase(); 
	return ((a < b) ? -1 : ((a > b) ? 1 : 0));
}