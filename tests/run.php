<?php
/**
 * Run with `studio wp eval-file <this-file>` on a disposable WordPress site.
 * Creates and removes its own fixture users/posts. Never run on production.
 */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'rrb_register_renderer' ) ) {
	fwrite( STDERR, "Load WordPress and activate Custom Hook Block 2.0.0 first.\n" );
	exit( 1 );
}
$results = array();
$check = static function ( $name, $pass ) use ( &$results ) { $results[] = array( 'test' => $name, 'pass' => (bool) $pass ); };
$renderer = array(
	'title' => 'Test renderer',
	'settings' => array(
		'heading' => array( 'type' => 'string', 'default' => 'Default', 'maxLength' => 20 ),
		'visible' => array( 'type' => 'boolean', 'default' => true ),
		'count' => array( 'type' => 'integer', 'default' => 1, 'minimum' => 0, 'maximum' => 5 ),
		'layout' => array( 'type' => 'string', 'enum' => array( 'table', 'list' ), 'default' => 'table' ),
	),
	'callback' => static function ( $settings, $context ) { return '<p>' . esc_html( wp_json_encode( array( 'settings' => $settings, 'context' => $context ) ) ) . '</p>'; },
	'legacy_hooks' => array( 'rrb_fixture_hook' ),
);
$check( 'Namespaced renderer can register', true === rrb_register_renderer( 'tests/context', $renderer ) );
$check( 'Duplicate renderer rejected', is_wp_error( rrb_register_renderer( 'tests/context', $renderer ) ) );
foreach ( array( '<script>', '../notice', 'notice;phpinfo()', 'NOTICE', '', array( 'notice' ) ) as $bad ) {
	$check( 'Malicious/invalid identifier rejected: ' . wp_json_encode( $bad ), is_wp_error( rrb_register_renderer( $bad, $renderer ) ) );
}
$check( 'Missing callable rejected', is_wp_error( rrb_register_renderer( 'tests/no-callback', array( 'title' => 'Missing' ) ) ) );
$check( 'Invalid default rejected', is_wp_error( rrb_register_renderer( 'tests/bad-default', array( 'title' => 'Bad', 'callback' => '__return_empty_string', 'settings' => array( 'x' => array( 'type' => 'boolean', 'default' => 'yes' ) ) ) ) ) );
$valid = rrb_validate_settings( array(), $renderer['settings'] );
$check( 'Schema defaults applied', $valid === array( 'heading' => 'Default', 'visible' => true, 'count' => 1, 'layout' => 'table' ) );
foreach ( array( array( 'visible' => 'false' ), array( 'count' => '3' ), array( 'count' => 1.5 ), array( 'count' => 99 ), array( 'layout' => 'javascript:alert(1)' ), array( 'heading' => array( 'x' ) ), array( 'heading' => str_repeat( 'x', 21 ) ), array( 'unknown' => 'x' ) ) as $bad ) {
	$check( 'Invalid setting rejected: ' . wp_json_encode( $bad ), is_wp_error( rrb_validate_settings( $bad, $renderer['settings'] ) ) );
}
$render = static function ( $attrs, $context = array(), $name = 'registered-render-blocks/renderer' ) {
	$block = new WP_Block( array( 'blockName' => $name, 'attrs' => $attrs, 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), $context );
	return $block->render();
};
$check( 'Unknown renderer produces no frontend output', '' === $render( array( 'renderer' => 'not/registered' ) ) );
$check( 'Non-scalar settings produce no frontend output', '' === $render( array( 'renderer' => 'notice', 'settings' => array( 'message' => array( 'bad' ) ) ) ) );
$notice = $render( array( 'renderer' => 'notice', 'settings' => array( 'heading' => '<img onerror=alert(1)>', 'message' => '<script>alert(1)</script>' ) ) );
$check( 'Bundled notice escapes HTML', false === strpos( $notice, '<script>' ) && false === strpos( $notice, '<img' ) && false !== strpos( $notice, '&lt;script&gt;' ) );
$front = html_entity_decode( $render( array( 'renderer' => 'tests/context', 'previewPostId' => 999999 ), array( 'postId' => 0 ) ) );
$check( 'Frontend uses block context, never previewPostId', false !== strpos( $front, '"post_id":0' ) && false !== strpos( $front, '"preview":false' ) && false === strpos( $front, '999999' ) );
$hook_called = false;
add_action( 'rrb_unregistered_danger_hook', static function () use ( &$hook_called ) { $hook_called = true; } );
$legacy = $render( array( 'hookName' => 'rrb_unregistered_danger_hook' ), array(), 'mytheme/custom-hook-block' );
$check( 'Unknown legacy hook never dispatches action', '' === $legacy && ! $hook_called );
$legacy = html_entity_decode( $render( array( 'hookName' => 'rrb_fixture_hook' ), array( 'postId' => 0 ), 'mytheme/custom-hook-block' ) );
$check( 'Explicit legacy alias maps to trusted renderer', false !== strpos( $legacy, '"post_id":0' ) );

