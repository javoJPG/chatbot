<?php
header('Content-Type: text/plain; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  echo "Webhook OK 🚀";
  exit;
}

$raw = file_get_contents('php://input');
$data = json_decode($raw, true);

if (!is_array($data)) { echo "Invalid JSON"; exit; }

if (($data['typeWebhook'] ?? '') !== 'incomingMessageReceived') { echo "Ignored"; exit; }

$chatId = $data['senderData']['chatId'] ?? null;
if (!$chatId) { echo "No chatId"; exit; }

// Extraer texto
$typeMessage = $data['messageData']['typeMessage'] ?? '';
$text = null;
if ($typeMessage === 'textMessage') {
  $text = $data['messageData']['textMessageData']['textMessage'] ?? '';
} elseif ($typeMessage === 'extendedTextMessage') {
  $text = $data['messageData']['extendedTextMessageData']['text'] ?? '';
}
if (!$text) $text = "hola";

// --- CONFIG (solo prueba) ---
$GREENAPI_BASE = "https://7105.api.greenapi.com";
$GREENAPI_ID   = "7105422392";
$GREENAPI_TOKEN= "b78d7825622b45ecb0d109b91cfdb360fb995c424a1b493b8b";

// Responder
sendGreenApiMessage($GREENAPI_BASE, $GREENAPI_ID, $GREENAPI_TOKEN, $chatId, "Bot activo ✅\nDijiste: ".$text);
echo "OK";

function sendGreenApiMessage($baseUrl, $idInstance, $token, $chatId, $message) {
  $url = rtrim($baseUrl,'/') . "/waInstance{$idInstance}/sendMessage/{$token}";

  $payload = json_encode(["chatId"=>$chatId, "message"=>$message], JSON_UNESCAPED_UNICODE);

  // sin cURL (simple)
  $opts = ["http" => [
    "method"  => "POST",
    "header"  => "Content-Type: application/json\r\n",
    "content" => $payload,
    "timeout" => 20
  ]];

  @file_get_contents($url, false, stream_context_create($opts));
}
