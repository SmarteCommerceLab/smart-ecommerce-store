<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Updater {
	const CACHE_KEY = 'ses_repository_release_v2';
	const CACHE_TTL = 15 * MINUTE_IN_SECONDS;

	public static function register() {
		add_filter('update_plugins_repository.smartecommerce.it', array(__CLASS__, 'repository_update'), 10, 4);
		add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'updates'));
		add_filter('site_transient_update_plugins', array(__CLASS__, 'updates'));
		add_filter('plugins_api', array(__CLASS__, 'information'), 10, 3);
		add_filter('upgrader_pre_download', array(__CLASS__, 'verify_download'), 10, 4);
		add_filter('plugin_action_links_' . plugin_basename(SES_FILE), array(__CLASS__, 'action_links'));
		add_filter('plugin_row_meta', array(__CLASS__, 'row_meta'), 10, 2);
		add_action('admin_post_ses_check_updates', array(__CLASS__, 'manual_check'));
		add_action('admin_notices', array(__CLASS__, 'manual_check_notice'));
		add_action('upgrader_process_complete', array(__CLASS__, 'upgrader_complete'), 10, 2);
	}

	public static function action_links($links) {
		$dashboard = '<a href="' . esc_url(admin_url('admin.php?page=smart-ecommerce-store')) . '">' . esc_html__('Dashboard', 'smart-ecommerce-store') . '</a>';
		$documentation = '<a href="' . esc_url(SES_PRODUCT_URL) . '" target="_blank" rel="noopener noreferrer">' . esc_html__('Documentazione', 'smart-ecommerce-store') . '</a>';
		$links = array_merge(array('ses_dashboard' => $dashboard, 'ses_documentation' => $documentation), $links);
		if (current_user_can('update_plugins')) {
			$url = wp_nonce_url(admin_url('admin-post.php?action=ses_check_updates'), 'ses_check_updates');
			$links['ses_check_updates'] = '<a href="' . esc_url($url) . '">' . esc_html__('Controlla aggiornamenti', 'smart-ecommerce-store') . '</a>';
		}
		return $links;
	}

	public static function row_meta($links, $file) {
		if (plugin_basename(SES_FILE) !== $file) { return $links; }
		$external = ' target="_blank" rel="noopener noreferrer"';
		$links[] = '<a href="' . esc_url(SES_PRODUCT_URL) . '"' . $external . '>' . esc_html__('Pagina del prodotto', 'smart-ecommerce-store') . '</a>';
		$links[] = '<a href="' . esc_url(SES_SUPPORT_URL) . '"' . $external . '>' . esc_html__('Supporto', 'smart-ecommerce-store') . '</a>';
		return $links;
	}

	public static function manual_check() {
		if (!current_user_can('update_plugins')) {
			wp_die(esc_html__('Non hai i permessi per controllare gli aggiornamenti.', 'smart-ecommerce-store'), 403);
		}
		check_admin_referer('ses_check_updates');
		self::clear_update_caches();
		wp_update_plugins();
		$updates = get_site_transient('update_plugins');
		$available = is_object($updates) && !empty($updates->response[plugin_basename(SES_FILE)]);
		$url = add_query_arg(array('ses_update_checked' => '1', 'ses_update_available' => $available ? '1' : '0'), self_admin_url('plugins.php'));
		wp_safe_redirect($url);
		exit;
	}

	public static function manual_check_notice() {
		if ('1' !== sanitize_key((string) wp_unslash($_GET['ses_update_checked'] ?? '')) || !current_user_can('update_plugins')) { return; }
		$available = '1' === sanitize_key((string) wp_unslash($_GET['ses_update_available'] ?? ''));
		$message = $available
			? __('È disponibile un aggiornamento verificato di Smart eCommerce Store.', 'smart-ecommerce-store')
			: __('Smart eCommerce Store è aggiornato all’ultima versione disponibile.', 'smart-ecommerce-store');
		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($message) . '</p></div>';
	}

	public static function upgrader_complete($upgrader, $options) {
		if ('update' !== ($options['action'] ?? '') || 'plugin' !== ($options['type'] ?? '')) { return; }
		$plugins = (array) ($options['plugins'] ?? array());
		if (!empty($options['plugin'])) { $plugins[] = $options['plugin']; }
		if (!in_array(plugin_basename(SES_FILE), array_map('plugin_basename', $plugins), true)) { return; }
		self::clear_update_caches();
	}

	public static function clear_update_caches() {
		delete_site_transient(self::CACHE_KEY);
		delete_site_transient('ses_repository_release_v1');
		delete_site_transient('update_plugins');
		wp_clean_plugins_cache(true);
	}

	public static function repository_update($update, $plugin_data, $plugin_file, $locales) {
		if (plugin_basename(SES_FILE) !== plugin_basename($plugin_file)) { return $update; }
		$release = self::release();
		if (!$release || !version_compare($release['version'], SES_VERSION, '>')) { return $update; }
		return array(
			'id' => SES_UPDATE_URL,
			'slug' => 'smart-ecommerce-store',
			'version' => $release['version'],
			'url' => $release['homepage'],
			'package' => $release['package'],
			'requires' => $release['requires'],
			'requires_php' => $release['requires_php'],
			'autoupdate' => false,
		);
	}

	private static function release() {
		$cached = get_site_transient(self::CACHE_KEY);
		if (is_array($cached)) { return $cached; }
		$response = wp_safe_remote_get(SES_UPDATE_URL, array('timeout' => 15, 'redirection' => 2, 'sslverify' => true, 'headers' => array('Accept' => 'application/json')));
		if (is_wp_error($response) || 200 !== (int) wp_remote_retrieve_response_code($response)) { return null; }
		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		if (!is_array($data)) { return null; }
		$package = esc_url_raw((string) ($data['download_url'] ?? $data['package'] ?? ''));
		$sha256 = strtolower(sanitize_text_field((string) ($data['sha256'] ?? '')));
		if (!self::trusted_url($package) || !preg_match('/^[a-f0-9]{64}$/', $sha256)) { return null; }
		$release = array(
			'name' => sanitize_text_field((string) ($data['name'] ?? 'Smart eCommerce Store')),
			'version' => sanitize_text_field((string) ($data['version'] ?? '')),
			'package' => $package, 'sha256' => $sha256,
			'homepage' => esc_url_raw((string) ($data['homepage'] ?? 'https://smartecommerce.it/smart-ecommerce-store/')),
			'requires' => sanitize_text_field((string) ($data['requires'] ?? '')),
			'requires_php' => sanitize_text_field((string) ($data['requires_php'] ?? '')),
			'description' => wp_kses_post((string) ($data['description'] ?? '')),
			'changelog' => wp_kses_post((string) ($data['changelog'] ?? '')),
		);
		if (!$release['version']) { return null; }
		set_site_transient(self::CACHE_KEY, $release, self::CACHE_TTL);
		return $release;
	}

	public static function updates($transient) {
		if (!is_object($transient)) { return $transient; }
		$release = self::release();
		if (!$release || !version_compare($release['version'], SES_VERSION, '>')) { return $transient; }
		$key = plugin_basename(SES_FILE);
		$transient->response[$key] = (object) array(
			'id' => 'smart-ecommerce-store', 'slug' => 'smart-ecommerce-store', 'plugin' => $key,
			'new_version' => $release['version'], 'url' => $release['homepage'], 'package' => $release['package'],
			'requires' => $release['requires'], 'requires_php' => $release['requires_php'],
		);
		return $transient;
	}

	public static function information($result, $action, $args) {
		if ('plugin_information' !== $action || empty($args->slug) || 'smart-ecommerce-store' !== $args->slug) { return $result; }
		$release = self::release();
		if (!$release) { return $result; }
		return (object) array(
			'name' => $release['name'], 'slug' => 'smart-ecommerce-store', 'version' => $release['version'],
			'homepage' => $release['homepage'], 'download_link' => $release['package'],
			'requires' => $release['requires'], 'requires_php' => $release['requires_php'],
			'sections' => array('description' => $release['description'], 'changelog' => $release['changelog']),
		);
	}

	public static function verify_download($reply, $package, $upgrader, $hook_extra) {
		if (false !== $reply) { return $reply; }
		$release = self::release();
		if (!$release || !hash_equals($release['package'], (string) $package)) { return $reply; }
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$file = download_url($package, 30);
		if (is_wp_error($file)) { return $file; }
		$actual = strtolower((string) hash_file('sha256', $file));
		if (!hash_equals($release['sha256'], $actual)) {
			wp_delete_file($file);
			return new WP_Error('ses_update_checksum', __('Checksum dell’aggiornamento non valido.', 'smart-ecommerce-store'));
		}
		SES_Audit::write('update_package_verified', array('version' => $release['version']));
		return $file;
	}

	private static function trusted_url($url) {
		return 'https' === strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))
			&& 'repository.smartecommerce.it' === strtolower((string) wp_parse_url($url, PHP_URL_HOST));
	}
}
