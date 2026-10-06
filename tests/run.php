<?php
/**
 * Run with `studio wp eval-file <this-file>` on a disposable WordPress site.
 * Creates and removes its own fixture users/posts. Never run on production.
 */
if ( ! defined( 'ABSPATH' ) || ! function_exists( 'rrb_register_renderer' ) ) {
	fwrite( STDERR, "Load WordPress and activate Registered Render Blocks first.\n" );
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

$old_user = get_current_user_id();
$old_post = $GLOBALS['post'] ?? null;
$stamp = strtolower( wp_generate_password( 10, false, false ) );
$admin = wp_insert_user( array( 'user_login' => 'rrb_admin_' . $stamp, 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) );
$author = wp_insert_user( array( 'user_login' => 'rrb_author_' . $stamp, 'user_pass' => wp_generate_password(), 'role' => 'author' ) );
$public = wp_insert_post( array( 'post_title' => 'RRB fixture', 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $author ) );
$private = wp_insert_post( array( 'post_title' => 'RRB private fixture', 'post_type' => 'post', 'post_status' => 'private', 'post_author' => $admin ) );
$password = wp_insert_post( array( 'post_title' => 'RRB password fixture', 'post_type' => 'post', 'post_status' => 'publish', 'post_author' => $admin, 'post_password' => 'rrb-password' ) );
$request = static function ( $attrs, $post_id = 0 ) {
	$req = new WP_REST_Request( 'POST', '/wp/v2/block-renderer/registered-render-blocks/renderer' );
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
	$check( 'Anonymous no-post preview blocked', $request( array( 'renderer' => 'notice' ) )->get_status() >= 400 );
	wp_set_current_user( $author );
	$response = $request( array( 'renderer' => 'tests/context' ), $public );
	$data = $response->get_data();
	$html = html_entity_decode( $data['rendered'] ?? '' );
	$check( 'Authorized post SSR succeeds', 200 === $response->get_status() && false !== strpos( $html, '"post_id":' . $public ) && false !== strpos( $html, '"preview":true' ) );
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
