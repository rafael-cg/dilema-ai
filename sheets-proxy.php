<?php
header('Access-Control-Allow-Origin: https://dilema.ai');
header('Content-Type: application/json');

// API Key armazenada no servidor
$API_KEY = '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';

// Proxy para Google Sheets API
$endpoint = $_GET['endpoint'] ?? '';
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $url = "https://sheets.googleapis.com/v4/spreadsheets/{$endpoint}?key={$API_KEY}";
    
    $options = [
        'http' => [
            'header' => "Content-Type: application/json\r\n",
            'method' => 'POST',
            'content' => json_encode($input)
        ]
    ];
    
    $context = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    echo $result;
} else {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}
?>
