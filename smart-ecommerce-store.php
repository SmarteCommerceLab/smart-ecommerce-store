<?php
/**
 * Plugin Name: Smart eCommerce Store
 * Plugin URI: https://smartecommerce.it/smart-ecommerce-store/
 * Description: Catalogo pubblico di plugin e temi Smart eCommerce distribuiti dal repository, WordPress.org e Freemius.
 * Version: 0.3.0
 * Requires at least: 6.5
 * Requires PHP: 7.4
 * Author: Smart eCommerce
 * Author URI: https://smartecommerce.it/
 * Text Domain: smart-ecommerce-store
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if (!defined('ABSPATH')) { exit; }

define('SES_VERSION', '0.3.0');
define('SES_FILE', __FILE__);
define('SES_DIR', plugin_dir_path(__FILE__));
define('SES_URL', plugin_dir_url(__FILE__));
define('SES_CATALOG_URL', 'https://repository.smartecommerce.it/updates/index.json');
define('SES_PREMIUM_INSTALL_URL', 'https://repository.smartecommerce.it/api/v1/premium-packages/install');
define('SES_PRODUCT_NAME', 'Smart eCommerce Store');
define('SES_PRODUCT_VERSION', SES_VERSION);
define('SES_PRODUCT_TEXT_DOMAIN', 'smart-ecommerce-store');
define('SES_PRODUCT_DIR_URL', SES_URL);

require_once SES_DIR . 'includes/class-ses-audit.php';
require_once SES_DIR . 'includes/class-ses-catalog.php';
require_once SES_DIR . 'includes/class-ses-products.php';
require_once SES_DIR . 'includes/class-ses-installer.php';
require_once SES_DIR . 'includes/class-ses-admin.php';

function ses_boot() {
	SES_Admin::register();
	SES_Installer::register();
}
add_action('plugins_loaded', 'ses_boot');

register_activation_hook(__FILE__, static function () {
	if (current_user_can('activate_plugins')) {
		SES_Audit::write('plugin_activated', array('version' => SES_VERSION));
	}
});
