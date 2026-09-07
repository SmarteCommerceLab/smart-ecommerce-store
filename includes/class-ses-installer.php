<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Installer {
	public static function register() {
		add_action('admin_post_ses_product_action', array(__CLASS__, 'handle'));
	}

	public static function handle() {
		$slug = sanitize_key(wp_unslash($_POST['slug'] ?? ''));
		$action = sanitize_key(wp_unslash($_POST['product_action'] ?? ''));
		check_admin_referer('ses_product_' . $slug);

		$catalog = SES_Catalog::get(true);
		if (is_wp_error($catalog) || empty($catalog['products'][$slug])) {
			self::redirect('error', is_wp_error($catalog) ? $catalog->get_error_message() : __('Prodotto non disponibile.', 'smart-ecommerce-store'));
		}
		$product = $catalog['products'][$slug];
		$is_theme = 'theme' === ($product['type'] ?? 'plugin');
		$capability = 'activate' === $action ? ($is_theme ? 'switch_themes' : 'activate_plugins') : ($is_theme ? ('update' === $action ? 'update_themes' : 'install_themes') : ('update' === $action ? 'update_plugins' : 'install_plugins'));
		if (!current_user_can($capability)) { wp_die(esc_html__('Permessi insufficienti.', 'smart-ecommerce-store'), 403); }
		if ('activate' === $action) {
			$result = self::activate($product);
		} elseif ('premium_install' === $action) {
			$result = self::install_premium($product, sanitize_text_field(wp_unslash($_POST['license_key'] ?? '')));
		} elseif ('repository' === $product['channel']) {
			$result = self::install_from_repository($product, 'update' === $action);
		} else {
			$result = self::install_from_wordpress_org($product);
		}
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
		$is_theme = 'theme' === ($product['type'] ?? 'plugin');
		$file = (string) ($products[$product['slug']][$is_theme ? 'theme_stylesheet' : 'plugin_file'] ?? '');
		if (!$file) { return new WP_Error('ses_not_installed', __('Installa prima il plugin.', 'smart-ecommerce-store')); }
		if ($is_theme) { switch_theme($file); return true; }
		return activate_plugin($file, '', is_multisite() && is_network_admin(), false);
	}

	private static function install_from_repository(array $product, $overwrite) {
		$url = (string) ($product['download_url'] ?? '');
		$sha256 = (string) ($product['sha256'] ?? '');
		if ('https' !== wp_parse_url($url, PHP_URL_SCHEME) || 'repository.smartecommerce.it' !== wp_parse_url($url, PHP_URL_HOST) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) {
			return new WP_Error('ses_repository_contract', __('Pacchetto repository non valido.', 'smart-ecommerce-store'));
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		$tmp = download_url($url, 60);
		if (is_wp_error($tmp)) { return $tmp; }
		$actual = strtolower((string) hash_file('sha256', $tmp));
		if (!hash_equals($sha256, $actual)) { wp_delete_file($tmp); return new WP_Error('ses_checksum', __('Checksum del pacchetto non valido.', 'smart-ecommerce-store')); }
		$is_theme = 'theme' === ($product['type'] ?? 'plugin');
		$upgrader = $is_theme ? new Theme_Upgrader(new Automatic_Upgrader_Skin()) : new Plugin_Upgrader(new Automatic_Upgrader_Skin());
		$result = $upgrader->install($tmp, array('overwrite_package' => $overwrite));
		wp_delete_file($tmp);
		if ($is_theme) { wp_clean_themes_cache(true); } else { wp_clean_plugins_cache(true); }
		return true === $result ? true : (is_wp_error($result) ? $result : new WP_Error('ses_install_failed', __('Installazione non completata.', 'smart-ecommerce-store')));
	}

	private static function install_premium(array $product, $license_key) {
		if ('freemius' !== $product['channel'] || 'commercial' !== $product['visibility']) {
			return new WP_Error('ses_not_premium', __('Questo prodotto non è installabile come Premium.', 'smart-ecommerce-store'));
		}
		if (strlen($license_key) < 20) {
			return new WP_Error('ses_license_missing', __('Inserisci una chiave di licenza Freemius valida.', 'smart-ecommerce-store'));
		}
		if (!wp_http_validate_url(SES_PREMIUM_INSTALL_URL) || 'repository.smartecommerce.it' !== wp_parse_url(SES_PREMIUM_INSTALL_URL, PHP_URL_HOST)) {
			return new WP_Error('ses_endpoint_invalid', __('Servizio Premium non attendibile.', 'smart-ecommerce-store'));
		}

		$uid = (string) get_option('ses_site_uid', '');
		if (!preg_match('/^[a-f0-9]{32}$/', $uid)) {
			$uid = str_replace('-', '', wp_generate_uuid4());
			update_option('ses_site_uid', $uid, false);
		}

		$tmp = wp_tempnam($product['slug'] . '.zip');
		if (!$tmp) { return new WP_Error('ses_temp_failed', __('Impossibile preparare il download Premium.', 'smart-ecommerce-store')); }
		$response = wp_remote_post(SES_PREMIUM_INSTALL_URL, array(
			'timeout' => 180,
			'sslverify' => true,
			'stream' => true,
			'filename' => $tmp,
			'headers' => array('Accept' => 'application/zip', 'Content-Type' => 'application/json'),
			'body' => wp_json_encode(array(
				'product_slug' => $product['slug'],
				'license_key' => $license_key,
				'site_url' => home_url('/'),
				'site_uid' => $uid,
			)),
		));
		if (is_wp_error($response)) { @unlink($tmp); return $response; }
		$status = (int) wp_remote_retrieve_response_code($response);
		$type = strtolower((string) wp_remote_retrieve_header($response, 'content-type'));
		if (200 !== $status || false === strpos($type, 'zip')) {
			$data = json_decode((string) file_get_contents($tmp), true);
			@unlink($tmp);
			$message = is_array($data) && !empty($data['message']) ? sanitize_text_field($data['message']) : __('Licenza non autorizzata o pacchetto non disponibile.', 'smart-ecommerce-store');
			return new WP_Error('ses_premium_denied', $message);
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
		$result = $upgrader->install($tmp);
		@unlink($tmp);
		wp_clean_plugins_cache(true);
		return true === $result ? true : (is_wp_error($result) ? $result : new WP_Error('ses_premium_install_failed', __('Installazione Premium non completata.', 'smart-ecommerce-store')));
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
