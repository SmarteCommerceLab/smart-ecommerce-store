<?php
$root = dirname(__DIR__);
$files = array(
	$root . '/smart-ecommerce-store.php',
	$root . '/includes/class-ses-audit.php',
	$root . '/includes/class-ses-catalog.php',
	$root . '/includes/class-ses-products.php',
	$root . '/includes/class-ses-licenses.php',
	$root . '/includes/class-ses-installer.php',
	$root . '/includes/class-ses-updater.php',
	$root . '/includes/class-ses-admin.php',
);

foreach ($files as $file) {
	$output = array();
	$code = 0;
	exec('php -l ' . escapeshellarg($file), $output, $code);
	if (0 !== $code) { fwrite(STDERR, implode("\n", $output) . "\n"); exit(1); }
}

$catalog = file_get_contents($root . '/includes/class-ses-catalog.php');
$installer = file_get_contents($root . '/includes/class-ses-installer.php');
$licenses = file_get_contents($root . '/includes/class-ses-licenses.php');
$admin = file_get_contents($root . '/includes/class-ses-admin.php');
$main = file_get_contents($root . '/smart-ecommerce-store.php');
$builder = file_get_contents($root . '/scripts/build-package.php');
$updater = file_get_contents($root . '/includes/class-ses-updater.php');
$assertions = array(
	"visibility'] ?? 'internal'" => 'missing visibility is private',
	"array('public', 'commercial')" => 'public visibility allow-list',
	"array('wordpress_org', 'freemius')" => 'public channel allow-list',
	"checkout.freemius.com" => 'trusted checkout host',
	"repository.smartecommerce.it" => 'trusted catalog host',
	"official_product_url" => 'official Smart eCommerce product links',
);
foreach ($assertions as $needle => $label) {
	if (false === strpos($catalog, $needle)) { fwrite(STDERR, "Missing assertion: {$label}\n"); exit(1); }
}

foreach (array(
	'SES_PRODUCT_NAME' => 'product identity constants',
	'function get_subpages' => 'subpage registry',
	'add_submenu_page' => 'registered subpages',
	'function template' => 'shared page template',
	'smart-admin-shell' => 'design system shell',
	'smart-admin-notices' => 'captured WordPress notices',
	'current_user_can' => 'page capability check',
	'const MENU_POSITION = 82' => 'final administration menu placement',
	'ses-details-button' => 'consistent details button styling',
	'notice_buffer_level' => 'owned notice buffer tracking',
	'function is_plugin_screen' => 'Store-only notice capture',
) as $needle => $label) {
	if (false === strpos($admin . $main, $needle)) { fwrite(STDERR, "Missing UI contract: {$label}\n"); exit(1); }
}

foreach (array(
	"preg_match('/^ \\* Version:" => 'package version from plugin header',
	"SES_VERSION" => 'package version identity check',
) as $needle => $label) {
	if (false === strpos($builder, $needle)) { fwrite(STDERR, "Missing package assertion: {$label}\n"); exit(1); }
}

foreach (array(
	'SES_PREMIUM_INSTALL_URL' => 'server-side premium endpoint',
	"'stream' => true" => 'streamed premium download',
	"'sslverify' => true" => 'TLS verification',
	"'license_key' => \$license_key" => 'license authorization payload',
	"'entitlement'" => 'opaque entitlement receipt',
	'ses_entitlement_invalid' => 'missing entitlement rejection',
	'Attiva ora il plugin' => 'explicit post-install activation step',
	"'Accept' => 'application/json'" => 'JSON installation contract',
	"array('api.freemius.com', 'fast-api.freemius.com')" => 'trusted signed package hosts',
	"'https' !== \$download_scheme" => 'HTTPS-only premium package URL',
) as $needle => $label) {
	if (false === strpos($installer, $needle) && false === strpos(file_get_contents($root . '/smart-ecommerce-store.php'), $needle)) {
		fwrite(STDERR, "Missing assertion: {$label}\n"); exit(1);
	}
}

foreach (array(
	'SES_PREMIUM_STATUS_URL' => 'protected license status endpoint',
	'checkout.freemius.com' => 'trusted renewal host',
	'customers.freemius.com' => 'trusted customer portal host',
	'15 * MINUTE_IN_SECONDS' => 'bounded license status cache',
) as $needle => $label) {
	if (false === strpos($licenses . $main, $needle)) { fwrite(STDERR, "Missing license assertion: {$label}\n"); exit(1); }
}

foreach (array(
	'pre_set_site_transient_update_plugins' => 'native WordPress update discovery',
	'upgrader_pre_download' => 'package verification hook',
	'update_package_verified' => 'verified update audit',
	"hash_file('sha256'" => 'SHA-256 package verification',
	'repository.smartecommerce.it' => 'trusted repository host',
	'plugin_action_links_' => 'plugin action links',
	'admin_post_ses_check_updates' => 'manual update check',
	'function clear_update_caches' => 'update cache invalidation',
	'15 * MINUTE_IN_SECONDS' => 'bounded update cache',
	'unset($transient->response[$key])' => 'stale update removal',
	'$transient->no_update[$key]' => 'current version state',
) as $needle => $label) {
	if (false === strpos($updater, $needle)) { fwrite(STDERR, "Missing updater assertion: {$label}\n"); exit(1); }
}

echo "Smart eCommerce Store checks passed.\n";
