<?php
/**
 * Jamroom Installer
 * copyright 2003 - 2022 by The Jamroom Network - All Rights Reserved
 * http://www.jamroom.net
 *
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0.  Please see the included "license.html" file.
 *
 * Jamroom includes works that are not developed by The Jamroom Network
 * and are used under license - copies of all licenses are included and
 * can be found in the "contrib" directory within the module, as well
 * as within the "license.html" file.
 *
 * Jamroom may use modules and skins that are licensed by third party
 * developers, and licensed under a different license than the Jamroom
 * Core - please reference the individual module or skin license that
 * is included with your download.
 *
 * This software is provided "as is" and any express or implied
 * warranties, including, but not limited to, the implied warranties
 * of merchantability and fitness for a particular purpose are
 * disclaimed.  In no event shall the Jamroom Network be liable for
 * any direct, indirect, incidental, special, exemplary or
 * consequential damages (including but not limited to, procurement
 * of substitute goods or services; loss of use, data or profits;
 * or business interruption) however caused and on any theory of
 * liability, whether in contract, strict liability, or tort
 * (including negligence or otherwise) arising from the use of this
 * software, even if advised of the possibility of such damage.
 * Some jurisdictions may not allow disclaimers of implied warranties
 * and certain statements in the above disclaimer may not apply to
 * you as regards implied warranties; the other terms and conditions
 * remain enforceable notwithstanding. In some jurisdictions it is
 * not permitted to limit liability and therefore such limitations
 * may not apply to you.
 */

// Define our base dir
define('APP_DIR', dirname(__FILE__));
const IN_JAMROOM_INSTALLER = 1;
const MARKETPLACE_URL      = 'https://www.jamroom.net/networkmarket/create_user';

$skin = jrInstall_get_active_skin();
define('ACTIVE_SKIN', $skin);
const INSTALL_ID = 'open-source';

// Distribution Name
$dist = 'Jamroom';
if (strpos(' ' . $dist, '%%')) {
    define('DISTRIBUTION_NAME', 'Jamroom');
}
else {
    define('DISTRIBUTION_NAME', $dist);
}

// Typically no need to edit below here
date_default_timezone_set('UTC');
ini_set('session.auto_start', 0);
ini_set('session.use_trans_sid', 0);
ini_set('display_errors', 0);
error_reporting(E_ALL ^ (E_NOTICE | E_WARNING | E_DEPRECATED));
session_start();

// Bring in core functionality
$_conf = array();
require_once APP_DIR . "/modules/jrCore/include.php";

// Default permissions
$_conf['jrCore_dir_perms']  = 0755;
$_conf['jrCore_file_perms'] = 0644;

// Check for already being installed
if (is_file(APP_DIR . '/data/config/config.php')) {
    echo 'ERROR: Config file found - ' . DISTRIBUTION_NAME . ' already appears to be installed';
    exit;
}

// Check PHP version
$min = '7.2.0';
if (version_compare(phpversion(), $min) == -1) {
    echo "ERROR: " . DISTRIBUTION_NAME . " requires PHP {$min} or newer - you are currently running PHP version " . phpversion() . " - contact your hosting provider and see if they can upgrade your PHP install to a newer release";
    exit;
}

// Make sure we have session support
if (!function_exists('session_start')) {
    echo 'ERROR: PHP does not appear to have Session Support - ' . DISTRIBUTION_NAME . ' requires PHP Session Support in order to work. Please contact your system administrator and have Session Support activated in your PHP.';
    exit;
}

// Check for skin install
if (!is_file(APP_DIR . "/skins/jrElastic2/include.php")) {
    echo 'ERROR: default skin directory skins/jrElastic2 not found - check that all files have been uploaded';
    exit;
}

