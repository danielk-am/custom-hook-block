<?php
/** Code Snippets: PHP, Run everywhere. Custom Hook Block 2.0.0 must be active. */
add_action( 'chb_register_renderers', function () {
    chb_register_renderer( 'examples/php-card', array(
        'title' => 'PHP greeting from Code Snippets',
        'description' => 'A PHP callback registered by a Code Snippets snippet.',
        'settings' => array(
            'heading' => array( 'type' => 'string', 'title' => 'Heading', 'default' => 'Hello from PHP', 'maxLength' => 120 ),
            'message' => array( 'type' => 'string', 'title' => 'Message', 'default' => 'This card is rendered by the PHP snippet, not saved HTML.', 'maxLength' => 500 ),
        ),
        'callback' => function ( $settings ) {
            return '<section><h3>' . esc_html( $settings['heading'] ) . '</h3><p>' . esc_html( $settings['message'] ) . '</p><p><small>Registered through chb_register_renderers.</small></p></section>';
        },
    ) );
} );
