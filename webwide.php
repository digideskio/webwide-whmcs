<?php
// ****************************************************************************
// *                                                                          *
// * WebWide WHMCS Registrar Module 1.0.0 alpha                               *
// * Homepage: http://api.webwide.net                                         *
// *                                                                          *
// * Copyright 2015 WebWide Internet Communication GmbH                       *
// *                                                                          *
// * Licensed under the Apache License, Version 2.0 (the "License");          *
// * you may not use this file except in compliance with the License.         *
// * You may obtain a copy of the License at                                  *
// *                                                                          *
// *    http://www.apache.org/licenses/LICENSE-2.0                            *
// *                                                                          *
// * Unless required by applicable law or agreed to in writing, software      *
// * distributed under the License is distributed on an "AS IS" BASIS,        *
// * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied. *
// * See the License for the specific language governing permissions and      *
// * limitations under the License.                                           *
// *                                                                          *
// ****************************************************************************
// *                                                                          *
// * To use this module you need a WebWide Reseller Account.                  *
// * For more information visit http://api.webwide.net                        *
// *                                                                          *
// *                                                                          *
// * Installation:                                                            *
// *                                                                          *
// * - Create a folder named "webwide" inside the "modules/registrar" folder  *
// *   of your WHMCS installation                                             *
// * - Place "webwide.php", "webwide_robot.php" and "logo.gif" into it        *
// * - In WHMCS navigate to Setup > Products/Services > Domain Registars      *
// * - Activate the WebWide Module and enter your API Credentials             *
// * - Create cronjob for {yourwhmcs}/crons/domainsync.php (every 4 hours)    *
// *                                                                          *
// ****************************************************************************
// *                                                                          *
// * Features NOT yet implemented:                                            *
// *                                                                          *
// * - Email Forwarding                                                       *
// * - URL Forwarding                                                         *
// * - Nameserver Registration                                                *
// *                                                                          *
// * Notice:                                                                  *
// *                                                                          *
// * - Registration Periods are ignored, because Domains get renewed until    *
// *   they get explicitely cancelled through the admin interface             *
// *                                                                          *
// ****************************************************************************

include('webwide_robot.php');

function webwide_getConfigArray()
{
	$configarray = array(
		'ApiUrl' => array('FriendlyName'=>'WebWide API Url', 'Type'=>'text', 'Size'=>50, 'Default'=>'https://api.webwide.net/rest/v2/'),
		'ApiUser' => array('FriendlyName'=>'Username', 'Type'=>'text', 'Size'=>25),
		'ApiPass' => array('FriendlyName'=>'Password', 'Type'=>'password', 'Size' => 25)
	);
	return $configarray;
}

function webwide_AdminCustomButtonArray()
{
	return array(
		'Request Hold' => 'RequestHold',
		'Revoke Delete / Hold' => 'RequestRevoke'
	);
}

function webwide_RegisterDomain($params, $transfer=false)
{
	$types = array('ownercontact'=>'','admincontact'=>'admin','techcontact'=>'tech','billingcontact'=>'billing');
	$robot = new WWRobot($params);

	// Structurize and create contact handles
	foreach($types as $wwType=>$whmcsType)
	{
		// Use admincontact for tech and billing, if not provided by WHMCS
		if(!isset($params[$whmcsType.'lastname']))
		{
			if($wwType == 'techcontact' || $wwType == 'billingcontact') $params[$wwType] = $params['admincontact'];
			continue;
		}

		// Request a handle from given contact data
		$robot->call('POST', 'handles', array(
			'category' 		=> 'WHMCS',
			'type' 			=> trim(webwide_ParseVal($params, 'company', $whmcsType)) ? 'ORG' : 'PERSON',
			'organization' 	=> webwide_ParseVal($params, 'company', $whmcsType),
			'firstname'		=> webwide_ParseVal($params, 'firstname', $whmcsType),
			'lastname' 		=> webwide_ParseVal($params, 'lastname', $whmcsType),
			'address' 		=> webwide_ParseVal($params, 'street', $whmcsType),
			'pcode' 		=> webwide_ParseVal($params, 'postcode', $whmcsType),
			'city' 			=> webwide_ParseVal($params, 'city', $whmcsType),
			'country' 		=> webwide_ParseVal($params, 'country', $whmcsType),
			'phone' 		=> webwide_ParseVal($params, 'phone', $whmcsType),
			'fax'	 		=> webwide_ParseVal($params, 'fax', $whmcsType),
			'email' 		=> webwide_ParseVal($params, 'email', $whmcsType),
		));

		if(!$robot->success()) return array('error' => $robot->getErrorMessage());
		$params[$wwType] = $robot->get('handle');
	}

	// Request domain registration
	$robot->call('POST', 'domains', array(
		'action' 			=> $transfer?'CHPROV':'CREATE',
		'domain' 			=> $params['sld'].'.'.$params['tld'],
		'ownercontact' 		=> $params['ownercontact'],
		'admincontact' 		=> $params['admincontact'],
		'techcontact' 		=> $params['techcontact'],
		'billingcontact' 	=> $params['billingcontact'],
		'nameserver1' 		=> $params['ns1'],
		'nameserver2' 		=> $params['ns2'],
		'nameserver3' 		=> $params['ns3'],
		'nameserver4' 		=> $params['ns4'],
		'nameserver5' 		=> $params['ns5'],
		'authcode'			=> $transfer ? $params['transfersecret'] : ''
	));

	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('success' => true);
}

