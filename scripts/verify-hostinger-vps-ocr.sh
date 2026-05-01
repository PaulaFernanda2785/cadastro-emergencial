#!/usr/bin/env bash
set -euo pipefail

echo "Tesseract:"
tesseract --version | head -n 1

echo
echo "Idiomas:"
tesseract --list-langs

echo
echo "PHP:"
php -v | head -n 1

echo
echo "Extensoes PHP exigidas:"
missing=0
for extension in gd exif fileinfo mbstring pdo_mysql; do
    if php -m | grep -qx "${extension}"; then
        echo "ok  ${extension}"
    else
        echo "erro ${extension}"
        missing=1
    fi
done

echo
if [[ "${missing}" -eq 0 ]]; then
    echo "Ambiente OCR pronto para a aplicacao."
else
    echo "Ambiente OCR incompleto." >&2
    exit 1
fi
