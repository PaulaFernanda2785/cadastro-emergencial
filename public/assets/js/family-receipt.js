(function () {
    'use strict';

    var qrCanvas = document.querySelector('[data-family-receipt-qr]');
    var ticket = document.querySelector('[data-family-receipt-ticket]');
    var shareButton = document.querySelector('[data-family-receipt-share]');
    var shareStatus = document.querySelector('[data-family-receipt-share-status]');

    function setStatus(text, fallback) {
        if (shareStatus) {
            shareStatus.textContent = '';
            shareStatus.appendChild(document.createTextNode(text));

            if (fallback && fallback.url) {
                shareStatus.appendChild(document.createTextNode(' '));

                var fallbackLink = document.createElement('a');
                fallbackLink.href = fallback.appUrl || fallback.url;
                fallbackLink.target = '_blank';
                fallbackLink.rel = 'noopener';
                fallbackLink.textContent = fallback.label || 'Enviar ao responsavel';
                shareStatus.appendChild(fallbackLink);
            }
        }
    }

    function renderQr(callback) {
        if (!qrCanvas || !window.QRCode || typeof window.QRCode.toCanvas !== 'function') {
            if (callback) {
                callback();
            }
            return;
        }

        window.QRCode.toCanvas(qrCanvas, qrCanvas.dataset.qrValue || '', {
            errorCorrectionLevel: 'M',
            margin: 1,
            width: 210
        }, function () {
            if (callback) {
                callback();
            }
        });
    }

    function textContent(selector, root) {
        var element = (root || document).querySelector(selector);

        return element ? element.textContent.replace(/\s+/g, ' ').trim() : '';
    }

    function collectTicketRows() {
        var rows = [];

        if (!ticket) {
            return rows;
        }

        ticket.querySelectorAll('.receipt-lines div').forEach(function (row) {
            rows.push({
                label: textContent('dt', row),
                value: textContent('dd', row)
            });
        });

        return rows;
    }

    function collectReceiptItems() {
        var items = [];

        if (!ticket) {
            return items;
        }

        ticket.querySelectorAll('.receipt-items tbody tr').forEach(function (row) {
            var cells = row.querySelectorAll('td');

            if (cells.length < 2) {
                return;
            }

            items.push({
                name: cells[0].textContent.replace(/\s+/g, ' ').trim(),
                quantity: cells[1].textContent.replace(/\s+/g, ' ').trim()
            });
        });

        return items;
    }

    function wrapText(context, text, maxWidth) {
        var words = String(text || '').split(/\s+/).filter(Boolean);
        var lines = [];
        var line = '';

        words.forEach(function (word) {
            var test = line === '' ? word : line + ' ' + word;

            if (context.measureText(test).width <= maxWidth) {
                line = test;
                return;
            }

            if (line !== '') {
                lines.push(line);
            }

            line = word;

            while (context.measureText(line).width > maxWidth && line.length > 1) {
                lines.push(line.slice(0, Math.max(1, line.length - 1)));
                line = line.slice(Math.max(1, line.length - 1));
            }
        });

        if (line !== '') {
            lines.push(line);
        }

        return lines.length > 0 ? lines : [''];
    }

    function drawWrapped(context, text, x, y, maxWidth, lineHeight) {
        var lines = wrapText(context, text, maxWidth);

        lines.forEach(function (line, index) {
            context.fillText(line, x, y + index * lineHeight);
        });

        return y + lines.length * lineHeight;
    }

    function drawSeparator(context, y, width, padding) {
        context.setLineDash([10, 7]);
        context.strokeStyle = '#555555';
        context.beginPath();
        context.moveTo(padding, y);
        context.lineTo(width - padding, y);
        context.stroke();
        context.setLineDash([]);

        return y + 24;
    }

    function renderTicketImage() {
        var width = 576;
        var padding = 30;
        var labelWidth = 150;
        var lineHeight = 25;
        var canvas = document.createElement('canvas');
        var context = canvas.getContext('2d');
        var rows = collectTicketRows();
        var receiptItems = collectReceiptItems();
        var footer = Array.prototype.slice.call(ticket ? ticket.querySelectorAll('.receipt-footer span') : [])
            .map(function (item) { return item.textContent.trim(); })
            .filter(Boolean);
        var qrSize = 214;
        var y = 28;
        var finalCanvas;
        var finalContext;

        canvas.width = width;
        canvas.height = 2200;
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.fillStyle = '#111111';
        context.textBaseline = 'top';
        context.font = '700 25px "Courier New", monospace';
        context.textAlign = 'center';
        context.fillText(textContent('.receipt-header strong', ticket), width / 2, y);
        y += 31;

        context.font = '20px "Courier New", monospace';
        Array.prototype.slice.call(ticket ? ticket.querySelectorAll('.receipt-header span') : []).forEach(function (item) {
            context.fillText(item.textContent.trim(), width / 2, y);
            y += 25;
        });

        y = drawSeparator(context, y + 12, width, padding);
        context.textAlign = 'left';

        rows.forEach(function (row, index) {
            context.font = '700 20px "Courier New", monospace';
            context.fillText(row.label, padding, y);
            context.font = '20px "Courier New", monospace';
            y = drawWrapped(context, row.value, padding + labelWidth, y, width - padding * 2 - labelWidth, lineHeight);
            y += 8;

            if ([5, 11, 15].indexOf(index) !== -1) {
                y = drawSeparator(context, y + 3, width, padding);
            }
        });

        if (receiptItems.length > 0) {
            y = drawSeparator(context, y + 3, width, padding);
            context.font = '700 20px "Courier New", monospace';
            context.fillText('Itens', padding, y);
            y += 30;

            receiptItems.forEach(function (item) {
                context.font = '700 19px "Courier New", monospace';
                y = drawWrapped(context, item.name, padding, y, width - padding * 2, 24);
                context.font = '19px "Courier New", monospace';
                y = drawWrapped(context, item.quantity, padding, y, width - padding * 2, 24);
                y += 10;
            });
        }

        y = drawSeparator(context, y + 3, width, padding);

        if (qrCanvas) {
            context.drawImage(qrCanvas, Math.round((width - qrSize) / 2), y, qrSize, qrSize);
            y += qrSize + 10;
        }

        context.font = '700 21px "Courier New", monospace';
        context.textAlign = 'center';
        context.fillText(ticket ? ticket.dataset.receiptCode || '' : '', width / 2, y);
        y += 29;

        context.font = '18px "Courier New", monospace';
        context.textAlign = 'center';
        y = drawWrapped(context, 'Apresente este comprovante na retirada da ajuda humanitaria.', width / 2, y, width - padding * 2, 23);
        y = drawSeparator(context, y + 13, width, padding);

        if (ticket && ticket.querySelector('.receipt-signature')) {
            context.setLineDash([]);
            context.strokeStyle = '#111111';
            context.beginPath();
            context.moveTo(padding + 42, y + 28);
            context.lineTo(width - padding - 42, y + 28);
            context.stroke();
            y += 40;
            context.font = '17px "Courier New", monospace';
            context.textAlign = 'center';
            y = drawWrapped(context, textContent('.receipt-signature p', ticket), width / 2, y, width - padding * 2, 22);
            y = drawSeparator(context, y + 13, width, padding);
        }

        context.font = '17px "Courier New", monospace';
        footer.forEach(function (line) {
            context.textAlign = 'center';
            y = drawWrapped(context, line, width / 2, y, width - padding * 2, 22);
        });

        finalCanvas = document.createElement('canvas');
        finalCanvas.width = width;
        finalCanvas.height = Math.ceil(y + 28);
        finalContext = finalCanvas.getContext('2d');
        finalContext.fillStyle = '#ffffff';
        finalContext.fillRect(0, 0, finalCanvas.width, finalCanvas.height);
        finalContext.drawImage(canvas, 0, 0);

        return finalCanvas;
    }

    function downloadBlob(blob, filename) {
        var link = document.createElement('a');
        var url = URL.createObjectURL(blob);

        link.href = url;
        link.download = filename;
        document.body.appendChild(link);
        link.click();
        link.remove();

        window.setTimeout(function () {
            URL.revokeObjectURL(url);
        }, 3000);
    }

    function copyImageToClipboard(blob) {
        if (!navigator.clipboard || typeof ClipboardItem === 'undefined') {
            return Promise.resolve(false);
        }

        return navigator.clipboard.write([
            new ClipboardItem({
                'image/png': blob
            })
        ]).then(function () {
            return true;
        }).catch(function () {
            return false;
        });
    }

    function whatsappFallbackData() {
        if (!ticket || !ticket.dataset.whatsappFallbackUrl) {
            return null;
        }

        return {
            appUrl: ticket.dataset.whatsappFallbackAppUrl || '',
            url: ticket.dataset.whatsappFallbackUrl,
            label: 'Enviar ao responsavel' + (ticket.dataset.whatsappFallbackName ? ' (' + ticket.dataset.whatsappFallbackName + ')' : '')
        };
    }

    function whatsappTargetDescription() {
        if (!ticket || !ticket.dataset.whatsappTargetLabel) {
            return 'destinatario selecionado';
        }

        if (!ticket.dataset.whatsappTargetName) {
            return ticket.dataset.whatsappTargetLabel;
        }

        return ticket.dataset.whatsappTargetLabel + ' (' + ticket.dataset.whatsappTargetName + ')';
    }

    function openWhatsappConversation(appUrl, webUrl) {
        if (appUrl) {
            window.location.href = appUrl;

            if (webUrl) {
                window.setTimeout(function () {
                    if (document.visibilityState !== 'hidden') {
                        window.open(webUrl, '_blank', 'noopener');
                    }
                }, 1200);
            }

            return;
        }

        if (webUrl) {
            window.open(webUrl, '_blank', 'noopener');
        }
    }

    function handleUnavailableDirectShare(blob, filename) {
        copyImageToClipboard(blob).then(function (copied) {
            if (copied) {
                setStatus('Envio direto indisponivel neste navegador. A imagem foi copiada; cole no WhatsApp.');
                return;
            }

            downloadBlob(blob, filename);
            setStatus('Envio direto indisponivel neste navegador. A imagem foi baixada; anexe o PNG no WhatsApp.');
        });
    }

    function handleTargetedWhatsappShare(blob, filename) {
        var fallback = whatsappFallbackData();

        copyImageToClipboard(blob).then(function (copied) {
            if (copied) {
                setStatus(
                    'WhatsApp aberto para ' + whatsappTargetDescription() + '. A imagem foi copiada; cole no atendimento.',
                    fallback
                );
                return;
            }

            downloadBlob(blob, filename);
            setStatus(
                'WhatsApp aberto para ' + whatsappTargetDescription() + '. A imagem foi baixada; anexe o PNG no atendimento.',
                fallback
            );
        });
    }

    function shareTicket() {
        if (!ticket) {
            return;
        }

        var directWhatsappAppUrl = ticket.dataset.whatsappAppUrl || '';
        var directWhatsappUrl = ticket.dataset.whatsappUrl || '';

        if (!directWhatsappAppUrl && !directWhatsappUrl) {
            setStatus('Nao ha telefone valido no cadastro para abrir uma conversa direta no WhatsApp.');
            return;
        }

        if (directWhatsappAppUrl || directWhatsappUrl) {
            openWhatsappConversation(directWhatsappAppUrl, directWhatsappUrl);
            setStatus('WhatsApp aberto para ' + whatsappTargetDescription() + '. Preparando imagem do comprovante...', whatsappFallbackData());
        }

        renderQr(function () {
            renderTicketImage().toBlob(function (blob) {
                var file;
                var filename = (ticket.dataset.receiptCode || 'comprovante') + '.png';

                if (!blob) {
                    setStatus('Nao foi possivel gerar a imagem do comprovante.', whatsappFallbackData());
                    return;
                }

                if (directWhatsappUrl) {
                    handleTargetedWhatsappShare(blob, filename);
                    return;
                }

                file = new File([blob], filename, { type: 'image/png' });

                if (navigator.canShare && navigator.share) {
                    try {
                        if (!navigator.canShare({ files: [file] })) {
                            throw new Error('file-share-unavailable');
                        }
                    } catch (error) {
                        handleUnavailableDirectShare(blob, filename);
                        return;
                    }

                    navigator.share({
                        files: [file],
                        title: ticket.dataset.shareTitle || 'Comprovante'
                    }).then(function () {
                        setStatus('Comprovante enviado para compartilhamento.');
                    }).catch(function () {
                        setStatus('Envio cancelado.');
                    });
                    return;
                }

                handleUnavailableDirectShare(blob, filename);
            }, 'image/png');
        });
    }

    renderQr();

    if (shareButton) {
        shareButton.addEventListener('click', shareTicket);
    }
})();
