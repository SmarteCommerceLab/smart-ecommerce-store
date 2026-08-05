<?php
$root = dirname(__DIR__);
$version = '0.2.6';
$slug = 'smart-ecommerce-store';
$dist = $root . '/dist';
$stage = $dist . '/' . $slug;

if (!class_exists('ZipArchive')) { fwrite(STDERR, "ZipArchive is required.\n"); exit(1); }
if (is_dir($dist)) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dist, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
	foreach ($iterator as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
}
@mkdir($stage, 0777, true);

$include = array('smart-ecommerce-store.php', 'uninstall.php', 'readme.txt', 'assets', 'includes');
foreach ($include as $name) {
	$source = $root . '/' . $name;
	$target = $stage . '/' . $name;
	if (is_dir($source)) {
		@mkdir($target, 0777, true);
		$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
		foreach ($it as $item) {
			$dest = $target . '/' . $it->getSubPathName();
			$item->isDir() ? @mkdir($dest, 0777, true) : copy($item->getPathname(), $dest);
		}
	} else { copy($source, $target); }
}

$zipPath = $dist . '/' . $slug . '-' . $version . '.zip';
$zip = new ZipArchive();
$zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($stage, FilesystemIterator::SKIP_DOTS));
foreach ($it as $file) {
	if ($file->isFile()) {
		$relative = str_replace('\\', '/', $it->getSubPathName());
		$zip->addFile($file->getPathname(), $slug . '/' . $relative);
	}
}
$zip->close();

$required = array(
	$slug . '/smart-ecommerce-store.php',
	$slug . '/readme.txt',
	$slug . '/assets/admin.css',
	$slug . '/assets/licenses.css',
	$slug . '/assets/actions.css',
	$slug . '/includes/class-ses-admin.php',
	$slug . '/includes/class-ses-catalog.php',
	$slug . '/includes/class-ses-licenses.php',
	$slug . '/includes/class-ses-updater.php',
);
$check = new ZipArchive();
$check->open($zipPath);
foreach ($required as $file) {
	if (false === $check->locateName($file)) {
		fwrite(STDERR, "Missing package file: {$file}\n");
		$check->close();
		exit(1);
	}
}
$check->close();
echo $zipPath . "\n";