// Load modules
$premium = 0;
$_mods   = array('jrCore' => jrCore_meta());
$_urls   = array('core' => 'jrCore');
if (is_dir(APP_DIR . "/modules")) {
    if ($h = opendir(APP_DIR . "/modules")) {
        while (($file = readdir($h)) !== false) {
            if ($file == 'index.html' || $file == '.' || $file == '..' || $file == 'jrCore' || strpos($file, '-release-')) {
                continue;
            }
            if ((is_link($file) || is_dir(APP_DIR . "/modules/{$file}")) && is_file(APP_DIR . "/modules/{$file}/include.php")) {
                require_once APP_DIR . "/modules/{$file}/include.php";
            }
            $mfunc = "{$file}_meta";
            if (function_exists($mfunc)) {
                $_mods[$file] = $mfunc();
                $murl         = $_mods[$file]['url'];
                $_urls[$murl] = $file;
                if (isset($_mods[$file]['license']) && $_mods[$file]['license'] == 'jcl') {
                    $premium = 1;
                }
            }
        }
    }
    closedir($h);
}

// If we were NOT premium coming out of modules, check skins
if ($premium == 0 && is_dir(APP_DIR . '/skins')) {
    // Check skins...
    if ($h = opendir(APP_DIR . "/skins")) {
        while (($file = readdir($h)) !== false) {
            if ($file == '.' || $file == '..' || $file == 'jrElastic2' || $file == 'jrNewLucid') {
                continue;
            }
            if ((is_link($file) || (is_dir(APP_DIR . "/skins/{$file}") && !strpos($file, '-release-'))) && is_file(APP_DIR . "/skins/{$file}/include.php")) {
                require_once APP_DIR . "/skins/{$file}/include.php";
            }
            $mfunc = "{$file}_skin_meta";
            if (function_exists($mfunc)) {
                $_tmp = $mfunc();
                if (isset($_tmp['license']) && $_tmp['license'] == 'jcl') {
                    $premium = 1;
                    break;
                }
            }
        }
    }
    closedir($h);
}

// kick off installer
if (isset($_REQUEST['action']) && $_REQUEST['action'] == 'install') {
    jrInstall_install_system();
}
else {
    jrInstall_show_install_form();
}

/**
 * Show the install form
 */
function jrInstall_show_install_form()
{
    jrInstall_header();
    jrInstall_install_form();
    jrInstall_footer();
}

/**
 * Install Jamroom
 */
