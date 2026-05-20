<?php
/**
 * Plugin Name: Blue Dog Website Manager
 * Plugin URI:  https://github.com/bluedognz/website-manager
 * Description: A collection of utility modules for managing common WordPress site settings — enable or disable features on a per-site basis.
 * Version:     1.0.12
 * Author:      Blue Dog Digital
 * Author URI:  http://www.bluedogdigitalmarketing.com
 * License:     GPL2
 * Text Domain: website-manager
 */

if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WEBSITE_MANAGER_VERSION', '1.0.12' );
define( 'WEBSITE_MANAGER_FILE',    __FILE__ );
define( 'WEBSITE_MANAGER_DIR',     plugin_dir_path( __FILE__ ) );
define( 'WEBSITE_MANAGER_URL',     plugin_dir_url( __FILE__ ) );

require_once WEBSITE_MANAGER_DIR . 'classes/class-website-manager.php';

// ── GitHub auto-updates via Plugin Update Checker ────────────
require_once WEBSITE_MANAGER_DIR . 'plugin-update-checker/plugin-update-checker.php';

use YahnisElsts\PluginUpdateChecker\v5\PucFactory;

$wm_updater = PucFactory::buildUpdateChecker(
    'https://github.com/bluedognz/website-manager',
    __FILE__,
    'website-manager'
);

// Optional: define in wp-config.php to avoid GitHub rate limits
//   define( 'WEBSITE_MANAGER_GH_TOKEN', 'ghp_yourtoken' );
if ( defined( 'WEBSITE_MANAGER_GH_TOKEN' ) && WEBSITE_MANAGER_GH_TOKEN ) {
    $wm_updater->setAuthentication( WEBSITE_MANAGER_GH_TOKEN );
}

Website_Manager::get_instance()->init();
