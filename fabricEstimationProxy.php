<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
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

$apiBaseUrls = [
    'http://127.0.0.1:5000',
    'http://localhost:5000'
];

$action = $_GET['action'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET' && $action === 'get_options') {
    $targetPath = '/get_options';
    $payload = null;
    $curlMethod = 'GET';
} elseif ($method === 'POST' && $action === 'predict') {
    $targetPath = '/predict';
    $rawBody = file_get_contents('php://input');
    $payload = $rawBody !== false ? $rawBody : '{}';
    $curlMethod = 'POST';
} else {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid Request',
        'message' => 'Use GET ?action=get_options or POST ?action=predict.'
    ]);
    exit;
}

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

function tryStartEstimationApi() {
    $projectDir = __DIR__;
    $venvPython = $projectDir . DIRECTORY_SEPARATOR . '.venv' . DIRECTORY_SEPARATOR . 'Scripts' . DIRECTORY_SEPARATOR . 'python.exe';
    $scriptPath = $projectDir . DIRECTORY_SEPARATOR . 'app.py';

    if (!file_exists($scriptPath)) {
        return false;
    }

    $pythonCmd = file_exists($venvPython) ? $venvPython : 'python';

    if (stripos(PHP_OS, 'WIN') === 0) {
        $escapedProjectDir = str_replace('"', '""', $projectDir);
        $escapedPythonCmd = str_replace('"', '""', $pythonCmd);
        $escapedScriptPath = str_replace('"', '""', $scriptPath);
        $cmd = 'cmd /c "cd /d \"' . $escapedProjectDir . '\" && start \"FabricSense Estimation API\" /MIN \"' . $escapedPythonCmd . '\" \"' . $escapedScriptPath . '\""';
    } else {
        $cmd = 'cd ' . escapeshellarg($projectDir) . ' && ' . escapeshellarg($pythonCmd) . ' ' . escapeshellarg($scriptPath) . ' > /dev/null 2>&1 &';
    }

    @pclose(@popen($cmd, 'r'));
    return true;
}

if (!isPortOpen('127.0.0.1', 5000, 1)) {
    tryStartEstimationApi();
    usleep(500000);
}

for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
    $isApiUp = isPortOpen('127.0.0.1', 5000, 1) || isPortOpen('localhost', 5000, 1);
    if (!$isApiUp) {
        $lastCurlError = 'Fabric Estimation API is not listening on port 5000 yet.';
        if ($attempt < $maxAttempts) {
            sleep($sleepBetweenAttemptsSeconds);
            continue;
        }
    }

    foreach ($apiBaseUrls as $baseUrl) {
        $url = $baseUrl . $targetPath;
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 25);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 2);

        if ($curlMethod === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        }

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
                'message' => 'Estimation API returned a non-JSON response.',
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
    'error' => 'Estimation API Unreachable',
    'message' => 'Unable to reach the Fabric Estimation API on port 5000.',
    'suggestion' => 'Run start_services.ps1 and keep it open, or start app.py manually.',
    'details' => $lastCurlError ?: 'Connection failed'
]);