function webwide_TransferDomain($params)
{
	return webwide_RegisterDomain($params, true);
}

function webwide_RenewDomain($params)
{
	return array('success' => true); // Domains do not expire unless canceled explicitly
}

function webwide_RequestDelete($params)
{
	$robot = new WWRobot($params);
	$robot->call('DELETE', 'domains/'.$params['sld'].'.'.$params['tld'], array('action' => 'DEL'));
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('success' => true);
}

function webwide_RequestHold($params)
{
	$robot = new WWRobot($params);
	$robot->call('DELETE', 'domains/'.$params['sld'].'.'.$params['tld'], array('action' => 'HOLD'));
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('success' => true);
}

function webwide_RequestRevoke($params)
{
	$robot = new WWRobot($params);
	$robot->call('DELETE', 'domains/'.$params['sld'].'.'.$params['tld'], array('action' => 'REVOKE'));
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('success' => true);
}

function webwide_GetRegistrarLock($params)
{
	// Webwide handles this as a CHPROV request
	$robot = new WWRobot($params);
	$robot->call('GET', 'domains/'.$params['sld'].'.'.$params['tld']);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	if($robot->get('cancelaction','') == 'CHPROV') return 'unlocked';
	return 'locked';
}

function webwide_SaveRegistrarLock($params)
{
	// Webwide handles this as a CHPROV request
	$robot = new WWRobot($params);
	$action = $params['lockenabled'] == 'locked' ? 'REVOKE' : 'CHPROV';
	$robot->call('DELETE', 'domains/'.$params['sld'].'.'.$params['tld'], array('action' => $action, 'duedate' => 'EXPIRE'));
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return 'unlocked';
}

function webwide_GetEPPCode($params)
{
	$robot = new WWRobot($params);
	$robot->call('GET', 'domains/'.$params['sld'].'.'.$params['tld']);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('eppcode' => $robot->get('authcode'));
}

function webwide_GetNameservers($params)
{
	$robot = new WWRobot($params);
	$robot->call('GET', 'domains/'.$params['sld'].'.'.$params['tld']);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array(
		'ns1' => $robot->get('nameserver1'),
		'ns2' => $robot->get('nameserver2'),
		'ns3' => $robot->get('nameserver3'),
		'ns4' => $robot->get('nameserver4'),
		'ns5' => $robot->get('nameserver5')
	);
}

function webwide_SaveNameservers($params)
{
	$robot = new WWRobot($params);
	$robot->call('PUT', 'domains/'.$params['sld'].'.'.$params['tld'], array(
		'action' 		=> 'UPDATE',
		'nameserver1' 	=> $params['ns1'],
		'nameserver2' 	=> $params['ns2'],
		'nameserver3' 	=> $params['ns3'],
		'nameserver4' 	=> $params['ns4'],
		'nameserver5' 	=> $params['ns5']
	));
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('success' => true);
}

/**
 * Handles (domain contacts)
 **/

