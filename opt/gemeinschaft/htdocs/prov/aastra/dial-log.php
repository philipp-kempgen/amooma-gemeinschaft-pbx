<?php
/*******************************************************************\
*            Gemeinschaft - asterisk cluster gemeinschaft
* 
* $Revision: 2480 $
* 
* Copyright 2007, amooma GmbH, Bachstr. 126, 56566 Neuwied, Germany,
* http://www.amooma.de/
* Stefan Wintermeyer <stefan.wintermeyer@amooma.de>
* Philipp Kempgen <philipp.kempgen@amooma.de>
* Peter Kozak <peter.kozak@amooma.de>
* 
* This program is free software; you can redistribute it and/or
* modify it under the terms of the GNU General Public License
* as published by the Free Software Foundation; either version 2
* of the License, or (at your option) any later version.
* 
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
* 
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free Software
* Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
* MA 02110-1301, USA.
\*******************************************************************/

# caution: earlier versions of Aastra firmware do not like
# indented XML

define( 'GS_VALID', true );  /// this is a parent file

require_once( '../../../inc/conf.php' );
require_once( GS_DIR .'inc/db_connect.php' );
require_once( GS_DIR .'inc/gs-fns/gs_aastrafns.php' );
$xml_buffer = '';

$type = trim( @$_REQUEST['t'] );
if (! in_array( $type, array('in','out','missed', 'ind', 'outd', 'missedd'), true )) {
	$type = false;
}

$timestamp = trim( @$_REQUEST['e'] );
$number = trim( @$_REQUEST['n'] );

$num_results = (int)gs_get_conf('GS_AASTRA_PROV_PB_NUM_RESULTS', 10);
$db = gs_db_slave_connect();

$typeToTitle = array(
	'out'    => "Gew�hlt",
	'missed' => "Verpasst",
	'in'     => "Angenommen",
);

$remote_addr = @$_SERVER['REMOTE_ADDR'];
	
$remote_addr = '192.168.0.201';

$user_id = $db->executeGetOne( 'SELECT `id` FROM `users` WHERE `current_ip`=\''. $remote_addr.'\'' );

