#!/bin/bash
# Yaeyama Token ビルド問題 解決スクリプト
# 目的: IDL生成を含む完全なビルドの成功

set -e  # エラーで停止

echo "=== Yaeyama Token ビルド修正スクリプト ==="
echo "現在時刻: $(date)"
echo ""

# プロジェクトディレクトリ
PROJECT_DIR="/mnt/c/Users/harum/yaeyama_token"

# 現在のバージョン確認
echo "📊 現在の環境:"
echo "Rust: $(rustc --version)"
echo "Solana: $(solana --version)"
echo "Anchor: $(anchor --version)"
echo ""

# ===================================
# 解決策1: Cargo.toml での依存関係固定
# ===================================
echo "🔧 解決策を適用中..."
echo ""

cd "$PROJECT_DIR"

# programs/yaeyama_token/Cargo.toml のバックアップ
cp programs/yaeyama_token/Cargo.toml programs/yaeyama_token/Cargo.toml.backup

# 依存関係を互換性のあるバージョンに固定
cat > programs/yaeyama_token/Cargo.toml << 'EOF'
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
anchor-spl = "0.30.1"

# 重要: indexmap を互換性のあるバージョンに固定
[patch.crates-io]
indexmap = { version = "=2.2.6" }
hashbrown = { version = "=0.14.3" }
EOF

echo "✅ Cargo.toml を更新しました"
echo ""

# Cargo.lock を削除して依存関係を再解決
echo "🗑️  Cargo.lock を削除..."
rm -f Cargo.lock
rm -f programs/yaeyama_token/Cargo.lock
echo ""

# Cargo キャッシュをクリーン
echo "🧹 Cargo キャッシュをクリーン..."
cd "$PROJECT_DIR"
anchor clean
cargo clean
echo ""

# ===================================
# 解決策2: Solana の rustc を置き換え
# ===================================
echo "🔄 Solana付属のrustcを修正..."

SOLANA_RUST_DIR="$HOME/solana-install/solana-release/bin/sdk/sbf/dependencies/platform-tools/rust"

if [ -d "$SOLANA_RUST_DIR" ]; then
    # バックアップ作成
    if [ ! -d "$SOLANA_RUST_DIR/bin/backup" ]; then
        mkdir -p "$SOLANA_RUST_DIR/bin/backup"
        cp "$SOLANA_RUST_DIR/bin/rustc" "$SOLANA_RUST_DIR/bin/backup/rustc.original" 2>/dev/null || true
    fi
    
    # システムのrustcをコピー（シンボリックリンクではなく実体をコピー）
    SYSTEM_RUSTC=$(which rustc)
    cp -L "$SYSTEM_RUSTC" "$SOLANA_RUST_DIR/bin/rustc"
    
    echo "✅ Solana付属のrustcを置き換えました"
    echo "   場所: $SOLANA_RUST_DIR/bin/rustc"
else
    echo "⚠️  Solana rustc ディレクトリが見つかりません"
fi
echo ""

# ===================================
# ビルド実行
# ===================================
echo "🏗️  ビルドを開始..."
cd "$PROJECT_DIR"

# 環境変数を設定
export RUSTUP_TOOLCHAIN=1.79.0

echo "試行1: anchor build"
if anchor build; then
    echo "✅ anchor build 成功!"
else
    echo "❌ anchor build 失敗"
    echo ""
    echo "試行2: cargo build-sbf"
    cargo build-sbf --manifest-path programs/yaeyama_token/Cargo.toml
fi
echo ""

# ===================================
# 結果確認
# ===================================
echo "📋 ビルド結果の確認:"
echo ""

SUCCESS=true

# .so ファイル
if [ -f "target/deploy/yaeyama_token.so" ]; then
    SIZE=$(ls -lh target/deploy/yaeyama_token.so | awk '{print $5}')
    echo "✅ プログラムバイナリ: target/deploy/yaeyama_token.so ($SIZE)"
else
    echo "❌ プログラムバイナリが生成されていません"
    SUCCESS=false
fi

# IDL ファイル（最重要）
if [ -f "target/idl/yaeyama_token.json" ]; then
    SIZE=$(ls -lh target/idl/yaeyama_token.json | awk '{print $5}')
    echo "✅ IDL ファイル: target/idl/yaeyama_token.json ($SIZE)"
    echo ""
    echo "📄 IDL プレビュー:"
    head -n 20 target/idl/yaeyama_token.json
else
    echo "❌ IDL ファイルが生成されていません"
    SUCCESS=false
fi

# TypeScript型定義
if [ -f "target/types/yaeyama_token.ts" ]; then
    SIZE=$(ls -lh target/types/yaeyama_token.ts | awk '{print $5}')
    echo "✅ TypeScript型定義: target/types/yaeyama_token.ts ($SIZE)"
else
    echo "❌ TypeScript型定義が生成されていません"
    SUCCESS=false
fi

echo ""
echo "================================"
if [ "$SUCCESS" = true ]; then
    echo "🎉 ビルド完全成功！"
    echo "   IDL、バイナリ、型定義すべて生成されました"
    echo ""
    echo "次のステップ:"
    echo "1. プログラムのデプロイ: anchor deploy"
    echo "2. IDLのアップロード: anchor idl init"
    echo "3. フロントエンド開発の開始"
else
    echo "⚠️  ビルドに問題があります"
    echo "   詳細なエラーログを確認してください"
fi
echo "================================"
