<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['error' => 'POST requests only']);
    exit();
}

$input = file_get_contents('php://input');

if (empty($input)) {
    echo json_encode(['error' => 'No input data']);
    exit();
}

// Sanitize input - only allow valid JSON numbers and strings
$decoded = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid JSON']);
    exit();
}

// Re-encode to ensure clean input (prevents injection)
$clean_input = json_encode($decoded);

// Path to Python 3 on Hostinger - try common paths
$python_paths = [
    '/usr/bin/python3',
    '/usr/local/bin/python3',
    '/opt/alt/python39/usr/bin/python3',
    'python3'
];

$python = 'python3';
foreach ($python_paths as $path) {
    if (file_exists($path)) {
        $python = $path;
        break;
    }
}

// Path to calculate.py - same folder as this file
$script = __DIR__ . '/calculate.py';

if (!file_exists($script)) {
    echo json_encode(['error' => 'calculate.py not found at ' . $script]);
    exit();
}

// Run Python, pipe JSON in via stdin
$descriptors = [
    0 => ['pipe', 'r'],
    1 => ['pipe', 'w'],
    2 => ['pipe', 'w']
];

$process = proc_open(
    escapeshellcmd($python) . ' ' . escapeshellarg($script),
    $descriptors,
    $pipes
);

if (!is_resource($process)) {
    echo json_encode(['error' => 'Failed to start Python process']);
    exit();
}

fwrite($pipes[0], $clean_input);
fclose($pipes[0]);

$output = stream_get_contents($pipes[1]);
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);

$exit_code = proc_close($process);

if ($exit_code !== 0 || empty($output)) {
    echo json_encode([
        'error'    => 'Python error',
        'details'  => $errors,
        'exitCode' => $exit_code
    ]);
    exit();
}

// Validate output is proper JSON before returning
$result = json_decode($output, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['error' => 'Invalid Python output', 'raw' => $output]);
    exit();
}

echo json_encode($result);
?>
