/**
 * T31 - RF-85, RF-86, RF-90, RNF-32: carrito y búsqueda asíncronos del POS.
 *
 * Mejora progresiva (RF-91): la página funciona con formularios. Este script
 * intercepta sólo los formularios marcados con `data-pos-async` y pide el mismo
 * endpoint con `Accept: application/json`. El servidor es la fuente de verdad
 * (D-22): el cliente nunca calcula totales; pinta los fragmentos HTML que el
 * servidor devuelve y, si algo falla, no toca nada y muestra el motivo.
 */
import axios from 'axios';
import { format, startStockPolling } from './stock';

const ALERT_STYLES = {
    error: 'border-red-200 bg-red-50 text-red-800 ring-red-100',
    warning: 'border-amber-200 bg-amber-50 text-amber-900 ring-amber-100',
};

export function firstValidationError(data) {
    const errors = data?.errors;

    if (!errors || typeof errors !== 'object') {
        return null;
    }

    for (const [field, messages] of Object.entries(errors)) {
        const message = Array.isArray(messages) ? messages[0] : messages;

        if (typeof message === 'string' && message !== '') {
            return { field, text: message };
        }
    }

    return null;
}

/** Traduce un fallo de axios al motivo que ve el cajero (RF-90). */
export function describeFailure(error, messages) {
    if (error?.invalidPayload) {
        return { kind: 'invalid', text: messages.generic, status: 200 };
    }

    const response = error?.response;

    if (!response) {
        return { kind: 'network', text: messages.network, status: 0 };
    }

    const { status, data } = response;

    if (status === 401) {
        return { kind: 'session', text: messages.session_expired, status };
    }

    if (status === 419) {
        return { kind: 'csrf', text: messages.csrf, status };
    }

    if (status === 403) {
        return { kind: 'forbidden', text: messages.forbidden, status };
    }

    if (status === 404) {
        return { kind: 'not_found', text: messages.not_found, status };
    }

    if (status === 422) {
        const first = firstValidationError(data);

        return {
            kind: 'validation',
            text: first?.text ?? data?.message ?? messages.generic,
            field: first?.field ?? null,
            status,
        };
    }

    if (status >= 500) {
        return { kind: 'server', text: format(messages.server, { status }), status };
    }

    return { kind: 'generic', text: messages.generic, status };
}

/** Lee un formulario de respaldo y lo convierte en la petición equivalente. */
export function readForm(form) {
    const formData = new FormData(form);
    const method = String(formData.get('_method') || form.getAttribute('method') || 'post').toLowerCase();

    formData.delete('_method');
    formData.delete('_token');

    const data = {};

    for (const [key, value] of formData.entries()) {
        data[key] = typeof value === 'string' && /^\d+$/.test(value) ? Number(value) : value;
    }

    return { method, url: form.getAttribute('action'), data };
}

