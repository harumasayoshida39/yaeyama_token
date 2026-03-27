<?php
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);
require_once '../config.php';
require_once __DIR__ . '/solana_transfer.php';

$db = getDB();

// パラメータ取得
$merchant_id = isset($_GET['merchant_id']) ? intval($_GET['merchant_id']) : 0;
$amount      = isset($_GET['amount'])      ? floatval($_GET['amount'])      : 0;
$customer_id = isset($_GET['customer_id']) ? intval($_GET['customer_id'])   : 0;

// バリデーション
$errors = [];
if ($merchant_id <= 0) $errors[] = '店舗情報が不正です。';
if ($amount <= 0)      $errors[] = '金額が不正です。';
if ($customer_id <= 0) $errors[] = 'ログインが必要です。';

$merchant = null;
$customer = null;

if (empty($errors)) {
    // 店舗情報取得
    $stmt = $db->prepare('SELECT * FROM merchants WHERE id = ? AND is_active = 1');
    $stmt->execute([$merchant_id]);
    $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$merchant) $errors[] = '店舗が見つかりません。';

    // 顧客情報取得
    $stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
    $stmt->execute([$customer_id]);
    $customer = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$customer) $errors[] = '顧客情報が見つかりません。';
}

// 残高チェック
if (empty($errors) && $customer['yae_balance'] < $amount) {
    $errors[] = 'YAE残高が不足しています。（残高: ' . number_format($customer['yae_balance'], 1) . ' YAE）';
}

// キャッシュバック計算
$cashback = 0;
if (empty($errors)) {
    $cashback = floor($amount * ($merchant['cashback_rate'] / 100));
}

