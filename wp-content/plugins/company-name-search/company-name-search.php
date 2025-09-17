<?php
/**
 * Plugin Name:       Company Name Availability Search
 * Plugin URI:        https://example.com/company-name-search
 * Description:       Provides a front-end search experience to help users determine if a company name appears to be available.
 * Version:           1.0.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            OpenAI Assistant
 * License:           GPL-2.0-or-later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       company-name-search
 * Domain Path:       /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
exit;
}

define( 'COMPANY_NAME_SEARCH_VERSION', '1.0.0' );
define( 'COMPANY_NAME_SEARCH_PLUGIN_FILE', __FILE__ );
define( 'COMPANY_NAME_SEARCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'COMPANY_NAME_SEARCH_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once COMPANY_NAME_SEARCH_PLUGIN_DIR . 'includes/class-company-name-search-plugin.php';
require_once COMPANY_NAME_SEARCH_PLUGIN_DIR . 'includes/class-company-name-search-api.php';
require_once COMPANY_NAME_SEARCH_PLUGIN_DIR . 'includes/class-company-name-search-rest.php';

/**
 * Returns the main plugin instance.
 *
 * @return Company_Name_Search_Plugin
 */
function company_name_search_plugin() {
return Company_Name_Search_Plugin::instance();
}

company_name_search_plugin();

register_activation_hook( __FILE__, array( 'Company_Name_Search_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Company_Name_Search_Plugin', 'deactivate' ) );
