<?php
/* WHMCS SMS Addon with GNU/GPL Licence
 *
 * twozero_sms - WHMCS SMS Addon powered by twozero.io
 *
 * Licence: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.txt)
 *
 * */
if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

require_once("smsclass.php");
$class = new TwoZeroSms();
$hooks = $class->getHooks();

foreach($hooks as $hook){
    add_hook($hook['hook'], 1, $hook['function'], "");
}