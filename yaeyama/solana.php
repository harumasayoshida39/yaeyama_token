<?php
require_once 'config.php';

// SOL残高を取得
function getBalance($address) {
    $result = solanaRPC('getBalance', [$address]);
    if (isset($result['result']['value'])) {
        return $result['result']['value'] / 1000000000; // lamports → SOL
    }
    return 0;
}

// トークン残高を取得
function getTokenBalance($address, $mintAddress) {
    $result = solanaRPC('getTokenAccountsByOwner', [
        $address,
        ['mint' => $mintAddress],
        ['encoding' => 'jsonParsed']
    ]);
    
    if (isset($result['result']['value'][0])) {
        $amount = $result['result']['value'][0]['account']['data']['parsed']['info']['tokenAmount']['uiAmount'];
        return $amount ?? 0;
    }
    return 0;
}

// トランザクション履歴を取得
function getTransactionHistory($address, $limit = 10) {
    $result = solanaRPC('getSignaturesForAddress', [
        $address,
        ['limit' => $limit]
    ]);
    return $result['result'] ?? [];
}

// Mint情報を取得
function getMintInfo($mintAddress) {
    $result = solanaRPC('getAccountInfo', [
        $mintAddress,
        ['encoding' => 'jsonParsed']
    ]);
    return $result['result']['value'] ?? null;
}
?>