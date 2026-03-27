<?php
require_once __DIR__ . '/../config.php';

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login_id = trim($_POST['login_id'] ?? '');  // メアド or 電話番号

    if ($login_id === '') {
        $error = 'メールアドレスまたは電話番号を入力してください。';
    } else {
        try {
            $pdo = new PDO(
                'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );

            // メアド or 電話番号で検索
            $stmt = $pdo->prepare(
                'SELECT * FROM customers WHERE email = ? OR phone = ? LIMIT 1'
            );
            $stmt->execute([$login_id, $login_id]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                // 既存システムに合わせて ?id=顧客ID 形式でマイページへ
                header('Location: mypage.php?id=' . $customer['id']);
                exit;
            } else {
                $error = '登録されていません。新規登録してください。';
            }
        } catch (PDOException $e) {
            $error = 'エラーが発生しました。しばらくしてから再試行してください。';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title>ログイン - YAEトークン</title>
<style>
  :root {
    --ocean: #0077b6;
    --ocean-light: #90e0ef;
    --deep: #023e8a;
    --sand: #fdf4e3;
    --coral: #f77f00;
    --white: #ffffff;
    --gray: #6c757d;
    --light: #f0f8ff;
    --error: #e63946;
    --radius: 20px;
    --shadow: 0 8px 32px rgba(0,119,182,0.15);
  }

  * { box-sizing: border-box; margin: 0; padding: 0; }

  body {
    font-family: 'Hiragino Kaku Gothic ProN', 'Noto Sans JP', sans-serif;
    background: linear-gradient(160deg, #caf0f8 0%, #e0f7fa 40%, #fdf4e3 100%);
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    padding: 24px 16px;
  }

  .logo-area {
    text-align: center;
    margin-bottom: 32px;
  }
  .logo-icon {
    font-size: 48px;
    line-height: 1;
    margin-bottom: 8px;
  }
  .logo-text {
    font-size: 22px;
    font-weight: 900;
    color: var(--deep);
    letter-spacing: -0.02em;
  }
  .logo-sub {
    font-size: 12px;
    font-weight: 600;
    color: var(--ocean);
    letter-spacing: 0.15em;
    text-transform: uppercase;
    margin-top: 2px;
  }

  .card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    width: 100%;
    max-width: 420px;
    padding: 36px 28px;
  }

  .card h1 {
    font-size: 20px;
    font-weight: 800;
    color: var(--deep);
    margin-bottom: 6px;
  }
  .card .subtitle {
    font-size: 13px;
    color: var(--gray);
    margin-bottom: 28px;
  }

  .field {
    margin-bottom: 20px;
  }
  .field label {
    display: block;
    font-size: 12px;
    font-weight: 700;
    color: var(--gray);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  .field input {
    width: 100%;
    padding: 14px 16px;
    border: 2.5px solid var(--ocean-light);
    border-radius: 12px;
    font-size: 16px;
    color: var(--deep);
    background: var(--light);
    outline: none;
    transition: border-color 0.2s;
    -webkit-appearance: none;
  }
  .field input:focus {
    border-color: var(--ocean);
    background: var(--white);
  }
  .field input::placeholder {
    color: #adb5bd;
  }

  .error-msg {
    background: #fff0f1;
    border: 2px solid var(--error);
    border-radius: 12px;
    padding: 12px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--error);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 8px;
  }
  .error-msg::before { content: '⚠️'; font-size: 16px; }

  .login-btn {
    width: 100%;
    padding: 17px;
    background: linear-gradient(135deg, var(--ocean) 0%, var(--deep) 100%);
    color: var(--white);
    border: none;
    border-radius: 14px;
    font-size: 17px;
    font-weight: 800;
    letter-spacing: 0.04em;
    cursor: pointer;
    box-shadow: 0 4px 16px rgba(0,119,182,0.35);
    transition: opacity 0.2s, transform 0.1s;
  }
  .login-btn:active { transform: scale(0.98); opacity: 0.9; }

  .divider {
    text-align: center;
    margin: 24px 0;
    position: relative;
  }
  .divider::before {
    content: '';
    position: absolute;
    top: 50%;
    left: 0; right: 0;
    border-top: 2px dashed var(--ocean-light);
  }
  .divider span {
    background: var(--white);
    position: relative;
    padding: 0 12px;
    font-size: 12px;
    color: var(--gray);
    font-weight: 600;
  }

  .register-link {
    display: block;
    width: 100%;
    padding: 15px;
    background: transparent;
    border: 2.5px solid var(--ocean-light);
    border-radius: 14px;
    font-size: 15px;
    font-weight: 700;
    color: var(--ocean);
    text-align: center;
    text-decoration: none;
    transition: all 0.15s;
  }
  .register-link:hover, .register-link:active {
    background: var(--light);
  }
  .register-link small {
    display: block;
    font-size: 11px;
    font-weight: 500;
    color: var(--gray);
    margin-top: 2px;
  }

  .footer {
    margin-top: 28px;
    font-size: 11px;
    color: var(--gray);
    text-align: center;
    opacity: 0.7;
  }
</style>
</head>
<body>

<div class="logo-area">
  <div class="logo-icon">🌺</div>
  <div class="logo-text">八重山トークン</div>
  <div class="logo-sub">YAE Token</div>
</div>

<div class="card">
  <h1>ログイン</h1>
  <p class="subtitle">登録時のメールアドレスまたは電話番号を入力してください</p>

  <?php if ($error): ?>
  <div class="error-msg"><?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST" action="">
    <div class="field">
      <label for="login_id">メールアドレス または 電話番号</label>
      <input
        type="text"
        id="login_id"
        name="login_id"
        placeholder="例）taro@example.com　または　09012345678"
        value="<?= htmlspecialchars($_POST['login_id'] ?? '') ?>"
        autocomplete="username"
        inputmode="email"
        required
      >
    </div>

    <button type="submit" class="login-btn">ログイン →</button>
  </form>

  <div class="divider"><span>はじめての方</span></div>

  <a href="register.php" class="register-link">
    新規会員登録
    <small>無料・約1分で完了します</small>
  </a>
</div>

<div class="footer">
  八重山トークンプロジェクト（YAE）<br>
  Powered by Solana Blockchain
</div>

</body>
</html>