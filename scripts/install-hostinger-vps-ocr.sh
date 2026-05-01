#!/usr/bin/env bash
set -euo pipefail

if [[ "${EUID}" -ne 0 ]]; then
    echo "Execute como root: sudo bash scripts/install-hostinger-vps-ocr.sh" >&2
    exit 1
fi

if ! command -v apt-get >/dev/null 2>&1; then
    echo "Este instalador atende VPS Ubuntu/Debian com apt-get." >&2
    exit 1
fi

export DEBIAN_FRONTEND=noninteractive

apt-get update
apt-get install -y \
    tesseract-ocr \
    tesseract-ocr-por \
    tesseract-ocr-eng \
    php-cli \
    php-gd \
    php-mbstring \
    php-xml \
    php-curl \
    php-mysql \
    unzip

echo
echo "Versao do Tesseract:"
tesseract --version | head -n 1

echo
echo "Idiomas instalados:"
tesseract --list-langs

if ! tesseract --list-langs 2>/dev/null | grep -qx "por"; then
    echo "Falha: idioma portugues (por) nao foi encontrado." >&2
    exit 1
fi

if ! tesseract --list-langs 2>/dev/null | grep -qx "eng"; then
    echo "Falha: idioma ingles (eng) nao foi encontrado." >&2
    exit 1
fi

echo
echo "Extensoes PHP relevantes:"
php -m | grep -E '^(gd|exif|fileinfo|mbstring|mysqli|pdo_mysql)$' || true

echo
echo "Configure o .env da aplicacao na VPS com:"
echo "TESSERACT_PATH=tesseract"
echo "TESSDATA_DIR="
echo
echo "OCR do servidor instalado."
