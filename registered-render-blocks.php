<?php
/**
 * Plugin Name: Registered Render Blocks
 * Description: Display developer-registered renderers as editable blocks with shared server previews.
 * Version: 0.1.0
 * Requires at least: 6.9
 * Requires PHP: 7.4
 * Author: Daniel Kam
 * Author URI: https://danielk.am
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: registered-render-blocks
 *
 * @package Registered_Render_Blocks
 */

defined( 'ABSPATH' ) || exit;
define( 'RRB_VERSION', '0.1.0' );
require_once __DIR__ . '/includes/registry.php';
require_once __DIR__ . '/includes/block.php';