function jrInstall_install_system()
{
    ini_set('max_execution_time', 300);
    global $_conf, $_mods, $premium;
    // Setup session
    $_todo = array(
        'base_url' => 'System URL',
        'db_host'  => 'Database Host',
        'db_port'  => 'Database Port',
        'db_name'  => 'Database Name',
        'db_user'  => 'Database User',
        'db_pass'  => 'Database User Password',
        'email'    => 'Email Address'
    );
    foreach ($_todo as $k => $v) {
        if (isset($_REQUEST[$k]) && strlen($_REQUEST[$k]) > 0) {
            $_SESSION[$k] = $_REQUEST[$k];
        }
        else {
            if ($k != 'email') {
                if (php_sapi_name() === 'cli') {
                    echo "ERROR: invalid value for ' . $v . ' - please enter a valid value";
                    exit;
                }
                $_SESSION['install_error']   = 'You have entered an invalid value for ' . $v . ' - please enter a valid value';
                $_SESSION['install_hilight'] = $k;
                jrCore_location('install.php');
            }
        }
    }

    sleep(1);

    // Check that we can create our config
    $config = APP_DIR . "/data/config/config.php";
    if (!is_file($config)) {
        touch($config);
        if (!is_file($config)) {
            if (php_sapi_name() === 'cli') {
                echo "ERROR: data/config/config.php does not exist, and cannot be opened or created - please create the config.php file";
                exit;
            }
            $_SESSION['install_error'] = 'data/config/config.php does not exist, and cannot be opened or created - please create the config.php file';
            jrCore_location('install.php');
        }
        unlink($config);
    }

    // Make sure MySQLi support is in place
    if (!function_exists('mysqli_init')) {
        if (php_sapi_name() === 'cli') {
            echo "ERROR: unable to initialize MySQLi support - please check your PHP config for MySQLi support";
            exit;
        }
        $_SESSION['install_error'] = 'Unable to initialize MySQLi support - please check your PHP config for MySQLi support';
        jrCore_location('install.php');
    }

    // Get the base install URL - this way things are setup nicely for the marketplace
    $base_url = '/base_install_url=' . jrCore_url_encode_string(jrCore_get_detected_url());

    // Check for Premium install
    $system_id = false;
    if (strlen(INSTALL_ID) === 32) {

        $res = jrCore_install_load_url(MARKETPLACE_URL . '/install_id=' . INSTALL_ID . $base_url);
        if ($res && strpos($res, '{') === 0) {
            $_tm = json_decode($res, true);
            if (isset($_tm['user_system_id']) && strlen($_tm['user_system_id']) === 32) {
                $system_id         = $_tm['user_system_id'];
                $_REQUEST['email'] = $_tm['user_email'];
            }
        }

    }

    // Open Source install ?
    if (!$system_id) {

        if (isset($_REQUEST['email']) && strlen($_REQUEST['email']) > 0) {
            if (strpos($_REQUEST['email'], '@')) {

                // Will be passed in during Hosting install
                if (isset($_REQUEST['system_id']) && strlen($_REQUEST['system_id']) === 32) {
                    $system_id = trim($_REQUEST['system_id']);
                }
                else {
                    $res = jrCore_install_load_url(MARKETPLACE_URL . '/email=' . jrCore_url_encode_string($_REQUEST['email']) . "/license={$premium}{$base_url}");
                    if ($res && strpos(trim($res), '{') === 0) {
                        $_tm = json_decode(trim($res), true);
                        if (isset($_tm['user_system_id']) && strlen($_tm['user_system_id']) === 32) {
                            $system_id = $_tm['user_system_id'];
                        }
                        elseif (isset($_tm['code']) && $_tm['code'] == 400) {
                            if (!isset($_tm['text']) || $_tm['text'] != 'PREMIUM_EXISTS') {
                                $_SESSION['install_error']   = $_tm['note'];
                                $_SESSION['install_hilight'] = 'email';
                                jrCore_location('install.php');
                            }
                        }
                    }
                    // Fall through - marketplace email will not be created...
                }
            }
            else {
                // Bad Email
                $_SESSION['install_error']   = 'You have entered an invalid email address - please enter a valid email address';
                $_SESSION['install_hilight'] = 'email';
                jrCore_location('install.php');
            }
        }
        else {
            if ($premium == 1) {
                // We must get a valid email address for a Premium Install
                $_SESSION['install_error']   = 'Please enter a valid email address to start your ' . DISTRIBUTION_NAME . ' free trial';
                $_SESSION['install_hilight'] = 'email';
                jrCore_location('install.php');
            }
        }
    }

    $myi = mysqli_init();
    if (!$myi) {
        if (php_sapi_name() === 'cli') {
            echo "ERROR: unable to initialize MySQLi support - please check your PHP config for MySQLi support (2)";
            exit;
        }
        $_SESSION['install_error'] = 'Unable to initialize MySQLi support - please check your PHP config for MySQLi support';
        jrCore_location('install.php');
    }
    if (!mysqli_real_connect($myi, $_REQUEST['db_host'], $_REQUEST['db_user'], $_REQUEST['db_pass'], $_REQUEST['db_name'], $_REQUEST['db_port'], null, MYSQLI_CLIENT_FOUND_ROWS)) {
        // If it is still at "localhost", try "127.0.0.1"
        if ($_REQUEST['db_host'] == 'localhost') {
            $_REQUEST['db_host'] = '127.0.0.1';
        }
        if (!mysqli_real_connect($myi, $_REQUEST['db_host'], $_REQUEST['db_user'], $_REQUEST['db_pass'], $_REQUEST['db_name'], $_REQUEST['db_port'], null, MYSQLI_CLIENT_FOUND_ROWS)) {
            if (php_sapi_name() === 'cli') {
                echo "ERROR: unable to connect to the MySQL database using the credentials provided: " . mysqli_connect_error();
                exit;
            }
            $_SESSION['install_error'] = 'Unable to connect to the MySQL database using the credentials provided - please check:<br>MySQL error: ' . mysqli_connect_error();
            jrCore_location('install.php');
        }
    }

    // Create config file
    $data = "<?php\n\$_conf['jrCore_db_host'] = '" . $_REQUEST['db_host'] . "';\n\$_conf['jrCore_db_port'] = '" . intval($_REQUEST['db_port']) . "';\n\$_conf['jrCore_db_name'] = '" . jrCore_escape_single_quote_string($_REQUEST['db_name']) . "';\n\$_conf['jrCore_db_user'] = '" . jrCore_escape_single_quote_string($_REQUEST['db_user']) . "';\n\$_conf['jrCore_db_pass'] = '" . jrCore_escape_single_quote_string($_REQUEST['db_pass']) . "';\n\$_conf['jrCore_base_url'] = '" . $_REQUEST['base_url'] . "';\n";
    jrCore_write_to_file($config, $data);

    // Bring it in for install
    require_once $config;

    // Init Core first
    $_conf['jrCore_active_skin'] = 'jrElastic2';
    jrCore_init();
    foreach ($_mods as $mod_dir => $_inf) {
        if ($mod_dir != 'jrCore') {
            $ifunc = "{$mod_dir}_init";
            if (function_exists($ifunc)) {
                $ifunc();
            }
        }
    }

    // schema
    jrCore_validate_module_schema('jrCore');
    foreach ($_mods as $mod_dir => $_inf) {
        if ($mod_dir != 'jrCore') {
            jrCore_validate_module_schema($mod_dir);
        }
    }

    foreach ($_mods as $mod_dir => $_inf) {

        // config
        if (is_file(APP_DIR . "/modules/{$mod_dir}/config.php")) {
            require_once APP_DIR . "/modules/{$mod_dir}/config.php";
            $func = "{$mod_dir}_config";
            if (function_exists($func)) {
                $func();
            }
        }

        // quota
        if (is_file(APP_DIR . "/modules/{$mod_dir}/quota.php")) {
            $func = "{$mod_dir}_quota_config";
            if (!function_exists($func)) {
                require_once APP_DIR . "/modules/{$mod_dir}/quota.php";
            }
            if (function_exists($func)) {
                $func();
            }
        }

        // lang strings
        if (is_dir(APP_DIR . "/modules/{$mod_dir}/lang")) {
            jrUser_install_lang_strings('module', $mod_dir);
        }
    }

    // Create first profile quota
    $qid = jrProfile_create_quota('example quota');

    // Build modules
    $_feat = jrCore_get_registered_module_features('jrCore', 'quota_support');
    foreach ($_mods as $mod_dir => $_inf) {

        jrCore_verify_module($mod_dir);

        // Turn on Quota if this module has quota options
        if (isset($_feat[$mod_dir])) {
            jrProfile_set_quota_value($mod_dir, $qid, 'allowed', 'on');
        }
        $_mods[$mod_dir]['module_active'] = 1;

    }

    // Setup skins
    $_skns = jrCore_get_skins();
    if (isset($_skns) && is_array($_skns)) {
        foreach ($_skns as $sk) {
            if (is_file(APP_DIR . "/skins/{$sk}/include.php")) {
                require_once APP_DIR . "/skins/{$sk}/include.php";
                $func = "{$sk}_skin_init";
                if (function_exists($func)) {
                    $func();
                }
            }
        }
        foreach ($_skns as $sk) {
            if (is_file(APP_DIR . "/skins/{$sk}/config.php")) {
                require_once APP_DIR . "/skins/{$sk}/config.php";
                $func = "{$sk}_skin_config";
                if (function_exists($func)) {
                    $func();
                }
            }
        }
        foreach ($_skns as $sk) {
            // Install Language strings for Skin
            jrUser_install_lang_strings('skin', $sk);
        }
    }

    // Turn on Sign ups for the first quota
    jrProfile_set_quota_value('jrUser', 1, 'allow_signups', 'on');

    // Activate all modules....
    $tbl = jrCore_db_table_name('jrCore', 'module');
    $req = "UPDATE {$tbl} SET module_active = '1'";
    jrCore_db_query($req);

    // Now we need to full reload conf here since we only have core
    $tbl = jrCore_db_table_name('jrCore', 'setting');
    $req = "SELECT module AS m, name AS k, value AS v FROM {$tbl}";
    $_rt = jrCore_db_query($req, 'NUMERIC');

    // Make sure we got settings
    if (!$_rt || !is_array($_rt)) {
        jrCore_notice('Error', "unable to initialize settings - very installation");
    }
    foreach ($_rt as $_s) {
        $_conf["{$_s['m']}_{$_s['k']}"] = $_s['v'];
    }

    // Set default skin
    jrCore_set_setting_value('jrCore', 'active_skin', ACTIVE_SKIN);
    $_conf['jrCore_default_skin'] = ACTIVE_SKIN;

    // Set skin CSS and JS for our default skin
    jrCore_create_master_css(ACTIVE_SKIN);
    jrCore_create_master_javascript(ACTIVE_SKIN);

    // Reset users
    jrCore_db_truncate_datastore('jrUser');
    jrCore_db_truncate_datastore('jrProfile');

    // If the user entered a valid email address, setup their Marketplace if we can
    if ($system_id) {

        // Update Marketplace
        $tbl = jrCore_db_table_name('jrMarket', 'system');
        $req = "UPDATE {$tbl} SET system_email = '" . jrCore_db_escape($_REQUEST['email']) . "', system_code = '" . jrCore_db_escape($system_id) . "' WHERE system_id = '1'";
        jrCore_db_query($req);

        // Update Support Center
        jrCore_set_setting_value('jrSupport', 'support_email', $_REQUEST['email']);
    }

    jrCore_notice_page('success', '<br><b>' . DISTRIBUTION_NAME . ' has been successfully installed!</b><br><br>The first account created will be the Admin account.<br><br>', $_REQUEST['base_url'] . '/user/signup', 'Create Admin Account', false);
    session_destroy();
}