// Canonical block and approved display-hook coverage.
$canonical = WP_Block_Type_Registry::get_instance()->get_registered( 'mytheme/custom-hook-block' );
$compat = WP_Block_Type_Registry::get_instance()->get_registered( 'registered-render-blocks/renderer' );
$check( 'Canonical hook block visible in inserter', $canonical && false !== ( $canonical->supports['inserter'] ?? true ) );
$check( 'RRB compatibility block hidden from inserter', $compat && false === ( $compat->supports['inserter'] ?? true ) );
$check( 'Hook registration API exists', function_exists( 'chb_register_hook' ) );
if ( function_exists( 'chb_register_hook' ) ) {
    $hook_calls = 0;
    add_action( 'chb_fixture_display', static function ( $settings, $context ) use ( &$hook_calls ) {
        ++$hook_calls;
        echo '<p class="fixture-display">' . esc_html( wp_json_encode( array( 'settings' => $settings, 'context' => $context ) ) ) . '</p>';
    }, 10, 2 );
    $definition = array( 'title' => 'Approved display hook', 'settings' => $renderer['settings'] );
    $check( 'Approved display hook registers', true === chb_register_hook( 'chb_fixture_display', $definition ) );
    $check( 'Duplicate display hook registration rejected', is_wp_error( chb_register_hook( 'chb_fixture_display', $definition ) ) );
    foreach ( array( '', '../hook', 'hook;phpinfo()', 'hook with spaces', array( 'hook' ) ) as $bad ) {
        $check( 'Invalid hook identifier rejected: ' . wp_json_encode( $bad ), is_wp_error( chb_register_hook( $bad, $definition ) ) );
    }
    $html = html_entity_decode( $render( array( 'hookName' => 'chb_fixture_display', 'settings' => array( 'count' => 3, 'visible' => false, 'layout' => 'list' ) ), array( 'postId' => 0 ), 'mytheme/custom-hook-block' ) );
    $check( 'Saved legacy hookName invokes approved echo action exactly once', 1 === $hook_calls && false !== strpos( $html, 'fixture-display' ) );
    $check( 'Hook receives typed settings and defaults', false !== strpos( $html, '"count":3' ) && false !== strpos( $html, '"visible":false' ) && false !== strpos( $html, '"heading":"Default"' ) );
    $check( 'Hook receives frontend context', false !== strpos( $html, '"post_id":0' ) && false !== strpos( $html, '"preview":false' ) );
    $html = $render( array( 'renderer' => 'hook-' . md5( 'chb_fixture_display' ), 'settings' => array() ), array(), 'mytheme/custom-hook-block' );
    $check( 'Registered hook renderer ID invokes same display action', 2 === $hook_calls && false !== strpos( $html, 'fixture-display' ) );
    $html = $render( array( 'hookName' => 'chb_fixture_display', 'settings' => array( 'count' => '3' ) ), array(), 'mytheme/custom-hook-block' );
    $check( 'Invalid typed hook settings never execute action', '' === $html && 2 === $hook_calls );
    $default_calls = 0;
    add_action( 'my_custom_hook', static function () use ( &$default_calls ) { ++$default_calls; echo '<p>Original default hook output</p>'; } );
    $check( 'Original default hook explicitly registered', true === chb_register_hook( 'my_custom_hook', array( 'title' => 'Original default' ) ) );
    $html = $render( array(), array(), 'mytheme/custom-hook-block' );
    $check( 'Original block missing hookName resolves registered my_custom_hook', 1 === $default_calls && false !== strpos( $html, 'Original default hook output' ) );
    $html = $render( array( 'hookName' => '' ), array(), 'mytheme/custom-hook-block' );
    $check( 'Explicit empty hookName starts empty without default action', '' === $html && 1 === $default_calls );
    $html = $render( array( 'renderer' => 'unknown/renderer', 'hookName' => 'my_custom_hook' ), array(), 'mytheme/custom-hook-block' );
    $check( 'Unknown explicit renderer never falls back to another hook', '' === $html && 1 === $default_calls );
    $check( 'Display hook definition cannot inject callback', is_wp_error( chb_register_hook( 'chb_callback_injection', array( 'title' => 'Bad', 'callback' => '__return_empty_string' ) ) ) );
    $check( 'Display hook definition cannot inject aliases', is_wp_error( chb_register_hook( 'chb_alias_injection', array( 'title' => 'Bad', 'legacy_hooks' => array( 'init' ) ) ) ) );
    $before_buffers = ob_get_level();
    add_action( 'chb_fixture_throw', static function () { echo 'PARTIAL_SECRET'; ob_start(); echo 'NESTED_SECRET'; throw new RuntimeException( 'fixture-only exception' ); } );
    chb_register_hook( 'chb_fixture_throw', array( 'title' => 'Throw fixture' ) );
    $html = $render( array( 'hookName' => 'chb_fixture_throw' ), array(), 'mytheme/custom-hook-block' );
    $check( 'Hook exception returns no partial frontend output', '' === $html );
    $check( 'Hook exception restores output-buffer depth', $before_buffers === ob_get_level() );
    $recursive_calls = 0;
    add_action( 'chb_fixture_recursive', static function () use ( &$recursive_calls, $render ) {
        ++$recursive_calls;
        if ( $recursive_calls < 4 ) { echo $render( array( 'hookName' => 'chb_fixture_recursive' ), array(), 'mytheme/custom-hook-block' ); }
        echo '<span>outer fixture</span>';
    } );
    chb_register_hook( 'chb_fixture_recursive', array( 'title' => 'Recursive fixture' ) );
    $html = $render( array( 'hookName' => 'chb_fixture_recursive' ), array(), 'mytheme/custom-hook-block' );
    $check( 'Recursive same hook suppressed after first dispatch', 1 === $recursive_calls && 1 === substr_count( $html, 'outer fixture' ) );
    $check( 'Recursive hook restores output-buffer depth', $before_buffers === ob_get_level() );
    $render( array( 'hookName' => 'chb_fixture_recursive' ), array(), 'mytheme/custom-hook-block' );
    $check( 'Recursion guard resets after render', 2 === $recursive_calls );
    $html = $render( array( 'renderer' => 'tests/context', 'anchor' => 'fixture-anchor', 'className' => 'fixture-native', 'style' => array( 'color' => array( 'background' => '#fefefe' ), 'spacing' => array( 'padding' => array( 'top' => '12px' ) ) ) ), array(), 'mytheme/custom-hook-block' );
    $check( 'Native wrapper keeps anchor and class', false !== strpos( $html, 'fixture-anchor' ) && false !== strpos( $html, 'fixture-native' ) );
    $check( 'Native wrapper preserves custom background', false !== strpos( $html, '#fefefe' ) && false === stripos( $html, '#2563eb' ) );
}

