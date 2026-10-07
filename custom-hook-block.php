<?php
/**
 * Plugin Name: Custom Hook Block
 * Description: Display explicitly registered PHP hooks and renderers with native block controls and shared server previews.
 * Version: 2.0.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Daniel Kam
 * Author URI: https://danielk.am
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: custom-hook-block
 *
 * @package Custom_Hook_Block
 */

defined( 'ABSPATH' ) || exit;
define( 'CHB_VERSION', '2.0.0' );

/** Delay compatibility APIs until every active plugin's main file has loaded. */
function chb_bootstrap() {
	if ( function_exists( 'rrb_registry' ) ) {
		add_action( 'admin_notices', 'chb_conflict_notice' );
		return;
	}
	require_once __DIR__ . '/includes/registry.php';
	require_once __DIR__ . '/includes/block.php';
}
add_action( 'plugins_loaded', 'chb_bootstrap', 0 );

/** Keep the existing provider active without duplicate functions or blocks. */
function chb_conflict_notice() {
	if ( ! current_user_can( 'activate_plugins' ) ) {
		return;
	}
	echo '<div class="notice notice-warning"><p>' . esc_html__( 'Custom Hook Block is paused because Registered Render Blocks is active. Deactivate Registered Render Blocks to use Custom Hook Block; saved blocks remain compatible.', 'custom-hook-block' ) . '</p></div>';
}