function webwide_GetContactDetails($params)
{
	$values = array();
	$robot = new WWRobot($params);
	$types = array('Registrant'=>'ownercontact','Admin'=>'admincontact','Tech'=>'techcontact','Billing'=>'billingcontact');

	// Get contact handles through domain info request
	$robot->call('GET', 'domains/'.$params['sld'].'.'.$params['tld']);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	foreach($types as $whmcsType=>$wwType) $values[$whmcsType]['Handle'] = $robot->get($wwType);

	// Request contact details for each handle
	foreach($types as $whmcsType=>$wwType)
	{
		$robot->call('GET', 'handles/'.$values[$whmcsType]['Handle']);
		if(!$robot->success()) return array('error' => $robot->getErrorMessage());
		$values[$whmcsType]['First Name'] 	= $robot->get('firstname');
		$values[$whmcsType]['Last Name'] 	= $robot->get('lastname');
		$values[$whmcsType]['Street'] 		= $robot->get('address');
		$values[$whmcsType]['City'] 		= $robot->get('city');
		$values[$whmcsType]['Post Code'] 	= $robot->get('pcode');
		$values[$whmcsType]['Country Code']	= $robot->get('country');
		$values[$whmcsType]['Phone Number']	= $robot->get('phone');
		$values[$whmcsType]['Fax Number']	= $robot->get('fax');
		$values[$whmcsType]['Email'] 		= $robot->get('email');
		$values[$whmcsType]['Company'] 		= $robot->get('organization');
		unset($values[$whmcsType]['Handle']);
	}
	return $values;
}

function webwide_SaveContactDetails($params)
{
	$handles = array();
	$robot = new WWRobot($params);
	$types = array('Registrant'=>'ownercontact','Admin'=>'admincontact','Tech'=>'techcontact','Billing'=>'billingcontact');

	// Structurize and create contact handles
	foreach($types as $whmcsType=>$wwType)
	{
		if(!isset($params['contactdetails'][$whmcsType])) continue;

		// Request a handle from given contact data
		$details = $params['contactdetails'][$whmcsType];
		$robot->call('POST', 'handles', array(
			'category' 		=> 'WHMCS',
			'type' 			=> trim(webwide_ParseVal($details, 'company'))?'ORG':'PERSON',
			'organization' 	=> webwide_ParseVal($details, 'company'),
			'firstname'		=> webwide_ParseVal($details, 'firstname'),
			'lastname' 		=> webwide_ParseVal($details, 'lastname'),
			'address' 		=> webwide_ParseVal($details, 'street'),
			'pcode' 		=> webwide_ParseVal($details, 'postcode'),
			'city' 			=> webwide_ParseVal($details, 'city'),
			'country' 		=> webwide_ParseVal($details, 'country'),
			'phone' 		=> webwide_ParseVal($details, 'phone'),
			'fax' 			=> webwide_ParseVal($details, 'fax'),
			'email' 		=> webwide_ParseVal($details, 'email')
		));
		if(!$robot->success()) return array('error' => $robot->getErrorMessage());
		$handles[$wwType] = $robot->get('handle');
	}

	// Request domain update
	$robot->call('PUT', 'domains/'.$params['sld'].'.'.$params['tld'], $handles);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return array('success' => true);
}

/**
 * DNS Zones
 **/

function webwide_GetDNS($params)
{
	$robot = new WWRobot($params);
	$robot->call('GET', 'zones/'.$params['sld'].'.'.$params['tld']);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());

	$hostrecords = array();
	foreach($robot->get('records') as $k=>$record)
	{
		$domain = $params['sld'].'.'.$params['tld'];
		$record = explode(' ', $record);
		$source = @$record[0];
		$type = @$record[1];
		$target = @$record[2];
		$prio = @$record[4];
		if(!in_array($type, array('A','AAAA','MX','MXE','CNAME','SPF'))) continue;
		if(!$source && !$target) continue;
		if(substr($source,-1,1) != '.')
		{
			// Remove domain from source
			$source = str_replace($domain,'',$source);
			if(substr($source,-1,1) == '.') $source = substr($source,0,-1);
		}
		if(in_array($type,array('MX','CNAME')))
		{
			// Remove domain from target or add dot
			if(strpos($target,$domain) !== false)
			{
				$target = substr(str_replace($domain,'',$target),0,-1);
				if(substr($target,-1,1) == '.') $target = substr($target,0,-1);
			}
			else
			{
				$target .= '.';
			}
		}
		$hostrecords[$k] = array('hostname' => $source, 'type' => $type, 'address' => $target, 'priority' => $prio);
	}
	return $hostrecords;
}

