# OCR na VPS Hostinger

Use este procedimento apenas em VPS Linux Ubuntu/Debian. Em hospedagem compartilhada, a instalacao de binarios do sistema como Tesseract normalmente nao e adequada para este fluxo.

## Instalar

Na VPS, dentro da pasta do projeto:

```bash
sudo bash scripts/install-hostinger-vps-ocr.sh
```

Depois configure o `.env` da aplicacao:

```env
TESSERACT_PATH=tesseract
TESSDATA_DIR=
```

## Verificar

```bash
bash scripts/verify-hostinger-vps-ocr.sh
```

O resultado esperado deve listar os idiomas `por` e `eng`, alem das extensoes PHP `gd`, `exif`, `fileinfo`, `mbstring` e `pdo_mysql`.

## Como a aplicacao usa

O formulario de familia envia a imagem para:

```text
/cadastros/familias/ocr-documento
```

O PHP chama o Tesseract no servidor e retorna o texto para o navegador. O preenchimento automatico so ocorre quando o parser identifica campos confiaveis.
