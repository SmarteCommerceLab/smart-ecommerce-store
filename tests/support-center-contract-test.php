<?php
$source = file_get_contents(__DIR__ . '/../includes/class-ses-admin.php');
$required = array('Assistenza', 'colors[1]', 'wp_json_encode', 'Report tecnico privo di segreti', 'Non include URL, utenti, email');
foreach ($required as $token) {
	if (false === strpos($source, $token)) {
		fwrite(STDERR, "Missing support center contract: {$token}\\n");
		exit(1);
	}
}
if (false !== strpos($source, 'colors[0]')) {
	fwrite(STDERR, "Product toolbar still uses the menu/submenu color.\\n");
	exit(1);
}
echo "Support center contract OK.\n";
