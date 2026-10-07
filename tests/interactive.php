<?php
/** Disposable WordPress: interaction capability must come from trusted PHP, never saved attributes. */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'rrb_register_renderer' ) ) { throw new RuntimeException( 'Load the plugin in disposable WordPress.' ); }
$results = array();
$check = static function ( $name, $pass ) use ( &$results ) { $results[] = array( 'test' => $name, 'pass' => (bool) $pass ); };
$definition = array( 'title' => 'Interactive fixture', 'callback' => static function () { return '<button type="button">Fixture</button>'; } );
rrb_register_renderer( 'tests/passive', $definition );
rrb_register_renderer( 'tests/interactive', array_merge( $definition, array( 'interactive' => true ) ) );
$registry = rrb_registry();
$check( 'Interactive capability defaults strictly false', isset( $registry['tests/passive']['interactive'] ) && false === $registry['tests/passive']['interactive'] );
$check( 'Trusted PHP opt-in stored strictly true', isset( $registry['tests/interactive']['interactive'] ) && true === $registry['tests/interactive']['interactive'] );
foreach ( array( 'true', 'false', 1, 0, array(), null ) as $i => $bad ) {
    $r = rrb_register_renderer( 'tests/invalid-interactive-' . $i, array_merge( $definition, array( 'interactive' => $bad ) ) );
    $check( 'Nonboolean interactive rejected: ' . wp_json_encode( $bad ), is_wp_error( $r ) && 'rrb_invalid_interactive' === $r->get_error_code() );
}
$hook = chb_register_hook( 'chb_interactive_fixture', array( 'title' => 'Interactive hook', 'interactive' => true ) );
$registry = rrb_registry();
$check( 'Display hooks support trusted interactive opt-in', true === $hook && true === ( $registry[ 'hook-' . md5( 'chb_interactive_fixture' ) ]['interactive'] ?? false ) );
foreach ( array( 'mytheme/custom-hook-block', 'registered-render-blocks/renderer' ) as $name ) {
    $type = WP_Block_Type_Registry::get_instance()->get_registered( $name );
    $attrs = $type ? $type->get_attributes() : array();
    foreach ( array( 'interactive', 'interact', 'editor_script_handles', 'scripts', 'initializer' ) as $key ) {
        $check( $name . ': saved attributes cannot set ' . $key, ! array_key_exists( $key, $attrs ) );
    }
}
$old_user = get_current_user_id();
$admin = wp_insert_user( array( 'user_login' => 'chb_interactive_' . strtolower( wp_generate_password( 10, false, false ) ), 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
if ( is_wp_error( $admin ) ) { throw new RuntimeException( $admin->get_error_message() ); }
try {
    foreach ( array( 'mytheme/custom-hook-block', 'registered-render-blocks/renderer' ) as $name ) {
        $request = static function ( $attrs ) use ( $name ) { $r = new WP_REST_Request( 'POST', '/wp/v2/block-renderer/' . $name ); $r->set_param( 'context', 'edit' ); $r->set_param( 'attributes', $attrs ); return rest_get_server()->dispatch( $r ); };
        wp_set_current_user( 0 );
        $check( $name . ': interactive SSR remains authenticated', $request( array( 'renderer' => 'tests/interactive' ) )->get_status() >= 400 );
        wp_set_current_user( $admin );
        $check( $name . ': authorized interactive SSR succeeds', 200 === $request( array( 'renderer' => 'tests/interactive' ) )->get_status() );
        foreach ( array( 'interactive' => true, 'editor_script_handles' => array( 'evil' ), 'scripts' => '<script>alert(1)</script>', 'initializer' => 'evil' ) as $key => $value ) {
            $check( $name . ': authorized SSR rejects injected ' . $key, $request( array( 'renderer' => 'tests/interactive', $key => $value ) )->get_status() >= 400 );
        }
    }
} finally {
    require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user( $admin ); wp_set_current_user( $old_user );
}
$failed = count( array_filter( $results, static function ( $r ) { return ! $r['pass']; } ) );
echo wp_json_encode( array( 'passed' => count( $results ) - $failed, 'failed' => $failed, 'results' => $results ), JSON_PRETTY_PRINT ) . "\n";
if ( $failed && defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::halt( 1 ); }
