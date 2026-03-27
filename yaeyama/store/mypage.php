<?php
require_once '../config.php';

$db = getDB();

$customer_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($customer_id === 0) {
    header('Location: login.php');
    exit;
}

$stmt = $db->prepare('SELECT * FROM customers WHERE id = ?');
$stmt->execute(array($customer_id));
$customer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$customer) {
    header('Location: login.php');
    exit;
}

// 来店履歴取得
$stmt = $db->prepare('
    SELECT vh.*, m.name as merchant_name, m.cashback_rate
    FROM visit_history vh
    LEFT JOIN merchants m ON vh.merchant_id = m.id
    WHERE vh.customer_id = ?
    ORDER BY vh.visited_at DESC
    LIMIT 20
');
$stmt->execute(array($customer_id));
$history = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 統計
$stmt = $db->prepare('SELECT COUNT(*) as visit_count, COALESCE(SUM(amount),0) as total_amount, COALESCE(SUM(yae_earned),0) as total_yae FROM visit_history WHERE customer_id = ?');
$stmt->execute(array($customer_id));
$stats = $stmt->fetch(PDO::FETCH_ASSOC);

// 店舗別統計
$stmt = $db->prepare('
    SELECT m.name as merchant_name, COUNT(*) as visit_count, COALESCE(SUM(vh.amount),0) as total_amount
    FROM visit_history vh
    LEFT JOIN merchants m ON vh.merchant_id = m.id
    WHERE vh.customer_id = ?
    GROUP BY vh.merchant_id
    ORDER BY visit_count DESC
');
$stmt->execute(array($customer_id));
$merchant_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0">
    <title>マイページ - 八重山トークン</title>
    <!-- jsQR（ローカル設置） -->
    <script src="jsQR.js"></script>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body { font-family: sans-serif; background: #1a1a2e; color: white; min-height: 100vh; padding-bottom: 40px; }
        .header { background: #16213e; padding: 20px; text-align: center; border-bottom: 1px solid #2a2a5a; }
        .header .logo { font-size: 24px; }
        .header .name { font-size: 18px; font-weight: bold; margin-top: 4px; }
        .container { max-width: 400px; margin: 0 auto; padding: 20px; }

        /* QRカード */
        .qr-card { background: white; color: #333; border-radius: 16px; padding: 24px; text-align: center; margin-bottom: 20px; }
        .qr-card .member-id { font-size: 12px; color: #999; margin-bottom: 12px; }
        .qr-card canvas { margin: 0 auto; display: block; }
        .qr-card .hint { font-size: 12px; color: #666; margin-top: 12px; }

        /* QRスキャンボタン */
        .scan-btn {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, #0077b6, #023e8a);
            color: white;
            border: none;
            border-radius: 14px;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            box-shadow: 0 4px 20px rgba(0,119,182,0.4);
            transition: opacity 0.2s, transform 0.1s;
        }
        .scan-btn:active { transform: scale(0.98); opacity: 0.9; }
        .scan-btn .icon { font-size: 24px; }

        /* スキャナーモーダル */
        .scanner-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 1000;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .scanner-overlay.active { display: flex; }

        .scanner-box {
            background: #16213e;
            border-radius: 20px;
            padding: 24px;
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .scanner-title {
            font-size: 18px;
            font-weight: bold;
            margin-bottom: 6px;
        }
        .scanner-sub {
            font-size: 13px;
            color: #999;
            margin-bottom: 20px;
        }

        #qr-reader {
            border-radius: 12px;
            overflow: hidden;
            width: 100% !important;
        }
        /* html5-qrcode のデフォルトUIを上書き */
        #qr-reader > img { display: none !important; }
        #qr-reader__scan_region { border-radius: 12px; overflow: hidden; }
        #qr-reader__dashboard { display: none !important; }

        .scanner-cancel {
            margin-top: 16px;
            width: 100%;
            padding: 14px;
            background: transparent;
            border: 2px solid #444;
            border-radius: 12px;
            color: #999;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.15s;
        }
        .scanner-cancel:hover { border-color: #666; color: #ccc; }

        /* 支払い確認モーダル */
        .confirm-overlay {
            display: none;
            position: fixed;
            inset: 0;
            background: rgba(0,0,0,0.92);
            z-index: 1001;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .confirm-overlay.active { display: flex; }

        .confirm-box {
            background: #16213e;
            border-radius: 20px;
            padding: 32px 24px;
            width: 100%;
            max-width: 380px;
            text-align: center;
        }
        .confirm-box .icon { font-size: 40px; margin-bottom: 12px; }
        .confirm-box h2 { font-size: 20px; font-weight: bold; margin-bottom: 6px; }
        .confirm-box .shop-name { font-size: 15px; color: #ab9df2; margin-bottom: 20px; }
        .confirm-box .amount-display {
            background: #1a1a2e;
            border-radius: 14px;
            padding: 20px;
            margin-bottom: 8px;
        }
        .confirm-box .amount-label { font-size: 12px; color: #999; margin-bottom: 6px; }
        .confirm-box .amount-num { font-size: 44px; font-weight: bold; letter-spacing: -0.02em; }
        .confirm-box .amount-unit { font-size: 16px; color: #ab9df2; margin-left: 4px; }
        .confirm-box .balance-info { font-size: 13px; color: #999; margin-bottom: 24px; }
        .confirm-box .balance-info span { color: #ab9df2; font-weight: bold; }

        .confirm-btn {
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
        .confirm-btn:active { opacity: 0.9; }
        .confirm-cancel {
            width: 100%;
            padding: 14px;
            background: transparent;
            border: 2px solid #444;
            border-radius: 12px;
            color: #999;
            font-size: 15px;
            font-weight: bold;
            cursor: pointer;
        }

        /* エラーメッセージ */
        .scan-error {
            display: none;
            background: #2d1515;
            border: 2px solid #f85149;
            border-radius: 12px;
            padding: 12px 16px;
            font-size: 13px;
            color: #f85149;
            text-align: center;
            margin-bottom: 16px;
        }
        .scan-error.active { display: block; }

        /* 統計カード */
        .stats { display: flex; gap: 12px; margin-bottom: 20px; }
        .stat-card { background: #16213e; border-radius: 12px; padding: 16px; flex: 1; text-align: center; }
        .stat-card .value { font-size: 28px; font-weight: bold; color: #ab9df2; }
        .stat-card .label { font-size: 11px; color: #999; margin-top: 4px; }

        /* YAE残高 */
        .yae-card { background: linear-gradient(135deg, #512da8, #ab9df2); border-radius: 16px; padding: 20px; margin-bottom: 20px; text-align: center; }
        .yae-card .yae-label { font-size: 14px; opacity: 0.8; }
        .yae-card .yae-amount { font-size: 40px; font-weight: bold; margin: 8px 0; }
        .yae-card .yae-unit { font-size: 16px; opacity: 0.8; }

        /* 店舗別 */
        .section-title { font-size: 16px; font-weight: bold; margin-bottom: 12px; color: #ab9df2; }
        .merchant-card { background: #16213e; border-radius: 12px; padding: 16px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .merchant-name { font-size: 15px; font-weight: bold; }
        .merchant-visits { font-size: 12px; color: #999; margin-top: 2px; }
        .merchant-amount { font-size: 16px; font-weight: bold; color: #ab9df2; }

        /* 履歴 */
        .history-item { background: #16213e; border-radius: 12px; padding: 14px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center; }
        .history-left .merchant { font-size: 14px; font-weight: bold; }
        .history-left .date { font-size: 12px; color: #999; margin-top: 2px; }
        .history-right { text-align: right; }
        .history-right .amount { font-size: 16px; font-weight: bold; }
        .history-right .yae { font-size: 12px; color: #ab9df2; }
        .empty { text-align: center; color: #666; padding: 20px; font-size: 14px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="logo">🌺</div>
        <div class="name"><?php echo htmlspecialchars($customer['name']); ?> さん</div>
    </div>

    <div class="container">

        <!-- QRカード（会員証） -->
        <div class="qr-card">
            <div class="member-id">会員ID: <?php echo htmlspecialchars($customer['qr_code']); ?></div>
            <div id="qrCanvas" style="display:inline-block;"></div>
            <div class="hint">お店でこのQRを提示してください</div>
        </div>

        <!-- QRスキャンボタン -->
        <button class="scan-btn" onclick="openScanner()">
            <span class="icon">📷</span>
            お店のQRをスキャンして支払う
        </button>

        <!-- YAE残高 -->
        <div class="yae-card">
            <div class="yae-label">YAE残高</div>
            <div class="yae-amount"><?php echo number_format($customer['yae_balance'], 1); ?></div>
            <div class="yae-unit">YAE</div>
        </div>

        <!-- 統計 -->
        <div class="stats">
            <div class="stat-card">
                <div class="value"><?php echo $stats['visit_count']; ?></div>
                <div class="label">来店回数</div>
            </div>
            <div class="stat-card">
                <div class="value"><?php echo number_format($stats['total_amount']); ?></div>
                <div class="label">累計利用(YAE)</div>
            </div>
        </div>

        <!-- 店舗別統計 -->
        <?php if (!empty($merchant_stats)): ?>
        <div class="section-title">🏪 店舗別利用状況</div>
        <?php foreach ($merchant_stats as $ms): ?>
        <div class="merchant-card">
            <div>
                <div class="merchant-name"><?php echo htmlspecialchars($ms['merchant_name']); ?></div>
                <div class="merchant-visits"><?php echo $ms['visit_count']; ?>回来店</div>
            </div>
            <div class="merchant-amount"><?php echo number_format($ms['total_amount']); ?> YAE</div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

        <!-- 来店履歴 -->
        <div class="section-title" style="margin-top:20px;">📋 来店履歴</div>
        <?php if (empty($history)): ?>
        <div class="empty">まだ来店履歴がありません</div>
        <?php else: ?>
        <?php foreach ($history as $h): ?>
        <div class="history-item">
            <div class="history-left">
                <div class="merchant"><?php echo htmlspecialchars($h['merchant_name']); ?></div>
                <div class="date"><?php echo $h['visited_at']; ?></div>
            </div>
            <div class="history-right">
                <div class="amount"><?php echo number_format($h['amount']); ?> YAE</div>
                <div class="yae">+<?php echo number_format($h['yae_earned']); ?> YAE獲得</div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>

    </div>

    <!-- ===== QRスキャナーモーダル ===== -->
    <div class="scanner-overlay" id="scannerOverlay">
        <div class="scanner-box">
            <div class="scanner-title">📷 QRコードをスキャン</div>
            <div class="scanner-sub">お店の画面にカメラを向けてください</div>
            <div class="scan-error" id="scanError"></div>
            <div style="position:relative; border-radius:12px; overflow:hidden; background:#000;">
                <video id="scanVideo" playsinline autoplay muted
                    style="width:100%; display:block; max-height:55vh; object-fit:cover;"></video>
                <canvas id="scanCanvas" style="display:none;"></canvas>
                <div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;pointer-events:none;z-index:1;">
                    <div style="width:200px;height:200px;border:3px solid #48cae4;border-radius:12px;"></div>
                </div>
            </div>
            <button class="scanner-cancel" id="cancelScanBtn" onclick="closeScanner(); return false;">キャンセル</button>
        </div>
    </div>

    <!-- ===== 支払い確認モーダル ===== -->
    <div class="confirm-overlay" id="confirmOverlay">
        <div class="confirm-box">
            <div class="icon">💳</div>
            <h2>お支払い確認</h2>
            <div class="shop-name" id="confirmShopName">読み込み中...</div>
            <div class="amount-display">
                <div class="amount-label">支払い金額</div>
                <div>
                    <span class="amount-num" id="confirmAmount">0</span>
                    <span class="amount-unit">YAE</span>
                </div>
            </div>
            <div class="balance-info">
                現在の残高: <span><?php echo number_format($customer['yae_balance'], 1); ?> YAE</span>
            </div>
            <button class="confirm-btn" onclick="doPayment()">支払う</button>
            <button class="confirm-cancel" onclick="closeConfirm()">キャンセル</button>
        </div>
    </div>

    <script src="qrcode.min.js"></script>
    <script src="jsQR.js"></script>
    <script>
        // 会員証QR生成
        new QRCode(document.getElementById('qrCanvas'), {
            text: '<?php echo $customer['qr_code']; ?>',
            width: 200,
            height: 200,
            colorDark: '#1a1a2e',
            colorLight: '#ffffff'
        });

        // ===== QRスキャナー（jsQR + getUserMedia）=====
        const CUSTOMER_ID = <?php echo $customer_id; ?>;
        let scanStream = null;
        let scanRafId = null;
        let scannedMerchantId = null;
        let scannedAmount = null;

        function openScanner() {
            document.getElementById('scanError').classList.remove('active');
            document.getElementById('scannerOverlay').classList.add('active');
            startCamera();
        }

        function closeScanner() {
            document.getElementById('scannerOverlay').classList.remove('active');
            stopCamera();
        }

        function startCamera() {
            var video = document.getElementById('scanVideo');
            navigator.mediaDevices.getUserMedia({video: { facingMode: 'environment' }})
            .then(function(stream) {
                scanStream = stream;
                video.srcObject = stream;
                video.onloadedmetadata = function() {
                    video.play();
                    scanRafId = requestAnimationFrame(scanTick);
                };
            })
            .catch(function(err) {
                showScanError('カメラを起動できませんでした。ブラウザのカメラ許可を確認してください。');
                console.error(err);
            });
        }

        function stopCamera() {
            if (scanRafId) { cancelAnimationFrame(scanRafId); scanRafId = null; }
            if (scanStream) {
                scanStream.getTracks().forEach(function(t) { t.stop(); });
                scanStream = null;
            }
            var video = document.getElementById('scanVideo');
            if (video) video.srcObject = null;
        }

        function scanTick() {
            if (!scanStream) return;
            var video  = document.getElementById('scanVideo');
            var canvas = document.getElementById('scanCanvas');
            if (video.readyState === video.HAVE_ENOUGH_DATA) {
                canvas.width  = video.videoWidth;
                canvas.height = video.videoHeight;
                var ctx = canvas.getContext('2d');
                ctx.drawImage(video, 0, 0);
                var imageData = ctx.getImageData(0, 0, canvas.width, canvas.height);
                var code = jsQR(imageData.data, imageData.width, imageData.height);
                if (code && code.data) {
                    onScanSuccess(code.data);
                    return;
                }
            }
            scanRafId = requestAnimationFrame(scanTick);
        }

        function onScanSuccess(data) {
            stopCamera();
            document.getElementById('scannerOverlay').classList.remove('active');

            try {
                let url;
                if (data.startsWith('http')) {
                    url = new URL(data);
                } else {
                    url = new URL('https://dummy.com/' + data);
                }
                const merchantId = url.searchParams.get('merchant_id');
                const amount     = url.searchParams.get('amount');

                if (!merchantId || !amount) {
                    alert('このQRコードは対応していません。');
                    return;
                }
                scannedMerchantId = merchantId;
                scannedAmount     = parseFloat(amount);
                fetchMerchantName(merchantId);
            } catch(e) {
                alert('QRコードの読み取りに失敗しました。');
            }
        }

        function fetchMerchantName(merchantId) {
            fetch('get_merchant.php?id=' + encodeURIComponent(merchantId))
                .then(r => r.json())
                .then(data => {
                    document.getElementById('confirmShopName').textContent = data.name || '店舗ID: ' + merchantId;
                    document.getElementById('confirmAmount').textContent = scannedAmount.toLocaleString();
                    document.getElementById('confirmOverlay').classList.add('active');
                })
                .catch(() => {
                    document.getElementById('confirmShopName').textContent = '店舗ID: ' + merchantId;
                    document.getElementById('confirmAmount').textContent = scannedAmount.toLocaleString();
                    document.getElementById('confirmOverlay').classList.add('active');
                });
        }

        function closeConfirm() {
            document.getElementById('confirmOverlay').classList.remove('active');
            scannedMerchantId = null;
            scannedAmount = null;
        }

        function doPayment() {
            if (!scannedMerchantId || !scannedAmount) return;
            window.location.href = `payment.php?merchant_id=${scannedMerchantId}&amount=${scannedAmount}&customer_id=${CUSTOMER_ID}`;
        }

        function showScanError(msg) {
            const el = document.getElementById('scanError');
            el.textContent = msg;
            el.classList.add('active');
        }
    </script>
</body>
</html>