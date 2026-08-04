<?php
$root = dirname(__DIR__);
$files = array(
	$root . '/smart-ecommerce-store.php',
	$root . '/includes/class-ses-audit.php',
	$root . '/includes/class-ses-catalog.php',
	$root . '/includes/class-ses-products.php',
	$root . '/includes/class-ses-installer.php',
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
$admin = file_get_contents($root . '/includes/class-ses-admin.php');
$main = file_get_contents($root . '/smart-ecommerce-store.php');
$assertions = array(
	"visibility'] ?? 'internal'" => 'missing visibility is private',
	"array('public', 'commercial')" => 'public visibility allow-list',
	"array('wordpress_org', 'freemius')" => 'public channel allow-list',
	"checkout.freemius.com" => 'trusted checkout host',
	"repository.smartecommerce.it" => 'trusted catalog host',
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
) as $needle => $label) {
	if (false === strpos($admin . $main, $needle)) { fwrite(STDERR, "Missing UI contract: {$label}\n"); exit(1); }
}

foreach (array(
	'SES_PREMIUM_INSTALL_URL' => 'server-side premium endpoint',
	"'stream' => true" => 'streamed premium download',
	"'sslverify' => true" => 'TLS verification',
	"'license_key' => \$license_key" => 'license authorization payload',
) as $needle => $label) {
	if (false === strpos($installer, $needle) && false === strpos(file_get_contents($root . '/smart-ecommerce-store.php'), $needle)) {
		fwrite(STDERR, "Missing assertion: {$label}\n"); exit(1);
	}
}

echo "Smart eCommerce Store checks passed.\n";
