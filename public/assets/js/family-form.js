(function () {
    'use strict';

    var forms = document.querySelectorAll('[data-family-form]');

    function openFilePicker(input) {
        if (!input || input.disabled) {
            return;
        }

        if (typeof input.showPicker === 'function') {
            try {
                input.showPicker();
                return;
            } catch (error) {
            }
        }

        input.click();
    }

    function setupRepresentative(form) {
        var toggle = form.querySelector('[data-representative-toggle]');
        var fields = form.querySelector('[data-representative-fields]');
        var inputs = form.querySelectorAll('[data-representative-input]');

        if (!toggle || !fields) {
            return;
        }

        function applyState() {
            var enabled = toggle.checked;

            fields.hidden = !enabled;
            inputs.forEach(function (input) {
                input.disabled = !enabled;
            });
        }

        toggle.addEventListener('change', applyState);
        applyState();
    }

    function setupBenefit(form) {
        var toggle = form.querySelector('[data-benefit-toggle]');
        var fields = form.querySelector('[data-benefit-fields]');
        var inputs = form.querySelectorAll('[data-benefit-input]');

        if (!toggle || !fields) {
            return;
        }

        function applyState() {
            fields.hidden = !toggle.checked;
            inputs.forEach(function (input) {
                input.disabled = !toggle.checked;
            });
        }

        toggle.addEventListener('change', applyState);
        applyState();
    }

    function setupQuantityStepper(form) {
        var stepper = form.querySelector('[data-quantity-stepper]');

        if (!stepper) {
            return;
        }

        var input = stepper.querySelector('[data-quantity-input]');
        var decrement = stepper.querySelector('[data-quantity-decrement]');
        var increment = stepper.querySelector('[data-quantity-increment]');

        if (!input || !decrement || !increment) {
            return;
        }

        function currentValue() {
            var value = parseInt(input.value, 10);
            return Number.isFinite(value) && value > 0 ? value : 1;
        }

        function setValue(value) {
            input.value = String(Math.max(1, value));
            input.dispatchEvent(new Event('change', { bubbles: true }));
        }

        decrement.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setValue(currentValue() - 1);
        });

        increment.addEventListener('click', function (event) {
            event.preventDefault();
            event.stopPropagation();
            setValue(currentValue() + 1);
        });

        input.addEventListener('blur', function () {
            setValue(currentValue());
        });
    }

    function setupDocumentUpload(form, wrapper) {

        if (!wrapper) {
            return;
        }

        var input = wrapper.querySelector('[data-family-doc-input]');
        var dropzone = wrapper.querySelector('[data-family-doc-dropzone]');
        var list = wrapper.querySelector('[data-family-doc-list]');
        var status = wrapper.querySelector('[data-family-doc-status]');
        var files = [];
        var syncing = false;
        var previewUrls = [];

        if (!input || !dropzone || !list) {
            return;
        }

        function setStatus(text) {
            if (status) {
                status.textContent = text;
            }
        }

        function syncInput() {
            var transfer;

            if (typeof DataTransfer === 'undefined') {
                return false;
            }

            transfer = new DataTransfer();
            files.forEach(function (file) {
                transfer.items.add(file);
            });

            syncing = true;
            input.files = transfer.files;
            syncing = false;
            return true;
        }

        function clearPreviewUrls() {
            previewUrls.forEach(function (url) {
                URL.revokeObjectURL(url);
            });
            previewUrls = [];
        }

        function createPreview(file) {
            var preview = document.createElement('div');

            preview.className = 'family-doc-preview';

            if (/^image\//.test(file.type)) {
                var image = document.createElement('img');
                var url = URL.createObjectURL(file);

                previewUrls.push(url);
                image.src = url;
                image.alt = 'Prévia de ' + file.name;
                preview.appendChild(image);
                return preview;
            }

            var icon = document.createElement('span');
            icon.className = 'family-doc-preview-icon';
            icon.textContent = (file.type || '').indexOf('pdf') !== -1 ? 'PDF' : 'DOC';
            preview.appendChild(icon);
            return preview;
        }

        function render() {
            clearPreviewUrls();
            list.innerHTML = '';

            if (files.length === 0) {
                list.hidden = true;
                dropzone.classList.remove('has-file');
                setStatus('JPG, PNG ou PDF. Tamanho máximo por arquivo: 5 MB.');
                syncInput();
                return;
            }

            files.forEach(function (file, index) {
                var item = document.createElement('div');
                var preview = createPreview(file);
                var info = document.createElement('div');
                var name = document.createElement('span');
                var meta = document.createElement('small');
                var remove = document.createElement('button');

                info.className = 'family-doc-info';
                name.textContent = file.name;
                meta.textContent = Math.max(1, Math.round(file.size / 1024)) + ' KB';
                remove.type = 'button';
                remove.textContent = 'Remover';
                remove.addEventListener('click', function () {
                    files.splice(index, 1);
                    render();
                });

                info.appendChild(name);
                info.appendChild(meta);
                item.appendChild(preview);
                item.appendChild(info);
                item.appendChild(remove);
                list.appendChild(item);
            });

            list.hidden = false;
            dropzone.classList.add('has-file');
            setStatus(files.length + ' documento(s) selecionado(s).');
            syncInput();
        }

        function addFiles(fileList) {
            Array.prototype.slice.call(fileList || []).forEach(function (file) {
                if (!file || files.some(function (item) {
                    return item.name === file.name && item.size === file.size && item.lastModified === file.lastModified;
                })) {
                    return;
                }

                files.push(file);
            });

            render();
        }

        input.addEventListener('change', function () {
            if (!syncing) {
                addFiles(input.files);
            }
        });

        dropzone.addEventListener('keydown', function (event) {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                openFilePicker(input);
            }
        });

        dropzone.addEventListener('click', function (event) {
            event.preventDefault();
            openFilePicker(input);
        });

        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('is-dragging');
            });
        });

        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function () {
                dropzone.classList.remove('is-dragging');
            });
        });

        dropzone.addEventListener('drop', function (event) {
            event.preventDefault();
            addFiles(event.dataTransfer ? event.dataTransfer.files : []);
        });

        form.addEventListener('reset', function () {
            files = [];
            window.setTimeout(render, 0);
        });

        window.addEventListener('beforeunload', clearPreviewUrls);

        return {
            addFiles: addFiles
        };
    }

    function setupDocumentUploads(form) {
        var wrappers = form.querySelectorAll('[data-family-doc-upload]');
        var uploads = [];

        wrappers.forEach(function (wrapper) {
            var upload = setupDocumentUpload(form, wrapper);

            if (upload) {
                uploads.push({
                    wrapper: wrapper,
                    upload: upload
                });
            }
        });

        if (uploads.length === 0) {
            return;
        }

        form.addEventListener('paste', function (event) {
            var files = event.clipboardData ? event.clipboardData.files : [];
            var activeWrapper = event.target instanceof Element ? event.target.closest('[data-family-doc-upload]') : null;
            var targetUpload = uploads.find(function (item) {
                return item.wrapper === activeWrapper;
            }) || uploads[0];

            targetUpload.upload.addFiles(files);
        });
    }

    function setupExistingDocuments(form) {
        form.querySelectorAll('[data-family-doc-remove]').forEach(function (input) {
            var item = input.closest('.family-existing-doc-item');
            var label = input.closest('.family-doc-remove-action');
            var text = label ? label.querySelector('span') : null;

            input.addEventListener('change', function () {
                if (item) {
                    item.classList.toggle('is-marked-for-removal', input.checked);
                }

                if (text) {
                    text.textContent = input.checked ? 'Manter' : 'Remover';
                }
            });
        });
    }

    function setupDocumentModal() {
        var modal = document.querySelector('[data-family-doc-modal]');

        if (!modal) {
            return;
        }

        var title = modal.querySelector('[data-family-doc-modal-title]');
        var image = modal.querySelector('[data-family-doc-modal-image]');
        var frame = modal.querySelector('[data-family-doc-modal-frame]');

        function clearContent() {
            if (image) {
                image.hidden = true;
                image.removeAttribute('src');
            }

            if (frame) {
                frame.hidden = true;
                frame.removeAttribute('src');
            }
        }

        document.querySelectorAll('[data-family-doc-open]').forEach(function (button) {
            button.addEventListener('click', function () {
                var src = button.getAttribute('data-doc-src') || '';
                var kind = button.getAttribute('data-doc-kind') || 'document';

                if (!src) {
                    return;
                }

                clearContent();

                if (title) {
                    title.textContent = button.getAttribute('data-doc-title') || 'Documento';
                }

                if (kind === 'image' && image) {
                    image.src = src;
                    image.hidden = false;
                } else if (frame) {
                    frame.src = src;
                    frame.hidden = false;
                }

                if (typeof modal.showModal === 'function') {
                    modal.showModal();
                } else {
                    window.open(src, '_blank', 'noopener');
                }
            });
        });

        modal.addEventListener('close', clearContent);
    }

    forms.forEach(function (form) {
        setupRepresentative(form);
        setupBenefit(form);
        setupQuantityStepper(form);
        setupDocumentUploads(form);
        setupExistingDocuments(form);
    });

    setupDocumentModal();
})();
