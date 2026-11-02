<?php
/**
 * Fabric Library API - List images from the dataset folder
 */
header('Content-Type: application/json');

$datasetPath = __DIR__ . '/TFD Textile Dataset';

// Check if dataset folder exists
if (!is_dir($datasetPath)) {
    echo json_encode([
        'success' => false,
        'error' => 'Dataset folder not found',
        'message' => 'Please ensure "TFD Textile Dataset" folder exists in the FabricSense directory'
    ]);
    exit;
}

$fabricTypes = ['Chiffon', 'Cotton', 'Denim', 'Linen', 'Nylon', 'Polyester', 'Satin', 'Silk', 'Velvet', 'Wool'];
$fabricLibrary = [];

foreach ($fabricTypes as $fabricType) {
    $fabricPath = $datasetPath . '/' . $fabricType;
    
    if (!is_dir($fabricPath)) {
        continue;
    }
    
    $images = [];
    $files = scandir($fabricPath);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..') continue;
        
        $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
        if (in_array($ext, ['jpg', 'jpeg', 'png'])) {
            $images[] = [
                'filename' => $file,
                'path' => 'TFD Textile Dataset/' . $fabricType . '/' . $file,
                'fabric_type' => $fabricType
            ];
            
            // Limit to 12 images per fabric type for performance
            if (count($images) >= 12) break;
        }
    }
    
    if (!empty($images)) {
        $fabricLibrary[$fabricType] = $images;
    }
}

echo json_encode([
    'success' => true,
    'library' => $fabricLibrary,
    'total_fabrics' => count($fabricLibrary),
    'dataset_path' => $datasetPath
]);
