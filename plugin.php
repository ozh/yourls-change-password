<?php
/*
Plugin Name: Change Password
Plugin URI: http://yourls.org/
Description: Update your password from YOURLS admin
Version: 1.0
Author: Ozh
Author URI: http://ozh.org/
*/

// No direct call
if( !defined( 'YOURLS_ABSPATH' ) ) die();

// Register our plugin admin page
yourls_add_action('plugins_loaded', 'ozh_chgpwd_add_page');
function ozh_chgpwd_add_page() {
    yourls_register_plugin_page('chgpwd_page', 'Change Password', 'ozh_chgpwd_do_page');
    // parameters: page slug, page title, and function that will display the page itself
}

// Error and die
function ozh_chgpwd_die($message) {
    yourls_die( $message . '. <a href="javascript:window.location.href=window.location.href">' . yourls__('Retry ?') . '</a>', yourls__( 'Error' ), 403 );
}

// Display admin page
function ozh_chgpwd_do_page() {

    // Check if a form was submitted
    if( isset( $_POST['cur_pwd'] ) ) {
        // Check nonce
        yourls_verify_nonce( 'chgpwd' );

        // Check current password is correct
        if(false === yourls_check_password_hash(YOURLS_USER, $_POST['cur_pwd'])) {
            ozh_chgpwd_die(yourls__( 'Old password is invalid' ));
        }

        // New password can't be empty
        if($_POST['new_pwd1'] === '') {
            ozh_chgpwd_die(yourls__( 'You did not enter a password' ));
        }

        // Check same new password has been typed twice
        if($_POST['new_pwd1'] !== $_POST['new_pwd2']) {
            ozh_chgpwd_die(yourls__( 'New passwords do not match' ));
        }

        // Process form
        $update = ozh_chgpwd_update_password($_POST['new_pwd1']);
        if($update !== true) {
            ozh_chgpwd_die(yourls__('Fatal error :' . $update));
        } else {
            yourls_add_notice(yourls__('Password updated'));
        }
    }

    // Create nonce
    $nonce = yourls_create_nonce( 'chgpwd' );

    echo <<<HTML
        <h2>Change your password</h2>

        <p>Update your password from the web page rather than editing a config file. This makes it easier for instance to integrate YOURLS with your password manager. Don't bother remember secure passwords, let tools do it for you. I (Ozh) use <a href="https://lastpass.com/f?93404531">Lastpass</a> for me and my family, and I whole heartedly recommend it.</p>

        <form method="post">
        <input type="hidden" name="nonce" value="$nonce" />
        <p><input type="password" id="cur_pwd" name="cur_pwd" value="" placeholder="Current password" class="text" /></p>
        <p><input type="password" id="new_pwd1" name="new_pwd1" value="" placeholder="New password" class="text" /></p>
        <p><input type="password" id="new_pwd2" name="new_pwd2" value="" placeholder="Confirm new password" class="text" /></p>
        <p><input type="submit" value="Update value" class="button"/></p>
        </form>

HTML;
}


function ozh_chgpwd_update_password($new_pass) {
    $config_file = YOURLS_CONFIGFILE;
    $user = YOURLS_USER;

    if( !is_readable( $config_file ) )
        return 'cannot read file'; // not sure that can actually happen...

    if( !is_writable( $config_file ) )
        return 'cannot write file';

    // Include file to read value of $yourls_user_passwords
    // Temporary suppress error reporting to avoid notices about redeclared constants
    $errlevel = error_reporting();
    error_reporting( 0 );
    require $config_file;
    error_reporting( $errlevel );

    $configdata = file_get_contents( $config_file );
    if( $configdata == false )
        return 'could not read file';

    $quotes = "'" . '"';
    $pattern = "/[$quotes]${user}[$quotes]\s*=>.*+/";
    $replace = "'$user' => '$new_pass',  ";

    $count = 0;
    $configdata = preg_replace( $pattern, $replace, $configdata, -1, $count );

    if($count != 1) {
        return 'Could not find user in config file';
    }

    $success = file_put_contents($config_file, $configdata);
    if ( $success === FALSE ) {
        yourls_debug_log( 'Failed writing to ' . $config_file );
        return 'could not write file';
    }
    return true;
}

