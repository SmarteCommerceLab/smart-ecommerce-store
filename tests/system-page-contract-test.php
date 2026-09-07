<?php
$source = file_get_contents(__DIR__ . '/../includes/class-ses-admin.php');
foreach (array("'slug' => 'ses-system'", 'render_system', 'system_content', 'Report tecnico per l assistenza', 'SES_CATALOG_URL') as $needle) {
	if (false === strpos($source, $needle)) {
		fwrite(STDERR, "Missing Store system-page contract: {$needle}\n");
		exit(1);
	}
}
echo "Store system-page contract OK\n";
