<?php
if (!defined('ABSPATH')) { exit; }

final class SES_Products {
	public static function enrich(array $catalog) {
		$plugins = get_plugins();
		$themes = function_exists('wp_get_themes') ? wp_get_themes() : array();
		$active = (array) get_option('active_plugins', array());
		$products = array();
		foreach ($catalog['products'] as $slug => $product) {
			$is_theme = 'theme' === ($product['type'] ?? 'plugin');
			$file = $is_theme ? self::find_theme($slug, $themes) : self::find_plugin($slug, $plugins);
			$product['plugin_file'] = $is_theme ? '' : $file;
			$product['theme_stylesheet'] = $is_theme ? $file : '';
			$product['installed'] = '' !== $file;
			$product['active'] = $file && ($is_theme ? get_stylesheet() === $file : (in_array($file, $active, true) || is_plugin_active_for_network($file)));
			$product['installed_version'] = $file ? sanitize_text_field((string) ($is_theme ? $themes[$file]->get('Version') : ($plugins[$file]['Version'] ?? ''))) : '';
			$product['update_available'] = $product['installed_version'] && $product['version'] && version_compare($product['version'], $product['installed_version'], '>');
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

	private static function find_theme($slug, array $themes) {
		if (isset($themes[$slug])) { return $slug; }
		foreach ($themes as $stylesheet => $theme) {
			if (sanitize_key($stylesheet) === $slug || sanitize_key((string) $theme->get('Name')) === $slug) { return $stylesheet; }
		}
		return '';
	}
}

