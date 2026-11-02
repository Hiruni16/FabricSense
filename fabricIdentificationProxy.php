<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method Not Allowed',
        'message' => 'Use POST for fabric identification requests.'
    ]);
    exit;
}

if (!isset($_FILES['image']) || !is_uploaded_file($_FILES['image']['tmp_name'])) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'No Image Uploaded',
        'message' => 'Please upload an image file in the request.'
    ]);
    exit;
}

if (!function_exists('curl_init')) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'cURL Not Available',
        'message' => 'PHP cURL extension is required for API proxying.'
    ]);
    exit;
}

$apiUrls = [
    'http://127.0.0.1:5001/identify',
    'http://localhost:5001/identify'
];

$uploadedFile = $_FILES['image'];
$curlFile = curl_file_create(
    $uploadedFile['tmp_name'],
    $uploadedFile['type'] ?: 'application/octet-stream',
    $uploadedFile['name'] ?: 'upload.jpg'
);

$postFields = [
    'image' => $curlFile,
    'purpose' => $_POST['purpose'] ?? '',
    'show_alternatives' => $_POST['show_alternatives'] ?? 'true',
    'include_care' => $_POST['include_care'] ?? 'true',
    'include_sustainability' => $_POST['include_sustainability'] ?? 'false'
];

$lastCurlError = null;
$maxAttempts = 20;
$sleepBetweenAttemptsSeconds = 2;

function isPortOpen($host, $port, $timeoutSeconds = 1) {
    $errno = 0;
    $errstr = '';
    $socket = @fsockopen($host, $port, $errno, $errstr, $timeoutSeconds);
    if ($socket) {
        fclose($socket);
        return true;
    }
    return false;
}

function tryStartFabricApi() {
    $projectDir = __DIR__;
    $venvPython = $projectDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    $scriptPath = $projectDir . DIRECTORY_SEPARATOR . 'fabric_identification_api.py';

    if (!file_exists($scriptPath)) {
        return false;
    }

    $pythonCmd = file_exists($venvPython) ? $venvPython : 'python';

    if (stripos(PHP_OS, 'WIN') === 0) {
        $escapedProjectDir = str_replace('"', '""', $projectDir);
        $escapedPythonCmd = str_replace('"', '""', $pythonCmd);
        $escapedScriptPath = str_replace('"', '""', $scriptPath);
        $cmd = 'cmd /c "cd /d \"' . $escapedProjectDir . '\" && start \"FabricSense Identification API\" /MIN \"' . $escapedPythonCmd . '\" \"' . $escapedScriptPath . '\""';
    } else {
        $cmd = 'cd ' . escapeshellarg($projectDir) . ' && ' . escapeshellarg($pythonCmd) . ' ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &';
    }

    @pclose(@popen($cmd, 'r'));
    return true;
}

if (!isPortOpen('127.0.0.1', 5001, 1)) {
    tryStartFabricApi();
    usleep(500000);
}

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    $isApiUp = isPortOpen('127.0.0.1', 5001, 1) || isPortOpen('localhost', 5001, 1);
    if (!$isApiUp) {
        $lastCurlError = 'Fabric Identification API is not listening on port 5001 yet.';
        if ($attempt < $maxAttempts) {
            sleep($sleepBetweenAttemptsSeconds);
            continue;
        }
    }

    foreach ($apiUrls as $apiUrl) {
        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        $responseBody = curl_exec($ch);
        $curlErrNo = curl_errno($ch);
        $curlErrMsg = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($curlErrNo !== 0) {
            $lastCurlError = $curlErrMsg;
            continue;
        }

        http_response_code($httpCode > 0 ? $httpCode : 200);

        $decoded = json_decode($responseBody, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            echo json_encode($decoded);
        } else {
            echo json_encode([
                'success' => false,
                'error' => 'Invalid API Response',
                'message' => 'Fabric API returned a non-JSON response.',
                'raw_response' => $responseBody
            ]);
        }
        exit;
    }

    if ($attempt < $maxAttempts) {
        sleep($sleepBetweenAttemptsSeconds);
    }
}

http_response_code(503);
echo json_encode([
    'success' => false,
    'error' => 'Fabric API Unreachable',
    'message' => 'Unable to reach the Fabric Identification API on port 5001 after waiting for startup.',
    'suggestion' => 'Run start_services.ps1 and keep it open, or start fabric_identification_api.py manually.',
    'details' => $lastCurlError ?: 'Connection failed'
]);
