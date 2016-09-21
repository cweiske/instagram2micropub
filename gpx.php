<?php
/**
 * Generate a GPX file from downloaded location files
 */
require_once __DIR__ . '/config.php';

$files = glob($dldir . '/*.location.json');
if (!count($files)) {
    file_put_contents('php://stderr', 'No location files found');
    exit(1);
}

echo '<?xml version="1.0" encoding="UTF-8" standalone="no" ?>' . "\n"
    . '<gpx version="1.1" creator="instagram-dl"'
    . ' xmlns="http://www.topografix.com/GPX/1/1">' . "\n"
    . ' <rte>' . "\n";
foreach ($files as $file) {
    $time = basename($file, '.location.json');
    $data = json_decode(file_get_contents($file));
    $loc  = $data->location;
    $url  = 'https://www.instagram.com/explore/locations/' . $loc->id . '/';
    echo '  <rtept lat="' . $loc->lat . '" lon="' . $loc->lng . '">' . "\n"
        . '   <name>' . htmlspecialchars($loc->name) . "</name>\n"
        . '   <link href="' . htmlspecialchars($url) . '"/>' . "\n"
        . "  </rtept>\n";
}

echo " </rte>\n"
    . "</gpx>\n";
?>