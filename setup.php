<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007 The Cacti Group                                      |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/


function plugin_easyvista_install () {
	api_plugin_register_hook('easyvista', 'config_settings', 'easyvista_config_settings', 'setup.php');
	api_plugin_register_hook('easyvista', 'api_device_new', 'easyvista_api_device_new', 'setup.php');
	api_plugin_register_hook('easyvista', 'utilities_action', 'easyvista_utilities_action', 'setup.php'); // add option to check if device exist or need to be added
	api_plugin_register_hook('easyvista', 'utilities_list', 'easyvista_utilities_list', 'setup.php');

// Device action
    api_plugin_register_hook('easyvista', 'device_action_array', 'easyvista_device_action_array', 'setup.php');
    api_plugin_register_hook('easyvista', 'device_action_execute', 'easyvista_device_action_execute', 'setup.php');
    api_plugin_register_hook('easyvista', 'device_action_prepare', 'easyvista_device_action_prepare', 'setup.php');

}

function plugin_easyvista_uninstall () {
	// Do any extra Uninstall stuff here

}

function plugin_easyvista_check_config () {
	// Here we will check to ensure everything is configured
	easyvista_check_upgrade();

	return true;
}

function plugin_easyvista_upgrade () {
	// Here we will upgrade to the newest version
	easyvista_check_upgrade();
	return false;
}

