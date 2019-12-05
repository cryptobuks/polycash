<?php
require_once(dirname(dirname(__FILE__))."/includes/connect.php");

$script_target_time = 174;
$loop_target_time = 30;
$script_start_time = microtime(true);

$allowed_params = ['key', 'print_debug'];
$app->safe_merge_argv_to_request($argv, $allowed_params);

if ($app->running_as_admin()) {
	$print_debug = false;
	if (!empty($_REQUEST['print_debug'])) $print_debug = true;
	
	$process_lock_name = "apply_strategies";
	$process_locked = $app->check_process_running($process_lock_name);
	
	if (!$process_locked) {
		$app->set_site_constant($process_lock_name, getmypid());
		
		$blockchains = [];
		
		do {
			$loop_start_time = microtime(true);
			
			$db_running_games = $app->run_query("SELECT * FROM games g JOIN blockchains b ON g.blockchain_id=b.blockchain_id WHERE b.online=1 AND g.game_status IN('published','running');");
			
			while ($db_running_game = $db_running_games->fetch()) {
				if (empty($blockchains[$db_running_game['blockchain_id']])) $blockchains[$db_running_game['blockchain_id']] = new Blockchain($app, $db_running_game['blockchain_id']);
				$running_game = new Game($blockchains[$db_running_game['blockchain_id']], $db_running_game['game_id']);
				
				if ($print_debug) {
					echo "Apply user strategies for ".$running_game->db_game['name']."...\n";
					$app->flush_buffers();
				}
				$running_game->apply_user_strategies($print_debug, 30);
				if ($print_debug) {
					echo "\n";
					$app->flush_buffers();
				}
			}
			
			$loop_time = microtime(true)-$loop_start_time;
			$sleep_usec = max(0, round(pow(10,6)*($loop_target_time - $loop_time)));
			
			if ($print_debug) {
				echo "Script run time: ".(microtime(true)-$script_start_time).", sleeping ".$sleep_usec/pow(10,6)." seconds.\n";
				$app->flush_buffers();
			}
			
			if ($sleep_usec > 0) usleep($sleep_usec);
		}
		while (microtime(true) < $script_start_time + ($script_target_time-$loop_time));
	}
	else echo "This process is already running.\n";
}
else echo "Please run this script as admin.\n";