<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Installer {
	public static function register() {
		add_action('admin_post_ses_product_action', array(__CLASS__, 'handle'));
	}

	public static function handle() {
		if (!current_user_can('install_plugins')) { wp_die(esc_html__('Permessi insufficienti.', 'smart-ecommerce-store'), 403); }
		$slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
		$action = sanitize_key(wp_unslash($_POST['product_action'] ?? ''));
		check_admin_referer('ses_product_' . $slug);

		$catalog = SES_Catalog::get(true);
		if (is_wp_error($catalog) || empty($catalog['products'][$slug])) {
			self::redirect('error', is_wp_error($catalog) ? $catalog->get_error_message() : __('Prodotto non disponibile.', 'smart-ecommerce-store'));
		}
		$product = $catalog['products'][$slug];
		$result = 'activate' === $action ? self::activate($product) : self::install_from_wordpress_org($product);
		SES_Audit::write('product_' . $action, array('slug' => $slug, 'result' => is_wp_error($result) ? $result->get_error_code() : 'success'));
		self::redirect(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : __('Operazione completata.', 'smart-ecommerce-store'));
	}

	private static function install_from_wordpress_org(array $product) {
		if ('wordpress_org' !== $product['channel']) {
			return new WP_Error('ses_not_wporg', __('Questo prodotto non è installabile da WordPress.org.', 'smart-ecommerce-store'));
		}
		require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$api = plugins_api('plugin_information', array('slug' => $product['wordpress_org_slug'], 'fields' => array('sections' => false)));
		if (is_wp_error($api) || empty($api->download_link)) {
			return new WP_Error('ses_wporg_unavailable', __('Il plugin non è ancora disponibile su WordPress.org.', 'smart-ecommerce-store'));
		}
		$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
		$result = $upgrader->install($api->download_link);
		wp_clean_plugins_cache(true);
		return true === $result ? true : (is_wp_error($result) ? $result : new WP_Error('ses_install_failed', __('Installazione non completata.', 'smart-ecommerce-store')));
	}

	private static function activate(array $product) {
		$products = SES_Products::enrich(array('products' => array($product['slug'] => $product)));
		$file = (string) ($products[$product['slug']]['plugin_file'] ?? '');
		if (!$file) { return new WP_Error('ses_not_installed', __('Installa prima il plugin.', 'smart-ecommerce-store')); }
		return activate_plugin($file, '', is_multisite() && is_network_admin(), false);
	}

	private static function redirect($status, $message) {
		wp_safe_redirect(add_query_arg(array(
			'page' => 'smart-ecommerce-store',
			'ses_status' => sanitize_key($status),
			'ses_message' => sanitize_text_field($message),
		), admin_url('admin.php')));
		exit;
	}
}

