<?php
$root = dirname(__DIR__);
$source = file_get_contents($root . '/includes/class-ses-catalog.php')
	. file_get_contents($root . '/includes/class-ses-products.php')
	. file_get_contents($root . '/includes/class-ses-installer.php')
	. file_get_contents($root . '/includes/class-ses-admin.php');
foreach (array("array('plugin', 'theme')", 'wp_get_themes', 'Theme_Upgrader', 'switch_theme', "'theme' => array(__('Temi'") as $needle) {
	if (false === strpos($source, $needle)) { fwrite(STDERR, "Missing theme contract: {$needle}\n"); exit(1); }
}
echo "Smart Store theme contract OK\n";
