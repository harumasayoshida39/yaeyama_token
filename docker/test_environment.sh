#!/bin/bash

# 八重山トークンプロジェクト用 Docker環境検証スクリプト
# このスクリプトはDockerコンテナ内で実行します

set -e

echo "=========================================="
echo "八重山トークン Docker環境検証"
echo "=========================================="
echo ""

# 環境情報の表示
echo "=== 環境情報 ==="
echo "Rust version: $(rustc --version)"
echo "Cargo version: $(cargo --version)"
echo "Solana version: $(solana --version)"
echo "Anchor version: $(anchor --version)"
echo "Node version: $(node --version)"
echo "Yarn version: $(yarn --version)"
echo ""

# Solana内蔵rustcの確認
echo "=== Solana内蔵rustcの確認 ==="
if command -v cargo-build-sbf &> /dev/null; then
    cargo-build-sbf --version
else
    echo "cargo-build-sbf not found (これは正常です - Solana 2.xでは使用されない可能性があります)"
fi
echo ""

# テストプロジェクトの作成
echo "=== テストプロジェクト作成 ==="
cd /tmp
rm -rf test_spl_project
anchor init test_spl_project
cd test_spl_project

echo ""
echo "=== Cargo.tomlにanchor-splを追加 ==="
cat >> programs/test_spl_project/Cargo.toml << 'EOF'
anchor-spl = "0.30.1"
EOF

echo "追加後のCargo.toml:"
cat programs/test_spl_project/Cargo.toml
echo ""

# 簡単なSPLトークンコードを追加
echo "=== SPLトークン機能を使用するコードに変更 ==="
cat > programs/test_spl_project/src/lib.rs << 'EOF'
use anchor_lang::prelude::*;
use anchor_spl::token::{self, Token, TokenAccount, Mint};

declare_id!("Fg6PaFpoGXkYsidMpWTK6W2BeZ7FEfcYkg476zPFsLnS");

#[program]
pub mod test_spl_project {
    use super::*;

    pub fn initialize(ctx: Context<Initialize>) -> Result<()> {
        msg!("SPL Token test initialized!");
        Ok(())
    }
}

#[derive(Accounts)]
pub struct Initialize<'info> {
    #[account(mut)]
    pub payer: Signer<'info>,
    pub system_program: Program<'info, System>,
}
EOF

echo "コード変更完了"
echo ""

# ビルドテスト
echo "=== ビルドテスト開始 ==="
echo "anchor-spl 0.30.1を使用してビルドを試みます..."
echo ""

if anchor build; then
    echo ""
    echo "=========================================="
    echo "✅ ビルド成功！"
    echo "=========================================="
    echo ""
    echo "この環境は八重山トークンプロジェクトに使用できます。"
    echo ""
    echo "確認されたバージョン:"
    echo "- Rust: $(rustc --version)"
    echo "- Solana: $(solana --version | head -n 1)"
    echo "- Anchor: $(anchor --version)"
    echo "- anchor-spl: 0.30.1"
    echo ""
    echo "次のステップ:"
    echo "1. このDockerコンテナに八重山トークンプロジェクトをコピー"
    echo "2. Cargo.tomlにanchor-spl = \"0.30.1\"を追加"
    echo "3. anchor buildを実行"
    echo ""
else
    echo ""
    echo "=========================================="
    echo "❌ ビルド失敗"
    echo "=========================================="
    echo ""
    echo "エラーログを確認してください。"
    echo "問題が発生した場合は、別のバージョンの組み合わせを試す必要があります。"
    echo ""
    exit 1
fi