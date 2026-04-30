(function () {
    'use strict';

    var form = document.querySelector('[data-offline-queue-form]');

    if (!form || !window.indexedDB) {
        return;
    }

    var DB_NAME = 'cadastroEmergencialOffline';
    var STORE = 'residencias';
    var panel = document.querySelector('[data-offline-panel]');
    var message = document.querySelector('[data-offline-message]');
    var syncButton = document.querySelector('[data-offline-sync]');
    var tokenInput = form.querySelector('input[name="_idempotency_token"]');
    var tokenPool = parseTokens(form.dataset.offlineTokens);

    function parseTokens(value) {
        try {
            var parsed = JSON.parse(value || '[]');
            return Array.isArray(parsed) ? parsed : [];
        } catch (error) {
            return [];
        }
    }

    function openDb() {
        return new Promise(function (resolve, reject) {
            var request = indexedDB.open(DB_NAME, 1);

            request.onupgradeneeded = function () {
                var db = request.result;

                if (!db.objectStoreNames.contains(STORE)) {
                    db.createObjectStore(STORE, { keyPath: 'id' });
                }
            };

            request.onsuccess = function () {
                resolve(request.result);
            };

            request.onerror = function () {
                reject(request.error);
            };
        });
    }

    function withStore(mode, callback) {
        return openDb().then(function (db) {
            return new Promise(function (resolve, reject) {
                var transaction = db.transaction(STORE, mode);
                var store = transaction.objectStore(STORE);
                var result = callback(store);

                transaction.oncomplete = function () {
                    db.close();
                    resolve(result);
                };

                transaction.onerror = function () {
                    db.close();
                    reject(transaction.error);
                };
            });
        });
    }

    function getAll() {
        return withStore('readonly', function (store) {
            return new Promise(function (resolve, reject) {
                var request = store.getAll();
                request.onsuccess = function () { resolve(request.result || []); };
                request.onerror = function () { reject(request.error); };
            });
        });
    }

    function put(record) {
        return withStore('readwrite', function (store) {
            store.put(record);
        });
    }

    function remove(id) {
        return withStore('readwrite', function (store) {
            store.delete(id);
        });
    }

    function nextToken() {
        if (tokenPool.length > 0) {
            return tokenPool.shift();
        }

        return tokenInput ? tokenInput.value : '';
    }

    function setPanel(text, isWarning) {
        if (!panel || !message) {
            return;
        }

        panel.hidden = false;
        panel.classList.toggle('is-warning', !!isWarning);
        message.textContent = text;
    }

    function refreshPanel() {
        getAll().then(function (items) {
            if (!panel || items.length === 0 && navigator.onLine) {
                if (panel) {
                    panel.hidden = true;
                }
                return;
            }

            if (!navigator.onLine) {
                setPanel(items.length > 0
                    ? items.length + ' cadastro(s) aguardando conexão para sincronizar.'
                    : 'Sem conexão com o servidor. O cadastro será salvo neste celular.', true);
                return;
            }

            setPanel(items.length + ' cadastro(s) aguardando sincronização.', false);
        }).catch(function () {});
    }

    function collectRecord() {
        var data = new FormData(form);
        var fields = {};
        var files = [];

        data.set('_idempotency_token', nextToken());

        data.forEach(function (value, key) {
            if (value instanceof File && value.name !== '') {
                files.push({
                    key: key,
                    file: value,
                    name: value.name,
                    type: value.type
                });
                return;
            }

            if (!(value instanceof File)) {
                fields[key] = value;
            }
        });

        return {
            id: String(Date.now()) + '-' + Math.random().toString(16).slice(2),
            action: form.action,
            method: form.method || 'POST',
            fields: fields,
            files: files,
            createdAt: new Date().toISOString()
        };
    }

    function buildFormData(record) {
        var data = new FormData();

        Object.keys(record.fields || {}).forEach(function (key) {
            data.append(key, record.fields[key]);
        });

        (record.files || []).forEach(function (item) {
            data.append(item.key, item.file, item.name);
        });

        return data;
    }

    function resetFormAfterQueue() {
        var csrf = form.querySelector('input[name="_csrf_token"]');
        var idempotency = form.querySelector('input[name="_idempotency_token"]');
        var csrfValue = csrf ? csrf.value : '';

        form.reset();

        if (csrf) {
            csrf.value = csrfValue;
        }

        if (idempotency) {
            idempotency.value = nextToken();
        }
    }

    function queueCurrentForm() {
        return put(collectRecord()).then(function () {
            resetFormAfterQueue();
            setPanel('Cadastro salvo neste celular. Ele será enviado quando a conexão voltar.', true);
        });
    }

    function syncQueued() {
        if (!navigator.onLine) {
            refreshPanel();
            return Promise.resolve();
        }

        if (syncButton) {
            syncButton.disabled = true;
        }

        return getAll().then(function (items) {
            var chain = Promise.resolve();

            items.forEach(function (item) {
                chain = chain.then(function () {
                    return fetch(item.action, {
                        method: item.method,
                        body: buildFormData(item),
                        credentials: 'same-origin'
                    }).then(function (response) {
                        if (!response.ok) {
                            throw new Error('Falha ao sincronizar cadastro offline.');
                        }

                        return remove(item.id);
                    });
                });
            });

            return chain;
        }).then(function () {
            setPanel('Cadastros offline sincronizados.', false);
            window.setTimeout(refreshPanel, 1800);
        }).catch(function () {
            setPanel('Não foi possível sincronizar agora. Tente novamente quando a conexão estiver estável.', true);
        }).finally(function () {
            if (syncButton) {
                syncButton.disabled = false;
            }
        });
    }

    form.addEventListener('submit', function (event) {
        if (navigator.onLine) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();
        queueCurrentForm();
    }, true);

    window.addEventListener('online', syncQueued);
    window.addEventListener('offline', refreshPanel);

    if (syncButton) {
        syncButton.addEventListener('click', syncQueued);
    }

    refreshPanel();
    syncQueued();
})();
