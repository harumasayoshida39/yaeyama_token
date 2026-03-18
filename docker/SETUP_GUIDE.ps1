# 八重山トークンプロジェクト - Docker環境セットアップガイド
# PowerShellで実行してください

# ========================================
# 前提条件の確認
# ========================================
# 
# 必要なソフトウェア:
# 1. Docker Desktop for Windows
#    - https://www.docker.com/products/docker-desktop/
#    - WSL2バックエンドを有効にしておく
# 
# 2. Git for Windows（オプション - ファイル取得用）
#

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "八重山トークン Docker環境セットアップ" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

# ========================================
# Step 1: Dockerファイルの準備
# ========================================

Write-Host "Step 1: Dockerファイルの準備" -ForegroundColor Yellow
Write-Host ""
Write-Host "以下のファイルをC:\Users\harum\yaeyama_token\docker\ に配置してください:" -ForegroundColor White
Write-Host "  - Dockerfile" -ForegroundColor Green
Write-Host "  - docker-compose.yml" -ForegroundColor Green
Write-Host "  - test_environment.sh" -ForegroundColor Green
Write-Host ""
Write-Host "配置が完了したらEnterキーを押してください..."
$null = Read-Host

# ========================================
# Step 2: Docker環境の構築
# ========================================

Write-Host ""
Write-Host "Step 2: Docker環境の構築" -ForegroundColor Yellow
Write-Host ""
Write-Host "Dockerイメージをビルドします（初回は10-15分程度かかります）" -ForegroundColor White
Write-Host ""

$dockerPath = "C:\Users\harum\yaeyama_token\docker"

if (Test-Path $dockerPath) {
    Set-Location $dockerPath
    
    Write-Host "docker-compose buildを実行中..." -ForegroundColor Cyan
    docker-compose build
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "✅ Dockerイメージのビルドに成功しました" -ForegroundColor Green
        Write-Host ""
    } else {
        Write-Host ""
        Write-Host "❌ Dockerイメージのビルドに失敗しました" -ForegroundColor Red
        Write-Host "エラーメッセージを確認してください" -ForegroundColor Red
        exit 1
    }
} else {
    Write-Host "❌ ディレクトリが見つかりません: $dockerPath" -ForegroundColor Red
    Write-Host "Dockerファイルを正しい場所に配置してください" -ForegroundColor Red
    exit 1
}

# ========================================
# Step 3: コンテナの起動と環境テスト
# ========================================

Write-Host ""
Write-Host "Step 3: コンテナの起動と環境テスト" -ForegroundColor Yellow
Write-Host ""
Write-Host "Dockerコンテナを起動して環境をテストします" -ForegroundColor White
Write-Host ""

# コンテナを起動
Write-Host "docker-compose upを実行中..." -ForegroundColor Cyan
Start-Process -NoNewWindow -Wait docker-compose -ArgumentList "up", "-d"

if ($LASTEXITCODE -eq 0) {
    Write-Host "✅ コンテナが起動しました" -ForegroundColor Green
    Write-Host ""
    
    # テストスクリプトを実行
    Write-Host "環境検証スクリプトを実行中..." -ForegroundColor Cyan
    Write-Host ""
    
    docker-compose exec solana-dev bash /workspace/test_environment.sh
    
    if ($LASTEXITCODE -eq 0) {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Green
        Write-Host "✅ 環境検証に成功しました！" -ForegroundColor Green
        Write-Host "========================================" -ForegroundColor Green
        Write-Host ""
        Write-Host "八重山トークンプロジェクトでanchor-splが使用できます。" -ForegroundColor White
        Write-Host ""
    } else {
        Write-Host ""
        Write-Host "========================================" -ForegroundColor Red
        Write-Host "❌ 環境検証に失敗しました" -ForegroundColor Red
        Write-Host "========================================" -ForegroundColor Red
        Write-Host ""
    }
} else {
    Write-Host "❌ コンテナの起動に失敗しました" -ForegroundColor Red
    exit 1
}

# ========================================
# Step 4: 使用方法の説明
# ========================================

Write-Host ""
Write-Host "========================================" -ForegroundColor Cyan
Write-Host "使用方法" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""

Write-Host "コンテナに入る:" -ForegroundColor Yellow
Write-Host "  docker-compose exec solana-dev bash" -ForegroundColor Green
Write-Host ""

Write-Host "プロジェクトのビルド（コンテナ内で実行）:" -ForegroundColor Yellow
Write-Host "  cd /workspace/project" -ForegroundColor Green
Write-Host "  anchor build" -ForegroundColor Green
Write-Host ""

Write-Host "コンテナの停止:" -ForegroundColor Yellow
Write-Host "  docker-compose down" -ForegroundColor Green
Write-Host ""

Write-Host "コンテナの再起動:" -ForegroundColor Yellow
Write-Host "  docker-compose up -d" -ForegroundColor Green
Write-Host ""

Write-Host "========================================" -ForegroundColor Cyan
Write-Host "次のステップ" -ForegroundColor Cyan
Write-Host "========================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "1. コンテナに入る:" -ForegroundColor White
Write-Host "   docker-compose exec solana-dev bash" -ForegroundColor Cyan
Write-Host ""
Write-Host "2. 八重山トークンプロジェクトのCargo.tomlを編集:" -ForegroundColor White
Write-Host "   anchor-spl = \"0.30.1\" を追加" -ForegroundColor Cyan
Write-Host ""
Write-Host "3. ビルドを実行:" -ForegroundColor White
Write-Host "   anchor build" -ForegroundColor Cyan
Write-Host ""