/**
 * HTML header
 */
function jrInstall_header()
{
    global $premium;

    echo '
    <!doctype html>
    <html lang="en" dir="ltr">
    <head>
    <title>' . DISTRIBUTION_NAME . ' Installer</title>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <script type="text/javascript" src="modules/jrCore/js/jquery-1.12.4.min.js"></script>
    <style>
    ';

    // Bring in style sheets
    $_css = glob(APP_DIR . '/skins/jrElastic2/css/*.css');
    foreach ($_css as $css_file) {
        if (!strpos($css_file, 'dark')) {
            // {$jrElastic2_img_url}
            $_rep = array(
                '{$jamroom_url}/'       => '',
                '{$jrElastic2_img_url}' => 'skins/jrElastic2/img'
            );
            echo str_replace(array_keys($_rep), $_rep, file_get_contents($css_file));
        }
    }

    // Check for install logo
    $logo = 'modules/jrCore/img/install_logo.png';
    if ($premium == 1) {
        $thank = DISTRIBUTION_NAME;
    }
    else {
        $thank = 'Jamroom Open Source';
    }

    echo '
    #wrapper {
        min-height: calc(100vh - 70px);
    }
    </style></head><body id="installer">
    <div id="header">
        <div id="header_content">
            <div class="container">
                <div class="row">
                    <div class="col4">
                        <div id="main_logo" style="padding:0">
                            <img src="' . $logo . '" width="280" height="55" alt="' . DISTRIBUTION_NAME . '" style="vertical-align:middle">
                        </div>
                    </div>
                    <div class="col8 last">
                        <div style="text-align:right;padding:22px 20px 0 0"><b>' . $thank . '</b></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="wrapper" style="padding-bottom:0"><div id="content">';
    return true;
}