function init(root) {
    const config = root.dataset;
    const searchUrl = config.searchUrl;
    const stockUrl = config.stockUrl;
    const loginUrl = config.loginUrl;
    const searchDelay = Number(config.searchDelay) || 300;
    const stockInterval = Number(config.stockInterval) || 10000;

    let messages = {};

    try {
        messages = JSON.parse(config.messages ?? '{}');
    } catch {
        messages = {};
    }

    const statusEl = document.getElementById('pos-status');
    const alertEl = document.getElementById('pos-alert');
    const cartEl = document.getElementById('pos-cart');
    const resultsEl = document.getElementById('pos-results');
    const searchForm = root.querySelector('form[data-pos-async="search"]');
    const searchInput = searchForm?.querySelector('input[name="q"]') ?? null;

    if (!cartEl || !resultsEl || !statusEl || !alertEl) {
        return;
    }

    const http = axios.create({
        timeout: 15000,
        headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
    });

    const pendingForms = new Set();
    const removing = new Set();
    let queue = Promise.resolve();
    let queued = 0;
    let searchTimer = null;
    let searchSeq = 0;
    let searchController = null;
    let stock = null;

    function announce(text) {
        statusEl.textContent = '';
        window.setTimeout(() => {
            statusEl.textContent = text;
        }, 60);
    }

    function clearAlert() {
        alertEl.replaceChildren();
    }

    function showAlert(text, tone = 'error') {
        const box = document.createElement('p');
        box.className = `mb-4 flex items-start justify-between gap-3 rounded-xl border p-3 text-sm ring-1 ring-inset ${ALERT_STYLES[tone]}`;

        const message = document.createElement('span');
        message.textContent = text;

        const dismiss = document.createElement('button');
        dismiss.type = 'button';
        dismiss.className = 'shrink-0 rounded px-2 font-bold leading-none focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-400';
        dismiss.textContent = '×';
        dismiss.setAttribute('aria-label', messages.dismiss ?? '');
        dismiss.addEventListener('click', clearAlert);

        box.append(message, dismiss);
        alertEl.replaceChildren(box);
    }

    function goToLogin() {
        showAlert(messages.session_expired);
        window.location.assign(loginUrl);
    }

    /** Una sola operación a la vez: el orden de las respuestas es el de las acciones (caso límite 4). */
    function enqueue(task) {
        queued += 1;

        const run = queue.then(task, task).finally(() => {
            queued -= 1;
        });

        queue = run.catch(() => {});

        return run;
    }

    /** Envía la petición; ante un 419 renueva el token con una lectura y reintenta una vez (caso límite 6). */
    async function send(request, retried = false) {
        try {
            return await http.request(request);
        } catch (error) {
            if (error?.response?.status === 419 && !retried) {
                await http.get(stockUrl);

                return send(request, true);
            }

            throw error;
        }
    }

    function applyCart(payload) {
        if (typeof payload?.fragments?.cart !== 'string') {
            const error = new Error('invalid payload');
            error.invalidPayload = true;

            throw error;
        }

        cartEl.innerHTML = payload.fragments.cart;
        stock?.cartChanged();
    }

    function applyResults(payload) {
        if (typeof payload?.fragments?.results !== 'string') {
            const error = new Error('invalid payload');
            error.invalidPayload = true;

            throw error;
        }

        resultsEl.innerHTML = payload.fragments.results;
    }

    function announceResults(count) {
        if (count === 0) {
            announce(messages.results_none);
        } else if (count === 1) {
            announce(messages.results_one);
        } else {
            announce(format(messages.results_many, { count }));
        }
    }

    /** Refresca el carrito desde el servidor (p. ej. tras un 404); no cambia la búsqueda. */
    function refreshCart() {
        return enqueue(async () => {
            const { data } = await send({ method: 'get', url: searchUrl, params: { q: '', scan: 0 } });
            applyCart(data);
        });
    }

    /**
     * Ejecuta una operación del carrito. Devuelve true si el servidor la aceptó.
     * Si falla no se toca el DOM del carrito (RF-90): sólo se muestra el motivo.
     */
    function runCart(request) {
        return enqueue(async () => {
            try {
                const { data } = await send(request);
                applyCart(data);
                clearAlert();

                return true;
            } catch (error) {
                await handleFailure(error);

                return false;
            }
        });
    }

    async function handleFailure(error) {
        const failure = describeFailure(error, messages);

        if (failure.kind === 'session') {
            goToLogin();

            return failure;
        }

        showAlert(failure.text);

        if (failure.kind === 'not_found') {
            try {
                const { data } = await send({ method: 'get', url: searchUrl, params: { q: '', scan: 0 } });
                applyCart(data);
            } catch {
                // El carrito queda como estaba; el aviso ya explica el problema.
            }
        }

        return failure;
    }

    function setBusy(element, busy) {
        element.setAttribute('aria-busy', busy ? 'true' : 'false');
    }

    function focusAfterRemoval(line) {
        const sibling = line?.nextElementSibling ?? line?.previousElementSibling;
        const id = sibling?.dataset?.productId;

        return () => (id ? document.getElementById(`qty-${id}`) : null) ?? searchInput;
    }

    function submitCartForm(form) {
        const request = readForm(form);
        const key = `${request.method} ${request.url}`;

        // Doble clic: la misma acción no entra dos veces mientras va en vuelo (caso límite 3).
        if (pendingForms.has(key)) {
            return;
        }

        pendingForms.add(key);

        const buttons = Array.from(form.querySelectorAll('button'));
        buttons.forEach((button) => {
            button.disabled = true;
        });
        setBusy(cartEl, true);

        const line = form.closest('[data-cart-line]');
        const qtyInput = form.querySelector('input[name="quantity"]');
        const qtyId = qtyInput?.id ?? null;
        const nextFocus = form.hasAttribute('data-line-remove') ? focusAfterRemoval(line) : null;

        runCart(request)
            .then((accepted) => {
                if (!accepted) {
                    // El carrito conserva lo que tenía: el campo vuelve al valor del servidor.
                    if (qtyInput?.isConnected) {
                        qtyInput.value = qtyInput.defaultValue;
                    }

                    return;
                }

                if (qtyId) {
                    document.getElementById(qtyId)?.focus();
                } else if (nextFocus) {
                    nextFocus()?.focus();
                }
            })
            .finally(() => {
                pendingForms.delete(key);
                setBusy(cartEl, false);
                buttons.forEach((button) => {
                    button.disabled = false;
                });
            });
    }

    function rememberTerm(term) {
        const url = term === '' ? window.location.pathname : `${window.location.pathname}?q=${encodeURIComponent(term)}`;
        window.history.replaceState(null, '', url);
    }

    /** Búsqueda al teclear (RF-86): `scan=0` garantiza que nunca modifica el carrito. */
    async function liveSearch() {
        searchTimer = null;

        const term = searchInput.value.trim();

        searchController?.abort();
        searchController = new AbortController();
        searchSeq += 1;

        const seq = searchSeq;

        setBusy(resultsEl, true);

        try {
            const { data } = await http.get(searchUrl, {
                params: { q: term, scan: 0 },
                signal: searchController.signal,
            });

            // Una respuesta lenta que llega tras otra más nueva se descarta (caso límite 4).
            if (seq !== searchSeq) {
                return;
            }

            applyResults(data);
            announceResults(data.results?.length ?? 0);
            rememberTerm(term);
            clearAlert();
        } catch (error) {
            if (axios.isCancel(error) || seq !== searchSeq) {
                return;
            }

            await handleSearchFailure(error);
        } finally {
            if (seq === searchSeq) {
                setBusy(resultsEl, false);
            }
        }
    }

    async function handleSearchFailure(error) {
        const failure = describeFailure(error, messages);

        if (failure.kind === 'session') {
            goToLogin();

            return;
        }

        showAlert(format(messages.search_failed, { reason: failure.text }));
    }

    /** Enter (o lector de códigos): igual que el formulario, con acierto exacto de código se agrega (RF-2). */
    function scanSearch() {
        window.clearTimeout(searchTimer);
        searchTimer = null;
        searchController?.abort();
        searchSeq += 1;

        const seq = searchSeq;
        const term = searchInput.value.trim();

        setBusy(resultsEl, true);

        return enqueue(async () => {
            try {
                const { data } = await send({ method: 'get', url: searchUrl, params: { q: term } });

                if (data?.scanned_product_id) {
                    applyCart(data);
                    searchInput.value = '';
                    announce(messages.scanned);
                }

                if (seq === searchSeq) {
                    applyResults(data);

                    if (!data?.scanned_product_id) {
                        announceResults(data.results?.length ?? 0);
                    }

                    rememberTerm(data?.scanned_product_id ? '' : term);
                }

                clearAlert();
            } catch (error) {
                const failure = await handleFailure(error);

                // Igual que la redirección sin ?q: un código rechazado deja el campo listo para el próximo.
                if (failure?.field === 'barcode') {
                    searchInput.value = '';
                    rememberTerm('');
                }
            } finally {
                if (seq === searchSeq) {
                    setBusy(resultsEl, false);
                }
            }
        });
    }

    root.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-pos-async')) {
            return;
        }

        event.preventDefault();

        if (form.dataset.posAsync === 'search') {
            scanSearch();
        } else {
            submitCartForm(form);
        }
    });

    searchInput?.addEventListener('input', () => {
        window.clearTimeout(searchTimer);
        searchTimer = window.setTimeout(liveSearch, searchDelay);
    });

    stock = startStockPolling({
        root,
        http,
        url: stockUrl,
        intervalMs: stockInterval,
        messages,
        announce,
        isBusy: () => (
            queued > 0
            || searchTimer !== null
            || pendingForms.size > 0
            || (document.activeElement instanceof HTMLInputElement
                && document.activeElement.name === 'quantity'
                && document.activeElement.value !== document.activeElement.defaultValue)
        ),
        onUnauthorized: goToLogin,
        onInactive: (lines) => {
            for (const { id, name } of lines) {
                const form = root.querySelector(`[data-cart-line][data-product-id="${id}"] form[data-line-remove]`);

                if (!form || removing.has(id)) {
                    continue;
                }

                removing.add(id);

                const request = readForm(form);

                runCart(request)
                    .then((accepted) => {
                        if (accepted) {
                            const text = format(messages.stock_inactive_removed, { name });
                            announce(text);
                            showAlert(text, 'warning');
                        }
                    })
                    .finally(() => {
                        removing.delete(id);
                    });
            }
        },
    });
}

const root = document.getElementById('pos-root');

if (root) {
    init(root);
}
