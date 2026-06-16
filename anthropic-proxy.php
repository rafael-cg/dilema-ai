<?php
/**
 * Proxy server-side para a Anthropic API — evita bloqueio CORS no browser.
 * Recebe: POST JSON { api_key: string, model: string, messages: [...], ... }
 * Retorna: resposta direta da Anthropic API
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true) ?: [];

$apiKey = trim($input['api_key'] ?? '');

if (!$apiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'api_key é obrigatório no body da requisição']);
    exit;
}

// Remove a chave do payload antes de repassar à Anthropic
unset($input['api_key']);

// Valida campos mínimos
if (empty($input['model']) || empty($input['messages'])) {
    http_response_code(400);
    echo json_encode(['error' => 'model e messages são obrigatórios']);
    exit;
}

$payload = json_encode($input, JSON_UNESCAPED_UNICODE);

$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $payload,
    CURLOPT_TIMEOUT        => 60,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        "x-api-key: {$apiKey}",
        'anthropic-version: 2023-06-01',
    ],
]);

$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(503);
    echo json_encode(['error' => "Falha na conexão com Anthropic: {$err}"]);
    exit;
}

http_response_code($status);
echo $body;
