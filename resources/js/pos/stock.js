/**
 * T33 - RF-88, RNF-33: stock vigente del POS por sondeo.
 *
 * Cada `intervalMs` consulta `GET /pos/stock` con los ids de las líneas del
 * carrito (una sola petición por intervalo) y avisa cuando una línea se agota,
 * queda corta o sale del catálogo. No pinta el carrito: sólo anota cada línea
 * y avisa, así que nunca interrumpe al cajero ni recarga la página.
 */

const NOTE_CLASS = 'mt-1 text-xs font-semibold text-amber-700';

export function format(template, params = {}) {
    return String(template ?? '').replace(/:(\w+)/g, (match, key) => (
        Object.prototype.hasOwnProperty.call(params, key) ? String(params[key]) : match
    ));
}

export function readCartLines(root) {
    return Array.from(root.querySelectorAll('[data-cart-line]'))
        .map((element) => {
            const input = element.querySelector('input[name="quantity"]');

            return {
                id: Number(element.dataset.productId),
                name: element.dataset.productName ?? '',
                quantity: input ? Number(input.defaultValue || input.value) : 0,
                element,
            };
        })
        .filter((line) => Number.isInteger(line.id));
}

/**
 * Estado de una línea frente al stock vivo: `inactive`, `out`, `low` u `ok`.
 * Un producto que el servidor ya no devuelve cuenta como fuera de catálogo.
 */
export function classifyLine(line, live) {
    if (!live || live.active === false) {
        return { key: 'inactive', state: 'inactive', stock: 0 };
    }

    const stock = Math.max(0, Number(live.stock) || 0);

    if (stock === 0) {
        return { key: 'out', state: 'out', stock };
    }

    if (stock < line.quantity) {
        return { key: `low:${stock}`, state: 'low', stock };
    }

    return { key: 'ok', state: 'ok', stock };
}

/**
 * Compara el stock vivo con lo que ya se avisó (`known`: id -> key) y devuelve
 * los estados por línea, los avisos nuevos y las líneas fuera de catálogo.
 * Función pura: no toca el DOM.
 */
export function planStockChanges(lines, products, known, messages) {
    const liveById = new Map((products ?? []).map((product) => [Number(product.id), product]));
    const states = {};
    const keys = {};
    const announcements = [];
    const inactive = [];

    for (const line of lines) {
        const result = classifyLine(line, liveById.get(line.id));
        states[line.id] = result;
        keys[line.id] = result.key;

        if (result.state === 'inactive') {
            inactive.push({ id: line.id, name: line.name });
        }

        if (result.state === 'ok' || (known[line.id] ?? 'ok') === result.key) {
            continue;
        }

        if (result.state === 'out') {
            announcements.push(format(messages.stock_out, { name: line.name }));
        } else if (result.state === 'low') {
            announcements.push(format(messages.stock_low, { name: line.name, stock: result.stock }));
        }
    }

    return { states, keys, announcements, inactive };
}

export function applyStockStates(lines, states, messages) {
    for (const line of lines) {
        const result = states[line.id];
        const input = line.element.querySelector('input[name="quantity"]');
        let note = line.element.querySelector('[data-stock-note]');

        if (input && result && result.stock > 0) {
            input.max = String(result.stock);
        }

        const text = result?.state === 'out'
            ? messages.stock_note_out
            : result?.state === 'low'
                ? format(messages.stock_note_low, { stock: result.stock })
                : '';

        if (text === '') {
            note?.remove();
            continue;
        }

        if (!note) {
            note = document.createElement('p');
            note.className = NOTE_CLASS;
            note.dataset.stockNote = '';
            const anchor = line.element.querySelector('[data-line-controls]');
            if (anchor) {
                anchor.after(note);
            } else {
                line.element.append(note);
            }
        }

        note.textContent = text;
    }
}

export function startStockPolling({
    root,
    http,
    url,
    intervalMs,
    messages,
    announce,
    isBusy = () => false,
    onInactive = () => {},
    onUnauthorized = () => {},
}) {
    let known = {};
    let live = null;
    let timer = null;
    let inFlight = false;
    let version = 0;
    let stopped = false;

    function schedule(delay = intervalMs) {
        window.clearTimeout(timer);
        timer = null;

        if (stopped || readCartLines(root).length === 0) {
            return;
        }

        timer = window.setTimeout(tick, delay);
    }

    function handle(products, { replay = false } = {}) {
        let lines = readCartLines(root);

        if (replay) {
            // Una línea nueva aún no se ha sondeado: no es "fuera de catálogo".
            const polled = new Set(products.map((product) => Number(product.id)));
            lines = lines.filter((line) => polled.has(line.id));
        }

        const plan = planStockChanges(lines, products, known, messages);

        known = plan.keys;
        applyStockStates(lines, plan.states, messages);

        if (plan.announcements.length > 0) {
            announce(plan.announcements.join(' '));
        }

        if (plan.inactive.length > 0) {
            onInactive(plan.inactive);
        }
    }

    async function tick() {
        timer = null;

        if (stopped || readCartLines(root).length === 0) {
            return;
        }

        if (inFlight || document.hidden || isBusy()) {
            schedule();

            return;
        }

        inFlight = true;
        const startedAt = version;
        const ids = readCartLines(root).map((line) => line.id);

        try {
            const { data } = await http.get(url, {
                params: { product_ids: ids },
                paramsSerializer: { indexes: false },
            });

            // Si el carrito cambió mientras la petición iba en vuelo, la
            // respuesta ya no describe las mismas líneas: se descarta.
            if (version === startedAt && Array.isArray(data?.products)) {
                live = data.products;
                handle(live);
            }
        } catch (error) {
            if (error?.response?.status === 401) {
                onUnauthorized();
            }
            // Cualquier otro fallo del sondeo es silencioso: no interrumpe al cajero.
        } finally {
            inFlight = false;
            schedule();
        }
    }

    function onVisibility() {
        if (!document.hidden) {
            schedule(0);
        }
    }

    document.addEventListener('visibilitychange', onVisibility);
    schedule();

    return {
        /** El carrito se repintó: se reaplican las notas y se reinicia el intervalo. */
        cartChanged() {
            version += 1;

            if (live !== null) {
                handle(live, { replay: true });
            }

            schedule();
        },
        stop() {
            stopped = true;
            window.clearTimeout(timer);
            document.removeEventListener('visibilitychange', onVisibility);
        },
    };
}
