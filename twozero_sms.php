<?php
/* WHMCS SMS Addon with GNU/GPL Licence
 *
 * twozero_sms - WHMCS SMS Addon powered by twozero.io
 *
 * Replaces the MIM SMS gateway (mim_sms) with the twozero.io SMS API.
 *
 * Licence: GPLv3 (http://www.gnu.org/licenses/gpl-3.0.txt)
 *
 * */
if (!defined("WHMCS"))
	die("This file cannot be accessed directly");

	//Creating hooks
require_once(__DIR__."/smsclass.php");

function twozero_sms_config() {
    $configarray = array(
        "name" => "Twozero SMS",
        "description" => "twozero_sms - WHMCS SMS Addon powered by twozero.io (https://sms.twozero.io).",
        "version" => "1.0",
        "author" => "<a href='https://sms.twozero.io' target='_blank'>twozero.io</a>",
		"language" => "english",
    );
    return $configarray;
}

function twozero_sms_activate() {

    $query = "CREATE TABLE IF NOT EXISTS `mod_twozero_sms_messages` (`id` int(11) NOT NULL AUTO_INCREMENT,`sender` varchar(40) NOT NULL,`to` varchar(15) DEFAULT NULL,`text` text,`msgid` varchar(50) DEFAULT NULL,`status` varchar(10) DEFAULT NULL,`errors` text,`logs` text,`user` int(11) DEFAULT NULL,`datetime` datetime NOT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1;";
	mysql_query($query);

    $query = "CREATE TABLE IF NOT EXISTS `mod_twozero_sms_settings` (`id` int(11) NOT NULL AUTO_INCREMENT,`api` varchar(40) CHARACTER SET utf8 NOT NULL,`apiparams` varchar(500) CHARACTER SET utf8 NOT NULL,`wantsmsfield` int(11) DEFAULT NULL,`gsmnumberfield` int(11) DEFAULT NULL,`dateformat` varchar(12) CHARACTER SET utf8 DEFAULT NULL,`version` varchar(6) CHARACTER SET utf8 DEFAULT NULL,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
	mysql_query($query);

    $query = "INSERT INTO `mod_twozero_sms_settings` (`api`, `apiparams`, `wantsmsfield`, `gsmnumberfield`,`dateformat`, `version`) VALUES ('twozero', '{\"senderid\":\"\",\"signature\":\"\",\"apiKey\":\"\"}', 0, 0,'%d.%m.%y','1.0');";
	mysql_query($query);

    $query = "CREATE TABLE IF NOT EXISTS `mod_twozero_sms_templates` (`id` int(11) NOT NULL AUTO_INCREMENT,`name` varchar(50) CHARACTER SET utf8 NOT NULL,`type` enum('client','admin') CHARACTER SET utf8 NOT NULL,`admingsm` varchar(255) CHARACTER SET utf8 NOT NULL,`template` varchar(240) CHARACTER SET utf8 NOT NULL,`variables` varchar(500) CHARACTER SET utf8 NOT NULL,`active` tinyint(1) NOT NULL,`extra` varchar(3) CHARACTER SET utf8 NOT NULL,`description` text CHARACTER SET utf8,PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
	mysql_query($query);

	//Create a New Table for OTP
    $query = "CREATE TABLE IF NOT EXISTS `mod_twozero_sms_otp` (`id` int(11) NOT NULL AUTO_INCREMENT,`otp` varchar(50) CHARACTER SET utf8 NOT NULL,`type` enum('client','admin') CHARACTER SET utf8 DEFAULT 'client',`relid` int(10) DEFAULT 0,`request` varchar(50) CHARACTER SET utf8 NOT NULL,`text` text,`status` tinyint(1) DEFAULT 0, `datetime` datetime NOT NULL, `phonenumber` text, PRIMARY KEY (`id`)) ENGINE=MyISAM  DEFAULT CHARSET=latin1 AUTO_INCREMENT=1;";
	mysql_query($query);


    $class = new TwoZeroSms();
    $class->checkHooks();

    return array('status'=>'success','description'=>'twozero_sms successfully activated :)');
}

function twozero_sms_deactivate() {

    $query = "DROP TABLE `mod_twozero_sms_templates`";
	mysql_query($query);
    $query = "DROP TABLE `mod_twozero_sms_settings`";
    mysql_query($query);
    $query = "DROP TABLE `mod_twozero_sms_messages`";
    mysql_query($query);
	//DROP Table for OTP
    $query = "DROP TABLE `mod_twozero_sms_otp`";
    mysql_query($query);

    return array('status'=>'success','description'=>'twozero_sms successfully deactivated :(');
}

function twozero_sms_upgrade($vars) {
    // Fresh-installation upgrade path; no legacy MIM data migration.
    // Tables are recreated by activate(); individual settings/hooks live in their own tables.
    $class = new TwoZeroSms();
    $class->checkHooks();
}

