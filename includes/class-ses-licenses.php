<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Licenses {
	const OPTION = 'ses_premium_entitlements';

	public static function store_entitlement($product_slug, $entitlement) {
		$product_slug = sanitize_key($product_slug);
		$entitlement = trim((string) $entitlement);
		if (!$product_slug || strlen($entitlement) < 32 || strlen($entitlement) > 4096) { return false; }
		$items = get_option(self::OPTION, array());
		if (!is_array($items)) { $items = array(); }
		$items[$product_slug] = $entitlement;
		delete_transient(self::cache_key($product_slug));
		return update_option(self::OPTION, $items, false);
	}

	public static function status($product_slug) {
		$product_slug = sanitize_key($product_slug);
		$cached = get_transient(self::cache_key($product_slug));
		if (is_array($cached)) { return $cached; }
		$items = get_option(self::OPTION, array());
		$entitlement = is_array($items) ? (string) ($items[$product_slug] ?? '') : '';
		if (!$entitlement) { return new WP_Error('ses_entitlement_missing', __('Ricollega la licenza per visualizzarne lo stato.', 'smart-ecommerce-store')); }
		if (!self::trusted_url(SES_PREMIUM_STATUS_URL, 'repository.smartecommerce.it')) {
			return new WP_Error('ses_status_endpoint_invalid', __('Servizio licenze non attendibile.', 'smart-ecommerce-store'));
		}
		$uid = (string) get_option('ses_site_uid', '');
		$response = wp_remote_post(SES_PREMIUM_STATUS_URL, array(
			'timeout' => 20,
			'sslverify' => true,
			'headers' => array('Accept' => 'application/json', 'Content-Type' => 'application/json'),
			'body' => wp_json_encode(array('entitlement' => $entitlement, 'site_url' => home_url('/'), 'site_uid' => $uid)),
		));
		if (is_wp_error($response)) { return $response; }
		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		if (200 !== (int) wp_remote_retrieve_response_code($response) || !is_array($data)) {
			$message = is_array($data) && !empty($data['message']) ? sanitize_text_field($data['message']) : __('Stato licenza temporaneamente non disponibile.', 'smart-ecommerce-store');
			return new WP_Error('ses_status_unavailable', $message);
		}
		$status = array(
			'status' => 'active' === ($data['status'] ?? '') ? 'active' : 'inactive',
			'expiration' => sanitize_text_field($data['expiration'] ?? ''),
			'site_url' => esc_url_raw($data['site_url'] ?? ''),
			'renew_url' => self::trusted_url($data['renew_url'] ?? '', 'checkout.freemius.com') ? esc_url_raw($data['renew_url']) : '',
			'portal_url' => self::trusted_url($data['portal_url'] ?? '', 'customers.freemius.com') ? esc_url_raw($data['portal_url']) : '',
		);
		set_transient(self::cache_key($product_slug), $status, 15 * MINUTE_IN_SECONDS);
		return $status;
	}

	private static function trusted_url($url, $host) {
		return wp_http_validate_url($url) && $host === strtolower((string) wp_parse_url($url, PHP_URL_HOST));
	}

	private static function cache_key($product_slug) {
		return 'ses_license_' . md5((string) $product_slug);
	}
}