/**
 * Show an install notice
 * @param string $type
 * @param string $text
 * @return bool
 */
function jrInstall_install_notice($type, $text)
{
    echo '<tr><td colspan="2" class="page_notice_drop"><div id="page_notice" class="page_notice ' . $type . '">' . $text . '</div></td></tr>';
    return true;
}

/**
 * Show the install form
 */
function jrInstall_install_form()
{
    global $premium, $_conf;
    $disabled = '';

    // Get license
    $license = '';
    if (is_file('modules/jrCore/license.html')) {
        $license = 'modules/jrCore/license.html';
    }
    elseif (is_file('modules/jrCore/root/mpl_license.html')) {
        $license = 'modules/jrCore/root/mpl_license.html';
    }
    echo '
    <div class="container">
      <div class="row">
        <div class="col12 last">
          <div style="padding:12px">
            <form id="install" method="post" action="install.php?action=install" accept-charset="utf-8" enctype="multipart/form-data">
            <table class="page_content">
              <tr>
                <td colspan="2" class="element page_note p20" style="font-size:16px">
                  Fill out the following system information and you will be up and running in 30 seconds<br><small>If you are not sure what settings to use, contact your hosting provider and they can assist you</small>
                </td>
              </tr>
              <tr><td>&nbsp;</td></tr>
              <tr>
                <td class="element_left form_input_left">
                  License
                </td>
                <td class="element_right form_input_right" style="height:160px">
                  <iframe src="' . $license . '?_v=' . time() . '" style="width:76%;height:160px;border:1px solid #7F7F7F;border-radius:3px;box-shadow: inset 0 0 2px #111;"></iframe>
                </td>
              </tr>';

    // Test to make sure our server is setup properly
    if (!is_dir(APP_DIR . '/data')) {
        jrInstall_install_notice('error', "&quot;data&quot; directory does not exist - create data directory and permission so web user can write to it");
        $disabled = ' disabled="disabled"';
    }
    // Check each dir
    $_dirs = array('cache', 'config', 'logs', 'media');
    $error = array();
    foreach ($_dirs as $dir) {
        $fdir = APP_DIR . "/data/{$dir}";
        if (!is_dir($fdir)) {
            mkdir($fdir, $_conf['jrCore_dir_perms']);
            if (!is_dir($fdir)) {
                $error[] = "data/{$dir}";
            }
        }
        elseif (!is_writable($fdir)) {
            chmod($fdir, $_conf['jrCore_dir_perms']);
            if (!is_writable($fdir)) {
                $error[] = "data/{$dir}";
            }
        }
    }
    if (isset($error) && is_array($error) && count($error) > 0) {
        jrInstall_install_notice('error', "The following directories are not writable:<br>" . implode('<br>', $error) . "<br>ensure they are permissioned so the web user can write to them");
        $disabled = ' disabled="disabled"';
    }

    // mod_rewrite check
    if (function_exists('apache_get_modules') && function_exists('php_sapi_name') && stristr(php_sapi_name(), 'apache')) {
        if (!in_array('mod_rewrite', apache_get_modules())) {
            jrInstall_install_notice('error', 'mod_rewrite does not appear to be enabled on your server - mod_rewrite is required for ' . DISTRIBUTION_NAME . ' to function.<br>Contact your hosting provider and ensure mod_rewrite is active in your account.');
        }
    }

    // Check for disabled functions
    $_funcs = array('system', 'json_encode', 'json_decode', 'ob_start', 'ob_end_clean', 'curl_init', 'curl_version', 'gd_info', 'mb_internal_encoding');
    $_flist = array();
    foreach ($_funcs as $rfunc) {
        if (!function_exists($rfunc)) {
            $_flist[] = $rfunc;
        }
    }
    if (count($_flist) > 0) {
        jrInstall_install_notice('error', "The following function(s) are not enabled in your PHP install:<br><br><b>" . implode('</b><br><b>', $_flist) . "</b><br><br>" . DISTRIBUTION_NAME . " will not function properly without these functions enabled so contact your hosting provider and make sure they are enabled.");
        $disabled = ' disabled="disabled"';
    }

    // Make sure .htaccess exists
    if (stristr($_SERVER['SERVER_SOFTWARE'], 'apache') && !is_file(APP_DIR . "/.htaccess")) {
        jrInstall_install_notice('error', "Unable to find the .htaccess file - please ensure the .htaccess from the " . DISTRIBUTION_NAME . " ZIP file is uploaded to your server.");
        $disabled = ' disabled="disabled"';
    }

    // Check for session errors
    if (isset($_SESSION['install_error'])) {
        jrInstall_install_notice('error', $_SESSION['install_error']);
        unset($_SESSION['install_error']);
    }

    if (empty($_SESSION['base_url'])) {
        $_SESSION['base_url'] = preg_replace('/\/$/', '', 'http://' . $_SERVER['SERVER_NAME'] . dirname($_SERVER['PHP_SELF']));
    }
    if (!isset($_SESSION['db_host'])) {
        $_SESSION['db_host'] = 'localhost';
    }
    if (!isset($_SESSION['db_port'])) {
        $_SESSION['db_port'] = '3306';
    }
    $port = '';
    if (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] != 80 && $_SERVER['SERVER_PORT'] != 443) {
        $port = ":{$_SERVER['SERVER_PORT']}";
    }

    jrInstall_text_field('text', 'System URL', 'base_url', $_SESSION['base_url'] . $port);
    jrInstall_text_field('text', 'Database Host', 'db_host', $_SESSION['db_host']);
    jrInstall_text_field('text', 'Database Port<br><span class="sublabel">(default: 3306)</span>', 'db_port', $_SESSION['db_port']);
    jrInstall_text_field('text', 'Database Name', 'db_name', $_SESSION['db_name']);
    jrInstall_text_field('text', 'Database User', 'db_user', $_SESSION['db_user']);
    jrInstall_text_field('password', 'Database User Password', 'db_pass', $_SESSION['db_pass']);

    if (strlen(INSTALL_ID) != 32) {
        if ($premium) {
            jrInstall_text_field('text', 'Email Address<br><span class="sublabel">Required for Premium Trial</span>', 'email', $_SESSION['email']);
        }
        else {
            jrInstall_text_field('text', 'Email Address<br><span class="sublabel">(optional) Creates Marketplace Account</span>', 'email', $_SESSION['email']);
        }
    }

    $refresh  = '';
    $disclass = '';
    if (strlen($disabled) > 0) {
        $disclass = ' form_button_disabled';
        $refresh  = '<input type="button" value="Check Again" class="form_button" onclick="location.reload();">';
    }
    echo '    <tr><td style="height:12px"></td></tr><tr>
                <td colspan="2" class="element form_submit_section">
                  <img id="form_submit_indicator" src="skins/jrElastic2/img/form_spinner.gif" width="24" height="24" alt="working...">' . $refresh . '
                  <input type="button" value="Install ' . DISTRIBUTION_NAME . '" class="form_button' . $disclass . '" ' . $disabled . ' onclick="if (confirm(\'Please be patient - the installion can take up to 30 seconds to run. Are you ready to install?\')){$(\'#form_submit_indicator\').show(300,function(){ $(\'#install\').submit(); });}">
                </td>
              </tr>  
            </table>
            </form>
          </div>
        </div>
      </div>
    </div>';
    return true;
}

