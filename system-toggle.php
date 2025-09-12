<?php
require 'vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

$config = require 'config.php';
$client = new Client($config);

// Find rule by comment
$query = new Query('/ip/firewall/filter/print');
$query->where('comment', '###NetworkWhitelist API###'); // adjust if needed
$rules = $client->query($query)->read();

if (empty($rules)) {
    http_response_code(404);
    echo json_encode(['error' => 'Rule not found']);
    exit;
}

$rule = $rules[0];
$ruleId = $rule['.id'];
$currentState = isset($rule['disabled']) && $rule['disabled'] === 'true';

// If a toggle was requested
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($currentState) {
        // currently disabled → enable it
        $q = new Query('/ip/firewall/filter/enable');
        $q->equal('.id', $ruleId);
        $client->query($q)->read();
        $newState = false;
    } else {
        // currently enabled → disable it
        $q = new Query('/ip/firewall/filter/disable');
        $q->equal('.id', $ruleId);
        $client->query($q)->read();
        $newState = true;
    }
    echo json_encode(['disabled' => $newState]);
    exit;
}

// On GET: return current state
header('Content-Type: application/json');
echo json_encode(['disabled' => $currentState]);
?>
