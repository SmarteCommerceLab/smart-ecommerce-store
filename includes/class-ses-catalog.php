<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Catalog {
	const CACHE_KEY = 'ses_public_catalog_v1';
	const FAILURE_KEY = 'ses_catalog_failure_v1';
	const CACHE_TTL = 900;

	public static function get($refresh = false) {
		if ($refresh) {
			delete_transient(self::CACHE_KEY);
			delete_transient(self::FAILURE_KEY);
		}
		$cached = get_transient(self::CACHE_KEY);
		if (is_array($cached)) { return $cached; }

		$url = (string) apply_filters('ses_catalog_url', SES_CATALOG_URL);
		if (!self::trusted_repository_url($url)) {
			return new WP_Error('ses_catalog_host', __('Catalogo non attendibile.', 'smart-ecommerce-store'));
		}
		$response = wp_safe_remote_get($url, array(
			'timeout' => 15,
			'redirection' => 2,
			'headers' => array('Accept' => 'application/json'),
		));
		if (is_wp_error($response)) { return self::fail('http', $response->get_error_message()); }
		if (200 !== (int) wp_remote_retrieve_response_code($response)) {
			return self::fail('status', __('Il catalogo non è disponibile.', 'smart-ecommerce-store'));
		}
		$data = json_decode((string) wp_remote_retrieve_body($response), true);
		if (!is_array($data)) { return self::fail('json', __('Il catalogo non contiene JSON valido.', 'smart-ecommerce-store')); }

		$verified = self::verify($data);
		if (is_wp_error($verified)) { return $verified; }
		$catalog = self::normalize($verified);
		set_transient(self::CACHE_KEY, $catalog, self::CACHE_TTL);
		delete_transient(self::FAILURE_KEY);
		SES_Audit::write('catalog_loaded', array('products' => count($catalog['products'])));
		return $catalog;
	}

	public static function verify(array $envelope) {
		if (empty($envelope['signed']) || !is_array($envelope['signed']) || empty($envelope['signature']) || empty($envelope['key_id'])) {
			return self::fail('unsigned', __('Il catalogo pubblico non è firmato.', 'smart-ecommerce-store'));
		}
		$key_id = sanitize_key((string) $envelope['key_id']);
		$keys = (array) apply_filters('ses_trusted_public_keys', self::public_keys());
		if (empty($keys[$key_id]) || !function_exists('openssl_verify')) {
			return self::fail('key', __('Impossibile verificare il catalogo pubblico.', 'smart-ecommerce-store'));
		}
		$signature = base64_decode((string) $envelope['signature'], true);
		$payload = self::canonical_json($envelope['signed']);
		if (false === $signature || 1 !== openssl_verify($payload, $signature, $keys[$key_id], OPENSSL_ALGO_SHA256)) {
			return self::fail('signature', __('La firma del catalogo pubblico non è valida.', 'smart-ecommerce-store'));
		}
		$expires = (string) ($envelope['signed']['expires'] ?? '');
		if (!$expires || strtotime($expires) <= time()) {
			return self::fail('expired', __('Il catalogo pubblico è scaduto.', 'smart-ecommerce-store'));
		}
		return $envelope['signed'];
	}

	private static function normalize(array $payload) {
		$products = array();
		foreach ((array) ($payload['products'] ?? array()) as $item) {
			if (!is_array($item) || 'plugin' !== ($item['type'] ?? 'plugin')) { continue; }
			$visibility = sanitize_key((string) ($item['visibility'] ?? 'internal'));
			if (!in_array($visibility, array('public', 'commercial'), true)) { continue; }
			$channel = sanitize_key((string) ($item['channel'] ?? ''));
			if (!in_array($channel, array('wordpress_org', 'freemius'), true)) { continue; }
			$slug = sanitize_key((string) ($item['slug'] ?? ''));
			if (!$slug) { continue; }
			$checkout_url = esc_url_raw((string) ($item['checkout_url'] ?? ''));
			if ('freemius' === $channel && !self::trusted_checkout_url($checkout_url)) { continue; }
			$products[$slug] = array(
				'slug' => $slug,
				'name' => sanitize_text_field((string) ($item['name'] ?? $slug)),
				'description' => wp_kses_post((string) ($item['description'] ?? '')),
				'version' => sanitize_text_field((string) ($item['version'] ?? '')),
				'visibility' => $visibility,
				'channel' => $channel,
				'wordpress_org_slug' => sanitize_key((string) ($item['wordpress_org_slug'] ?? $slug)),
				'checkout_url' => $checkout_url,
				'homepage' => esc_url_raw((string) ($item['homepage'] ?? '')),
				'documentation_url' => esc_url_raw((string) ($item['documentation_url'] ?? '')),
				'icon_url' => self::trusted_asset_url((string) ($item['icon_url'] ?? '')),
			);
		}
		return array(
			'schema_version' => sanitize_text_field((string) ($payload['schema_version'] ?? '1.0')),
			'expires' => sanitize_text_field((string) ($payload['expires'] ?? '')),
			'products' => $products,
		);
	}

	private static function trusted_repository_url($url) {
		return 'https' === strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))
			&& 'repository.smartecommerce.it' === strtolower((string) wp_parse_url($url, PHP_URL_HOST));
	}

	private static function trusted_asset_url($url) {
		return self::trusted_repository_url($url) ? esc_url_raw($url) : '';
	}

	private static function trusted_checkout_url($url) {
		return 'https' === strtolower((string) wp_parse_url($url, PHP_URL_SCHEME))
			&& 'checkout.freemius.com' === strtolower((string) wp_parse_url($url, PHP_URL_HOST));
	}

	private static function public_keys() {
		return array(
			'repository-2026-01' => "-----BEGIN PUBLIC KEY-----\nMIIBojANBgkqhkiG9w0BAQEFAAOCAY8AMIIBigKCAYEAqg61GTtjVKmA6WXuE/HN\n7JDsjoa7RzzV9y0OIO2QMq2LZ4FcUZkqPHfRYpjdPlpys4D/vMjTpI16yU9w/ayZ\npr+aHfL5nT8NDTRsugQq2WqPgGRBd/ltlkEA1y+uEXYj6lKPpf6OrnbHIp0PZdfn\nWdPBiX6HDjoZ44VEgxkoTMmYm4poM/5GE0aAr29uxCu1aAiIKtZ0k04jv1zdeU2C\nenPWBihLgBpPPYb93BAHzy3WBRykessDohp//Xib2G+Usws75B6rwFdni0O94W1A\nfUK9eQ9lQoz+K0nf1vnZnPHfYrQxHoEkOI0EoelogPaYRmtOV+cjjIS53imqn90r\nFCODSGUyJuy7Mf32cl9Cuqpg7Kg4BF9/G02jdPujM9hOgorKL/S8svaRkVamEG/m\nNL+rDnZdafcvyxDVvNb5gda9Ydb1hIA2+I53FZzhYC53RSwFVwCE+q40/6KRtGkP\nfnJTgvrPDh46/jIjqOK/WN+3RF8tj+5mSONLHtHpqx4zAgMBAAE=\n-----END PUBLIC KEY-----",
		);
	}

	private static function canonical_json($value) {
		$value = self::canonicalize($value);
		return wp_json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
	}

	private static function canonicalize($value) {
		if (!is_array($value)) { return $value; }
		$is_list = array_keys($value) === range(0, count($value) - 1);
		if (!$is_list) { ksort($value, SORT_STRING); }
		foreach ($value as $key => $item) { $value[$key] = self::canonicalize($item); }
		return $value;
	}

	private static function fail($code, $message) {
		set_transient(self::FAILURE_KEY, array('code' => sanitize_key($code), 'message' => sanitize_text_field($message)), MINUTE_IN_SECONDS);
		SES_Audit::write('catalog_error', array('code' => $code));
		return new WP_Error('ses_' . sanitize_key($code), $message);
	}
}