/**
 * Show a text field in the install form
 * @param $type string
 * @param $label string
 * @param $name string
 * @param $value string
 * @return bool
 */
function jrInstall_text_field($type, $label, $name, $value = '')
{
    $cls = '';
    if (isset($_SESSION['install_hilight']) && $_SESSION['install_hilight'] == $name) {
        $cls = ' field-hilight';
        unset($_SESSION['install_hilight']);
    }
    echo '<tr><td class="element_left form_input_left">' . $label . '</td><td class="element_right form_input_right">';
    switch ($type) {
        case 'text':
            echo '<input type="text" name="' . $name . '" value="' . $value . '" class="form_text' . $cls . '"></td></tr>';
            break;
        case 'password':
            echo '<input type="password" name="' . $name . '" value="' . $value . '" class="form_text' . $cls . '"></td></tr>';
            break;
    }
    return true;
}

/**
 * HTML footer
 */
function jrInstall_footer()
{
    echo '</div></body></html>';
    return true;
}

/**
 * Get the skin we will use to install with
 * @return string
 */
function jrInstall_get_active_skin()
{
    $_fn = array();
    if (is_dir(APP_DIR . "/skins")) {
        if ($h = opendir(APP_DIR . "/skins")) {
            while (($file = readdir($h)) !== false) {
                if ($file == '.' || $file == '..') {
                    continue;
                }
                if (is_dir(APP_DIR . "/skins/{$file}") || is_link(APP_DIR . "/skins/{$file}")) {
                    switch ($file) {
                        case 'jrElastic2':
                        case 'jrNewLucid':
                            $_fn[$file] = 1;
                            break;
                        default:
                            // We found a different skin - use it
                            return $file;
                    }
                }
            }
        }
        closedir($h);
    }
    if (count($_fn) > 0 && isset($_fn['jrNewLucid'])) {
        return 'jrNewLucid';
    }
    return 'jrElastic2';
}

