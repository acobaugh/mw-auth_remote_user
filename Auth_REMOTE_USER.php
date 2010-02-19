<?php

if (!defined( 'MEDIAWIKI' )) {
	die('This file is a MediaWiki extension, it is not a valid entry point');
}

$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => "Auth_REMOTE_USER",
	'description' => "Authenticates user based on REMOTE_USER. Creates users if they don't exist, possibly using LDAP.",
	'version' => "1.0",
	'author' => "Andy Cobaugh (phalenor@bx.psu.edu)",
	'url' => "http://github.com/phalenor/mw-auth_remote_user"
);

// Auth_REMOTE_USER extends the AuthPlugin class
require_once('AuthPlugin.php');
 
function efAuth_REMOTE_USER() {
	global $realm_to_ldap;
	global $realm_to_email_domain;
	global $wgUser;
	global $wgRequest;
	global $_SERVER;
 
	// Do nothing if session is valid
	$user = User::newFromSession();
	if (!$user->isAnon()) {
		return;
	}
 
	// Copied from includes/SpecialUserlogin.php
	if(!isset($wgCommandLineMode) && !isset($_COOKIE[session_name()])) {
		wfSetupSession();
	}

	/* Get the short principal name and realm */
	list($spn, $foo) = split('@', $_SERVER['REMOTE_USER']);
	$realm = $_SERVER['REMOTE_REALM'];

	/* Get the name out of ldap based on spn and realm
	 * or set the name to $spn if we can't find them, or 
	 * their name isn't in ldap 
	 */
	if (array_key_exists($realm, $realm_to_ldap)) {
		$ldap_server = $realm_to_ldap[$realm]['hostname'];
		$ldap_base = $realm_to_ldap[$realm]['base'];
		$ldap_name_attr = $realm_to_ldap[$realm]['name_attr'];

		$ldapconn = ldap_connect($ldap_server) or die ("Could not connect to ldap server.");
		$ldapbind = ldap_bind($ldapconn) or die ("Could not bind to ldap server $ldap_server.");
	 
		$sr = ldap_search($ldapconn, $ldap_base, "uid=$spn", array("$ldap_name_attr"), 0, 1);
		$entries = ldap_get_entries($ldapconn, $sr);

		$real_name = $entries[0]["$ldap_name_attr"][0];

		if ($real_name == NULL)	{
			$real_name = $_SERVER['REMOTE_USER'];
		}

		ldap_close($ldapconn);
	} else {
		$real_name = $_SERVER['REMOTE_USER'];
	}

	// deduce their email address
	if (array_key_exists($realm, $realm_to_email_domain)) {
		$email = $spn . '@' . $realm_to_email_domain[$realm];
	} else {
		$email = '';
	}

  // Submit a fake login form to authenticate the user.
	$params = new FauxRequest(
		array(
			'wpName' => $_SERVER['REMOTE_USER'],
			'wpPassword' => '',
			'wpDomain' => '',
			'wpRemember' => '',
			'wpRealName' => $real_name,
			'wpEmail' => $email
	  ));
 
  // authenticateUserData() will automatically create new users.
  $loginForm = new LoginForm($params);
  $result = $loginForm->authenticateUserData();
  if ($result != LoginForm::SUCCESS) {
    error_log('Unexpected REMOTE_USER authentication failure.');
    return;
  }
 
  $wgUser->setCookies();
  return;
}
 
class Auth_REMOTE_USER extends AuthPlugin {
 
	function Auth_REMOTE_USER() {
		global $_SERVER;
		// Register our hook function.  This hook will be executed on every page
		// load.  Its purpose is to automatically log the user in, if necessary.
		if (array_key_exists('REMOTE_USER', $_SERVER)) {
			global $wgExtensionFunctions;
			if (!isset($wgExtensionFunctions)) {
				$wgExtensionFunctions = array();
			} else if (!is_array($wgExtensionFunctions)) {
				$wgExtensionFunctions = array( $wgExtensionFunctions );
			}
			array_push($wgExtensionFunctions, 'efAuth_REMOTE_USER');
		}
		return;
	}
 
	function allowPasswordChange() {
		return false;
	}
 
	function setPassword($user, $password) {
		return false;
	}
 
	function updateExternalDB($user) {
		return true;
	}
	 
	function canCreateAccounts() {
		return false;
	}
	 
	function addUser($user, $password) {
		return false;
	}
	 
	function userExists($username) {
		return true;
	}
 
	/*
	 * Check whether the given name matches REMOTE_USER.
	 * The name will be normalized to MediaWiki's requirements, so
	 * lower it and the REMOTE_USER before checking.
	 */
	function authenticate($username, $password) {
		global $_SERVER;
		 //list($spn, $realm) = split('@', $_SERVER['REMOTE_USER']);
		$spn = $_SERVER['REMOTE_USER'];
	
		return isset($_SERVER['REMOTE_USER']) &&
			(strtolower($username) == strtolower($spn));
	}
 
	function validDomain($domain) {
		return true;
	}
 
	function updateUser(&$user) {
		// We only set this stuff when accounts are created.
		return true;
	}
	 
	function autoCreate() {
		return true;
	}
	 
	function strict() {
		return true;
	}
 
	function modifyUITemplate(&$template) {
		//disable the mail new password box
		$template->set('useemail', false);
		//disable 'remember me' box
		$template->set('remember', false);
		$template->set('create', false);
		$template->set('domain', false);
		$template->set('usedomain', false);
	}
 
	function getCanonicalName($username) {
		/* lowercase the username */
		$username[0] = strtoupper($username[0]);
		return $username;
	}
}
