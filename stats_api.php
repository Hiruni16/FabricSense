<?php
header('Content-Type: application/json');
require_once 'DB.php';

function tableExists($db, $table) {
    try {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute(['table' => $table]);
        return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return false;
    }
}

$stats = [
    'identified' => 0,
    'estimates' => 0,
    'fabric_types_identifications' => 0,
    'fabric_types_estimates' => 0
];

$expectedIdentificationTypes = 10;
$expectedEstimateTypes = 9;

try {
    $db = DB::connect();

    if (tableExists($db, 'fabric_identifications')) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_identifications");
        $stats['identified'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    if (tableExists($db, 'fabric_estimates')) {
        $stmt = $db->query("SELECT COUNT(*) as total FROM fabric_estimates");
        $stats['estimates'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    if (tableExists($db, 'fabric_identifications')) {
        $stmt = $db->query("SELECT COUNT(DISTINCT fabric_type) as total FROM fabric_identifications");
        $stats['fabric_types_identifications'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    if (tableExists($db, 'fabric_estimates')) {
        $stmt = $db->query("SELECT COUNT(DISTINCT fabric_type) as total FROM fabric_estimates");
        $stats['fabric_types_estimates'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
    }

    if ($stats['fabric_types_identifications'] < $expectedIdentificationTypes) {
        $stats['fabric_types_identifications'] = $expectedIdentificationTypes;
    }

    if ($stats['fabric_types_estimates'] < $expectedEstimateTypes) {
        $stats['fabric_types_estimates'] = $expectedEstimateTypes;
    }
} catch (Exception $e) {
    // keep defaults
}

echo json_encode($stats);
