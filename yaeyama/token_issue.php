<?php
require_once 'config.php';

$db = getDB();
$error = '';
$success = '';

// 加盟店一覧取得
$merchants = $db->query('SELECT * FROM merchants WHERE is_active = 1 ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);

// POST処理（トランザクション成功後にDBに記録）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $merchant_id = intval(isset($_POST['merchant_id']) ? $_POST['merchant_id'] : 0);
    $amount = floatval(isset($_POST['amount']) ? $_POST['amount'] : 0);
    $tx_signature = trim(isset($_POST['tx_signature']) ? $_POST['tx_signature'] : '');

    if ($merchant_id === 0) {
        $error = '加盟店を選択してください';
    } elseif ($amount <= 0) {
        $error = '発行量を入力してください';
    } elseif (empty($tx_signature)) {
        $error = 'トランザクションIDがありません';
    } else {
        try {
            $stmt = $db->prepare('INSERT INTO token_issues (merchant_id, amount, tx_signature) VALUES (?, ?, ?)');
            $stmt->execute(array($merchant_id, $amount, $tx_signature));
            $success = number_format($amount) . ' YAEを発行しました！';
        } catch (Exception $e) {
            $error = 'DBエラー: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>トークン発行 - 八重山トークン管理</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; }
        .header { background: #1a1a2e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .nav a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 600px; margin: 30px auto; padding: 0 20px; }
        .card { background: white; border-radius: 8px; padding: 30px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 6px; font-weight: bold; color: #333; }
        input, select { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px; font-size: 16px; box-sizing: border-box; }
        .btn { padding: 12px 24px; border: none; border-radius: 4px; cursor: pointer; font-size: 16px; width: 100%; }
        .btn-phantom { background: #512da8; color: white; margin-bottom: 10px; }
        .btn-phantom:disabled { background: #999; cursor: not-allowed; }
        .btn-secondary { background: #666; color: white; text-decoration: none; padding: 12px 24px; border-radius: 4px; display: inline-block; }
        .alert { padding: 12px; border-radius: 4px; margin-bottom: 20px; }
        .alert-error { background: #ffebee; color: #c62828; }
        .alert-success { background: #e8f5e9; color: #2e7d32; }
        .hint { font-size: 12px; color: #999; margin-top: 4px; }
        .wallet-status { padding: 10px; border-radius: 4px; margin-bottom: 20px; font-size: 14px; }
        .wallet-connected { background: #e8f5e9; color: #2e7d32; }
        .wallet-disconnected { background: #fff3e0; color: #e65100; }
        .tx-result { background: #f5f5f5; padding: 12px; border-radius: 4px; font-size: 12px; font-family: monospace; word-break: break-all; margin-top: 10px; }
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
        <h2>トークン発行</h2>

        <?php if ($error): ?>
        <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        <?php if ($success): ?>
        <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>

        <div class="card">
            <!-- ウォレット接続状態 -->
            <div id="walletStatus" class="wallet-status wallet-disconnected">
                🔴 Phantomウォレット未接続
            </div>

            <div class="form-group">
                <label>加盟店</label>
                <select id="merchantSelect">
                    <option value="0">-- 選択してください --</option>
                    <?php foreach ($merchants as $m): ?>
                    <option value="<?php echo $m['id']; ?>" data-wallet="<?php echo htmlspecialchars($m['wallet_address']); ?>">
                        <?php echo htmlspecialchars($m['name']); ?> (<?php echo $m['cashback_rate']; ?>%)
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>発行量（YAE）</label>
                <input type="number" id="amount" min="1" step="1" placeholder="例：1000">
                <p class="hint">※1YAE = 1,000,000 lamports（decimals=6）</p>
            </div>

            <!-- Phantom接続・発行ボタン -->
            <button id="connectBtn" class="btn btn-phantom" onclick="connectAndMint()">
                🔗 Phantomで接続してトークン発行
            </button>

            <div id="txResult" style="display:none;">
                <p style="color:#2e7d32; font-weight:bold;">✅ トランザクション成功！</p>
                <div class="tx-result" id="txSignature"></div>
            </div>
        </div>

        <!-- DBへの記録フォーム（JS経由で自動送信） -->
        <form id="saveForm" method="POST" style="display:none;">
            <input type="hidden" name="merchant_id" id="formMerchantId">
            <input type="hidden" name="amount" id="formAmount">
            <input type="hidden" name="tx_signature" id="formTxSignature">
        </form>

        <div style="margin-top:20px;">
            <a href="index.php" class="btn-secondary">← ダッシュボードに戻る</a>
        </div>
    </div>

    <!-- Solana Web3.js -->
	<script src="https://bundle.run/buffer@6.0.3"></script>
	<script>window.Buffer = buffer.Buffer;</script>
    <script src="https://cdn.jsdelivr.net/npm/@solana/web3.js@latest/lib/index.iife.min.js"></script>
    <!-- Anchor IDL用 -->
    <script>
        const PROGRAM_ID = '<?php echo PROGRAM_ID; ?>';
        const MINT_ADDRESS = '<?php echo MINT_ADDRESS; ?>';
        const CONNECTION_URL = 'https://api.devnet.solana.com';

        let walletPublicKey = null;

        // ページ読み込み時にPhantom確認
        window.addEventListener('load', async () => {
            if (window.solana && window.solana.isPhantom) {
                try {
                    const resp = await window.solana.connect({ onlyIfTrusted: true });
                    walletPublicKey = resp.publicKey.toString();
                    updateWalletStatus(true);
                } catch (e) {
                    // 未接続でもOK
                }
            }
        });

        function updateWalletStatus(connected) {
            const status = document.getElementById('walletStatus');
            if (connected) {
                status.className = 'wallet-status wallet-connected';
                status.textContent = '🟢 接続済み: ' + walletPublicKey.slice(0,8) + '...' + walletPublicKey.slice(-8);
            } else {
                status.className = 'wallet-status wallet-disconnected';
                status.textContent = '🔴 Phantomウォレット未接続';
            }
        }

        async function connectAndMint() {
            // 入力チェック
            const merchantSelect = document.getElementById('merchantSelect');
            const amount = document.getElementById('amount').value;

            if (merchantSelect.value === '0') {
                alert('加盟店を選択してください');
                return;
            }
            if (!amount || amount <= 0) {
                alert('発行量を入力してください');
                return;
            }

            const merchantId = merchantSelect.value;
            const merchantWallet = merchantSelect.options[merchantSelect.selectedIndex].dataset.wallet;

            try {
                // Phantom接続
                if (!window.solana || !window.solana.isPhantom) {
                    alert('Phantomウォレットをインストールしてください');
                    return;
                }

                const resp = await window.solana.connect();
                walletPublicKey = resp.publicKey.toString();
                updateWalletStatus(true);

                const connection = new solanaWeb3.Connection(CONNECTION_URL, 'confirmed');
                const mintPubkey = new solanaWeb3.PublicKey(MINT_ADDRESS);
                const authorityPubkey = new solanaWeb3.PublicKey(walletPublicKey);
                const merchantPubkey = new solanaWeb3.PublicKey(merchantWallet);

                // 発行先のトークンアカウントを取得（Associated Token Account）
                const TOKEN_PROGRAM_ID = new solanaWeb3.PublicKey('TokenkegQfeZyiNwAJbNbGKPFXCWuBvf9Ss623VQ5DA');
                //const ASSOCIATED_TOKEN_PROGRAM_ID = new solanaWeb3.PublicKey('ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJe1bJ8');
				const ASSOCIATED_TOKEN_PROGRAM_ID = new solanaWeb3.PublicKey('ATokenGPvbdGVxr1b2hvZbsiqW5xWH25efTNsLJA8knL');



                // ATA導出
                const [merchantTokenAccount] = await solanaWeb3.PublicKey.findProgramAddress(
                    [
                        merchantPubkey.toBuffer(),
                        TOKEN_PROGRAM_ID.toBuffer(),
                        mintPubkey.toBuffer(),
                    ],
                    ASSOCIATED_TOKEN_PROGRAM_ID
                );

                // mint_tokens命令を構築
                const amountLamports = BigInt(Math.floor(amount * 1_000_000));

                // Anchorのdiscriminator計算（mint_tokens）
                //const discriminator = Buffer.from([172, 137, 183, 14, 91, 128, 191, 118]);
				const discriminator = Buffer.from([59, 132, 24, 246, 122, 39, 8, 243]);
                const amountBuffer = Buffer.alloc(8);
                amountBuffer.writeBigUInt64LE(amountLamports);

                const data = Buffer.concat([discriminator, amountBuffer]);

                const instruction = new solanaWeb3.TransactionInstruction({
                    keys: [
                        { pubkey: mintPubkey, isSigner: false, isWritable: true },
                        { pubkey: merchantTokenAccount, isSigner: false, isWritable: true },
                        { pubkey: authorityPubkey, isSigner: true, isWritable: false },
                        { pubkey: TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
                    ],
                    programId: new solanaWeb3.PublicKey(PROGRAM_ID),
                    data: data,
                });

				// ATA作成命令を追加
				const createAtaInstruction = new solanaWeb3.TransactionInstruction({
				    keys: [
				        { pubkey: authorityPubkey, isSigner: true, isWritable: true },
				        { pubkey: merchantTokenAccount, isSigner: false, isWritable: true },
				        { pubkey: merchantPubkey, isSigner: false, isWritable: false },
				        { pubkey: mintPubkey, isSigner: false, isWritable: false },
				        { pubkey: solanaWeb3.SystemProgram.programId, isSigner: false, isWritable: false },
				        { pubkey: TOKEN_PROGRAM_ID, isSigner: false, isWritable: false },
				    ],
				    programId: ASSOCIATED_TOKEN_PROGRAM_ID,
				    data: Buffer.alloc(0),
				});

				const transaction = new solanaWeb3.Transaction()
				    .add(createAtaInstruction)
				    .add(instruction);

                const { blockhash } = await connection.getLatestBlockhash();
                transaction.recentBlockhash = blockhash;
                transaction.feePayer = authorityPubkey;

                // Phantomで署名
                const signed = await window.solana.signTransaction(transaction);
                const signature = await connection.sendRawTransaction(signed.serialize());
                await connection.confirmTransaction(signature, 'confirmed');

                // 成功表示
                document.getElementById('txResult').style.display = 'block';
                document.getElementById('txSignature').textContent = signature;

                // DBに保存
                document.getElementById('formMerchantId').value = merchantId;
                document.getElementById('formAmount').value = amount;
                document.getElementById('formTxSignature').value = signature;
                document.getElementById('saveForm').submit();

            } catch (e) {
                alert('エラー: ' + e.message);
                console.error(e);
            }
        }
    </script>
</body>
</html>