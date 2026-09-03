#!/usr/bin/env bash
# =============================================================================
# Deployment Script untuk Server VPS
# =============================================================================

set -e

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_TARGET_DIR="/var/www/app2"

echo "========================================="
echo " Sinkronisasi & Deploy Aplikasi BK SMA 7"
echo "========================================="

if [ -d "$APP_TARGET_DIR" ]; then
    echo "[1/3] Menyalin file dari $PROJECT_DIR ke $APP_TARGET_DIR..."
    rsync -av \
        --exclude '.git' \
        --exclude '.env' \
        --exclude 'node_modules' \
        --exclude 'assets/uploads/profil/*' \
        "$PROJECT_DIR/" "$APP_TARGET_DIR/"
    
    echo "[2/3] Menjalankan deploy server..."
    if [ -f "/var/www/deploy.sh" ]; then
        /var/www/deploy.sh app2
    fi

    echo "[3/3] Selesai! Aplikasi aktif."
else
    echo "Info: Folder $APP_TARGET_DIR tidak ditemukan (lingkungan non-VPS/Lokal)."
    echo "Untuk localhost / XAMPP, silakan jalankan langsung melalui web server Apache & MySQL XAMPP."
fi

echo "========================================="
