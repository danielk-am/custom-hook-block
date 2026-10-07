<?php
/** Block registration, rendering and authorized core REST previews. @package Custom_Hook_Block */
defined( 'ABSPATH' ) || exit;

/** Preview state belongs only to an authorized core block-renderer request. */
function &rrb_preview_state() {
	static $state = null;
	return $state;
}

/** Additional permissions for our core SSR endpoint; no custom REST route. */
function rrb_preview_permissions( $response, $handler, $request ) {
	$route = $request->get_route();
	if ( ! in_array( $route, array( '/wp/v2/block-renderer/registered-render-blocks/renderer', '/wp/v2/block-renderer/mytheme/custom-hook-block' ), true ) ) {
		return $response;
	}
	if ( null !== $response ) {
		return $response;
	}
	$post_id = absint( $request->get_param( 'post_id' ) );
	if ( ! is_user_logged_in() || ( $post_id && ( ! get_post( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) ) || ( ! $post_id && ! current_user_can( 'edit_theme_options' ) ) ) {
		return new WP_Error( 'rrb_preview_forbidden', __( 'You cannot preview this content.', 'custom-hook-block' ), array( 'status' => rest_authorization_required_code() ) );
	}
	$state =& rrb_preview_state();
	$state = array( 'request' => $request, 'post_id' => $post_id );
	return $response;
}
add_filter( 'rest_request_before_callbacks', 'rrb_preview_permissions', 10, 3 );

/** Do not let preview state bleed into other REST callbacks or frontend renders. */
function rrb_end_preview( $response, $handler, $request ) {
	$state =& rrb_preview_state();
	if ( $state && $state['request'] === $request ) {
		$state = null;
	}
	return $response;
}
add_filter( 'rest_request_after_callbacks', 'rrb_end_preview', 10, 3 );

/** Resolve a registered ID, or an explicitly registered legacy alias. */
function rrb_resolve_renderer( $attributes, $legacy = false ) {
	$registry = rrb_registry();
	$id = $attributes['renderer'] ?? '';
	if ( is_string( $id ) && '' !== $id ) {
		return isset( $registry[ $id ] ) ? $id : '';
	}
	// Version 1.0 used my_custom_hook when hookName was omitted.
	$hook_name = $attributes['hookName'] ?? 'my_custom_hook';
	if ( $legacy && is_string( $hook_name ) && '' !== $hook_name ) {
		foreach ( $registry as $registered_id => $renderer ) {
			if ( in_array( $hook_name, $renderer['legacy_hooks'], true ) ) {
				return $registered_id;
			}
		}
	}
	return '';
}

/** Render the same escaped/trusted callback output on the site and in SSR. */
function rrb_render_block( $attributes, $content, $block ) {
	$preview_state = rrb_preview_state();
	$preview = null !== $preview_state;
	$legacy = 'mytheme/custom-hook-block' === $block->name;
	$id = rrb_resolve_renderer( $attributes, $legacy );
	if ( '' === $id ) {
		return $preview ? '<p class="rrb-editor-message">' . esc_html__( 'Choose a registered renderer. Unregistered hooks are not executed.', 'custom-hook-block' ) . '</p>' : '';
	}
	$registry = rrb_registry();
	$renderer = $registry[ $id ];
	$settings = rrb_validate_settings( $attributes['settings'] ?? array(), $renderer['settings'] );
	if ( is_wp_error( $settings ) ) {
		return $preview ? '<p class="rrb-editor-message">' . esc_html( $settings->get_error_message() ) . '</p>' : '';
	}
	$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : ( is_singular() ? absint( get_queried_object_id() ) : 0 );
	if ( $preview ) {
		// Core SSR uses request post_id to establish block context. Do not accept
		// an unrelated ambient/global post or any saved previewPostId attribute.
		$post_id = isset( $block->context['postId'] ) ? absint( $block->context['postId'] ) : $preview_state['post_id'];
		if ( $post_id !== $preview_state['post_id'] || ( $post_id && ! current_user_can( 'edit_post', $post_id ) ) ) {
			return '';
		}
	}
	if ( ! $preview && $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || post_password_required( $post ) || ( ! is_post_publicly_viewable( $post ) && ! current_user_can( 'read_post', $post_id ) ) ) {
			return '';
		}
	}
	try {
		$output = call_user_func( $renderer['callback'], $settings, array( 'post_id' => $post_id, 'preview' => $preview ) );
	} catch ( Throwable $error ) {
		return $preview ? '<p class="rrb-editor-message">' . esc_html__( 'The renderer could not produce a preview.', 'custom-hook-block' ) . '</p>' : '';
	}
	if ( ! is_string( $output ) || '' === trim( $output ) ) {
		return $preview ? '<p class="rrb-editor-message">' . esc_html__( 'This renderer has no content for the selected post.', 'custom-hook-block' ) . '</p>' : '';
	}
	foreach ( $renderer['style_handles'] as $handle ) {
		if ( wp_style_is( $handle, 'registered' ) ) {
			wp_enqueue_style( $handle );
		}
	}
	if ( ! $preview ) {
		foreach ( $renderer['view_script_handles'] as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}
	// Output comes only from trusted PHP registration; callback authors escape
	// every value for its context. No executable source is read from attributes.
	$wrapper = array( 'class' => 'rrb-renderer rrb-renderer--' . sanitize_html_class( str_replace( '/', '-', $id ) ) );
	if ( isset( $attributes['anchor'] ) && is_string( $attributes['anchor'] ) && '' !== $attributes['anchor'] ) {
		$wrapper['id'] = $attributes['anchor'];
	}
	return '<div ' . get_block_wrapper_attributes( $wrapper ) . '>' . $output . '</div>';
}

/** Register bundled renderer and invite site/plugin integrations. */
function rrb_boot_renderers() {
	rrb_register_renderer( 'notice', array(
		'title'       => __( 'Notice', 'custom-hook-block' ),
		'description' => __( 'A heading and message rendered by PHP.', 'custom-hook-block' ),
		'settings'    => array(
			'heading' => array( 'type' => 'string', 'title' => __( 'Heading', 'custom-hook-block' ), 'default' => __( 'A useful notice', 'custom-hook-block' ), 'maxLength' => 160 ),
			'message' => array( 'type' => 'string', 'title' => __( 'Message', 'custom-hook-block' ), 'default' => __( 'Write a message in the block settings.', 'custom-hook-block' ), 'maxLength' => 2000 ),
		),
		'callback'    => static function ( $settings ) {
			return '<h3>' . esc_html( $settings['heading'] ) . '</h3><p>' . esc_html( $settings['message'] ) . '</p>';
		},
	) );
	do_action( 'chb_register_renderers' );
	do_action( 'rrb_register_renderers' );
}
add_action( 'init', 'rrb_boot_renderers', 9 );

/** Register API3 metadata and the hidden compatibility block. */
function rrb_register_blocks() {
	$metadata = dirname( __DIR__ ) . '/build/renderer';
	if ( ! file_exists( $metadata . '/block.json' ) ) {
		return;
	}
	$type = register_block_type_from_metadata( $metadata, array( 'render_callback' => 'rrb_render_block' ) );
	$compat_registered = false;
	if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'registered-render-blocks/renderer' ) ) {
		$compat_registered = (bool) register_block_type( 'registered-render-blocks/renderer', array(
			'api_version' => 3,
			'title' => __( 'Registered Renderer (compatible)', 'custom-hook-block' ),
			'attributes' => array_merge( $type->attributes, array( 'renderer' => array( 'type' => 'string', 'default' => 'notice' ), 'hookName' => array( 'type' => 'string', 'default' => '' ) ) ),
			'uses_context' => array( 'postId', 'postType' ),
			'supports' => array_merge( $type->supports, array( 'inserter' => false ) ),
			'editor_script_handles' => $type->editor_script_handles,
			'editor_style_handles' => $type->editor_style_handles,
			'style_handles' => $type->style_handles,
			'render_callback' => 'rrb_render_block',
		) );
	}
	$config = array( 'renderers' => array(), 'compat' => $compat_registered );
	foreach ( rrb_registry() as $id => $renderer ) {
		$config['renderers'][] = array( 'id' => $id, 'title' => $renderer['title'], 'description' => $renderer['description'], 'settings' => $renderer['settings'], 'legacyHooks' => $renderer['legacy_hooks'] );
	}
	foreach ( $type->editor_script_handles as $handle ) {
		wp_add_inline_script( $handle, 'window.rrbSettings = ' . wp_json_encode( $config, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT ) . ';', 'before' );
		wp_set_script_translations( $handle, 'custom-hook-block', dirname( __DIR__ ) . '/languages' );
	}
}
add_action( 'init', 'rrb_register_blocks', 20 );

/** Registered renderer styles belong in the iframe canvas, too. */
function rrb_editor_assets() {
	if ( ! is_admin() ) {
		return;
	}
	foreach ( rrb_registry() as $renderer ) {
		foreach ( $renderer['style_handles'] as $handle ) {
			if ( wp_style_is( $handle, 'registered' ) ) {
				wp_enqueue_style( $handle );
			}
		}
	}
}
add_action( 'enqueue_block_assets', 'rrb_editor_assets', 20 );

/** Integrators may opt in separate trusted editor scripts. */
function rrb_editor_scripts() {
	foreach ( rrb_registry() as $renderer ) {
		foreach ( $renderer['editor_script_handles'] as $handle ) {
			if ( wp_script_is( $handle, 'registered' ) ) {
				wp_enqueue_script( $handle );
			}
		}
	}
}
add_action( 'enqueue_block_editor_assets', 'rrb_editor_scripts', 20 );
