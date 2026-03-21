<?php
require_once 'config.php';
require_once 'solana.php';

$db = getDB();

// 加盟店一覧取得
$merchants = $db->query('SELECT * FROM merchants ORDER BY created_at DESC')->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="ja">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>加盟店一覧 - 八重山トークン管理</title>
    <style>
        body { font-family: sans-serif; margin: 0; background: #f5f5f5; }
        .header { background: #1a1a2e; color: white; padding: 15px 30px; display: flex; justify-content: space-between; align-items: center; }
        .header h1 { margin: 0; font-size: 20px; }
        .nav a { color: white; text-decoration: none; margin-left: 20px; }
        .container { max-width: 1000px; margin: 30px auto; padding: 0 20px; }
        .btn { padding: 8px 16px; border: none; border-radius: 4px; cursor: pointer; text-decoration: none; display: inline-block; }
        .btn-primary { background: #512da8; color: white; }
        .btn-success { background: #2e7d32; color: white; }
        .btn-danger { background: #c62828; color: white; }
        table { width: 100%; border-collapse: collapse; background: white; border-radius: 8px; overflow: hidden; }
        th { background: #1a1a2e; color: white; padding: 12px; text-align: left; }
        td { padding: 12px; border-bottom: 1px solid #eee; }
        tr:last-child td { border-bottom: none; }
        .badge { padding: 3px 8px; border-radius: 12px; font-size: 12px; }
        .badge-active { background: #e8f5e9; color: #2e7d32; }
        .badge-inactive { background: #ffebee; color: #c62828; }
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
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
            <h2>加盟店一覧</h2>
            <a href="merchant_add.php" class="btn btn-primary">+ 新規登録</a>
        </div>
        <table>
            <tr>
                <th>店舗名</th>
                <th>ウォレットアドレス</th>
                <th>キャッシュバック率</th>
                <th>状態</th>
                <th>登録日</th>
                <th>操作</th>
            </tr>
            <?php if (empty($merchants)): ?>
            <tr>
                <td colspan="6" style="text-align:center; color:#999;">加盟店がまだ登録されていません</td>
            </tr>
            <?php else: ?>
            <?php foreach ($merchants as $m): ?>
            <tr>
                <td><?= htmlspecialchars($m['name']) ?></td>
                <td style="font-size:12px; font-family:monospace;">
                    <?= substr($m['wallet_address'], 0, 8) ?>...<?= substr($m['wallet_address'], -8) ?>
                </td>
                <td><?= $m['cashback_rate'] ?>%</td>
                <td>
                    <span class="badge <?= $m['is_active'] ? 'badge-active' : 'badge-inactive' ?>">
                        <?= $m['is_active'] ? '有効' : '無効' ?>
                    </span>
                </td>
                <td><?= $m['created_at'] ?></td>
                <td>
                    <a href="merchant_edit.php?id=<?= $m['id'] ?>" class="btn btn-success" style="font-size:12px;">編集</a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </table>
    </div>
</body>
</html>