if (!$type) {

	
	
	
	aawrite('<AastraIPPhoneTextMenu destroyOnExit="yes" LockIn="no" style="none">');
	aawrite('<Title>'.__('Anrufliste').'</Title>');
	
	foreach ($typeToTitle as $key => $title) {
		aawrite('<MenuItem>');
		aawrite('<Prompt>'.$title.'</Prompt>');
		aawrite('<URI>http://'.GS_PROV_HOST.':'.GS_PROV_PORT.'/aastra/dial-log.php?t='.$key.'</URI>');
		//aawrite('<Selection>0&amp;menu_pos=1</Selection>'."\n";
		aawrite('</MenuItem>');
	} 

	aawrite('<SoftKey index="1">');
	aawrite('<Label>OK</Label>');
	aawrite('<URI>SoftKey:Select</URI>');
	aawrite('</SoftKey>');
	aawrite('<SoftKey index="4">');
	aawrite('<Label>'.__('Abbrechen').'</Label>');
	aawrite('<URI>SoftKey:Exit</URI>');
	aawrite('</SoftKey>');
	aawrite('</AastraIPPhoneTextMenu>');

} else if ($type=='out' || $type=='in' || $type=='missed') {

	aawrite('<AastraIPPhoneTextMenu destroyOnExit="yes" LockIn="no" style="none" cancelAction = "http://'.GS_PROV_HOST.':'.GS_PROV_PORT.'/aastra/dial-log.php">');
	aawrite('<Title>'.$typeToTitle[$type].'</Title>');
	
	$query =
'SELECT
	MAX(`timestamp`) `ts`, `number`, `remote_name`, `remote_user_id`,
	COUNT(*) `num_calls`
FROM `dial_log`
WHERE
	`user_id`='. $user_id .' AND
	`type`=\''. $type .'\'
GROUP BY `number`
ORDER BY `ts` DESC
LIMIT '.$num_results;

	//echo $query;

	$rs = $db->execute( $query );
	while ($r = $rs->fetchRow()) {
		
		$entry_name = $r['number'];
		if ($r['remote_name'] != '') {
			$entry_name .= ' '. $r['remote_name'];
		}
		if ($type=='missed') {
			$when = date('H:i', (int)$r['ts']);
			$entry_name = $when .'  '. $entry_name;
		}
		if ($r['num_calls'] > 1) {
			$entry_name .= ' ('. $r['num_calls'] .')';
		}
		aawrite('<MenuItem>');
		aawrite('<Prompt>'.$entry_name.'</Prompt>');
		aawrite('<Dial>'.$r['number'].'</Dial>');
		aawrite('<URI>http://'.GS_PROV_HOST.':'.GS_PROV_PORT.'/aastra/dial-log.php?t='.$type.'d&amp;e='.$r['ts'].'</URI>');
		aawrite('</MenuItem>');
	
	}
	
	aawrite('<SoftKey index="1">');
	aawrite('<Label>OK</Label>');
	aawrite('<URI>SoftKey:Select</URI>');
	aawrite('</SoftKey>');
	aawrite('<SoftKey index="2">');
	aawrite('<Label>'.__('Anrufen').'</Label>');
	aawrite('<URI>SoftKey:Dial2</URI>');
	aawrite('</SoftKey>');
	aawrite('<SoftKey index="4">');
	aawrite('<Label>'.__('Abbrechen').'</Label>');
	aawrite('<URI>SoftKey:Exit</URI>');
	aawrite('</SoftKey>');

	aawrite('</AastraIPPhoneTextMenu>');
	
	
} else if ($type=='outd' || $type=='ind' || $type=='missedd') {

	$type=substr($type,0,strlen($type)-1);
	aawrite('<AastraIPPhoneFormattedTextScreen destroyOnExit="yes" cancelAction = "http://'.GS_PROV_HOST.':'.GS_PROV_PORT.'/aastra/dial-log.php?t='.$type.'">');
	
	$query =
'SELECT
	`d`.`timestamp` `ts`, `d`.`number` `number`, `d`.`remote_name` `remote_name`, `d`.`remote_user_id` `remote_user_id`, `u`.`firstname` `fn`, `u`.`lastname` `ln`,
	COUNT(*) `num_calls` 
FROM `dial_log` `d` LEFT JOIN `users` `u`
ON `d`.`remote_user_id` = `u`.`id`
WHERE
	`d`.`user_id`='. $user_id .' AND
	`d`.`type`=\''. $type .'\' AND
	`d`.`timestamp`=\''. $timestamp .'\'
GROUP BY `number`
LIMIT 1';

	//echo $query;

	$rs = $db->execute( $query );
	if ($rs->numRows() !== 0) {

		$r = $rs->fetchRow();
		
		$name = '';
		if ($r['remote_name'] != '') {
			if ($r['ln'] != '') $name = $r['ln'];
			if ($r['ln'] != '') $name.= ', '.$r['fn'];
			if ($name == '') $name = $r['remote_name'];
		} 

		$when = date('d.m.Y H:i:s', (int)$r['ts']);

		if ($r['num_calls'] > 1) {
			$num_calls = ' ('. $r['num_calls'] .')';
		}		

		aawrite('<Line Align="left">'.$name.'</Line>');
		aawrite('<Line Align="right" Size="double">'.$r['number'].'</Line>');
		aawrite('<Line Align="left">'.$when.'</Line>');
	}
	
	aawrite('<SoftKey index="2">');
	aawrite('<Label>'.__('Anrufen').'</Label>');
	aawrite('<URI>Dial:'.$r['number'].'</URI>');
	aawrite('</SoftKey>');
	aawrite('<SoftKey index="4">');
	aawrite('<Label>'.__('Abbrechen').'</Label>');
	aawrite('<URI>SoftKey:Exit</URI>');
	aawrite('</SoftKey>');

	aawrite('</AastraIPPhoneFormattedTextScreen>');
	
	
}

aastra_transmit();

# delete outdated entries
#
$db->execute( 'DELETE FROM `dial_log` WHERE `user_id`='. $user_id .' AND `timestamp`<'. (time()-(int)GS_PROV_DIAL_LOG_LIFE) );
