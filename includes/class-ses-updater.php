<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Updater {
	const CACHE_KEY = 'ses_repository_release_v2';
	const CACHE_TTL = 6 * HOUR_IN_SECONDS;

	public static function register() {
		add_filter('update_plugins_repository.smartecommerce.it', array(__CLASS__, 'repository_update'), 10, 4);
		add_filter('pre_set_site_transient_update_plugins', array(__CLASS__, 'updates'));
		add_filter('site_transient_update_plugins', array(__CLASS__, 'updates'));
		add_filter('plugins_api', array(__CLASS__, 'information'), 10, 3);
		add_filter('upgrader_pre_download', array(__CLASS__, 'verify_download'), 10, 4);
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
