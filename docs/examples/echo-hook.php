<?php
/** Code Snippets: PHP, Run everywhere. Custom Hook Block 2.0.0 must be active. */
// Keep the familiar WordPress action callback. Escape every dynamic value.
add_action( 'my_custom_hook', function ( $settings, $context ) {
    echo '<section><h3>' . esc_html( $settings['heading'] ) . '</h3>';
    echo '<p>' . esc_html( $settings['message'] ) . '</p></section>';
}, 10, 2 );

// Explicitly approve this dedicated display hook for block output.
add_action( 'chb_register_renderers', function () {
    chb_register_hook( 'my_custom_hook', array(
        'title'       => 'Greeting from a registered hook',
        'description' => 'A WordPress action echoes the content; Custom Hook Block captures its output.',
        'settings'    => array(
            'heading' => array( 'type' => 'string', 'title' => 'Heading', 'default' => 'Hello from a WordPress hook', 'maxLength' => 120 ),
            'message' => array( 'type' => 'string', 'title' => 'Message', 'default' => 'This content comes from add_action. Edit it using the block settings.', 'maxLength' => 500 ),
        ),
    ) );
} );
