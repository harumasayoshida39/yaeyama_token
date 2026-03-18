# 八重山トークン Docker開発環境

## 📋 概要

このDocker環境は、八重山トークンプロジェクトでSPLトークン機能を実装するために構築されました。

### 解決する問題

**問題:** 
- Solana 1.18.26に内蔵のrustc 1.75.0-devでは、anchor-spl 0.28.0が要求するindexmap 2.12.0（rustc 1.82+が必要）がビルドできない

**解決策:**
- Anchor 0.30.1以降は**System Rust**を使用する
- Docker環境でrustc 1.82+をインストールすることで、anchor-splが使用可能になる

### 環境構成

| ソフトウェア | バージョン | 備考 |
|------------|----------|------|
| Ubuntu | 22.04 | ベースOS |
| Rust | 1.82.0 | System Rust（Anchor 0.30.1が使用） |
| Solana CLI | 2.1.5 | 最新安定版 |
| Anchor CLI | 0.30.1 | System Rustを使用する最新版 |
| anchor-spl | 0.30.1 | SPLトークン機能 |
| Node.js | 20.x | TypeScriptテスト用 |

## 🚀 セットアップ手順

### 前提条件

1. **Docker Desktop for Windows**
   - https://www.docker.com/products/docker-desktop/
   - WSL2バックエンドを有効化

2. **十分なディスク容量**
   - Dockerイメージ: 約3GB
   - ビルドキャッシュ: 約1-2GB

### Step 1: ファイルの配置

以下のファイルを `C:\Users\harum\yaeyama_token\docker\` に配置:
```
C:\Users\harum\yaeyama_token\docker\
├── Dockerfile
├── docker-compose.yml
├── test_environment.sh
└── SETUP_GUIDE.ps1
```

### Step 2: Docker環境のビルド

PowerShellで実行:
```powershell
cd C:\Users\harum\yaeyama_token\docker
docker-compose build
```

**所要時間:** 初回は10-15分程度

### Step 3: 環境の検証
```powershell
# コンテナを起動
docker-compose up -d

# 環境テストを実行
docker-compose exec solana-dev bash /workspace/test_environment.sh
```

**期待される結果:**
```
==========================================
✅ ビルド成功！
==========================================

この環境は八重山トークンプロジェクトに使用できます。

確認されたバージョン:
- Rust: rustc 1.82.0
- Solana: solana-cli 2.1.5
- Anchor: anchor-cli 0.30.1
- anchor-spl: 0.30.1
```

## 💻 使用方法

### コンテナに入る
```powershell
docker-compose exec solana-dev bash
```

### プロジェクトのビルド（コンテナ内）
```bash
cd /workspace/project

# Cargo.tomlを確認・編集
cat programs/yaeyama_token/Cargo.toml

# anchor-splが追加されているか確認
# なければ追加:
# [dependencies]
# anchor-lang = "0.30.1"
# anchor-spl = "0.30.1"

# ビルド実行
anchor build
```

### ウォレットの設定（初回のみ）
```bash
# 新しいウォレットを生成
solana-keygen new -o ~/.config/solana/id.json

# または既存のウォレットをコピー
# Windowsから: docker cp を使用
```

### devnetの設定
```bash
# devnetに接続
solana config set --url https://api.devnet.solana.com

# エアドロップ（テスト用SOL）
solana airdrop 2
```

## 📁 ファイル構成

### マウントポイント

- **プロジェクトフォルダ:** 
  - ホスト: `C:\Users\harum\yaeyama_token`
  - コンテナ: `/workspace/project`

- **Cargoキャッシュ:**
  - Dockerボリューム: `cargo-cache`
  - ビルド高速化のため

- **ターゲットキャッシュ:**
  - Dockerボリューム: `target-cache`
  - ビルド成果物の保存

### 編集ワークフロー

1. **Windowsでコード編集**
   - VS Code等で `C:\Users\harum\yaeyama_token` を開く
   - ファイルの変更は即座にコンテナに反映

2. **コンテナでビルド**
   - `docker-compose exec solana-dev bash`
   - `anchor build`

## 🔧 トラブルシューティング

### ビルドエラー: "requires rustc 1.82 or newer"

**原因:** Anchor 0.30.1がSystem Rustを使用していない

**解決策:**
```bash
# コンテナ内でRustバージョン確認
rustc --version
# 期待: rustc 1.82.0

# Anchorバージョン確認
anchor --version
# 期待: anchor-cli 0.30.1
```

### ビルドエラー: "indexmap requires rustc 1.82"

**原因:** Cargo.tomlのバージョン不整合

**解決策:**
```toml
# programs/yaeyama_token/Cargo.toml
[dependencies]
anchor-lang = "0.30.1"  # 0.28.0ではなく0.30.1
anchor-spl = "0.30.1"   # 0.28.0ではなく0.30.1
```

### コンテナが起動しない
```powershell
# コンテナのログを確認
docker-compose logs

# コンテナを再構築
docker-compose down
docker-compose build --no-cache
docker-compose up -d
```

### ディスク容量不足
```powershell
# 未使用のDockerリソースを削除
docker system prune -a

