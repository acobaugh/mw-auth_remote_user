<?php 

/* output buffering lets us send one last http Header to redirect
 * after mediawiki does its thing
 */
ob_start();

include_once("index.php");

/* at this point, the user should have been autocreated if they didn't exist,
 * and logged into the wiki according to $_SESSION
 *
 * redirect from whence they came, but over SSL
 *
 * NOTE: the session doesn't exist unless you go over ssl, and it would be bad to make it do so
 * otherwise, as the session id would be passed in plain text
 */
$redirect = 'https://' . $_SERVER["SERVER_NAME"] . dirname($_SERVER["PHP_SELF"]) . '/' . $_GET['returnto'];
header("Location: $redirect");

ob_end_flush();
?>
