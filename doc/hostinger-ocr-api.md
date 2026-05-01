# OCR externo na Hostinger

Use esta opcao quando a hospedagem nao permitir instalar Tesseract no servidor.

## Provedor recomendado

Google Cloud Vision OCR via REST.

A aplicacao chama:

```text
POST https://vision.googleapis.com/v1/images:annotate
```

com o recurso `DOCUMENT_TEXT_DETECTION`.

## Configurar

No Google Cloud:

1. Crie ou selecione um projeto.
2. Ative a Cloud Vision API.
3. Vincule uma conta de faturamento ao projeto.
4. Crie uma chave de API.
5. Restrinja a chave por dominio/IP quando possivel.

Sem faturamento ativo, a API retorna `403 PERMISSION_DENIED` com motivo `BILLING_DISABLED`.

No `.env` da Hostinger:

```env
OCR_PROVIDER=google_vision
GOOGLE_VISION_API_KEY=SUA_CHAVE_AQUI
TESSERACT_PATH=
TESSDATA_DIR=
```

## Como testar

Abra o formulario de familia, anexe uma imagem JPG/PNG do documento e observe o status abaixo do campo de anexo.

Se a chave estiver ausente ou invalida, a resposta do sistema sera controlada e o formulario continuara funcionando sem preenchimento automatico.

## Observacoes

Nao use Playwright/Selenium para preencher o sistema. A aplicacao ja controla o formulario e deve chamar OCR diretamente pelo backend PHP.
