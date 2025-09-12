<?php
#ini_set('display_errors', 1);
#ini_set('display_startup_errors', 1);
#error_reporting(E_ALL);

require 'vendor/autoload.php';

use RouterOS\Client;
use RouterOS\Query;

$config = require 'config.php';
$client = new Client($config);

$ip     = $_POST['ip'] ?? null;
$action = $_POST['action'] ?? null;

if (!$ip || !$action) {
    http_response_code(400);
    exit("Invalid request");
}

if ($action === 'enable') {
    // Check if already in address list
    $query = new Query('/ip/firewall/address-list/print');
    $query->where('list', 'allowed_internet')->where('address', $ip);
    $existing = $client->query($query)->read();

    if (empty($existing)) {
        // Add to list
        $query = new Query('/ip/firewall/address-list/add');
        $query->equal('list', 'allowed_internet')
              ->equal('address', $ip)
              ->equal('comment', 'Enabled by NetworkWhitelist API');
        $client->query($query)->read();
    }
} elseif ($action === 'disable') {
    // Remove from list
    $query = new Query('/ip/firewall/address-list/print');
    $query->where('list', 'allowed_internet')->where('address', $ip);
    $entries = $client->query($query)->read();

    foreach ($entries as $entry) {
        $q = new Query('/ip/firewall/address-list/remove');
        $q->equal('.id', $entry['.id']);
        $client->query($q)->read();
    }
}

echo "OK";
?>
