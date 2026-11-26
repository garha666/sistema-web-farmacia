import './bootstrap';

const queue = document.querySelector('[data-prescription-queue]');

if (queue) {
    const count = queue.querySelector('[data-queue-count]');
    const list = queue.querySelector('[data-queue-list]');
    const error = queue.querySelector('[data-queue-error]');
    let lastHtml = list.innerHTML.trim();

    async function refreshQueue() {
        const startedAt = performance.now();
        try {
            const response = await fetch(queue.dataset.prescriptionQueue, {
                headers: { Accept: 'application/json' },
                cache: 'no-store',
                signal: AbortSignal.timeout(7000),
            });
            if (!response.ok) throw new Error();

            const pending = await response.json();
            error.hidden = true;
            if (count.textContent !== String(pending.count)) count.textContent = pending.count;

            // Keep keyboard focus on the current action while the pharmacist is using it.
            if (pending.html.trim() !== lastHtml && !list.contains(document.activeElement)) {
                list.innerHTML = pending.html;
                lastHtml = pending.html.trim();
            }
        } catch {
            error.hidden = false;
        } finally {
            window.setTimeout(refreshQueue, Math.max(0, 8000 - (performance.now() - startedAt)));
        }
    }

    window.setTimeout(refreshQueue, 8000);
}
