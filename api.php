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

function callLineApi($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30); // タイムアウト設定
    
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

switch ($action) {
    case 'list':
        $res = callLineApi('https://api.line.me/v2/bot/richmenu/list', 'GET', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo $res['data'] ?: json_encode(['richmenus' => []]);
        break;

    case 'create':
        $input = file_get_contents('php://input');
        $res = callLineApi('https://api.line.me/v2/bot/richmenu', 'POST', [
            "Authorization: $token",
            'Content-Type: application/json'
        ], $input);
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'delete':
        $richMenuId = $_GET['richMenuId'] ?? '';
        $res = callLineApi("https://api.line.me/v2/bot/richmenu/$richMenuId", 'DELETE', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'upload':
        $richMenuId = $_POST['richMenuId'] ?? '';
        if (isset($_FILES['image']) && $richMenuId) {
            $imageData = file_get_contents($_FILES['image']['tmp_name']);
            $fileType = $_FILES['image']['type'];
            
            $res = callLineApi("https://api-data.line.me/v2/bot/richmenu/$richMenuId/content", 'POST', [
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

    case 'upload_template':
        $richMenuId = $_GET['richMenuId'] ?? '';
        $templatePath = $_GET['path'] ?? '';
        
        if ($richMenuId && $templatePath && file_exists($templatePath)) {
            $imageData = file_get_contents($templatePath);
            $fileType = 'image/png';
            if (strpos($templatePath, '.jpg') !== false || strpos($templatePath, '.jpeg') !== false) {
                $fileType = 'image/jpeg';
            }
            
            $res = callLineApi("https://api-data.line.me/v2/bot/richmenu/$richMenuId/content", 'POST', [
                "Authorization: $token",
                "Content-Type: $fileType"
            ], $imageData);
            
            http_response_code($res['code'] ?: 200);
            echo $res['data'];
        } else {
            http_response_code(400);
            echo json_encode(['error' => 'Template path not found or invalid ID']);
        }
        break;

    case 'get_image':
        $richMenuId = $_GET['richMenuId'] ?? '';
        $res = callLineApi("https://api-data.line.me/v2/bot/richmenu/$richMenuId/content", 'GET', ["Authorization: $token"]);
        
        if ($res['code'] === 200 && !empty($res['data'])) {
            $base64 = base64_encode($res['data']);
            echo json_encode(['image' => "data:image/png;base64,$base64"]);
        } else {
            http_response_code($res['code'] ?: 404);
            echo $res['data'] ?: json_encode(['error' => 'No image content']);
        }
        break;

    case 'get_default':
        // デフォルトのリッチメニューIDを取得
        $res = callLineApi('https://api.line.me/v2/bot/user/all/richmenu', 'GET', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo $res['data'];
        break;

    case 'set_default':
        // 特定のリッチメニューをデフォルトに設定
        $richMenuId = $_GET['richMenuId'] ?? '';
        $res = callLineApi("https://api.line.me/v2/bot/user/all/richmenu/$richMenuId", 'POST', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo json_encode(['success' => true]);
        break;

    case 'cancel_default':
        // デフォルト設定を解除
        $res = callLineApi('https://api.line.me/v2/bot/user/all/richmenu', 'DELETE', ["Authorization: $token"]);
        http_response_code($res['code'] ?: 200);
        echo json_encode(['success' => true]);
        break;

    default:
        http_response_code(404);
        echo json_encode(['error' => 'Action not found']);
        break;
}