function webwide_SaveDNS($params)
{
	$soa = array();
	$records = array();
	$result = array('success' => true);
	foreach($params['dnsrecords'] AS $record)
	{
		// Append domain to non-qualified hostnames or (MX / CNAME) addresses
		$domain = $params['sld'].'.'.$params['tld'];
		$source = trim($record['hostname']);
		$target = trim($record['address']);
		$type = trim($record['type']);
		$prio = $type=='MX' ? $record['priority'] : 0;
		if(!$source && !$target) continue;
		if(in_array($type, array('URL','FRAME'))) $result['error'] = 'URL Redirection is not supported for this domain.';
		if(!in_array($type, array('A','AAAA','MX','MXE','CNAME','SPF'))) continue;
		if(substr($source,-1,1) != '.')
		{
			if(strlen($source)>0) $source .= '.';
			$source .= $domain;
		}
		if(in_array($type,array('MX','CNAME')))
		{
			if(substr($target,-1,1) != '.') $target .= '.'.$domain; else $target = substr($target,0,-1);
		}
		// Concatenate to e.g. "test.com MX mail.test.com 86400 10" format
		$records[] = $source.' '.$type.' '.$target.' 86400 '.$prio;
	}

	$robot = new WWRobot($params);
	$robot->call('PUT', 'zones/'.$params['sld'].'.'.$params['tld'], array(
		'records' => $records,
		'updateonly' => array('A', 'AAAA', 'MX', 'MXE', 'CNAME', 'SPF')
	));
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());
	return $result;
}

/**
 * SYNC CRONS
 **/

function webwide_Sync($params)
{
	$robot = new WWRobot($params);
	$robot->call('GET', 'domains/'.$params['sld'].'.'.$params['tld']);
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());

	$values = array('active' => true);
	$state = $robot->get('state');
	$expirydate = $robot->get('expirydate');
	$registrationdate = $robot->get('regdate');

	if($registrationdate) $values['registrationdate'] = $registrationdate;
	if($expirydate) $values['expirydate'] = $expirydate;
	if($state != 'active') $values['active'] = false;
	if($state == 'expired') $values['expired'] = true;
	if($state == 'cancelled') $values['cancelled'] = true;

	return $values;
}

function webwide_TransferSync($params)
{
	$robot = new WWRobot($params);
	$robot->call('GET', 'domains/'.$params['sld'].'.'.$params['tld']);

	if($robot->getErrorMessage)
	if(!$robot->success() && $robot->getErrorCode() == 404) return array('failed'=>true,'reason'=>'Transfer failed');
	if(!$robot->success()) return array('error' => $robot->getErrorMessage());

	$values = array('completed' => false);
	$transferstate = $robot->get('transferstate');
	$expirydate = $robot->get('expirydate');
	$regdate = $robot->get('regdate');

	if($transferstate == 'done' || $transferstate == 'none')
	{
		$values['completed'] = true;
		if($expirydate) $values['expirydate'] = $expirydate;
		if($regdate) $values['registrationdate'] = $regdate;
	}

	if($transferstate == 'failed')
	{
		$values['failed'] = true;
		$values['reason'] = 'Transfer failed';
		unset($values['completed']);
	}

	return $values;
}

/**
 * HELPERS
 **/

function webwide_ParseVal($params, $key, $prefix='')
{
	// WHMCS is not very consistent with key names, so we do some guessing here
	$keys = array(
		'firstname'	=> array('firstname', 'First Name'),
		'lastname' 	=> array('lastname', 'Last Name'),
		'company' 	=> array('company', 'companyname', 'Company', 'Company Name', 'Organisation Name', 'organisation', 'Organization Name', 'organization'),
		'address1' 	=> array('address1', 'street', 'address', 'Street', 'Address 1'),
		'address2' 	=> array('address2', 'Address 2'),
		'postcode' 	=> array('postcode', 'Postcode', 'post code', 'Post Code', 'ZIP Code', 'ZIP'),
		'city' 		=> array('city', 'City'),
		'country' 	=> array('countrycode', 'Country Code', 'country code', 'Country', 'country'),
		'phone' 	=> array('fullphonenumber', 'Phone Number', 'Phone', 'phone', 'phone number'),
		'fax'		=> array('fullfaxnumber', 'Fax Number', 'Fax', 'fax', 'fax number'),
		'email' 	=> array('email', 'Email')
	);

	if($key=='street')
	{
		return trim(webwide_ParseVal($params, 'address1', $prefix).' '.webwide_ParseVal($params, 'address2', $prefix));
	}
	elseif(isset($keys[$key]))
	{
		foreach($keys[$key] as $guessedKey)
		{
			if(isset($params[$prefix.$guessedKey])) return $params[$prefix.$guessedKey];
		}
	}

	return isset($params[$prefix.$key]) ? $params[$prefix.$key] : '';
}

/**
 * METHODS NOT YET SUPPORTED BY THIS MODULE
 **/

/*
function webwide_GetEmailForwarding($params) {}

function webwide_SaveEmailForwarding($params) {}

function webwide_RegisterNameserver($params) {}

function webwide_ModifyNameserver($params) {}

function webwide_DeleteNameserver($params) {}
*/

?>