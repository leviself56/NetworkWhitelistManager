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
 
	// --- NEW: scan connections & remove any where src or dst contains the IP (port suffixes included) ---
	try {
	    // Get all connections (you can narrow this if you wish, but for reliability we fetch and filter in PHP)
	    $q = new Query('/ip/firewall/connection/print');
	    $allConns = $client->query($q)->read();

	    $found = [];
	    $removedIds = [];

	    foreach ($allConns as $conn) {
	        $src = $conn['src-address'] ?? '';
	        $dst = $conn['dst-address'] ?? '';

	        // match connections where the IP is at the start of the src/dst
	        if (strpos($src, $ip) === 0 || strpos($dst, $ip) === 0) {
	            $found[] = [
	                '.id' => $conn['.id'],
	                'src-address' => $src,
	                'dst-address' => $dst,
	                'proto' => $conn['proto'] ?? null,
	            ];

	            $rem = new Query('/ip/firewall/connection/remove');
	            $rem->equal('.id', $conn['.id']);
	            $client->query($rem)->read();

	            $removedIds[] = $conn['.id'];
	        }
	    }

	    header('Content-Type: application/json');
	    echo json_encode([
	        'success' => true,
	        'address_list_removed' => count($entries),
	        'connections_found' => count($found),
	        'connections_removed' => count($removedIds),
	        'removed_ids' => $removedIds,
	        'found_samples' => array_slice($found, 0, 10) // small sample for debugging
	    ]);
	    exit;
	} catch (Exception $e) {
	    header('Content-Type: application/json', true, 500);
	    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
	    exit;
	}

}

echo "OK";
?>
