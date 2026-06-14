<?php
/* ============================================================
   MarketOracle — Oracle AI proxy (server-side, secure)
   Deploy to: public_html/api/oracle.php  on Bluehost
   Then in index.html set:
       AI_CONFIG.endpoint = "https://YOURDOMAIN/api/oracle.php";
       AI_CONFIG.enabled  = true;
   The API key lives ONLY here on the server — never in the browser.
   ============================================================ */

header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");          // tighten to your domain at launch
header("Access-Control-Allow-Headers: Content-Type");
if ($_SERVER["REQUEST_METHOD"] === "OPTIONS") { exit; }

// ---- 1. Your secret key (keep this file private; never commit the real key) ----
$ANTHROPIC_API_KEY = getenv("ANTHROPIC_API_KEY");   // set in Bluehost env, or paste below
// $ANTHROPIC_API_KEY = "sk-ant-...";               // (less safe) hardcode fallback

// ---- 2. Read the request from the dashboard ----
$body   = json_decode(file_get_contents("php://input"), true);
$model  = isset($body["model"])      ? $body["model"]      : "claude-sonnet-4-6";
$prompt = isset($body["prompt"])     ? $body["prompt"]     : "";
$maxTok = isset($body["max_tokens"]) ? (int)$body["max_tokens"] : 700;

if ($prompt === "") { http_response_code(400); echo json_encode(["text" => "Empty prompt."]); exit; }

// ---- 3. Call the Anthropic Messages API ----
$payload = json_encode([
  "model"      => $model,
  "max_tokens" => $maxTok,
  "messages"   => [["role" => "user", "content" => $prompt]]
]);

$ch = curl_init("https://api.anthropic.com/v1/messages");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_POST           => true,
  CURLOPT_POSTFIELDS     => $payload,
  CURLOPT_TIMEOUT        => 30,
  CURLOPT_HTTPHEADER     => [
    "Content-Type: application/json",
    "x-api-key: " . $ANTHROPIC_API_KEY,
    "anthropic-version: 2023-06-01"
  ]
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $code !== 200) {
  http_response_code(502);
  echo json_encode(["text" => "Oracle upstream error (HTTP $code)."]);
  exit;
}

// ---- 4. Return clean text to the dashboard ----
$data = json_decode($resp, true);
$text = "";
if (isset($data["content"])) {
  foreach ($data["content"] as $block) {
    if (($block["type"] ?? "") === "text") { $text .= $block["text"]; }
  }
}
echo json_encode(["text" => $text !== "" ? $text : "No response."]);
