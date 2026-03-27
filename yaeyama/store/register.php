<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once '../config.php';

$db = getDB();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim(isset($_POST['name']) ? $_POST['name'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');

    if (empty($name)) {
        $error = 'お名前は必須です';
    } elseif (empty($email) && empty($phone)) {
        $error = 'メールアドレスまたは電話番号のどちらかを入力してください';
    } else {
        if (!empty($email)) {
            $stmt = $db->prepare('SELECT id FROM customers WHERE email = ?');
            $stmt->execute(array($email));
            if ($stmt->fetch()) {
                $error = 'このメールアドレスはすでに登録されています';
            }
        }
        if (empty($error) && !empty($phone)) {
            $stmt = $db->prepare('SELECT id FROM customers WHERE phone = ?');
            $stmt->execute(array($phone));
            if ($stmt->fetch()) {
                $error = 'この電話番号はすでに登録されています';
            }
        }

        if (empty($error)) {
            // QRコード用ユニークID生成
            $qr_code = 'YAE' . strtoupper(substr(md5(uniqid(rand(), true)), 0, 10));

            // Solanaキーペア生成（Ed25519）
            $keypair = sodium_crypto_sign_keypair();
            $secret_key = sodium_crypto_sign_secretkey($keypair);
            $public_key = sodium_crypto_sign_publickey($keypair);

            // Base58エンコード
            $wallet_address = base58_encode($public_key);
            $wallet_secret = base58_encode($secret_key);

            try {
                $stmt = $db->prepare('INSERT INTO customers (name, email, phone, qr_code, wallet_address, wallet_secret) VALUES (?, ?, ?, ?, ?, ?)');
                $stmt->execute(array($name, $email ?: null, $phone ?: null, $qr_code, $wallet_address, $wallet_secret));
                $customer_id = $db->lastInsertId();

                header('Location: mypage.php?id=' . $customer_id);
                exit;
            } catch (Exception $e) {
                $error = 'DBエラー: ' . $e->getMessage();
            }
        }
    }
}

// Base58エンコード関数
function base58_encode($data) {
    $alphabet = '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz';
    $base = strlen($alphabet);
    $num = gmp_import($data);
    $result = '';
    while (gmp_cmp($num, 0) > 0) {
        list($num, $remainder) = gmp_div_qr($num, $base);
        $result = $alphabet[gmp_intval($remainder)] . $result;
    }
    for ($i = 0; $i < strlen($data) && $data[$i] === "\x00"; $i++) {
        $result = '1' . $result;
    }
    return $result;
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>会員登録 - 八重山トークン</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #1a1a2e; color: white; min-height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding: 20px; }
        .logo { font-size: 32px; margin-bottom: 8px; }
        .title { font-size: 22px; font-weight: bold; margin-bottom: 4px; }
        .subtitle { font-size: 14px; color: #ab9df2; margin-bottom: 30px; }
        .card { background: white; color: #333; border-radius: 16px; padding: 24px; width: 100%; max-width: 360px; }
        .form-group { margin-bottom: 16px; }
        label { display: block; font-size: 13px; color: #666; margin-bottom: 6px; }
        input { width: 100%; padding: 12px; border: 1px solid #ddd; border-radius: 8px; font-size: 16px; outline: none; }
        input:focus { border-color: #512da8; }
        .btn { width: 100%; background: #512da8; color: white; border: none; border-radius: 8px; padding: 16px; font-size: 18px; font-weight: bold; cursor: pointer; margin-top: 8px; }
        .alert-error { background: #ffebee; color: #c62828; border-radius: 8px; padding: 12px; margin-bottom: 16px; font-size: 14px; }
        .divider { text-align: center; color: #999; font-size: 13px; margin: 16px 0; }
        .login-link { text-align: center; margin-top: 16px; }
        .login-link a { color: #512da8; text-decoration: none; font-size: 14px; }
        .optional { color: #999; font-size: 11px; margin-left: 4px; }
    </style>
</head>
<body>
    <div class="logo">🌺</div>
    <div class="title">八重山トークン</div>
    <div class="subtitle">会員登録して特典をゲット！</div>

    <div class="card">
        <?php if ($error): ?>
        <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>お名前</label>
                <input type="text" name="name" placeholder="山田 太郎" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>">
            </div>
            <div class="form-group">
                <label>メールアドレス <span class="optional">※どちらか必須</span></label>
                <input type="email" name="email" placeholder="example@email.com" value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
            </div>
            <div class="divider">または</div>
            <div class="form-group">
                <label>電話番号</label>
                <input type="tel" name="phone" placeholder="090-1234-5678" value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>" inputmode="numeric">
            </div>
            <button type="submit" class="btn">会員登録する</button>
        </form>

        <div class="login-link">
            <a href="login.php">すでに会員の方はこちら</a>
        </div>
    </div>
</body>
</html>