/**
 * Load Remote URL
 * @param string $url Url to load
 * @param array $_vars URI variables for URL
 * @return string Returns value of loaded URL, or false on failure
 */
function jrCore_install_load_url($url, $_vars = null)
{
    $port = 80;
    if (strpos($url, 'https:') === 0) {
        $port = 443;
    }
    $_opts = array(
        CURLOPT_POST           => false,
        CURLOPT_HEADER         => false,
        CURLOPT_USERAGENT      => 'Jamroom Installer',
        CURLOPT_URL            => $url,
        CURLOPT_PORT           => $port,
        CURLOPT_FRESH_CONNECT  => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FORBID_REUSE   => true,
        CURLOPT_CONNECTTIMEOUT => 30,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_VERBOSE        => false,
        CURLOPT_FAILONERROR    => false,
        CURLOPT_HTTPGET        => true
    );
    if (!stristr(' ' . PHP_OS, 'darwin')) {
        $_opts[CURLOPT_SSL_VERIFYHOST] = false;
        $_opts[CURLOPT_SSL_VERIFYPEER] = false;
    }
    if (is_array($_vars) && count($_vars) > 0) {
        if (strpos($url, '?')) {
            $_opts[CURLOPT_URL] = $url . '&' . http_build_query($_vars);
        }
        else {
            $_opts[CURLOPT_URL] = $url . '?' . http_build_query($_vars);
        }
    }
    elseif (!is_null($_vars) && strlen($_vars) > 0) {
        if (strpos($url, '?')) {
            $_opts[CURLOPT_URL] = $url . '&' . trim($_vars);
        }
        else {
            $_opts[CURLOPT_URL] = $url . '?' . trim($_vars);
        }
    }
    $ch = curl_init();
    if (curl_setopt_array($ch, $_opts)) {
        $res = curl_exec($ch);
        $err = curl_errno($ch);
        if (!isset($err) || $err === 0) {
            curl_close($ch);
            return $res;
        }
    }
    curl_close($ch);
    return false;
}

?>
