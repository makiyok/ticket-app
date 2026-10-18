#!/usr/bin/env bash
set -e

echo "==> プロジェクトのディレクトリへ移動します"
cd /var/www/ticket-app

echo "==> 最新のコードを取得します (git pull)"
git pull origin main

echo "==> PHP の依存パッケージをインストールします (composer install)"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> フロントエンドをビルドします (npm ci && npm run build)"
npm ci && npm run build

echo "==> マイグレーションを実行します"
php artisan migrate --force

echo "==> キャッシュを最適化します"
php artisan optimize

echo "==> デプロイが完了しました"
