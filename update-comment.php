<?php
require 'vendor/autoload.php';
use RouterOS\Client;
use RouterOS\Query;

$config = require 'config.php';
$client = new Client($config);

$id = $_POST['id'] ?? null;
$comment = $_POST['comment'] ?? '';

if (!$id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing lease ID']);
    exit;
}

try {
    $query = new Query('/ip/dhcp-server/lease/set');
    $query->equal('.id', $id)->equal('comment', $comment);
    $client->query($query)->read();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
?>