function twozero_sms_output($vars){
	$modulelink = $vars['modulelink'];
	$version = $vars['version'];
	$LANG = $vars['_lang'];
	putenv("TZ=Asia/Kolkata");

    $class = new TwoZeroSms();

    // AJAX endpoint: refresh balance (used by the settings-page Refresh button).
    if (isset($_GET['action']) && $_GET['action'] === 'refresh_balance') {
        $balance = $class->getBalance();
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store, must-revalidate');
        echo json_encode($balance);
        exit;
    }

    $tab = $_GET['tab'];
    echo '<div id="twozero_sms_plugin_container">
    <style>
    /* Twozero SMS design system. Everything is scoped to the addon container
       so WHMCS admin styles are never affected. */
    #twozero_sms_plugin_container{
        --tz-accent:#2563eb; --tz-accent-dark:#1d4ed8; --tz-accent-soft:#eff6ff;
        --tz-ink:#0f172a; --tz-muted:#64748b; --tz-faint:#94a3b8;
        --tz-border:#e2e8f0; --tz-bg:#f8fafc;
        --tz-green-bg:#dcfce7; --tz-green:#166534;
        --tz-amber-bg:#fef3c7; --tz-amber:#92400e;
        --tz-red-bg:#fee2e2; --tz-red:#991b1b;
        color:var(--tz-ink); line-height:1.5;
    }
    #twozero_sms_plugin_container *, #twozero_sms_plugin_container *::before, #twozero_sms_plugin_container *::after{box-sizing:border-box}
    /* Page heading */
    .tz-pagehead{margin:2px 0 18px}
    .tz-pagehead h2{margin:0 0 4px;font-size:20px;font-weight:700;color:var(--tz-ink)}
    .tz-pagehead p{margin:0;font-size:13px;color:var(--tz-muted);max-width:720px}
    /* Tabs */
    .tz-tabs{display:flex;flex-wrap:wrap;gap:4px;border-bottom:1px solid var(--tz-border);margin-bottom:20px}
    .tz-tab{display:inline-flex;align-items:center;gap:8px;padding:10px 14px;font-size:13px;font-weight:600;color:var(--tz-muted);text-decoration:none;border-bottom:2px solid transparent;margin-bottom:-1px;white-space:nowrap}
    .tz-tab svg{width:15px;height:15px;flex-shrink:0}
    .tz-tab:hover{color:var(--tz-ink);text-decoration:none;background:#f8fafc}
    .tz-tab.tz-active{color:var(--tz-accent);border-bottom-color:var(--tz-accent)}
    /* Cards */
    .tz-card{background:#fff;border:1px solid var(--tz-border);border-radius:10px;padding:22px;margin-bottom:18px;box-shadow:0 1px 2px rgba(15,23,42,.05)}
    .tz-card h3{margin:0 0 4px;font-size:15px;font-weight:700}
    .tz-card .tz-sub{margin:0 0 18px;font-size:12.5px;color:var(--tz-muted)}
    .tz-narrow{max-width:720px}
    /* Forms */
    .tz-field{margin-bottom:16px}
    .tz-field label{display:block;font-size:13px;font-weight:600;margin-bottom:6px}
    .tz-field label .tz-optional{font-weight:400;color:var(--tz-faint)}
    .tz-input,.tz-textarea,.tz-select{display:block;width:100%;max-width:560px;padding:8px 12px;font-size:13px;font-family:inherit;color:var(--tz-ink);background:#fff;border:1px solid #cbd5e1;border-radius:7px;box-shadow:none}
    .tz-textarea{min-height:88px;resize:vertical;line-height:1.5}
    .tz-select{width:auto;min-width:220px}
    .tz-input:focus,.tz-textarea:focus,.tz-select:focus{outline:none;border-color:var(--tz-accent);box-shadow:0 0 0 3px rgba(37,99,235,.15)}
    .tz-help{margin-top:6px;font-size:12px;color:var(--tz-muted);max-width:560px}
    .tz-help code,.tz-chip{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace}
    .tz-row{display:flex;flex-wrap:wrap;gap:16px}
    .tz-row .tz-field{flex:1 1 240px;min-width:0}
    .tz-form-actions{margin-top:20px;padding-top:18px;border-top:1px solid var(--tz-border);display:flex;align-items:center;gap:12px}
    /* Buttons */
    .tz-btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;padding:9px 20px;font-size:13px;font-weight:600;font-family:inherit;border-radius:7px;cursor:pointer;border:1px solid transparent;text-decoration:none;transition:background .15s,border-color .15s}
    .tz-btn svg{width:14px;height:14px}
    .tz-btn-primary{background:var(--tz-accent);border-color:var(--tz-accent);color:#fff}
    .tz-btn-primary:hover{background:var(--tz-accent-dark);border-color:var(--tz-accent-dark);color:#fff}
    .tz-btn-secondary{background:#fff;border-color:#cbd5e1;color:#334155}
    .tz-btn-secondary:hover{background:#f8fafc;border-color:#94a3b8;color:var(--tz-ink)}
    /* Alerts */
    .tz-alert{display:flex;gap:10px;align-items:flex-start;padding:12px 14px;border-radius:8px;font-size:13px;margin-bottom:16px;border:1px solid}
    .tz-alert svg{width:17px;height:17px;flex-shrink:0;margin-top:1px}
    .tz-alert-success{background:#f0fdf4;border-color:#bbf7d0;color:#166534}
    .tz-alert-error{background:#fef2f2;border-color:#fecaca;color:#991b1b}
    .tz-alert-info{background:var(--tz-accent-soft);border-color:#bfdbfe;color:#1e40af}
    /* Tables */
    .tz-tablewrap{background:#fff;border:1px solid var(--tz-border);border-radius:10px;overflow-x:auto;box-shadow:0 1px 2px rgba(15,23,42,.05)}
    table.tz-table{width:100%;border-collapse:collapse;margin:0;font-size:13px}
    table.tz-table thead th{text-align:left;font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--tz-muted);font-weight:600;padding:11px 14px;background:var(--tz-bg);border-bottom:1px solid var(--tz-border);white-space:nowrap}
    table.tz-table tbody td{padding:11px 14px;border-top:1px solid #f1f5f9;vertical-align:top}
    table.tz-table tbody tr:first-child td{border-top:0}
    table.tz-table tbody tr:hover{background:#f8fafc}
    .tz-mono{font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;font-size:12.5px;white-space:nowrap}
    .tz-msgtext{max-width:420px;overflow:hidden;text-overflow:ellipsis;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical}
    /* Badges + chips */
    .tz-badge{display:inline-flex;align-items:center;gap:6px;padding:3px 10px;border-radius:999px;font-size:11px;font-weight:600;white-space:nowrap}
    .tz-badge::before{content:"";width:6px;height:6px;border-radius:50%;background:currentColor}
    .tz-badge-green{background:var(--tz-green-bg);color:var(--tz-green)}
    .tz-badge-amber{background:var(--tz-amber-bg);color:var(--tz-amber)}
    .tz-badge-red{background:var(--tz-red-bg);color:var(--tz-red)}
    .tz-badge-gray{background:#f1f5f9;color:#475569}
    .tz-badge-blue{background:var(--tz-accent-soft);color:#1d4ed8}
    .tz-chips{display:flex;flex-wrap:wrap;gap:6px}
    .tz-chip{display:inline-block;background:#f1f5f9;border:1px solid var(--tz-border);border-radius:6px;padding:2px 8px;font-size:12px;color:#334155}
    /* Toggle switch */
    .tz-switch{position:relative;display:inline-flex;align-items:center;gap:10px;cursor:pointer;font-size:13px;font-weight:500}
    .tz-switch input{position:absolute;opacity:0;width:0;height:0}
    .tz-switch .tz-slider{position:relative;width:38px;height:22px;flex-shrink:0;background:#cbd5e1;border-radius:999px;transition:background .15s}
    .tz-switch .tz-slider::after{content:"";position:absolute;top:3px;left:3px;width:16px;height:16px;background:#fff;border-radius:50%;transition:left .15s;box-shadow:0 1px 2px rgba(0,0,0,.25)}
    .tz-switch input:checked + .tz-slider{background:var(--tz-accent)}
    .tz-switch input:checked + .tz-slider::after{left:19px}
    .tz-switch input:focus + .tz-slider{box-shadow:0 0 0 3px rgba(37,99,235,.25)}
    /* Template cards */
    .tz-tpl{margin-bottom:14px}
    .tz-tpl-head{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:4px}
    .tz-tpl-name{font-size:14px;font-weight:700}
    .tz-tpl-vars{margin:10px 0 0}
    .tz-tpl-vars .tz-vars-label{font-size:12px;color:var(--tz-muted);margin-bottom:6px}
    /* Empty state */
    .tz-empty{text-align:center;padding:48px 20px;background:#fff;border:1px dashed var(--tz-border);border-radius:10px}
    .tz-empty-icon{width:48px;height:48px;border-radius:14px;background:var(--tz-accent-soft);display:inline-flex;align-items:center;justify-content:center;margin-bottom:12px}
    .tz-empty-icon svg{width:22px;height:22px;color:var(--tz-accent)}
    .tz-empty h3{margin:0 0 4px;font-size:14px}
    .tz-empty p{margin:0;font-size:13px;color:var(--tz-muted)}
    /* Pagination */
    .tz-pagination{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:14px}
    .tz-pagination .tz-pageinfo{font-size:12.5px;color:var(--tz-muted)}
    .tz-pagination .tz-select{min-width:0;width:auto}
    /* Debug log */
    .tz-debug{margin-top:6px}
    .tz-debug summary{cursor:pointer;font-size:13px;font-weight:600;color:var(--tz-accent);list-style:none}
    .tz-debug summary::-webkit-details-marker{display:none}
    .tz-debug summary::before{content:"▸ ";font-size:12px}
    .tz-debug[open] summary::before{content:"▾ "}
    .tz-debug-body{background:#1e293b;color:#e2e8f0;border-radius:8px;padding:14px 16px;margin-top:10px;font-size:12px;max-height:320px;overflow:auto}
    .tz-debug-body pre{background:none;border:0;color:inherit;margin:0;padding:0;white-space:pre-wrap;word-break:break-word}
    .tz-debug-body ul{margin:6px 0;padding-left:20px}
    .tz-debug-body li{margin-bottom:4px}
    .tz-counter{font-size:12px;color:var(--tz-muted);text-align:right;max-width:560px;margin-top:6px}
    .tz-iconbtn{display:inline-flex;align-items:center;justify-content:center;width:30px;height:30px;border-radius:7px;color:var(--tz-faint);border:1px solid transparent;background:none;cursor:pointer;text-decoration:none}
    .tz-iconbtn svg{width:15px;height:15px}
    .tz-iconbtn:hover{color:var(--tz-red);background:var(--tz-red-bg);border-color:#fecaca}
    /* Footer */
    .tz-footer{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;margin-top:20px;padding-top:14px;border-top:1px solid var(--tz-border);font-size:12px;color:var(--tz-faint)}
    .tz-footer a{color:var(--tz-accent);text-decoration:none}
    .tz-footer a:hover{text-decoration:underline}
    .tz-footer-credits{display:inline-flex;align-items:center;gap:8px}
    @media (max-width:640px){
        .tz-card{padding:16px}
        .tz-pagehead h2{font-size:17px}
        .tz-tab{padding:9px 10px;font-size:12.5px}
        .tz-form-actions .tz-btn{flex:1}
    }
    @media (prefers-reduced-motion:reduce){
        .tz-refresh-btn.is-spinning svg{animation:none}
        .tz-btn,.tz-switch .tz-slider,.tz-switch .tz-slider::after{transition:none}
    }
    </style>
    <nav class="tz-tabs">
        <a class="tz-tab' . (($tab == "settings" || ((isset($_GET['type']) ? $_GET['type'] : "") == "" && $tab == ""))?" tz-active":"") . '" href="addonmodules.php?module=twozero_sms&tab=settings"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.324.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 011.37.49l1.296 2.247a1.125 1.125 0 01-.26 1.431l-1.003 1.003c-.29.29-.406.716-.317 1.12l.272 1.234c.096.44-.04.89-.358 1.207l-.908.908c-.318.318-.767.454-1.207.358l-1.234-.272a1.125 1.125 0 01-1.12.317l-1.003 1.003a1.125 1.125 0 01-1.431.26l-2.247-1.296a1.125 1.125 0 01-.49-1.37l.456-1.217c.133-.355.072-.751-.124-1.075a9.122 9.122 0 00-.127-.22c-.184-.332-.496-.582-.87-.645l-1.281-.213A1.125 1.125 0 013.94 14.9h0a1.125 1.125 0 01-.94-1.11l.213-1.281c.063-.374.313-.686.645-.87.074-.04.147-.083.22-.127.324-.196.72-.257 1.075-.124l1.217.456a1.125 1.125 0 001.37-.49l1.296-2.247a1.125 1.125 0 00-.26-1.431l-1.003-1.003a1.125 1.125 0 00-1.12-.317l-1.234.272c-.44.096-.89-.04-1.207-.358l-.908-.908c-.318-.318-.454-.767-.358-1.207l.272-1.234c.089-.404-.027-.83-.317-1.12l-1.003-1.003a1.125 1.125 0 00-1.431-.26L3.94 9.594z" /><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" /></svg>'.$LANG['settings'].'</a>
        <a class="tz-tab' . (((isset($_GET['type']) ? $_GET['type'] : "") == "client")?" tz-active":"") . '" href="addonmodules.php?module=twozero_sms&tab=templates&type=client"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg>'.$LANG['clientsmstemplates'].'</a>
        <a class="tz-tab' . (((isset($_GET['type']) ? $_GET['type'] : "") == "admin")?" tz-active":"") . '" href="addonmodules.php?module=twozero_sms&tab=templates&type=admin"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75m-1.5-5.036A11.959 11.959 0 013.598 6 11.99 11.99 0 003 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285z" /></svg>'.$LANG['adminsmstemplates'].'</a>
        <a class="tz-tab' . (($tab == "sendbulk")?" tz-active":"") . '" href="addonmodules.php?module=twozero_sms&tab=sendbulk"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>'.$LANG['sendsms'].'</a>
        <a class="tz-tab' . (($tab == "messages")?" tz-active":"") . '" href="addonmodules.php?module=twozero_sms&amp;tab=messages"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg>'.$LANG['messages'].'</a>
    </nav>
    ';
    if (!isset($tab) || $tab == "settings")
    {
        /* UPDATE SETTINGS */
        $tzSaved = false;
        if (!empty($_POST['params']) && is_array($_POST['params'])) {
            $tzPosted = $_POST['params'];
            // Leaving the API key field blank keeps the existing key so a
            // settings save can never wipe the connection by accident.
            if (trim((string) (isset($tzPosted['apiKey']) ? $tzPosted['apiKey'] : '')) === '') {
                $tzExisting = $class->getSettings();
                $tzExistingParams = json_decode(isset($tzExisting['apiparams']) ? (string) $tzExisting['apiparams'] : '');
                $tzPosted['apiKey'] = (is_object($tzExistingParams) && isset($tzExistingParams->apiKey)) ? $tzExistingParams->apiKey : '';
            }
            $update = array(
                "api" => isset($_POST['api']) ? $_POST['api'] : 'twozero',
                "apiparams" => json_encode($tzPosted),
                'dateformat' => isset($_POST['dateformat']) ? $_POST['dateformat'] : '%d.%m.%y'
            );
            if (isset($_POST['wantsmsfield'])) {
                $update['wantsmsfield'] = $_POST['wantsmsfield'];
            }
            if (isset($_POST['gsmnumberfield'])) {
                $update['gsmnumberfield'] = $_POST['gsmnumberfield'];
            }
            update_query("mod_twozero_sms_settings", $update, "");
            $tzSaved = true;
        }
        /* UPDATE SETTINGS */

        $settings = $class->getSettings();
        $apiparams = json_decode(isset($settings['apiparams']) ? (string) $settings['apiparams'] : '');

        $tzExistingKey = (is_object($apiparams) && isset($apiparams->apiKey)) ? (string) $apiparams->apiKey : '';
        $tzKeyBadge = $tzExistingKey !== ''
            ? '<span class="tz-badge tz-badge-green">Configured &bull; ending ' . htmlspecialchars(substr($tzExistingKey, -4)) . '</span>'
            : '<span class="tz-badge tz-badge-amber">Not configured</span>';

        echo '<div class="tz-pagehead"><h2>' . $LANG['settings'] . '</h2><p>Connect your twozero.io account, then set a default sender ID and message signature.</p></div>';
        if (!empty($tzSaved)) {
            echo '<div class="tz-alert tz-alert-success"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span>Settings saved.</span></div>';
        }

        /* BALANCE PANEL — live fetch + manual refresh button. */
        $balance = $class->getBalance();
        $now     = date('Y-m-d H:i:s');

        // Pre-compute pieces used in both server render and JS refresh.
        $tzHasError = false;
        $tzErrorMsg = '';
        $tzCreditsInt = 0;
        $tzKycStatus = '';
        $tzKycVerified = false;
        $tzKycLabel   = 'Unknown';
        $tzKycClass   = 'unknown';

        if ($balance === false) {
            $tzHasError = true;
            $tzErrorMsg = 'Invalid API selected in settings.';
        } elseif (!is_array($balance)) {
            $tzHasError = true;
            $tzErrorMsg = 'Balance not available (sender returned no data).';
        } elseif (!empty($balance['success'])) {
            $tzCreditsInt   = (int) $balance['credits'];
            $tzKycStatus    = strtolower((string) ($balance['kyc_status'] ?? ''));
            $tzKycVerified  = !empty($balance['kyc_verified']);
            $tzKycMap       = array('approved' => 'approved', 'pending' => 'pending', 'rejected' => 'rejected');
            $tzKycClass     = isset($tzKycMap[$tzKycStatus]) ? $tzKycMap[$tzKycStatus] : 'unknown';
            $tzKycLabel     = $tzKycStatus !== '' ? ucfirst($tzKycStatus) : 'Unknown';
        } else {
            $tzHasError = true;
            $tzErrorMsg = !empty($balance['error']) ? $balance['error'] : 'Unable to fetch balance.';
        }

        if ($tzHasError) {
            $tzValueHtml = '<span class="tz-balance-error"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="tz-balance-error-icon"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg><span>' . htmlspecialchars($tzErrorMsg) . '</span></span>';
            $tzKycHtml   = '';
        } else {
            $tzCreditClass = $tzCreditsInt < 10 ? 'tz-balance-critical' : ($tzCreditsInt < 100 ? 'tz-balance-low' : '');
            $tzValueHtml  = '<span class="' . $tzCreditClass . '">' . number_format($tzCreditsInt) . '</span><small>credits</small>';
            $tzKycHtml    = '<span class="tz-kyc-pill tz-kyc-' . $tzKycClass . '"><span class="dot"></span>KYC: ' . htmlspecialchars($tzKycLabel) . '</span>'
                          . '<span class="tz-kyc-verified">' . ($tzKycVerified ? 'Verified' : 'Unverified') . '</span>';
        }

        echo '
        <style>
        .tz-balance-card{display:flex;align-items:center;gap:20px;background:linear-gradient(135deg,#f8fafc 0%,#eef2f7 100%);border:1px solid #e2e8f0;border-radius:10px;padding:18px 22px;margin-bottom:20px;box-shadow:0 1px 2px rgba(15,23,42,0.04)}
        .tz-balance-icon{flex-shrink:0;width:52px;height:52px;background:#dbeafe;border-radius:12px;display:flex;align-items:center;justify-content:center}
        .tz-balance-icon svg{width:26px;height:26px;color:#2563eb}
        .tz-balance-main{flex:1;min-width:0}
        .tz-balance-label{font-size:11px;text-transform:uppercase;letter-spacing:0.6px;color:#64748b;font-weight:600;margin-bottom:6px}
        .tz-balance-credits{font-size:30px;font-weight:700;color:#0f172a;line-height:1;display:flex;align-items:baseline;gap:8px}
        .tz-balance-credits small{font-size:13px;font-weight:500;color:#64748b}
        .tz-balance-credits .tz-balance-low{color:#b45309}
        .tz-balance-credits .tz-balance-critical{color:#b91c1c}
        .tz-balance-meta{font-size:11px;color:#94a3b8;margin-top:8px}
        .tz-balance-side{display:flex;flex-direction:column;align-items:flex-end;gap:10px;flex-shrink:0}
        .tz-kyc-pill{display:inline-flex;align-items:center;gap:6px;padding:4px 10px;border-radius:999px;font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.4px}
        .tz-kyc-pill .dot{width:6px;height:6px;border-radius:50%;background:currentColor;display:inline-block}
        .tz-kyc-approved{background:#dcfce7;color:#166534}
        .tz-kyc-pending{background:#fef3c7;color:#92400e}
        .tz-kyc-rejected{background:#fee2e2;color:#991b1b}
        .tz-kyc-unknown{background:#f1f5f9;color:#475569}
        .tz-kyc-verified{font-size:11px;color:#64748b}
        .tz-refresh-btn{display:inline-flex;align-items:center;gap:6px;padding:7px 14px;background:#fff;color:#334155;border:1px solid #cbd5e1;border-radius:7px;font-size:12px;font-weight:500;cursor:pointer;transition:all .15s;font-family:inherit}
        .tz-refresh-btn:hover{background:#f8fafc;border-color:#94a3b8;color:#0f172a}
        .tz-refresh-btn:disabled{opacity:0.65;cursor:wait}
        .tz-refresh-btn svg{width:14px;height:14px}
        .tz-refresh-btn.is-spinning svg{animation:tz-spin 1s linear infinite}
        @keyframes tz-spin{from{transform:rotate(0)}to{transform:rotate(360deg)}}
        .tz-balance-error{display:inline-flex;align-items:center;gap:8px;background:#fef2f2;border:1px solid #fecaca;color:#991b1b;border-radius:7px;padding:8px 14px;font-size:13px;font-weight:500}
        .tz-balance-error-icon{width:18px;height:18px;flex-shrink:0}
        </style>
        <div class="tz-balance-card" id="twozero-balance-card">
            <div class="tz-balance-icon">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" /></svg>
            </div>
            <div class="tz-balance-main">
                <div class="tz-balance-label">Available Credits</div>
                <div class="tz-balance-credits" id="twozero-balance-value">' . $tzValueHtml . '</div>
                <div class="tz-balance-meta" id="twozero-balance-meta">Last updated ' . htmlspecialchars($now) . '</div>
            </div>
            <div class="tz-balance-side">
                <span id="twozero-balance-kyc">' . $tzKycHtml . '</span>
                <button type="button" id="twozero-balance-refresh" class="tz-refresh-btn">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0l3.181 3.183a8.25 8.25 0 0013.803-3.7M4.031 9.865a8.25 8.25 0 0113.803-3.7l3.181 3.182m0-4.991v4.99" /></svg>
                    <span>Refresh</span>
                </button>
            </div>
        </div>
        <script>
        (function(){
            var card    = document.getElementById("twozero-balance-card");
            var btn     = document.getElementById("twozero-balance-refresh");
            var valueEl = document.getElementById("twozero-balance-value");
            var metaEl  = document.getElementById("twozero-balance-meta");
            var kycEl   = document.getElementById("twozero-balance-kyc");
            if (!btn || !card || !valueEl || !metaEl || !kycEl) return;

            function escHtml(s){
                return String(s).replace(/[&<>"\'\/]/g, function(c){
                    return ({"&":"&amp;","<":"&lt;",">":"&gt;","\'":"&#39;","\"":"&quot;","/":"&#x2F;"})[c];
                });
            }

            function render(d){
                if (!d || !d.success) {
                    var msg = (d && d.error) ? d.error : "Unable to fetch balance.";
                    valueEl.innerHTML = "<span class=\"tz-balance-error\"><svg xmlns=\"http://www.w3.org/2000/svg\" fill=\"none\" viewBox=\"0 0 24 24\" stroke-width=\"2\" stroke=\"currentColor\" class=\"tz-balance-error-icon\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" d=\"M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z\" /></svg><span>" + escHtml(msg) + "</span></span>";
                    kycEl.innerHTML = "";
                    return;
                }
                var n = Number(d.credits) || 0;
                var creditClass = n < 10 ? "tz-balance-critical" : (n < 100 ? "tz-balance-low" : "");
                valueEl.innerHTML = "<span class=\"" + creditClass + "\">" + n.toLocaleString() + "</span><small>credits</small>";

                var kycStatus = (d.kyc_status || "").toLowerCase();
                var kycClassMap = { approved: "approved", pending: "pending", rejected: "rejected" };
                var kycClass = kycClassMap[kycStatus] || "unknown";
                var kycLabel = kycStatus ? (kycStatus.charAt(0).toUpperCase() + kycStatus.slice(1)) : "Unknown";
                var verified = d.kyc_verified ? "Verified" : "Unverified";

                kycEl.innerHTML = "<span class=\"tz-kyc-pill tz-kyc-" + kycClass + "\"><span class=\"dot\"></span>KYC: " + escHtml(kycLabel) + "</span>"
                                + "<span class=\"tz-kyc-verified\">" + verified + "</span>";

                metaEl.textContent = "Last updated " + (d.last_checked || "");
            }

            btn.addEventListener("click", function(){
                btn.disabled = true;
                btn.classList.add("is-spinning");
                var origHtml = btn.innerHTML;
                btn.innerHTML = btn.innerHTML.replace(">Refresh<", ">Refreshing<");
                fetch("addonmodules.php?module=twozero_sms&action=refresh_balance", { credentials: "same-origin" })
                    .then(function(r){ return r.json(); })
                    .then(function(d){
                        btn.disabled = false;
                        btn.classList.remove("is-spinning");
                        btn.innerHTML = origHtml;
                        render(d);
                    })
                    .catch(function(){
                        btn.disabled = false;
                        btn.classList.remove("is-spinning");
                        btn.innerHTML = origHtml;
                        render(null);
                    });
            });
        })();
        </script>
        ';

        $tzSenderId  = (is_object($apiparams) && isset($apiparams->senderid)) ? (string) $apiparams->senderid : '';
        $tzSignature = (is_object($apiparams) && isset($apiparams->signature)) ? (string) $apiparams->signature : '';
        $tzDateFmt   = isset($settings['dateformat']) ? (string) $settings['dateformat'] : '';

        echo '
        <form action="" method="post" id="form">
        <input type="hidden" name="action" value="save" />
        <input type="hidden" value="twozero" name="api" id="api"/>
        <div class="tz-card tz-narrow">
            <h3>Gateway connection</h3>
            <p class="tz-sub">Generate an API key from your <a href="https://sms.twozero.io" target="_blank" rel="noopener">twozero.io dashboard</a>, then paste it below.</p>
            <div class="tz-field">
                <label for="tz-apikey">API Key &nbsp;' . $tzKeyBadge . '</label>
                <input type="password" class="tz-input" id="tz-apikey" name="params[apiKey]" placeholder="sk_..." value="" autocomplete="new-password">
                <div class="tz-help">Leave blank to keep the existing key. Enter a new key only when rotating.</div>
            </div>
            <div class="tz-row">
                <div class="tz-field">
                    <label for="tz-senderid">' . $LANG['senderid'] . ' <span class="tz-optional">(optional)</span></label>
                    <input type="text" class="tz-input" id="tz-senderid" name="params[senderid]" maxlength="11" placeholder="e.g. TZLABS" value="' . htmlspecialchars($tzSenderId, ENT_QUOTES) . '">
                    <div class="tz-help">Up to 11 characters. Leave empty to use your account default.</div>
                </div>
                <div class="tz-field">
                    <label for="tz-dateformat">' . $LANG['dateformat'] . '</label>
                    <input type="text" class="tz-input" id="tz-dateformat" name="dateformat" value="' . htmlspecialchars($tzDateFmt, ENT_QUOTES) . '">
                    <div class="tz-help">e.g. <code>%d.%m.%y</code> renders as 27.01.2014 in <code>{duedate}</code>.</div>
                </div>
            </div>
            <div class="tz-field">
                <label for="tz-signature">' . $LANG['signature'] . ' <span class="tz-optional">(optional)</span></label>
                <textarea class="tz-textarea" id="tz-signature" name="params[signature]" rows="3" placeholder="e.g.  - My Company">' . htmlspecialchars($tzSignature) . '</textarea>
                <div class="tz-help">Appended to every outgoing message.</div>
            </div>
            <div class="tz-form-actions">
                <button type="submit" class="tz-btn tz-btn-primary"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>' . $LANG['save'] . '</button>
            </div>
        </div>
        </form>
        ';
    }
    elseif ($tab == "templates")
    {
        $tzTplSaved = false;
        if (!empty($_POST['submit'])) {
            $where = array("type" => array("sqltype" => "LIKE", "value" => $_GET['type']));
            $result = select_query("mod_twozero_sms_templates", "*", $where);
            while ($data = mysql_fetch_array($result)) {
                if (isset($_POST[$data['id'] . '_active']) && $_POST[$data['id'] . '_active'] == "on") {
                    $tmp_active = 1;
                } else {
                    $tmp_active = 0;
                }
                $update = array(
                    "template" => $_POST[$data['id'] . '_template'],
                    "active" => $tmp_active
                );

                if(isset($_POST[$data['id'] . '_extra'])){
                    $update['extra']= trim($_POST[$data['id'] . '_extra']);
                }
                if(isset($_POST[$data['id'] . '_admingsm'])){
                    $update['admingsm']= $_POST[$data['id'] . '_admingsm'];
                    $update['admingsm'] = str_replace(" ","",$update['admingsm']);
                }
                update_query("mod_twozero_sms_templates", $update, "id = " . $data['id']);
            }
            $tzTplSaved = true;
        }

        $tzTplType = isset($_GET['type']) ? $_GET['type'] : 'client';
        $tzTplTitle = ($tzTplType == 'admin') ? $LANG['adminsmstemplates'] : $LANG['clientsmstemplates'];
        $tzTplDesc = ($tzTplType == 'admin')
            ? 'Automatic notifications sent to your admin numbers when these events fire.'
            : 'Automatic messages sent to clients when these events fire. Insert the listed variables anywhere in the text.';

        echo '<div class="tz-pagehead"><h2>' . $tzTplTitle . '</h2><p>' . $tzTplDesc . '</p></div>';
        if (!empty($tzTplSaved)) {
            echo '<div class="tz-alert tz-alert-success"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span>Templates saved.</span></div>';
        }

        echo '<form action="" method="post">
        <input type="hidden" name="action" value="save" />';
        $where = array("type" => array("sqltype" => "LIKE", "value" => $_GET['type']));
        $result = select_query("mod_twozero_sms_templates", "*", $where);

        $tzTplCount = 0;
        while ($data = mysql_fetch_array($result)) {
            $tzTplCount++;
            $tzTplActive = ($data['active'] == 1) ? 'checked="checked"' : '';
            $tzTplDesc = json_decode(isset($data['description']) ? (string) $data['description'] : '');
            $tzLangKey = isset($LANG['lang']) ? (string) $LANG['lang'] : 'english';
            if (is_object($tzTplDesc) && isset($tzTplDesc->$tzLangKey)) {
                $tzTplName = $tzTplDesc->$tzLangKey;
            } else {
                $tzTplName = isset($data['name']) ? $data['name'] : '';
            }
            $tzVarChips = '';
            foreach (explode(',', isset($data['variables']) ? (string) $data['variables'] : '') as $tzVar) {
                $tzVar = trim($tzVar);
                if ($tzVar !== '') {
                    $tzVarChips .= '<span class="tz-chip">' . htmlspecialchars($tzVar) . '</span>';
                }
            }
            echo '<div class="tz-card tz-tpl">'
                . '<div class="tz-tpl-head"><span class="tz-tpl-name">' . htmlspecialchars((string) $tzTplName) . '</span>'
                . '<label class="tz-switch"><input type="checkbox" value="on" name="' . $data['id'] . '_active" ' . $tzTplActive . '><span class="tz-slider"></span>' . $LANG['active'] . '</label></div>'
                . '<div class="tz-field" style="margin-bottom:0;margin-top:12px">'
                . '<textarea class="tz-textarea" style="max-width:none" rows="3" name="' . $data['id'] . '_template">' . htmlspecialchars(isset($data['template']) ? (string) $data['template'] : '') . '</textarea>'
                . '</div>';
            if ($tzVarChips !== '') {
                echo '<div class="tz-tpl-vars"><div class="tz-vars-label">' . $LANG['parameter'] . '</div><div class="tz-chips">' . $tzVarChips . '</div></div>';
            }
            if (!empty($data['extra']) || $tzTplType == 'admin') {
                echo '<div class="tz-row" style="margin-top:14px">';
                if (!empty($data['extra'])) {
                    echo '<div class="tz-field" style="margin-bottom:0"><label>' . $LANG['ekstra'] . '</label>'
                        . '<input type="text" class="tz-input" name="' . $data['id'] . '_extra" value="' . htmlspecialchars((string) $data['extra'], ENT_QUOTES) . '"></div>';
                }
                if ($tzTplType == 'admin') {
                    echo '<div class="tz-field" style="margin-bottom:0"><label>' . $LANG['admingsm'] . '</label>'
                        . '<input type="text" class="tz-input" style="max-width:none" name="' . $data['id'] . '_admingsm" placeholder="e.g. 9779841xxxxxx,9779851xxxxxx" value="' . htmlspecialchars(isset($data['admingsm']) ? (string) $data['admingsm'] : '', ENT_QUOTES) . '">'
                        . '<div class="tz-help" style="max-width:none">' . $LANG['admingsmornek'] . '</div></div>';
                }
                echo '</div>';
            }
            echo '</div>';
        }
        if ($tzTplCount == 0) {
            echo '<div class="tz-empty"><div class="tz-empty-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" /></svg></div><h3>No templates found</h3><p>Templates are created automatically when the addon is activated.</p></div>';
        }
        echo '<div class="tz-card"><div class="tz-form-actions" style="margin-top:0;padding-top:0;border-top:0">'
            . '<button type="submit" name="submit" value="Save Changes" class="tz-btn tz-btn-primary"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>Save Changes</button>'
            . '</div></div></form>';

    }
    elseif ($tab == "messages")
    {
        $tzDeleted = false;
        if(!empty($_GET['deletesms'])){
            $smsid = (int) $_GET['deletesms'];
            $sql = "DELETE FROM mod_twozero_sms_messages WHERE id = '$smsid'";
            mysql_query($sql);
            $tzDeleted = true;
        }

        echo '<div class="tz-pagehead"><h2>' . $LANG['messages'] . '</h2><p>Every message sent through the gateway, newest first.</p></div>';
        if ($tzDeleted) {
            echo '<div class="tz-alert tz-alert-info"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M11.25 11.25l.041-.02a.75.75 0 011.063.852l-.708 2.836a.75.75 0 001.063.853l.041-.021M21 12a9 9 0 11-18 0 9 9 0 0118 0zm-9-3.75h.008v.008H12V8.25z" /></svg><span>Message record deleted.</span></div>';
        }

        // Getting pagination values.
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = (isset($_GET['limit']) && $_GET['limit']<=50) ? (int)$_GET['limit'] : 10;
        $start  = ($page > 1) ? ($page*$limit)-$limit : 0;
        $order = isset($_GET['order']) ? $_GET['order'] : 'DESC';
        /* Getting messages order by date desc */
        $sql = "SELECT `m`.*,`user`.`firstname`,`user`.`lastname`
        FROM `mod_twozero_sms_messages` as `m`
        JOIN `tblclients` as `user` ON `m`.`user` = `user`.`id`
        ORDER BY `m`.`datetime` {$order} limit {$start},{$limit}";
        $result = mysql_query($sql);
        $i = 0;

        //Getting total records
        $total = "SELECT count(id) as toplam FROM `mod_twozero_sms_messages`";
        $sonuc = mysql_query($total);
        $sonuc = mysql_fetch_array($sonuc);
        $toplam = $sonuc['toplam'];

        //Page calculation
        $sayfa = ceil($toplam/$limit);

        $tzRows = '';
        while ($data = mysql_fetch_array($result)) {
            if($data['msgid'] && $data['status'] == ""){
                $status = $class->getReport($data['msgid']);
                mysql_query("UPDATE mod_twozero_sms_messages SET status = '$status' WHERE id = ".$data['id']);
            }else{
                $status = $data['status'];
            }

            $i++;

            if ($status === 'error') {
                $tzBadgeCls = 'tz-badge-red';
                $tzStatusLabel = isset($LANG['error']) ? $LANG['error'] : 'Error';
            } elseif ($status === '' || $status === null) {
                if (!empty($data['msgid'])) {
                    $tzBadgeCls = 'tz-badge-green';
                    $tzStatusLabel = isset($LANG['sent']) ? $LANG['sent'] : 'Sent';
                } else {
                    $tzBadgeCls = 'tz-badge-gray';
                    $tzStatusLabel = '—';
                }
            } elseif ($status === 'pending') {
                $tzBadgeCls = 'tz-badge-amber';
                $tzStatusLabel = isset($LANG['pending']) ? $LANG['pending'] : $status;
            } else {
                $tzBadgeCls = 'tz-badge-blue';
                $tzStatusLabel = isset($LANG[$status]) ? $LANG[$status] : $status;
            }

            $tzRows .= '<tr>'
            . '<td class="tz-mono">' . (int) $data['id'] . '</td>'
            . '<td><a href="clientssummary.php?userid=' . (int) $data['user'] . '">' . htmlspecialchars((string) $data['firstname'] . ' ' . (string) $data['lastname']) . '</a></td>'
            . '<td class="tz-mono">' . htmlspecialchars((string) $data['to']) . '</td>'
            . '<td><span class="tz-msgtext" title="' . htmlspecialchars((string) $data['text'], ENT_QUOTES) . '">' . htmlspecialchars((string) $data['text']) . '</span></td>'
            . '<td class="tz-mono">' . htmlspecialchars((string) $data['datetime']) . '</td>'
            . '<td><span class="tz-badge ' . $tzBadgeCls . '">' . htmlspecialchars((string) $tzStatusLabel) . '</span></td>'
            . '<td><a class="tz-iconbtn" href="addonmodules.php?module=twozero_sms&tab=messages&deletesms=' . (int) $data['id'] . '&page=' . $page . '&limit=' . $limit . '&order=' . $order . '" title="' . htmlspecialchars((string) $LANG['delete'], ENT_QUOTES) . '" onclick="return confirm(\'Delete this message record?\')"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0" /></svg></a></td></tr>';
        }
        /* Getting messages order by date desc */

        if ($tzRows === '') {
            echo '<div class="tz-empty"><div class="tz-empty-icon"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M8.625 12a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H8.25m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0H12m4.125 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm0 0h-.375M21 12c0 4.556-4.03 8.25-9 8.25a9.764 9.764 0 01-2.555-.337A5.972 5.972 0 015.41 20.97a5.969 5.969 0 01-.474-.065 4.48 4.48 0 00.978-2.025c.09-.457-.133-.901-.467-1.226C3.93 16.178 3 14.189 3 12c0-4.556 4.03-8.25 9-8.25s9 3.694 9 8.25z" /></svg></div><h3>No messages yet</h3><p>Messages sent through the gateway will appear here with their delivery status.</p></div>';
        } else {
            echo '<div class="tz-tablewrap"><table class="tz-table">'
            . '<thead><tr><th>#</th><th>' . $LANG['client'] . '</th><th>' . $LANG['gsmnumber'] . '</th><th>' . $LANG['message'] . '</th><th>' . $LANG['datetime'] . '</th><th>' . $LANG['status'] . '</th><th></th></tr></thead>'
            . '<tbody>' . $tzRows . '</tbody></table></div>';
        }
        if ($toplam > 0) {
            $list="";
            for($a=1;$a<=$sayfa;$a++)
            {
                $selected = ($page==$a) ? 'selected="selected"' : '';
                $list.="<option value='addonmodules.php?module=twozero_sms&tab=messages&page={$a}&limit={$limit}&order={$order}' {$selected}>{$a}</option>";
            }
            $tzPrev = ($page > 1) ? '<a class="tz-btn tz-btn-secondary" style="padding:7px 14px" href="addonmodules.php?module=twozero_sms&tab=messages&page=' . ($page - 1) . '&limit=' . $limit . '&order=' . $order . '">&larr; Prev</a>' : '';
            $tzNext = ($page < $sayfa) ? '<a class="tz-btn tz-btn-secondary" style="padding:7px 14px" href="addonmodules.php?module=twozero_sms&tab=messages&page=' . ($page + 1) . '&limit=' . $limit . '&order=' . $order . '">Next &rarr;</a>' : '';
            echo '<div class="tz-pagination"><span class="tz-pageinfo">Page ' . $page . ' of ' . max($sayfa, 1) . ' &middot; ' . (int) $toplam . ' messages</span>'
                . '<span style="display:inline-flex;gap:8px;align-items:center">' . $tzPrev
                . "<select class=\"tz-select\" onchange=\"this.options[this.selectedIndex].value && (window.location = this.options[this.selectedIndex].value);\">{$list}</select>"
                . $tzNext . '</span></div>';
        }

    }
    elseif($tab=="sendbulk")
    {
        $settings = $class->getSettings();

        $responseToShow = '';
        $tzSendOk = null;

        if(!empty($_POST['client'])){
            $userinf = explode("_",$_POST['client']);
            $userid = $userinf[0];
            $gsmnumber = $userinf[1];
            $country = $userinf[4];

            $replacefrom = array("{firstname}","{lastname}");
            $replaceto = array($userinf[2],$userinf[3]);
            $message = str_replace($replacefrom,$replaceto,$_POST['message']);

            ;

            $class->setCountryCode($class->getCodeBy($country));
            $class->setGsmnumber($gsmnumber);
            $class->setMessage($message);
            $class->setUserid($userid);

            $result = $class->send();
            if($result == false){
                $responseToShow =  $class->getErrors();
                $tzSendOk = false;
            }else{
                $responseToShow =  $LANG['smssent'].' '.htmlspecialchars($gsmnumber);
                $tzSendOk = true;
            }

            if(!empty($_POST["debug"]) && $_POST["debug"] == "ON"){
                $debug = 1;
            }
        }

        $userSql = "SELECT `a`.`id`,`a`.`firstname`, `a`.`lastname`, `a`.`country`, `a`.`phonenumber` as `gsmnumber`
        FROM `tblclients` as `a` order by `a`.`firstname`";

        $clients = '';
        $result = mysql_query($userSql);
        while ($data = mysql_fetch_array($result)) {
            $tzOptValue = $data['id'].'_'.$data['gsmnumber'].'_'.$data['firstname'].'_'.$data['lastname'].'_'.$data['country'];
            $clients .= '<option value="' . htmlspecialchars($tzOptValue, ENT_QUOTES) . '">' . htmlspecialchars($data['firstname'] . ' ' . $data['lastname'] . ' (#' . $data['id'] . ')') . '</option>';
        }

        echo '
        <script>
        jQuery.fn.filterByText = function(textbox, selectSingleMatch) {
          return this.each(function() {
            var select = this;
            var options = [];
            $(select).find("option").each(function() {
              options.push({value: $(this).val(), text: $(this).text()});
            });
            $(select).data("options", options);
            $(textbox).bind("change keyup", function() {
              var options = $(select).empty().scrollTop(0).data("options");
              var search = $.trim($(this).val());
              var regex = new RegExp(search,"gi");

              $.each(options, function(i) {
                var option = options[i];
                if(option.text.match(regex) !== null) {
                  $(select).append(
                     $("<option>").text(option.text).val(option.value)
                  );
                }
              });
              if (selectSingleMatch === true &&
                  $(select).children().length === 1) {
                $(select).children().get(0).selected = true;
              }
            });
          });
        };
        $(function() {
          $("#clientdrop").filterByText($("#textbox"), true);
        });
        </script>';




        echo '<div class="tz-pagehead"><h2>' . $LANG['sendsms'] . '</h2><p>Send a one-off message to a single client. Use <span class="tz-chip">{firstname}</span> and <span class="tz-chip">{lastname}</span> to personalize the text.</p></div>';
        if ($tzSendOk === true) {
            echo '<div class="tz-alert tz-alert-success"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" /></svg><span>' . $responseToShow . '</span></div>';
        } elseif ($tzSendOk === false) {
            echo '<div class="tz-alert tz-alert-error"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" /></svg><div>' . $responseToShow . '</div></div>';
        }

        echo '<form action="" method="post">
        <input type="hidden" name="action" value="save" />
        <div class="tz-card tz-narrow">
            <h3>Compose message</h3>
            <p class="tz-sub">Your configured signature is appended automatically on send.</p>
            <div class="tz-field">
                <label for="textbox">' . $LANG['client'] . '</label>
                <input id="textbox" class="tz-input" type="text" placeholder="Type to filter clients..." autocomplete="off">
                <select name="client" class="tz-select" multiple size="8" id="clientdrop" style="margin-top:8px;min-width:0;width:100%;max-width:560px">
                    <option value="">' . $LANG['selectclient'] . '</option>
                    ' . $clients . '
                </select>
            </div>
            <div class="tz-field">
                <label for="tz-sendmsg">' . $LANG['message'] . '</label>
                <textarea class="tz-textarea" id="tz-sendmsg" rows="5" name="message"></textarea>
                <div class="tz-counter" id="tz-sendcounter">0 characters</div>
            </div>
            <div class="tz-field">
                <label class="tz-switch"><input type="checkbox" name="debug" value="ON" ' . ((isset($debug)) ? "checked" : "") . '><span class="tz-slider"></span>' . $LANG['debug'] . '</label>
            </div>
            ' . ((isset($debug)) ? '<details class="tz-debug" open><summary>Debug log</summary><div class="tz-debug-body">' . $class->getLogs() . '</div></details>' : '') . '
            <div class="tz-form-actions">
                <button type="submit" class="tz-btn tz-btn-primary"><svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 12L3.269 3.126A59.768 59.768 0 0121.485 12 59.77 59.77 0 013.27 20.876L5.999 12zm0 0h7.5" /></svg>' . $LANG['send'] . '</button>
            </div>
        </div>
        </form>
        <script>
        (function(){
            var ta = document.getElementById("tz-sendmsg");
            var out = document.getElementById("tz-sendcounter");
            if (!ta || !out) return;
            function update(){
                var len = ta.value.length;
                var seg = len === 0 ? 0 : Math.ceil(len / 160);
                out.textContent = len + " character" + (len === 1 ? "" : "s") + (seg > 0 ? " \u2022 " + seg + " SMS segment" + (seg === 1 ? "" : "s") : "");
            }
            ta.addEventListener("input", update);
            update();
        })();
        </script>';


    }

    // Reuse the balance already fetched on the settings tab to avoid a second API call.
    $credit = isset($balance) ? $balance : $class->getBalance();
    $tzFooterCredits = '';
    if (is_array($credit) && !empty($credit['success']) && isset($credit['credits'])) {
        $tzFooterCredits = '<span class="tz-footer-credits"><span class="tz-badge tz-badge-blue">' . $LANG['credit'] . ': ' . number_format((int) $credit['credits']) . '</span></span>';
    }
    echo '<div class="tz-footer">' . $tzFooterCredits
        . '<span>' . $LANG['lisans'] . ' &middot; <a href="https://sms.twozero.io/api-docs" target="_blank" rel="noopener">API docs</a></span></div>';
    echo '</div>';
}