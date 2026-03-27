<?php
// 非推奨警告・通知を非表示（allow_url_includeなどphp.ini由来の警告対策）
error_reporting(E_ALL & ~E_DEPRECATED & ~E_NOTICE);

require_once __DIR__ . '/../config.php';

// 店舗IDをURLパラメータから取得
$merchant_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$error_message = '';
$merchant = null;

if ($merchant_id > 0) {
    try {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $stmt = $pdo->prepare('SELECT * FROM merchants WHERE id = ? AND is_active = 1');
        $stmt->execute([$merchant_id]);
        $merchant = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$merchant) {
            $error_message = '店舗が見つかりません。URLを確認してください。';
        }
    } catch (PDOException $e) {
        $error_message = 'システムエラーが発生しました。しばらくしてから再試行してください。';
    }
} else {
    $error_message = '店舗IDが指定されていません。お店のスタッフにお声がけください。';
}

// エラー時はHTMLエラーページを表示
if ($error_message || !$merchant) {
    ?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>エラー - YAEトークン</title>
<style>
  body { font-family: 'Hiragino Kaku Gothic ProN', sans-serif; background: linear-gradient(160deg,#caf0f8,#fdf4e3); min-height:100vh; display:flex; align-items:center; justify-content:center; padding:24px; }
  .box { background:#fff; border-radius:20px; padding:40px 32px; text-align:center; max-width:360px; box-shadow:0 8px 32px rgba(0,119,182,0.15); }
  .icon { font-size:48px; margin-bottom:16px; }
  h1 { font-size:18px; font-weight:800; color:#023e8a; margin-bottom:10px; }
  p { font-size:14px; color:#6c757d; line-height:1.6; }
</style>
</head>
<body>
<div class="box">
  <div class="icon">⚠️</div>
  <h1>アクセスエラー</h1>
  <p><?= htmlspecialchars($error_message) ?></p>
</div>
</body>
</html>
    <?php
    exit;
}

$site_base = 'https://h-ecocard.com/yaeyama/store';
?>
<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<title><?= htmlspecialchars($merchant['name']) ?> - YAE支払い</title>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
<style>
  :root {
    --ocean: #0077b6;
    --ocean-light: #90e0ef;
    --coral: #f77f00;
    --sand: #fdf4e3;
    --deep: #023e8a;
    --white: #ffffff;
    --gray: #6c757d;
    --light: #f0f8ff;
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
    padding: 24px 16px 40px;
  }

  /* ヘッダー */
  .header {
    text-align: center;
    margin-bottom: 28px;
  }
  .header .logo {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.15em;
    color: var(--ocean);
    text-transform: uppercase;
    margin-bottom: 6px;
  }
  .header .shop-name {
    font-size: 26px;
    font-weight: 800;
    color: var(--deep);
    letter-spacing: -0.02em;
  }

  /* カード */
  .card {
    background: var(--white);
    border-radius: var(--radius);
    box-shadow: var(--shadow);
    width: 100%;
    max-width: 460px;
    padding: 32px 28px;
  }

  /* 金額入力フォーム */
  .amount-section h2 {
    font-size: 14px;
    font-weight: 700;
    color: var(--gray);
    letter-spacing: 0.08em;
    text-transform: uppercase;
    margin-bottom: 16px;
  }

  .amount-input-wrap {
    display: flex;
    align-items: center;
    border: 2.5px solid var(--ocean-light);
    border-radius: 14px;
    padding: 10px 18px;
    background: var(--light);
    transition: border-color 0.2s;
    margin-bottom: 20px;
  }
  .amount-input-wrap:focus-within {
    border-color: var(--ocean);
  }
  .amount-input-wrap .unit {
    font-size: 18px;
    font-weight: 800;
    color: var(--ocean);
    margin-right: 10px;
    line-height: 1;
    white-space: nowrap;
  }
  .amount-input-wrap input[type="number"] {
    font-size: 40px;
    font-weight: 800;
    color: var(--deep);
    border: none;
    background: transparent;
    outline: none;
    width: 100%;
    letter-spacing: -0.02em;
    -moz-appearance: textfield;
  }
  .amount-input-wrap input[type="number"]::-webkit-outer-spin-button,
  .amount-input-wrap input[type="number"]::-webkit-inner-spin-button {
    -webkit-appearance: none;
    margin: 0;
  }

  /* クイック金額ボタン */
  .quick-amounts {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 8px;
    margin-bottom: 24px;
  }
  .quick-btn {
    background: var(--light);
    border: 2px solid var(--ocean-light);
    border-radius: 10px;
    padding: 10px 4px;
    font-size: 13px;
    font-weight: 700;
    color: var(--ocean);
    cursor: pointer;
    transition: all 0.15s;
    text-align: center;
  }
  .quick-btn:hover, .quick-btn:active {
    background: var(--ocean);
    color: var(--white);
    border-color: var(--ocean);
  }

  /* QR生成ボタン */
  .generate-btn {
    width: 100%;
    padding: 18px;
    background: linear-gradient(135deg, var(--ocean) 0%, var(--deep) 100%);
    color: var(--white);
    border: none;
    border-radius: 14px;
    font-size: 18px;
    font-weight: 800;
    letter-spacing: 0.05em;
    cursor: pointer;
    transition: opacity 0.2s, transform 0.1s;
    box-shadow: 0 4px 16px rgba(0,119,182,0.35);
  }
  .generate-btn:active { transform: scale(0.98); opacity: 0.92; }

  /* QR表示エリア */
  .qr-section {
    display: none;
    margin-top: 28px;
    text-align: center;
  }
  .qr-section.visible { display: block; }

  .divider {
    border: none;
    border-top: 2px dashed var(--ocean-light);
    margin: 24px 0;
  }

  .qr-label {
    font-size: 13px;
    font-weight: 700;
    color: var(--gray);
    letter-spacing: 0.1em;
    text-transform: uppercase;
    margin-bottom: 8px;
  }
  .qr-amount-display {
    font-size: 36px;
    font-weight: 900;
    color: var(--deep);
    margin-bottom: 20px;
    letter-spacing: -0.02em;
  }
  .qr-amount-display span {
    font-size: 20px;
    color: var(--ocean);
    margin-left: 6px;
  }

  #qrcode {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: var(--white);
    border: 3px solid var(--ocean-light);
    border-radius: 16px;
    box-shadow: 0 4px 20px rgba(0,119,182,0.12);
  }
  #qrcode canvas, #qrcode img {
    display: block;
  }

  .qr-hint {
    margin-top: 14px;
    font-size: 13px;
    color: var(--gray);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
  }
  .qr-hint::before { content: '📱'; font-size: 16px; }

  /* リセットボタン */
  .reset-btn {
    margin-top: 20px;
    width: 100%;
    padding: 14px;
    background: transparent;
    border: 2.5px solid var(--ocean-light);
    border-radius: 12px;
    font-size: 15px;
    font-weight: 700;
    color: var(--ocean);
    cursor: pointer;
    transition: all 0.15s;
  }
  .reset-btn:hover, .reset-btn:active {
    background: var(--light);
  }

  /* フッター */
  .footer {
    margin-top: 24px;
    font-size: 11px;
    color: var(--gray);
    text-align: center;
    opacity: 0.7;
  }
</style>
</head>
<body>

<div class="header">
  <div class="logo">🌺 YAE Token</div>
  <div class="shop-name"><?= htmlspecialchars($merchant['name']) ?></div>
</div>

<div class="card">

  <!-- 金額入力セクション -->
  <div class="amount-section" id="inputSection">
    <h2>お支払い金額を入力</h2>

    <div class="amount-input-wrap">
      <span class="unit">YAE</span>
      <input type="number" id="amountInput" placeholder="0" min="1" max="99999" inputmode="numeric" step="0.01">
    </div>

    <!-- クイック金額 -->
    <div class="quick-amounts">
      <button class="quick-btn" onclick="setAmount(10)">10 YAE</button>
      <button class="quick-btn" onclick="setAmount(50)">50 YAE</button>
      <button class="quick-btn" onclick="setAmount(100)">100 YAE</button>
      <button class="quick-btn" onclick="setAmount(200)">200 YAE</button>
      <button class="quick-btn" onclick="setAmount(500)">500 YAE</button>
      <button class="quick-btn" onclick="setAmount(1000)">1,000 YAE</button>
      <button class="quick-btn" onclick="setAmount(5000)">5,000 YAE</button>
      <button class="quick-btn" onclick="clearAmount()">クリア</button>
    </div>

    <button class="generate-btn" onclick="generateQR()">
      QRコードを生成する
    </button>
  </div>

  <!-- QR表示セクション -->
  <div class="qr-section" id="qrSection">
    <hr class="divider">
    <div class="qr-label">お客様にスキャンしてもらってください</div>
    <div class="qr-amount-display" id="qrAmountDisplay"></div>
    <div id="qrcode"></div>
    <div class="qr-hint">スマホのカメラでQRコードを読み取ってください</div>
    <button class="reset-btn" onclick="resetQR()">← 金額を変更する</button>
  </div>

</div>

<div class="footer">
  店舗ID: <?= $merchant_id ?> &nbsp;|&nbsp; キャッシュバック率: <?= htmlspecialchars($merchant['cashback_rate']) ?>%
</div>

<script>
const MERCHANT_ID = <?= $merchant_id ?>;
const BASE_URL = '<?= $site_base ?>';

function setAmount(val) {
  document.getElementById('amountInput').value = val;
}

function clearAmount() {
  document.getElementById('amountInput').value = '';
  document.getElementById('amountInput').focus();
}

function generateQR() {
  const amount = parseInt(document.getElementById('amountInput').value, 10);

  if (!amount || amount <= 0) {
    alert('金額を入力してください');
    return;
  }
  if (amount > 99999) {
    alert('金額は99,999 YAE以下で入力してください');
    return;
  }

  // QR内容：payment.phpへのURL
  const paymentUrl = `${BASE_URL}/payment.php?merchant_id=${MERCHANT_ID}&amount=${amount}`;

  // QR生成
  const qrContainer = document.getElementById('qrcode');
  qrContainer.innerHTML = '';
  new QRCode(qrContainer, {
    text: paymentUrl,
    width: 240,
    height: 240,
    colorDark: '#023e8a',
    colorLight: '#ffffff',
    correctLevel: QRCode.CorrectLevel.M
  });

  // 金額表示
  document.getElementById('qrAmountDisplay').innerHTML =
    `${amount.toLocaleString()}<span> YAE</span>`;

  // QRセクション表示
  document.getElementById('qrSection').classList.add('visible');

  // 入力フォームを隠す（オプション：見せたままでも可）
  // document.getElementById('inputSection').style.display = 'none';

  // QR位置にスクロール
  document.getElementById('qrSection').scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function resetQR() {
  document.getElementById('qrSection').classList.remove('visible');
  document.getElementById('qrcode').innerHTML = '';
  document.getElementById('amountInput').value = '';
  document.getElementById('amountInput').focus();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// Enterキーでも生成できるように
document.getElementById('amountInput').addEventListener('keydown', function(e) {
  if (e.key === 'Enter') generateQR();
});
</script>

</body>
</html>