// POST で支払い実行
$success = false;
$tx_message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    try {
        $db->beginTransaction();

        // ① オンチェーン送金（顧客 → 加盟店）
        $tx_result = solana_transfer_yae(
            $customer['wallet_secret'],
            $customer['wallet_address'],
            $merchant['wallet_address'],
            $amount
        );

        if (!$tx_result['success']) {
            $db->rollBack();
            $errors[] = 'オンチェーン送金失敗: ' . $tx_result['error'];
        } else {
            $tx_sig = $tx_result['signature'];

            // ② DB残高更新
            $new_balance = $customer['yae_balance'] - $amount + $cashback;
            $stmt = $db->prepare('UPDATE customers SET yae_balance = ? WHERE id = ?');
            $stmt->execute([$new_balance, $customer_id]);

            // ③ 来店履歴に記録（txシグネチャも保存）
            $stmt = $db->prepare(
                'INSERT INTO visit_history (customer_id, merchant_id, amount, yae_earned, visited_at)
                 VALUES (?, ?, ?, ?, NOW())'
            );
            $stmt->execute([$customer_id, $merchant_id, $amount, $cashback]);

            $db->commit();
            $success = true;
            $customer['yae_balance'] = $new_balance;
        }

    } catch (Exception $e) {
        $db->rollBack();
        $errors[] = '処理中にエラーが発生しました: ' . $e->getMessage();
        error_log('payment error: ' . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>お支払い - 八重山トークン</title>
<style>
    * { box-sizing: border-box; margin: 0; padding: 0; }
    body { font-family: sans-serif; background: #1a1a2e; color: white; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 24px 16px; }

    .card {
        background: #16213e;
        border-radius: 20px;
        padding: 32px 24px;
        width: 100%;
        max-width: 400px;
        text-align: center;
    }

    /* エラー */
    .error-box {
        background: #2d1515;
        border: 2px solid #f85149;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .error-box .icon { font-size: 40px; margin-bottom: 10px; }
    .error-box h2 { color: #f85149; font-size: 18px; margin-bottom: 8px; }
    .error-box p { font-size: 14px; color: #ccc; line-height: 1.6; }

    /* 成功 */
    .success-box { text-align: center; }
    .success-icon { font-size: 64px; margin-bottom: 12px; animation: pop .4s ease; }
    @keyframes pop { 0%{transform:scale(0.5);opacity:0} 80%{transform:scale(1.1)} 100%{transform:scale(1);opacity:1} }
    .success-title { font-size: 22px; font-weight: bold; margin-bottom: 6px; }
    .success-sub { font-size: 14px; color: #999; margin-bottom: 24px; }

    /* 確認画面 */
    .confirm-title { font-size: 20px; font-weight: bold; margin-bottom: 4px; }
    .shop-name { font-size: 14px; color: #ab9df2; margin-bottom: 24px; }

    .amount-block {
        background: #1a1a2e;
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 12px;
    }
    .amount-label { font-size: 12px; color: #999; margin-bottom: 6px; }
    .amount-num { font-size: 44px; font-weight: bold; letter-spacing: -0.02em; }
    .amount-unit { font-size: 16px; color: #ab9df2; margin-left: 4px; }

    .cashback-block {
        background: #1a2a1a;
        border: 1px solid #3fb950;
        border-radius: 12px;
        padding: 12px 16px;
        margin-bottom: 8px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 14px;
    }
    .cashback-block .label { color: #3fb950; }
    .cashback-block .value { font-weight: bold; color: #3fb950; }

    .balance-info {
        font-size: 13px;
        color: #999;
        margin-bottom: 24px;
        text-align: left;
        padding: 0 4px;
    }
    .balance-info span { color: #ab9df2; font-weight: bold; }

    .pay-btn {
        width: 100%;
        padding: 17px;
        background: linear-gradient(135deg, #512da8, #ab9df2);
        color: white;
        border: none;
        border-radius: 14px;
        font-size: 18px;
        font-weight: bold;
        cursor: pointer;
        margin-bottom: 10px;
        box-shadow: 0 4px 16px rgba(81,45,168,0.4);
        transition: opacity 0.2s;
    }
    .pay-btn:active { opacity: 0.9; }
    .pay-btn:disabled { opacity: 0.5; cursor: not-allowed; }

    .back-btn {
        width: 100%;
        padding: 14px;
        background: transparent;
        border: 2px solid #444;
        border-radius: 12px;
        color: #999;
        font-size: 15px;
        font-weight: bold;
        cursor: pointer;
        text-decoration: none;
        display: block;
    }

    /* 完了後の残高表示 */
    .new-balance {
        background: linear-gradient(135deg, #512da8, #ab9df2);
        border-radius: 14px;
        padding: 20px;
        margin-bottom: 20px;
    }
    .new-balance .label { font-size: 13px; opacity: 0.8; margin-bottom: 4px; }
    .new-balance .num { font-size: 36px; font-weight: bold; }
    .new-balance .unit { font-size: 14px; opacity: 0.8; }

    .detail-row {
        display: flex;
        justify-content: space-between;
        font-size: 14px;
        padding: 10px 0;
        border-bottom: 1px solid #2a2a5a;
        color: #ccc;
    }
    .detail-row:last-child { border-bottom: none; }
    .detail-row .val { font-weight: bold; color: white; }
</style>
</head>
<body>

<div class="card">

<?php if (!empty($errors)): ?>
    <!-- エラー表示 -->
    <div class="error-box">
        <div class="icon">⚠️</div>
        <h2>エラー</h2>
        <?php foreach ($errors as $e): ?>
        <p><?= htmlspecialchars($e) ?></p>
        <?php endforeach; ?>
    </div>
    <a href="mypage.php?id=<?= $customer_id ?>" class="back-btn">← マイページに戻る</a>

<?php elseif ($success): ?>
    <!-- 支払い完了 -->
    <div class="success-box">
        <div class="success-icon">✅</div>
        <div class="success-title">お支払い完了！</div>
        <div class="success-sub"><?= htmlspecialchars($merchant['name']) ?></div>

        <div class="new-balance">
            <div class="label">新しいYAE残高</div>
            <div class="num"><?= number_format($customer['yae_balance'], 1) ?></div>
            <div class="unit">YAE</div>
        </div>

        <div style="margin-bottom:20px;">
            <div class="detail-row">
                <span>お支払い</span>
                <span class="val">-<?= number_format($amount, 1) ?> YAE</span>
            </div>
            <?php if ($cashback > 0): ?>
            <div class="detail-row">
                <span>キャッシュバック</span>
                <span class="val" style="color:#3fb950;">+<?= number_format($cashback, 1) ?> YAE</span>
            </div>
            <?php endif; ?>
        </div>

        <a href="mypage.php?id=<?= $customer_id ?>" class="pay-btn" style="display:block; text-decoration:none; line-height:1.2; padding:17px;">
            マイページに戻る
        </a>
        <?php if (!empty($tx_sig)): ?>
        <div style="margin-top:14px; font-size:11px; color:#666; word-break:break-all;">
            <a href="https://explorer.solana.com/tx/<?= htmlspecialchars($tx_sig) ?>?cluster=devnet"
               target="_blank" style="color:#ab9df2;">
               🔗 Solana Explorerで確認
            </a>
        </div>
        <?php endif; ?>
    </div>

<?php else: ?>
    <!-- 支払い確認画面 -->
    <div class="confirm-title">お支払い確認</div>
    <div class="shop-name"><?= htmlspecialchars($merchant['name']) ?></div>

    <div class="amount-block">
        <div class="amount-label">支払い金額</div>
        <div>
            <span class="amount-num"><?= number_format($amount, 1) ?></span>
            <span class="amount-unit">YAE</span>
        </div>
    </div>

    <?php if ($cashback > 0): ?>
    <div class="cashback-block">
        <span class="label">🎁 キャッシュバック</span>
        <span class="value">+<?= number_format($cashback, 1) ?> YAE</span>
    </div>
    <?php endif; ?>

    <div class="balance-info">
        現在の残高: <span><?= number_format($customer['yae_balance'], 1) ?> YAE</span>
        　→　支払い後: <span><?= number_format($customer['yae_balance'] - $amount + $cashback, 1) ?> YAE</span>
    </div>

    <form method="POST" action="">
        <input type="hidden" name="confirmed" value="1">
        <button type="submit" class="pay-btn" id="payBtn" onclick="this.disabled=true; this.textContent='処理中...'; this.form.submit();">
            支払う
        </button>
    </form>
    <a href="mypage.php?id=<?= $customer_id ?>" class="back-btn">キャンセル</a>

<?php endif; ?>

</div>

</body>
</html>
