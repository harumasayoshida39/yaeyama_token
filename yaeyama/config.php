<?php
// データベース設定
define('DB_HOST', 'mysql3114.db.sakura.ne.jp');
define('DB_USER', 'harumasa_yaeyama_token');
define('DB_PASS', 'aRcoiris0saj732-as_said6chwea-');
define('DB_NAME', 'harumasa_yaeyama_token');

// Solana設定
define('SOLANA_RPC', 'https://api.devnet.solana.com');
define('PROGRAM_ID', '5PAP6AwioCRco33xoFEDSojU6tVbiRg5Bgt8gz5MoTJd');

// DB接続
function getDB() {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8',
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    return $pdo;
}

// Solana RPC呼び出し
function solanaRPC($method, $params = []) {
    $data = json_encode([
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => $method,
        'params' => $params
    ]);
    
    $ch = curl_init(SOLANA_RPC);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $result = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($result, true);
}
?>