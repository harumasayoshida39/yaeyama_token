<?php
require_once 'config.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $wallet = trim(isset($_POST['wallet_address']) ? $_POST['wallet_address'] : '');
    $cashback = intval(isset($_POST['cashback_rate']) ? $_POST['cashback_rate'] : 0);

    if (empty($name) || empty($wallet)) {
        $error = '店舗名とウォレットアドレスは必須です';
    } elseif ($cashback < 0 || $cashback > 100) {
        $error = 'キャッシュバック率は0〜100で入力してください';
    } else {
        try {
            $db = getDB();
            $stmt = $db->prepare('INSERT INTO merchants (name, wallet_address, cashback_rate) VALUES (?, ?, ?)');
            $stmt->execute(array($name, $wallet, $cashback));
            $success = '加盟店を登録しました！';
        } catch (Exception $e) {
            $error = 'ウォレットアドレスが重複しています';
        }
    }
}

$name_val = isset($_POST['name']) ? htmlspecialchars($_POST['name']) : '';
$wallet_val = isset($_POST['wallet_address']) ? htmlspecialchars($_POST['wallet_address']) : '';
$cashback_val = isset($_POST['cashback_rate']) ? htmlspecialchars($_POST['cashback_rate']) : '0';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>加盟店登録 - 八重山トークン管理</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; }
        .header { background: #1a1a2e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .nav a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 8px; padding: 30px; }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #333; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; box-sizing: border-box; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; }
        .btn-primary { background: #512da8; color: white; width: 100%; }
        .btn-secondary { background: #666; color: white; text-decoration: none; padding: 12px 24px; border-radius: 4px; display: inline-block; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .hint { font-size: 12px; color: #999; margin-top: 4px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🌺 八重山トークン管理</h1>
        <nav class="nav">
            <a href="index.php">ダッシュボード</a>
            <a href="merchants.php">加盟店</a>
            <a href="token_issue.php">トークン発行</a>
        </nav>
    </div>
    <div class="container">
        <h2>加盟店登録</h2>
        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success">
            <?php echo htmlspecialchars($success); ?>
            <a href="merchants.php">一覧に戻る</a>
        </div>
        <?php endif; ?>
        <div class="card">
            <form method="POST">
                <div class="form-group">
                    <label>店舗名</label>
                    <input type="text" name="name" placeholder="例：石垣島カフェ１" value="<?php echo $name_val; ?>">
                </div>
                <div class="form-group">
                    <label>ウォレットアドレス</label>
                    <input type="text" name="wallet_address" placeholder="Solanaウォレットアドレス" value="<?php echo $wallet_val; ?>">
                    <p class="hint">店舗用に生成したSolanaウォレットアドレスを入力してください</p>
                </div>
                <div class="form-group">
                    <label>キャッシュバック率（%）</label>
                    <input type="number" name="cashback_rate" min="0" max="100" value="<?php echo $cashback_val; ?>">
                </div>
                <button type="submit" class="btn btn-primary">登録する</button>
            </form>
        </div>
        <div style="margin-top:20px;">
            <a href="merchants.php" class="btn-secondary">← 一覧に戻る</a>
        </div>
    </div>
</body>
</html>