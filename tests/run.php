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

echo "Smart eCommerce Store checks passed.\n";