function easyvista_check_upgrade() {
	global $config;

	$version = plugin_easyvista_version ();
	$current = $version['version'];
	$old     = db_fetch_cell('SELECT version
		FROM plugin_config
		WHERE directory="easyvista"');

	if ($current != $old) {

		// Set the new version
		db_execute("UPDATE plugin_config SET version='$current' WHERE directory='easyvista'");
		db_execute("UPDATE plugin_config SET 
			version='" . $version['version'] . "', 
			name='"    . $version['longname'] . "', 
			author='"  . $version['author'] . "', 
			webpage='" . $version['homepage'] . "' 
			WHERE directory='" . $version['name'] . "' ");
	}
}

function plugin_easyvista_version () {
	global $config;
	$info = parse_ini_file($config['base_path'] . '/plugins/easyvista/INFO', true);
	return $info['info'];
}


function easyvista_utilities_list () {
	global $colors, $config;
	html_header(array("easyvista Plugin"), 4);
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=easyvista_check'>Check if devices are on easyvista.</a>
		</td>
		<td class="textArea">
			Check all devices to see if they are on easyvista and 'En Service'
		</td>
	<?php
	form_end_row();
	form_alternate_row();
	?>
		<td class="textArea">
			<a href='utilities.php?action=easyvista_report'>Display device that are wrong on easyvista.</a>
		</td>
		<td class="textArea">
			Display device that are not on easyvista, or not En service
		</td>
	<?php
	form_end_row();
}

function easyvista_utilities_action ($action) {
	global $item_rows;
	
	if ( $action == 'easyvista_check' || $action == 'easyvista_report' ){
		if ($action == 'easyvista_check') {
	// get device list,  where serial number is empty, or type
			$dbquery = db_fetch_assoc("SELECT * FROM host 
			WHERE status = '3' AND disabled != 'on'
			AND snmp_sysDescr LIKE '%cisco%'
			ORDER BY id");
		// Upgrade the easyvista value
			if( $dbquery > 0 ) {
				foreach ($dbquery as $host_id) {
					if( !empty($host_id['external_id']) ) {
						continue;
					}
					easyvista_process_device( $host_id );
				}
			}
			top_header();
			utilities();
			bottom_footer();
		} else if ($action == 'easyvista_report') {
	// get device list, where eternal_id is empty or EZV no En Service
			$dbquery = db_fetch_assoc("SELECT * FROM host 
			WHERE status = '3' AND disabled != 'on'
			AND snmp_sysDescr LIKE '%cisco%'
			AND (external_id = '' OR notes NOT LIKE '%EZV: En Service%')
			ORDER BY id");
			if( $dbquery > 0 ) {
				// export CSV device list
				header("Content-Type: csv/plain");
				header("Content-Disposition: attachment; filename=easyvista_report.csv");
				print( 'description,serial_no,notes'."\r\n" );
				foreach ($dbquery as $host) {
					print( $host['description'].','.$host['serial_no'].','.$host['notes'] );
					print("\r\n");
				}				
			}
		}
	} 
	return $action;
}

function easyvista_config_settings () {
	global $tabs, $settings;
	$tabs["misc"] = "Misc";

	if (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) != 'settings.php')
		return;

	$tabs['misc'] = 'Misc';
	$temp = array(
		"easyvista_general_header" => array(
			"friendly_name" => "easyvista",
			"method" => "spacer",
			),
		"easyvista_url" => array(
			"friendly_name" => "URL of the easyvista server",
			"description" => "URL of the easyvista server.",
			"method" => "textbox",
			"max_length" => 80,
			"default" => ""
			), 
		"easyvista_account" => array(
			"friendly_name" => "Account ID",
			"description" => "Account ID for the easyvista server.",
			"method" => "textbox",
			"max_length" => 80,
			"default" => ""
			), 
		"easyvista_login" => array(
			"friendly_name" => "Login ID",
			"description" => "Login ID for the easyvista server.",
			"method" => "textbox",
			"max_length" => 80,
			"default" => ""
			), 
		"easyvista_password" => array(
			"friendly_name" => "password",
			"description" => "Password for the easyvista server.",
			"method" => "textbox_password",
			"max_length" => 80,
			"default" => ""
			), 
		'easyvista_log_debug' => array(
			'friendly_name' => 'Debug Log',
			'description' => 'Enable logging of debug messages during easyvista exchange',
			'method' => 'checkbox',
			'default' => 'off'
			)
	);
	
	if (isset($settings['misc']))
		$settings['misc'] = array_merge($settings['misc'], $temp);
	else
		$settings['misc']=$temp;
}

function easyvista_check_dependencies() {
	global $plugins, $config;

	return true;
}

function easyvista_device_action_array($device_action_array) {
    $device_action_array['check_easyvista'] = __('Check if device is on easyvista, and change status');
        return $device_action_array;
}

function easyvista_device_action_execute($action) {
   global $config;

   if ($action != 'check_easyvista' ) {
           return $action;
   }

   $selected_items = sanitize_unserialize_selected_items(get_nfilter_request_var('selected_items'));

	if ($selected_items != false ) {
		if ($action == 'check_easyvista' ) {
			foreach( $selected_items as $host_id ) {
				if ($action == 'check_easyvista') {
					$dbquery = db_fetch_row("SELECT * FROM host WHERE id=".$host_id);
					easyvista_process_device($dbquery);
				}
			}
		}
    }
	return $action;
}

function easyvista_device_action_prepare($save) {
    global $host_list;

    $action = $save['drp_action'];

    if ($action != 'check_easyvista' ) {
		return $save;
    }

    if ($action == 'check_easyvista' ) {
		$action_description = 'Check if device is on easyvista, and fix it s status';
			print "<tr>
                    <td colspan='2' class='even'>
                            <p>" . __('Click \'Continue\' to %s on these Device(s)', $action_description) . "</p>
                            <p><div class='itemlist'><ul>" . $save['host_list'] . "</ul></div></p>
                    </td>
            </tr>";
    }
	return $save;
}

function easyvista_api_device_new( $host_id ) {
	global $asset_status;

// check valid call
	if( !array_key_exists('disabled', $host_id ) || !array_key_exists('id', $host_id) ) {
		easyvista_log('Not valid call: '. print_r($host_id, true) );
		return $host_id;
	}

    easyvista_log('Enter EZV: '.$host_id['description'].'('.$host_id['id'].')' );
	
	easyvista_process_device($host_id);
	
    easyvista_log('End EZV' );
	
	return $host_id;
}

// do check and change status
function easyvista_process_device( $host_id, $doforce=true ) {
	global $asset_status;

	$host_array = db_fetch_row("SELECT * FROM host WHERE hostname='".$host_id['hostname']."'");

	// if device is disabled, or snmp has nothing, don't save do it
	if ($host_array['disabled'] == 'on' || $host_array['snmp_version'] == 0 || empty($host_array['serial_no']) ) {
		easyvista_log('don t use EZV on: '.$host_array['description'] );
		return;
	}

	// if more than 1 serial number, (stack, iss) just create an array of it, and process each of it
	$arraysn = explode(' ', $host_array['serial_no'] );

	$externalid='';
	foreach($arraysn as $SN){
		$host_array['serial_no'] = $SN;
		
		$result = easyvista_check_exist( $host_array );
	
		// device does exist
		if( $result !== false ) {
			$jsondata = json_decode($result['body'], true, 512 );
			$externalid = $externalid . $jsondata['records'][0]['ASSET_TAG'].' ';
			
			$result = easyvista_check_status( $host_array, $jsondata, $doforce ); // check status and change to 'En Service'
		}
	}
	if( !empty($externalid) ) {
		$mysql = "update host set external_id='". $externalid."' WHERE id=" . $host_array['id'];
		db_execute($mysql);
	}
}

function easyvista_check_exist( $host ){
	global $asset_status;

	$ezvurl = read_config_option("easyvista_url");
	$ezvaccount = read_config_option("easyvista_account");
	$ezvlogin = read_config_option("easyvista_login");
	$ezvpassword = read_config_option("easyvista_password");
		
	// check if device allready exist
	// https://easyvista-vali.lausanne.ch/api/v1/50004/assets?fields=asset_id,serial_number,CATALOG_ID,Network_identifier,Asset_TAG,status_id\&search=serial_number:FOC2138Y6AB
	
	$url = $ezvurl .'/'. $ezvaccount. "/assets?fields=asset_id,serial_number,CATALOG_ID,Network_identifier,Asset_TAG,status_id&search=serial_number:".$host['serial_no'];
	
    $handle = curl_init();
	curl_setopt( $handle, CURLOPT_URL, $url );
	curl_setopt( $handle, CURLOPT_POST, false );
	curl_setopt( $handle, CURLOPT_HEADER, true );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $handle, CURLOPT_USERPWD, "$ezvlogin:$ezvpassword" );
	curl_setopt( $handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
	curl_setopt( $handle, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json; charset=UTF-8','cache-control:no-cache') );

	$response = curl_exec($handle);
	$error = curl_error($handle);
	$result = array( 'header' => '',
                     'body' => '',
                     'curl_error' => '',
                     'http_code' => '',
                     'last_url' => '');

    $header_size = curl_getinfo($handle,CURLINFO_HEADER_SIZE);
    $result['header'] = substr($response, 0, $header_size);
    $result['body'] = substr( $response, $header_size );
    $result['http_code'] = curl_getinfo($handle,CURLINFO_HTTP_CODE);
    $result['last_url'] = curl_getinfo($handle,CURLINFO_EFFECTIVE_URL);

	$ret = $result;   
    if ( $result['http_code'] > "299" ) {
		easyvista_log( "EZV URL: ". $url );
        $result['curl_error'] = $error;
		easyvista_log( "EZV error: ". print_r($result, true)  );
		$ret = false;
	} else {
		$jsondata = json_decode($result['body'], true, 512 );
		if( $jsondata['record_count'] == 0 ){
			easyvista_log( "Device not on EZV: ". print_r($result, true) );
			$ret = false;
		} else {
			easyvista_log( "Device on EZV: ". $host['description'].' recu:'.print_r($result, true) );
		}
	}
   
	curl_close($handle);

	return $ret;
}

function easyvista_check_status( $host_id, $jsondata, $doforce=true ){
	$asset_status = array(
		"1" => "En panne",
		"2" => "En réparation",
		"3" => "Prêté",
		"4" => "En stock",
		"5" => "Mis à jour",
		"6" => "Retourné",
		"7" => "Détruit",
		"8" => "En service",
		"9" => "Volé",
		"10" => "En cours de transfert",
		"11" => "A vérifier",
		"12" => "A Restituer",
		"13" => "Doublon",
		"14" => "Recyclé",
		"15" => "Spare Tcom",
		"16" => "Abo suspendu",
		"17" => "En panne GETRONICS",
		"18" => "Désactivé",
		"19" => "Retour au fournisseur",
		"20" => "Perdu",
		"21" => "Repris par l'utilisateur",
		"22" => "En commande",
		"25" => "Inventaire à contrôler",
		"27" => "En migration",
		"28" => "Résilié"
	);

	$ezvurl = read_config_option("easyvista_url");
	$ezvaccount = read_config_option("easyvista_account");
	$ezvlogin = read_config_option("easyvista_login");
	$ezvpassword = read_config_option("easyvista_password");

	$status_id = $jsondata['records'][0]['STATUS_ID'];
	easyvista_log( "Check and change status EZV ".$host_id["description"] .' json: '.print_r($jsondata, true) );

	$result = db_fetch_cell("SELECT notes from host WHERE id=". $host_id['id'] );
/*
18/01/2021 15:50:45 - EASYVISTA new status:Test EZV: En stock
18/01/2021 15:50:45 - EASYVISTA status:Test EZV: En Service
*/
	$newpos = strpos($result, 'EZV: ');
	if( $newpos === false ) {
		$notes = $result .'\n\r'.'EZV: '.$asset_status[$status_id];
	} else {
		$newstatus = '\n\rEZV: '.$asset_status[$status_id];
		$notes = substr_replace( $result, $newstatus , $newpos );
	}

	$mysql = "update host set notes='". $notes."' WHERE id=" . $host_id['id'];
	db_execute($mysql);

}

function easyvista_add_device( $host_id ){
	global $asset_status;

	$ezvurl = read_config_option("easyvista_url");
	$ezvaccount = read_config_option("easyvista_account");
	$ezvlogin = read_config_option("easyvista_login");
	$ezvpassword = read_config_option("easyvista_password");
	
	//$host_id["hostname"] do a nslook if necessary
	$ip = gethostbyname($host_id["hostname"]);
	if( $host_id['snmp_version'] == 3 ){
		$snmp_profile = 5;
	} else {
		$snmp_profile = 4;
	}
	
	// https://
	$url = $ezvurl . "";
	
	$handle = curl_init();
	curl_setopt( $handle, CURLOPT_URL, $url );
	curl_setopt( $handle, CURLOPT_POST, false );
	curl_setopt( $handle, CURLOPT_HEADER, true );
	curl_setopt( $handle, CURLOPT_SSL_VERIFYPEER, false );
	curl_setopt( $handle, CURLOPT_RETURNTRANSFER, true );
	curl_setopt( $handle, CURLOPT_USERPWD, "$ezvlogin:$ezvpassword" );
	curl_setopt( $handle, CURLOPT_HTTPAUTH, CURLAUTH_BASIC );
	curl_setopt( $handle, CURLOPT_HTTPHEADER, array( 'Content-Type:application/json; charset=UTF-8','cache-control:no-cache') );

	$response = curl_exec($handle);
	$error = curl_error($handle);
	$result = array( 'header' => '',
					'body' => '',
					'curl_error' => '',
					'http_code' => '',
					'last_url' => '');

	$header_size = curl_getinfo($handle,CURLINFO_HEADER_SIZE);
	$result['header'] = substr($response, 0, $header_size);
	$result['body'] = substr( $response, $header_size );
	$result['http_code'] = curl_getinfo($handle,CURLINFO_HTTP_CODE);
	$result['last_url'] = curl_getinfo($handle,CURLINFO_EFFECTIVE_URL);

	if ( $result['http_code'] > "299" )
	{
		$result['curl_error'] = $error;
		easyvista_log( "ezv URL: ". $url );
		easyvista_log( "ezv error: ". print_r($result, true)  );
	}

	curl_close($handle);
}

function easyvista_log( $text ){
    	$dolog = read_config_option('easyvista_log_debug');
	if( $dolog ) cacti_log( $text, false, "EASYVISTA" );
}


?>
