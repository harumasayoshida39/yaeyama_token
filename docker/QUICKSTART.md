# 八重山トークン Docker環境 - クイックスタート

## 🚀 5分で始める

### 1️⃣ ファイルの配置
```
C:\Users\harum\yaeyama_token\docker\
├── Dockerfile
├── docker-compose.yml
├── test_environment.sh
└── SETUP_GUIDE.ps1
```

### 2️⃣ Docker環境の構築（PowerShell）
```powershell
cd C:\Users\harum\yaeyama_token\docker
docker-compose build
docker-compose up -d
```

### 3️⃣ 環境の検証
```powershell
docker-compose exec solana-dev bash /workspace/test_environment.sh
```

**期待される出力:**
```
✅ ビルド成功！
```

### 4️⃣ 開発を開始
```powershell
# コンテナに入る
docker-compose exec solana-dev bash

# プロジェクトに移動
cd /workspace/project

# Cargo.tomlを編集してanchor-splを追加
nano programs/yaeyama_token/Cargo.toml
# [dependencies]
# anchor-lang = "0.30.1"
# anchor-spl = "0.30.1"  # ← 追加

# ビルド！
anchor build
```

## ✅ これで完了！

SPLトークン機能が使えるようになりました。

---

## 📋 主要コマンド

| 操作 | コマンド |
|------|---------|
| コンテナに入る | `docker-compose exec solana-dev bash` |
| ビルド | `anchor build` （コンテナ内） |
| コンテナ停止 | `docker-compose down` |
| コンテナ起動 | `docker-compose up -d` |

---

## ❓ トラブルシューティング

**Q: ビルドエラーが出る**
→ Cargo.tomlのバージョンが0.30.1になっているか確認

**Q: コンテナが起動しない**
→ `docker-compose logs` でログを確認

**Q: ディスク容量不足**
→ `docker system prune -a` で未使用リソースを削除

---

詳細は `README.md` を参照してください。