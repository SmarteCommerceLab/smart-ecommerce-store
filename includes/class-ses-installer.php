<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Installer {
	const PENDING_RESET_OPTION = 'ses_pending_freemius_reset';

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
		} elseif ('reset_freemius' === $action) {
			$result = self::reset_freemius($product);
		} elseif ('premium_install' === $action) {
			$result = self::install_premium($product, sanitize_text_field(wp_unslash($_POST['license_key'] ?? '')));
		} elseif ('repository' === ($product['channel'] ?? '')) {
			$result = self::install_from_repository($product, 'update' === $action);
		} else {
			$result = self::install_from_wordpress_org($product);
		}
		SES_Audit::write('product_' . $action, array('slug' => $slug, 'result' => is_wp_error($result) ? $result->get_error_code() : 'success'));
		$success_message = 'premium_install' === $action
			? __('Pacchetto Premium installato. Attiva ora il plugin e inserisci la licenza nella schermata Freemius del prodotto.', 'smart-ecommerce-store')
			: __('Operazione completata.', 'smart-ecommerce-store');
		self::redirect(is_wp_error($result) ? 'error' : 'success', is_wp_error($result) ? $result->get_error_message() : $success_message);
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

	private static function install_from_repository(array $product, $overwrite = false) {
		$download_url = esc_url_raw((string) ($product['download_url'] ?? ''));
		$expected_sha256 = strtolower(sanitize_text_field((string) ($product['sha256'] ?? '')));
		if (
			'https' !== strtolower((string) wp_parse_url($download_url, PHP_URL_SCHEME))
			|| 'repository.smartecommerce.it' !== strtolower((string) wp_parse_url($download_url, PHP_URL_HOST))
			|| !preg_match('/^[a-f0-9]{64}$/', $expected_sha256)
		) {
			return new WP_Error('ses_repository_package_invalid', __('Il pacchetto del repository non è attendibile.', 'smart-ecommerce-store'));
		}
		$tmp = wp_tempnam($product['slug'] . '.zip');
		if (!$tmp) { return new WP_Error('ses_temp_failed', __('Impossibile preparare il download.', 'smart-ecommerce-store')); }
		$response = wp_remote_get($download_url, array('timeout' => 180, 'sslverify' => true, 'stream' => true, 'filename' => $tmp));
		if (is_wp_error($response)) { @unlink($tmp); return $response; }
		if (200 !== (int) wp_remote_retrieve_response_code($response)) {
			@unlink($tmp);
			return new WP_Error('ses_repository_download_failed', __('Il pacchetto non è disponibile nel repository.', 'smart-ecommerce-store'));
		}
		$actual_sha256 = hash_file('sha256', $tmp);
		if (!is_string($actual_sha256) || !hash_equals($expected_sha256, strtolower($actual_sha256))) {
			@unlink($tmp);
			return new WP_Error('ses_repository_checksum_failed', __('La verifica di integrità del pacchetto non è riuscita.', 'smart-ecommerce-store'));
		}
		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$is_theme = 'theme' === ($product['type'] ?? 'plugin');
		$upgrader = $is_theme ? new Theme_Upgrader(new Automatic_Upgrader_Skin()) : new Plugin_Upgrader(new Automatic_Upgrader_Skin());
		$result = $upgrader->install($tmp, array('overwrite_package' => $overwrite));
		@unlink($tmp);
		if ($is_theme) { wp_clean_themes_cache(true); } else { wp_clean_plugins_cache(true); }
		return true === $result ? true : (is_wp_error($result) ? $result : new WP_Error('ses_install_failed', __('Installazione non completata.', 'smart-ecommerce-store')));
	}

	private static function activate(array $product) {
		$products = SES_Products::enrich(array('products' => array($product['slug'] => $product)));
		$is_theme = 'theme' === ($product['type'] ?? 'plugin');
		$file = (string) ($products[$product['slug']][$is_theme ? 'theme_stylesheet' : 'plugin_file'] ?? '');
		if (!$file) { return new WP_Error('ses_not_installed', __('Installa prima il plugin.', 'smart-ecommerce-store')); }
		if ($is_theme) { switch_theme($file); return true; }
		$result = activate_plugin($file, '', is_multisite() && is_network_admin(), false);
		if (!is_wp_error($result) && self::has_pending_reset($product['slug'])) {
			$reset = self::reset_freemius($product);
			if (is_wp_error($reset)) { return $reset; }
		}
		return $result;
	}

	private static function reset_freemius(array $product) {
		if ('freemius' !== ($product['channel'] ?? '') || !current_user_can('activate_plugins')) {
			return new WP_Error('ses_freemius_reset_denied', __('Il collegamento Freemius non può essere ripristinato.', 'smart-ecommerce-store'));
		}
		$product_id = self::freemius_product_id($product);
		if (!$product_id || !function_exists('freemius')) {
			return new WP_Error('ses_freemius_runtime_missing', __('Attiva prima il plugin Premium, quindi riprova.', 'smart-ecommerce-store'));
		}
		$instance = freemius($product_id);
		if (
			!is_object($instance)
			|| !is_callable(array($instance, 'get_id'))
			|| (int) $instance->get_id() !== $product_id
			|| !is_callable(array($instance, 'delete_account_event'))
		) {
			return new WP_Error('ses_freemius_identity_invalid', __('Il runtime Freemius del prodotto non corrisponde al catalogo.', 'smart-ecommerce-store'));
		}
		$instance->delete_account_event(false);
		self::clear_pending_reset($product['slug']);
		SES_Audit::write('freemius_connection_reset', array('slug' => $product['slug'], 'product_id' => $product_id));
		return true;
	}

	private static function freemius_product_id(array $product) {
		if ('checkout.freemius.com' !== strtolower((string) wp_parse_url($product['checkout_url'] ?? '', PHP_URL_HOST))) { return 0; }
		$path = (string) wp_parse_url($product['checkout_url'], PHP_URL_PATH);
		return preg_match('#/plugin/(\d+)(?:/|$)#', $path, $match) ? (int) $match[1] : 0;
	}

	private static function has_pending_reset($slug) {
		$pending = get_option(self::PENDING_RESET_OPTION, array());
		return is_array($pending) && !empty($pending[$slug]);
	}

	private static function mark_pending_reset($slug) {
		$pending = get_option(self::PENDING_RESET_OPTION, array());
		$pending = is_array($pending) ? $pending : array();
		$pending[$slug] = time();
		update_option(self::PENDING_RESET_OPTION, $pending, false);
	}

	private static function clear_pending_reset($slug) {
		$pending = get_option(self::PENDING_RESET_OPTION, array());
		if (!is_array($pending) || !isset($pending[$slug])) { return; }
		unset($pending[$slug]);
		$pending ? update_option(self::PENDING_RESET_OPTION, $pending, false) : delete_option(self::PENDING_RESET_OPTION);
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

		$response = wp_remote_post(SES_PREMIUM_INSTALL_URL, array(
			'timeout' => 30,
			'sslverify' => true,
			'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
			'body' => wp_json_encode(array(
				'product_slug' => $product['slug'],
				'license_key' => $license_key,
				'site_url' => home_url('/'),
				'site_uid' => $uid,
			)),
		));
		if (is_wp_error($response)) { return $response; }
		$status = (int) wp_remote_retrieve_response_code($response);
		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		if (200 !== $status || !is_array($data)) {
			$message = is_array($data) && !empty($data['message']) ? sanitize_text_field($data['message']) : __('Licenza non autorizzata o pacchetto non disponibile.', 'smart-ecommerce-store');
			return new WP_Error('ses_premium_denied', $message);
		}

		$download_url = esc_url_raw((string) ($data['download_url'] ?? ''));
		$entitlement = sanitize_text_field((string) ($data['entitlement'] ?? ''));
		if (strlen($entitlement) < 32 || strlen($entitlement) > 4096) {
			return new WP_Error('ses_entitlement_invalid', __('Il servizio non ha restituito una ricevuta di licenza valida.', 'smart-ecommerce-store'));
		}
		$download_scheme = strtolower((string) wp_parse_url($download_url, PHP_URL_SCHEME));
		$download_host = strtolower((string) wp_parse_url($download_url, PHP_URL_HOST));
		$trusted_download_hosts = array('api.freemius.com', 'fast-api.freemius.com');
		if (!wp_http_validate_url($download_url) || 'https' !== $download_scheme || !in_array($download_host, $trusted_download_hosts, true)) {
			return new WP_Error('ses_package_url_invalid', __('Freemius non ha restituito un download attendibile.', 'smart-ecommerce-store'));
		}

		$tmp = wp_tempnam($product['slug'] . '.zip');
		if (!$tmp) { return new WP_Error('ses_temp_failed', __('Impossibile preparare il download Premium.', 'smart-ecommerce-store')); }
		$package = wp_remote_get($download_url, array(
			'timeout' => 180,
			'sslverify' => true,
			'stream' => true,
			'filename' => $tmp,
		));
		if (is_wp_error($package)) { @unlink($tmp); return $package; }
		$package_type = strtolower((string) wp_remote_retrieve_header($package, 'content-type'));
		if (200 !== (int) wp_remote_retrieve_response_code($package) || (false === strpos($package_type, 'zip') && false === strpos($package_type, 'octet-stream'))) {
			@unlink($tmp);
			return new WP_Error('ses_package_invalid', __('Il pacchetto Premium ricevuto non è valido.', 'smart-ecommerce-store'));
		}

		require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
		$result = $upgrader->install($tmp);
		@unlink($tmp);
		wp_clean_plugins_cache(true);
		if (true === $result) {
			SES_Licenses::store_entitlement($product['slug'], $entitlement);
			self::mark_pending_reset($product['slug']);
		}
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
