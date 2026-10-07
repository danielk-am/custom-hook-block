/* Trusted frontend asset registered by the PHP snippet. No editor interaction. */
(() => {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest?.('[data-rrb-clock-refresh]');
        if (!button || button.disabled) return;
        const card = button.closest('[data-rrb-clock]');
        const output = card.querySelector('[data-rrb-clock-output]');
        const status = card.querySelector('[role="status"]');
        const controller = new AbortController();
        const timeout = setTimeout(() => controller.abort(), 10000);
        button.disabled = true;
        card.setAttribute('aria-busy', 'true');
        status.textContent = 'Requesting a fresh value from PHP…';
        try {
            const response = await fetch(card.dataset.endpoint, {
                method: 'GET', credentials: 'omit', cache: 'no-store', signal: controller.signal,
            });
            if (!response.ok) throw new Error('Request failed');
            const result = await response.json();
            if (!result.success || typeof result.data?.utc !== 'string') throw new Error('Invalid response');
            output.textContent = result.data.utc;
            status.textContent = 'Updated from PHP over Ajax. The page did not reload.';
        } catch (error) {
            status.textContent = 'Could not refresh. Please try again.';
        } finally {
            clearTimeout(timeout);
            card.removeAttribute('aria-busy');
            button.disabled = false;
        }
    });
})();
