/* Used by both registered asset handles. Each editor preview gets its own root. */
(() => {
    function mountClock({ root, signal }) {
        const view = (root.ownerDocument || root).defaultView;
        const pending = new Set();
        let stopped = false;
        async function onClick(event) {
            const button = event.target.closest?.('[data-rrb-clock-refresh]');
            if (!button || !root.contains(button) || button.disabled || stopped) return;
            const card = button.closest('[data-rrb-clock]');
            const output = card.querySelector('[data-rrb-clock-output]');
            const status = card.querySelector('[role="status"]');
            const controller = new view.AbortController();
            pending.add(controller);
            const timeout = view.setTimeout(() => controller.abort(), 10000);
            button.disabled = true;
            card.setAttribute('aria-busy', 'true');
            status.textContent = 'Requesting a fresh value from PHP…';
            try {
                const response = await view.fetch(card.dataset.endpoint, {
                    method: 'GET', credentials: 'omit', cache: 'no-store', signal: controller.signal,
                });
                if (!response.ok) throw new Error('Request failed');
                const result = await response.json();
                if (!result.success || typeof result.data?.utc !== 'string') throw new Error('Invalid response');
                if (stopped || signal?.aborted || !root.contains(card)) return;
                output.textContent = result.data.utc;
                status.textContent = 'Updated from PHP over Ajax. No page reload.';
            } catch (error) {
                if (!stopped && !signal?.aborted && root.contains(card)) {
                    status.textContent = 'Could not refresh. Please try again.';
                }
            } finally {
                view.clearTimeout(timeout);
                pending.delete(controller);
                if (!stopped && root.contains(card)) {
                    card.removeAttribute('aria-busy');
                    button.disabled = false;
                }
            }
        }
        function cleanup() {
            stopped = true;
            root.removeEventListener('click', onClick);
            signal?.removeEventListener('abort', cleanup);
            pending.forEach((controller) => controller.abort());
            pending.clear();
        }
        if (signal?.aborted) return cleanup;
        root.addEventListener('click', onClick);
        signal?.addEventListener('abort', cleanup, { once: true });
        return cleanup;
    }
    if (window.chbEditor) {
        window.chbEditor.registerPreview('examples/ajax-clock', mountClock);
    } else {
        mountClock({ root: document });
    }
})();
