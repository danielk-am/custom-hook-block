<?php
/** Code Snippets: PHP, Run everywhere. The JavaScript supports frontend and editor preview lifecycles. */
// Public, read-only demo endpoint: returns only server UTC time. No private data or writes.
// A nonce is deliberately unnecessary for this public clock; add authentication,
// capability checks and CSRF protection before adapting this to private data or mutations.
$rrb_clock_response = static function () {
    if ( 'GET' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
        wp_send_json_error( array( 'message' => 'Use GET.' ), 405 );
    }
    nocache_headers();
    wp_send_json_success( array( 'utc' => gmdate( 'Y-m-d H:i:s' ) . ' UTC' ) );
};
add_action( 'wp_ajax_rrb_example_clock', $rrb_clock_response );
add_action( 'wp_ajax_nopriv_rrb_example_clock', $rrb_clock_response );

add_action( 'chb_register_renderers', function () {
    $script = <<<'CHB_JS'
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
CHB_JS;
    wp_register_script( 'rrb-example-clock', false, array(), '2.1.0', true );
    wp_add_inline_script( 'rrb-example-clock', $script );
    wp_register_script( 'chb-example-clock-editor', false, array(), '2.1.0', true );
    wp_add_inline_script( 'chb-example-clock-editor', $script );
    chb_register_renderer( 'examples/ajax-clock', array(
        'title' => 'Ajax server clock from Code Snippets',
        'description' => 'PHP renders the card; JavaScript refreshes its server time in the editor and on the site.',
        'interactive' => true,
        'editor_script_handles' => array( 'chb-example-clock-editor' ),
        'settings' => array(
            'heading' => array( 'type' => 'string', 'title' => 'Heading', 'default' => 'PHP + JavaScript + Ajax', 'maxLength' => 120 ),
        ),
        'view_script_handles' => array( 'rrb-example-clock' ),
        'callback' => function ( $settings, $context ) {
            $endpoint = add_query_arg( 'action', 'rrb_example_clock', admin_url( 'admin-ajax.php' ) );
            $hint = $context['preview'] ? 'Turn on Interact in the block toolbar to test this Ajax button.' : 'Press the button to request a fresh timestamp from PHP.';
            return '<section data-rrb-clock data-endpoint="' . esc_url( $endpoint ) . '"><h3>' . esc_html( $settings['heading'] ) . '</h3><p>Server time: <strong data-rrb-clock-output>' . esc_html( gmdate( 'Y-m-d H:i:s' ) . ' UTC' ) . '</strong></p><button type="button" class="wp-element-button" data-rrb-clock-refresh>Refresh server time</button><p role="status" aria-live="polite">' . esc_html( $hint ) . '</p></section>';
        },
    ) );
} );
