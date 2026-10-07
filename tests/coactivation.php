<?php
/** Standalone bootstrap isolation: php coactivation.php old-first|new-first /path/to/old/registered-render-blocks.php */
if ( PHP_SAPI !== 'cli' || empty( $argv[2] ) || ! in_array( $argv[1] ?? '', array( 'old-first', 'new-first' ), true ) ) { fwrite( STDERR, "Supply load order and original RRB plugin main file.\n" ); exit( 1 ); }
define( 'ABSPATH', __DIR__ . '/' );
$hooks = array();
function add_action( $hook, $callback, $priority = 10, $args = 1 ) { $GLOBALS['hooks'][ $hook ][ $priority ][] = $callback; }
function add_filter( ...$args ) { add_action( ...$args ); }
$old = realpath( $argv[2] );
$new = dirname( __DIR__ ) . '/custom-hook-block.php';
if ( ! $old ) { throw new RuntimeException( 'Original RRB file unavailable.' ); }
if ( 'old-first' === $argv[1] ) { require $old; require $new; } else { require $new; require $old; }
// Simulate WordPress reaching plugins_loaded only after both main files have loaded.
chb_bootstrap();
$reflection = new ReflectionFunction( 'rrb_registry' );
$original_owns_api = str_starts_with( realpath( $reflection->getFileName() ), dirname( $old ) . DIRECTORY_SEPARATOR );
$notice = in_array( 'chb_conflict_notice', $hooks['admin_notices'][10] ?? array(), true );
$pass = $original_owns_api && $notice && ! function_exists( 'chb_register_hook' );
echo json_encode( array( 'order' => $argv[1], 'original_api_preserved' => $original_owns_api, 'notice_registered' => $notice, 'new_provider_paused' => ! function_exists( 'chb_register_hook' ), 'pass' => $pass ), JSON_PRETTY_PRINT ) . "\n";
exit( $pass ? 0 : 1 );
