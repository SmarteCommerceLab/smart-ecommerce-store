<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Products {
	public static function enrich(array $catalog) {
		$plugins = get_plugins();
		$active = (array) get_option('active_plugins', array());
		$products = array();
		foreach ($catalog['products'] as $slug => $product) {
			$plugin_file = self::find_plugin($slug, $plugins);
			$product['plugin_file'] = $plugin_file;
			$product['installed'] = '' !== $plugin_file;
			$product['active'] = $plugin_file && (in_array($plugin_file, $active, true) || is_plugin_active_for_network($plugin_file));
			$product['installed_version'] = $plugin_file ? sanitize_text_field((string) ($plugins[$plugin_file]['Version'] ?? '')) : '';
			$products[$slug] = $product;
		}
		return $products;
	}

	private static function find_plugin($slug, array $plugins) {
		foreach ($plugins as $file => $headers) {
			if (0 === strpos($file, $slug . '/')) { return $file; }
		}
		return '';
	}
}