$old_user = get_current_user_id();
$old_post = $GLOBALS['post'] ?? null;
$stamp = strtolower( wp_generate_password( 10, false, false ) );
$admin = wp_insert_user( array( 'user_login' => 'rrb_admin_' . $stamp, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
$author = wp_insert_user( array( 'user_login' => 'rrb_author_' . $stamp, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$public = wp_insert_post( array( 'post_title' => 'RRB fixture', 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author ) );
$private = wp_insert_post( array( 'post_title' => 'RRB private fixture', 'post_type' => 'post', 'post_status' => 'private', 'post_author' => $admin ) );
$password = wp_insert_post( array( 'post_title' => 'RRB password fixture', 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $admin, 'post_password' => 'rrb-password' ) );
$request = static function ( $attrs, $post_id = 0, $name = 'registered-render-blocks/renderer' ) {
	$req = new WP_REST_Request( 'POST', '/wp/v2/block-renderer/' . $name );
	$req->set_param( 'context', 'edit' );
	$req->set_param( 'attributes', $attrs );
	$req->set_param( 'post_id', $post_id );
	return rest_get_server()->dispatch( $req );
};
try {
	wp_set_current_user( 0 );
	$check( 'Anonymous private context produces no frontend output', '' === $render( array( 'renderer' => 'tests/context' ), array( 'postId' => $private ) ) );
	$check( 'Password-protected context produces no frontend output', '' === $render( array( 'renderer' => 'tests/context' ), array( 'postId' => $password ) ) );
	$context_html = html_entity_decode( $render( array( 'renderer' => 'tests/context', 'previewPostId' => $private ), array( 'postId' => $public ) ) );
	$check( 'Public block context wins over saved preview ID', false !== strpos( $context_html, '"post_id":' . $public ) );
	$check( 'Anonymous SSR blocked', $request( array( 'renderer' => 'tests/context' ), $public )->get_status() >= 400 );
	$check( 'Anonymous canonical preview blocked', $request( array( 'renderer' => 'tests/context' ), $public, 'mytheme/custom-hook-block' )->get_status() >= 400 );
	$check( 'Anonymous no-post preview blocked', $request( array( 'renderer' => 'notice' ) )->get_status() >= 400 );
	wp_set_current_user( $author );
	$response = $request( array( 'renderer' => 'tests/context' ), $public );
	$data = $response->get_data();
	$html = html_entity_decode( $data['rendered'] ?? '' );
	$check( 'Authorized post SSR succeeds', 200 === $response->get_status() && false !== strpos( $html, '"post_id":' . $public ) && false !== strpos( $html, '"preview":true' ) );
	$check( 'Authorized canonical preview succeeds', 200 === $request( array( 'hookName' => 'chb_fixture_display' ), $public, 'mytheme/custom-hook-block' )->get_status() );
	$check( 'Canonical private-post preview denied', $request( array( 'hookName' => 'chb_fixture_display' ), $private, 'mytheme/custom-hook-block' )->get_status() >= 400 );
	$check( 'Other author private-post preview denied', $request( array( 'renderer' => 'tests/context' ), $private )->get_status() >= 400 );
	$check( 'No-post template preview requires edit_theme_options', $request( array( 'renderer' => 'notice' ) )->get_status() >= 400 );
	wp_set_current_user( $admin );
	$check( 'Theme editor can preview without post', 200 === $request( array( 'renderer' => 'notice' ) )->get_status() );
	$check( 'Saved previewPostId is rejected by REST schema', $request( array( 'renderer' => 'tests/context', 'previewPostId' => $private ), $public )->get_status() >= 400 );
	$check( 'SSR request state cleared', null === rrb_preview_state() );
	$front = html_entity_decode( $render( array( 'renderer' => 'tests/context' ), array( 'postId' => $public ) ) );
	$check( 'Frontend after SSR never inherits preview flag', false !== strpos( $front, '"preview":false' ) );
} finally {
	wp_delete_post( $public, true );
	wp_delete_post( $private, true );
	wp_delete_post( $password, true );
	require_once ABSPATH . 'wp-admin/includes/user.php';
	wp_delete_user( $author );
	wp_delete_user( $admin );
	wp_set_current_user( $old_user );
	$GLOBALS['post'] = $old_post;
}
$failed = count( array_filter( $results, static function ( $row ) { return ! $row['pass']; } ) );
echo wp_json_encode( array( 'passed' => count( $results ) - $failed, 'failed' => $failed, 'results' => $results ), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) . "\n";
if ( $failed && defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::halt( 1 ); }
