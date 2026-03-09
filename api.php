<?php
header('Content-Type: application/json');

// エラー報告を有効にする (デバッグ用)
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

$method = $_SERVER['REQUEST_METHOD'];
$action = $_GET['action'] ?? '';
$token = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

if (empty($token)) {
    http_response_code(401);
    echo json_encode(['error' => 'Authorization token is required']);
    exit;
}

function callApi($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60); // AI生成は時間がかかるため長めに設定
    
    if ($data) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return ['code' => 500, 'data' => json_encode(['error' => $error])];
    }
    
    curl_close($ch);
    return ['code' => $httpCode, 'data' => $response];
}

// 互換性のために古い関数名も維持
function callLineApi($url, $method = 'GET', $headers = [], $data = null) {
    return callApi($url, $method, $headers, $data);
}

switch ($action) {
    case 'list':
        $res = callApi('https://api.line.me/v2/bot/richmenu/list', 'GET', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo $res['data'] ?: json_encode(['richmenus' => []]);
        break;

    case 'create':
        $input = file_get_contents('php://input');
        $res = callApi('https://api.line.me/v2/bot/richmenu', 'POST', [
            "Authorization: $token",
            'Content-Type: application/json'
        ], $input);
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'delete':
        $richMenuId = $_GET['richMenuId'] ?? '';
        $res = callApi("https://api.line.me/v2/bot/richmenu/$richMenuId", 'DELETE', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'upload':
        $richMenuId = $_POST['richMenuId'] ?? '';
        if (isset($_FILES['image']) && $richMenuId) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $fileType = $_FILES['image']['type'];
            
            $res = callApi("https://api-data.line.me/v2/bot/richmenu/$richMenuId/content", 'POST', [
                "Authorization: $token",
                "Content-Type: $fileType"
            ], $imageData);
            
            http_response_code($res['code'] ?: 200);
            echo $res['data'];
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid upload request']);
        }
        break;

    case 'ai_generate':
        $input = json_decode(file_get_contents('php://input'), true);
        $prompt = $input['prompt'] ?? '';
        $guideImagePath = $input['guideImage'] ?? '';
        $model = $input['model'] ?? 'gemini-2.5-flash'; // フロントから指定されたモデルを使用
        $geminiApiKey = $_SERVER['HTTP_X_GEMINI_API_KEY'] ?? '';

        if (!$prompt || !$geminiApiKey) {
            http_response_code(400);
            echo json_encode(['error' => 'Prompt and Gemini API Key are required']);
            exit;
        }

        // ガイド画像を読み込んでBase64化
        $guideImageBase64 = '';
        $mimeType = 'image/png';
        if ($guideImagePath && file_exists($guideImagePath)) {
            $guideImageBase64 = base64_encode(file_get_contents($guideImagePath));
            if (strpos($guideImagePath, '.jpg') !== false || strpos($guideImagePath, '.jpeg') !== false) {
                $mimeType = 'image/jpeg';
            }
        }

        // Gemini API (Google AI) call
        $url = "https://generativelanguage.googleapis.com/v1beta/models/$model:generateContent?key=$geminiApiKey";
        
        $payload = [
            "contents" => [
                [
                    "role" => "user",
                    "parts" => [
                        ["text" => $prompt . "\n\n必ず、生成した画像をPNGバイナリデータ（inline_data）として直接返してください。テキストでの説明は不要です。"],
                        [
                            "inline_data" => [
                                "mime_type" => $mimeType,
                                "data" => $guideImageBase64
                            ]
                        ]
                    ]
                ]
            ],
            "generationConfig" => [
                "temperature" => 0.4,
                "topP" => 0.95,
                "topK" => 40,
                "maxOutputTokens" => 8192,
            ]
        ];

        $res = callApi($url, 'POST', ['Content-Type: application/json'], json_encode($payload));
        
        if ($res['code'] !== 200) {
            http_response_code($res['code']);
            echo $res['data'];
            exit;
        }

        $data = json_decode($res['data'], true);
        $generatedImage = null;
        $responseText = "";
        
        if (isset($data['candidates'][0]['content']['parts'])) {
            foreach ($data['candidates'][0]['content']['parts'] as $part) {
                // 1. 直接的な画像バイナリをチェック
                if (isset($part['inline_data'])) {
                    $generatedImage = 'data:' . $part['inline_data']['mime_type'] . ';base64,' . $part['inline_data']['data'];
                    break;
                }
                // 2. テキストレスポンスを収集
                if (isset($part['text'])) {
                    $responseText .= $part['text'];
                    // テキスト内にBase64が埋め込まれているケースを救済
                    if (preg_match('/data:image\/[a-zA-Z]+;base64,[a-zA-Z0-9+\/]+={0,2}/', $part['text'], $matches)) {
                        $generatedImage = $matches[0];
                        break;
                    }
                }
            }
        }

        if ($generatedImage) {
            echo json_encode(['image' => $generatedImage]);
        } else {
            // 画像が返ってこなかった場合、AIのレスポンス全体を返して原因を特定する
            echo json_encode([
                'error' => 'AI did not generate an image binary.',
                'ai_response' => $responseText ?: 'No text response from AI.',
                'finish_reason' => $data['candidates'][0]['finishReason'] ?? 'UNKNOWN',
                'safety_ratings' => $data['candidates'][0]['safetyRatings'] ?? [],
                'prompt_feedback' => $data['promptFeedback'] ?? 'No feedback',
                'full_raw_response' => $data
            ]);
        }
        break;

    case 'list_models':
        $geminiApiKey = $_SERVER['HTTP_X_GEMINI_API_KEY'] ?? '';
        if (!$geminiApiKey) {
            http_response_code(400);
            echo json_encode(['error' => 'Gemini API Key is required']);
            exit;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models?key=$geminiApiKey";
        $res = callApi($url, 'GET', ['Content-Type: application/json']);
        
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'get_image':
        $richMenuId = $_GET['richMenuId'] ?? '';
        $res = callApi("https://api-data.line.me/v2/bot/richmenu/$richMenuId/content", 'GET', ["Authorization: $token"]);
        
        if ($res['code'] === 200 && !empty($res['data'])) {
            $base64 = base64_encode($res['data']);
            echo json_encode(['image' => "data:image/png;base64,$base64"]);
        } else {
            http_response_code($res['code'] ?: 404);
            echo $res['data'] ?: json_encode(['error' => 'No image content']);
        }
        break;

    case 'get_default':
        $res = callApi('https://api.line.me/v2/bot/user/all/richmenu', 'GET', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'set_default':
        $richMenuId = $_GET['richMenuId'] ?? '';
        $res = callApi("https://api.line.me/v2/bot/user/all/richmenu/$richMenuId", 'POST', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo json_encode(['success' => true]);
        break;

    case 'cancel_default':
        $res = callApi('https://api.line.me/v2/bot/user/all/richmenu', 'DELETE', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
        break;
}