# ボリュームも削除（注意: キャッシュが消えます）
docker volume prune
```

## 📝 重要な技術ノート

### Anchor 0.30.x の重要な変更

**0.30.0以前:**
- `anchor build` は Solana内蔵rustcを使用
- Solanaバージョンによってrustcバージョンが固定される
- rustc 1.75.0-dev等の古いバージョンでは最新のanchor-splが動作しない

**0.30.0以降:**
- `anchor build` は **System Rust**を使用
- `rustup`でインストールしたrustcを使用
- これにより、最新のrustc（1.82+）が使用可能になる

### なぜDocker環境が必要か

1. **環境の固定化**
   - 動作する環境を確実に再現
   - ホストOSの影響を受けない

2. **バージョン管理**
   - 複数のプロジェクトで異なるバージョンを使用可能
   - システムを汚さない

3. **チーム開発**
   - 全員が同じ環境で開発できる
   - "私の環境では動く"問題の解消

## 🎯 次のステップ

### 1. プロジェクトのアップグレード
```bash
# programs/yaeyama_token/Cargo.toml を編集
[package]
name = "yaeyama_token"
version = "0.1.0"
description = "Created with Anchor"
edition = "2021"

[lib]
crate-type = ["cdylib", "lib"]
name = "yaeyama_token"

[features]
no-entrypoint = []
no-idl = []
no-log-ix-name = []
cpi = ["no-entrypoint"]
default = []

[dependencies]
anchor-lang = "0.30.1"
anchor-spl = "0.30.1"  # ← これで動作する！
```

### 2. SPLトークン機能の実装
```rust
use anchor_lang::prelude::*;
use anchor_spl::token::{self, Token, TokenAccount, Mint, Transfer};

// トークンの発行
pub fn mint_token(ctx: Context<MintToken>, amount: u64) -> Result<()> {
    // 実装
}

// トークンの転送
pub fn transfer_token(ctx: Context<TransferToken>, amount: u64) -> Result<()> {
    // 実装
}
```

### 3. QR決済機能の設計

- QRコード生成ロジック
- 支払い検証
- トランザクション記録

### 4. スタンプカード機能の設計

- NFTベースのスタンプ
- 来店記録
- 特典付与ロジック

### 5. ポイントカード機能の設計

- ポイント計算
- 有効期限管理
- ポイント交換

## 📚 参考リソース

### 公式ドキュメント

- **Anchor Book:** https://book.anchor-lang.com/
- **Solana Cookbook:** https://solanacookbook.com/
- **SPL Token Program:** https://spl.solana.com/token

### コミュニティ

- **Anchor Discord:** https://discord.gg/anchor
- **Solana Stack Exchange:** https://solana.stackexchange.com/
- **Solana GitHub:** https://github.com/solana-labs/solana

## 🔄 メンテナンス

### 定期的な更新
```bash
# コンテナ内で実行
# Solana CLIの更新
solana-install update

# Anchorの更新（必要に応じて）
cargo install --git https://github.com/coral-xyz/anchor anchor-cli --tag v0.30.1 --force
```

### バックアップ

重要なファイルのバックアップ:
- `~/.config/solana/id.json` (ウォレット)
- `C:\Users\harum\yaeyama_token\` (プロジェクト全体)

## ✅ 成功の確認

以下のコマンドが全て成功すれば、環境構築完了:
```bash
# 1. Rustバージョン確認
rustc --version
# 期待: rustc 1.82.0 (以上)

# 2. Solanaバージョン確認
solana --version
# 期待: solana-cli 2.1.5

# 3. Anchorバージョン確認
anchor --version
# 期待: anchor-cli 0.30.1

# 4. テストプロジェクトでanchor-splのビルド
cd /tmp
anchor init test_spl
cd test_spl
echo 'anchor-spl = "0.30.1"' >> programs/test_spl/Cargo.toml
anchor build
# 期待: Finished release [optimized] target(s)
```

## 🎉 まとめ

この環境では:
- ✅ anchor-spl 0.30.1が使用可能
- ✅ SPLトークン機能が実装可能
- ✅ QR決済機能の開発が可能
- ✅ スタンプカード機能の開発が可能
- ✅ ポイントカード機能の開発が可能

**30回以上の試行錯誤の末に到達した解決策です！**

---

作成日: 2025-11-17
作成者: Claude (Anthropic)
対象プロジェクト: 八重山トークン (yaeyama_token)
```

**保存方法:**
- ファイル名: `README.md`
- ファイルの種類: `すべてのファイル (*.*)`
- 保存先: `C:\Users\harum\yaeyama_token\docker\`

---

## 🎉 全ファイル完成！

これで6つのファイルすべてが揃いました：
```
C:\Users\harum\yaeyama_token\docker\
├── Dockerfile              ✅
├── docker-compose.yml      ✅
├── test_environment.sh     ✅
├── SETUP_GUIDE.ps1         ✅
├── QUICKSTART.md           ✅
└── README.md               ✅