<?php
/**
 * Proxy para Twitter API v2 — evita CORS no frontend.
 * Recebe: POST JSON { topic: string, twitter_token: string }
 * Retorna: JSON { tweets: string[], hashtags: string[], count: int }
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
$topic = trim($input['topic'] ?? '');
$token = trim($input['twitter_token'] ?? getenv('TWITTER_BEARER_TOKEN') ?: '');

if (!$topic) {
    http_response_code(400);
    echo json_encode(['error' => 'topic is required']);
    exit;
}

if (!$token) {
    http_response_code(401);
    echo json_encode(['error' => 'Twitter Bearer Token not provided', 'code' => 'NO_TOKEN']);
    exit;
}

// Constrói query: tópico, sem retweets, em PT ou EN
$q = urlencode('"' . $topic . '" -is:retweet (lang:pt OR lang:en)');
$url = "https://api.twitter.com/2/tweets/search/recent"
     . "?query={$q}"
     . "&max_results=20"
     . "&tweet.fields=public_metrics,entities,created_at"
     . "&sort_order=relevancy";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 10,
    CURLOPT_HTTPHEADER     => ["Authorization: Bearer {$token}", "User-Agent: MalokaSocialTrends/1.0"],
]);

$body   = curl_exec($ch);
$status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err    = curl_error($ch);
curl_close($ch);

if ($err) {
    http_response_code(503);
    echo json_encode(['error' => "Falha na requisição: {$err}"]);
    exit;
}

if ($status !== 200) {
    http_response_code($status);
    $detail = json_decode($body, true);
    echo json_encode([
        'error'   => "Twitter API retornou HTTP {$status}",
        'details' => $detail['detail'] ?? $detail['title'] ?? $body,
    ]);
    exit;
}

$data   = json_decode($body, true);
$tweets = $data['data'] ?? [];

$hashtags   = [];
$tweetTexts = [];

foreach ($tweets as $tw) {
    // Remove URLs do texto
    $text = preg_replace('/https?:\/\/\S+/', '', $tw['text']);
    $text = trim(preg_replace('/\s+/', ' ', $text));
    if ($text) {
        $tweetTexts[] = $text;
    }

    // Extrai hashtags
    foreach ($tw['entities']['hashtags'] ?? [] as $ht) {
        $tag = strtolower($ht['tag']);
        $hashtags[$tag] = ($hashtags[$tag] ?? 0) + 1;
    }
}

arsort($hashtags);

echo json_encode([
    'tweets'   => array_slice($tweetTexts, 0, 10),
    'hashtags' => array_slice(array_keys($hashtags), 0, 15),
    'count'    => count($tweets),
], JSON_UNESCAPED_UNICODE);
