<?php
/**
 * Online
 *
 * @package   MyAAC
 * @author    Gesior <jerzyskalski@wp.pl>
 * @author    Slawkens <slawkens@gmail.com>
 * @copyright 2019 MyAAC
 * @link      https://my-aac.org
 */

use MyAAC\Models\ServerConfig;
use MyAAC\Models\ServerRecord;

defined('MYAAC') or die('Direct access not allowed!');
$title = 'Who is online?';

if (setting('core.account_country'))
	require SYSTEM . 'countries.conf.php';

$promotion = '';
if($db->hasColumn('players', 'promotion'))
	$promotion = '`promotion`,';

$order = $_GET['order'] ?? 'name_asc';
if(!in_array($order, ['country_asc', 'country_desc', 'name_asc', 'name_desc', 'level_asc', 'level_desc', 'vocation_asc', 'vocation_desc'])) {
	$order = 'name_asc';
}
else if($order == 'vocation_asc' || $order == 'vocation_desc') {
	$order = $promotion . 'vocation_' . (str_contains($order, 'asc') ? 'asc' : 'desc');
}

$orderExplode = explode('_', $order);
$orderSql = $orderExplode[0] . ' ' . $orderExplode[1];

$skull_type = 'skull';
if($db->hasColumn('players', 'skull_type')) {
	$skull_type = 'skull_type';
}

$skull_time = 'skulltime';
if($db->hasColumn('players', 'skull_time')) {
	$skull_time = 'skull_time';
}

$outfit_addons = false;
$outfit = '';
if (setting('core.online_outfit')) {
	$outfit = ', lookbody, lookfeet, lookhead, looklegs, looktype';
	if($db->hasColumn('players', 'lookaddons')) {
		$outfit .= ', lookaddons';
		$outfit_addons = true;
	}
}

$vocs = [];
if (setting('core.online_vocations')) {
	foreach($config['vocations'] as $id => $name) {
		$vocs[$id] = 0;
	}
}

if($db->hasTable('players_online')) // tfs 1.0
	$playersOnline = $db->query('SELECT `accounts`.`country`, `players`.`name`, `players`.`level`, `players`.`vocation`' . $outfit . ', `' . $skull_time . '` as `skulltime`, `' . $skull_type . '` as `skull` FROM `accounts`, `players`, `players_online` WHERE `players`.`id` = `players_online`.`player_id` AND `accounts`.`id` = `players`.`account_id`  ORDER BY ' . $orderSql);
else
	$playersOnline = $db->query('SELECT `accounts`.`country`, `players`.`name`, `players`.`level`, `players`.`vocation`' . $outfit . ', ' . $promotion . ' `' . $skull_time . '` as `skulltime`, `' . $skull_type . '` as `skull` FROM `accounts`, `players` WHERE `players`.`online` > 0 AND `accounts`.`id` = `players`.`account_id`  ORDER BY ' . $orderSql);

$players_data = [];
$players = 0;
$data = '';
foreach($playersOnline as $player) {
	$skull = '';
	if (setting('core.online_skulls'))
	{
		if($player['skulltime'] > 0)
		{
			if($player['skull'] == 3)
				$skull = ' <img style="border: 0;" src="images/white_skull.gif"/>';
			elseif($player['skull'] == 4)
				$skull = ' <img style="border: 0;" src="images/red_skull.gif"/>';
			elseif($player['skull'] == 5)
				$skull = ' <img style="border: 0;" src="images/black_skull.gif"/>';
		}
	}

	if(isset($player['promotion'])) {
		if((int)$player['promotion'] > 0)
			$player['vocation'] += ($player['promotion'] * $config['vocations_amount']);
	}

	$players_data[] = array(
		'name' => getPlayerLink($player['name']),
		'player' => $player,
		'level' => $player['level'],
		'vocation' => $config['vocations'][$player['vocation']],
		'country_image' => setting('core.account_country') ? getFlagImage($player['country']) : null,
		'outfit' => setting('core.online_outfit') ? setting('core.outfit_images_url') . '?id=' . $player['looktype'] . ($outfit_addons ? '&addons=' . $player['lookaddons'] : '') . '&head=' . $player['lookhead'] . '&body=' . $player['lookbody'] . '&legs=' . $player['looklegs'] . '&feet=' . $player['lookfeet'] : null
	);

	if (setting('core.online_vocations')) {
		$vocs[($player['vocation'] > $config['vocations_amount'] ? $player['vocation'] - $config['vocations_amount'] : $player['vocation'])]++;
	}
}

$record = '';
if(count($players_data) > 0) {
	if( setting('core.online_record')) {
		$result = null;
		$timestamp = false;
		if($db->hasTable('server_record')) {
			$timestamp = true;
			$result = ServerRecord::where('world_id', $config['lua']['worldId'])->orderByDesc('record')->first()->toArray();
		} else if($db->hasTable('server_config')) { // tfs 1.0
			$row = ServerConfig::where('config', 'players_record')->first();
			if ($row) {
				$result = ['record' => $row->value];
			}
		}

		if($result) {
			$record = $result['record'] . ' player' . ($result['record'] > 1 ? 's' : '') . ($timestamp ? ' (on ' . date("M d Y, H:i:s", $result['timestamp']) . ')' : '');
		}
	}
}

$twig->display('online.html.twig', array(
	'players' => $players_data,
	'record' => $record,
	'vocs' => $vocs,
	'order' => $order,
));

//search bar
$twig->display('characters.form.html.twig');
?>
