<?php
/**
 * @package wp-sparkpost
 */
/*
Plugin Name: SparkPost
Plugin URI: http://sparkpost.com/
Description: Send all your email from Wordpress through SparkPost, the world's most advanced email delivery service.
Version: 1.2.0
Author: SparkPost
Author URI: http://sparkpost.com
License: GPLv2 or later
Text Domain: wpsp
*/

// If ABSPATH is defined, we assume WP is calling us.
// Otherwise, this could be an illicit direct request.
if (!defined('ABSPATH')) exit();

define('WPSP_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('WPSP_PLUGIN_PATH', WPSP_PLUGIN_DIR . basename(__FILE__));


require_once(WPSP_PLUGIN_DIR . 'sparkpost.class.php');
if (is_admin()) {
    require_once(WPSP_PLUGIN_DIR . 'widget.class.php');
    new SparkPostAdmin();
}
$sp = new SparkPost();

if ($sp->get_option('enable_sparkpost')) {

    if ($sp->get_option('sending_method') == 'smtp') {
        require_once(WPSP_PLUGIN_DIR . 'mailer.class.php');
        new SparkPostMailer();
    } else {
        require_once(WPSP_PLUGIN_DIR . 'sp_mailer.class.php');
        add_filter('wp_mail', function ($args) {
            global $phpmailer;
            if (!$phpmailer instanceof SparkPostMail) {
                $phpmailer = new SparkPostMail();
            }
            return $args;
        });
    }
}


