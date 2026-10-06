<?php
/** Renderer registration and settings validation. @package Registered_Render_Blocks */
defined( 'ABSPATH' ) || exit;

/** Return the request-local registry; never populated from post content. */
function &rrb_registry() {
	static $renderers = array();
	return $renderers;
}

/**
 * Register a trusted, read-only renderer. Call on rrb_register_renderers.
 *
 * @param string $id   Stable identifier, optionally namespace/id.
 * @param array  $args Renderer metadata, setting schemas and callable.
 * @return true|WP_Error
 */
function rrb_register_renderer( $id, $args ) {
	$registry =& rrb_registry();
	if ( ! is_string( $id ) || ! preg_match( '/^[a-z][a-z0-9-]*(?:\/[a-z][a-z0-9-]*)?$/D', $id ) || strlen( $id ) > 100 ) {
		return new WP_Error( 'rrb_invalid_id', __( 'Invalid renderer identifier.', 'registered-render-blocks' ) );
	}
	if ( isset( $registry[ $id ] ) ) {
		return new WP_Error( 'rrb_duplicate_id', __( 'Renderer identifiers must be unique.', 'registered-render-blocks' ) );
	}
	if ( ! is_array( $args ) || empty( $args['title'] ) || ! is_string( $args['title'] ) || ! isset( $args['callback'] ) || ! is_callable( $args['callback'] ) ) {
		return new WP_Error( 'rrb_invalid_renderer', __( 'A renderer needs a title and a callable.', 'registered-render-blocks' ) );
	}
	$schemas = $args['settings'] ?? array();
	if ( ! is_array( $schemas ) || count( $schemas ) > 40 ) {
		return new WP_Error( 'rrb_invalid_schema', __( 'Invalid settings schema.', 'registered-render-blocks' ) );
	}
	$allowed_schema_keys = array( 'type', 'title', 'description', 'default', 'enum', 'minimum', 'maximum', 'maxLength' );
	foreach ( $schemas as $key => &$schema ) {
		if ( ! is_string( $key ) || ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_]*$/D', $key ) || in_array( $key, array( '__proto__', 'constructor', 'prototype' ), true ) || ! is_array( $schema ) || array_diff( array_keys( $schema ), $allowed_schema_keys ) || ! in_array( $schema['type'] ?? '', array( 'string', 'boolean', 'number', 'integer' ), true ) ) {
			return new WP_Error( 'rrb_invalid_schema', __( 'Settings must use supported scalar types.', 'registered-render-blocks' ) );
		}
		if ( ! isset( $schema['default'] ) && ! array_key_exists( 'default', $schema ) ) {
			return new WP_Error( 'rrb_missing_default', __( 'Each setting needs a default.', 'registered-render-blocks' ) );
		}
		if ( 'string' === $schema['type'] ) {
			$schema['maxLength'] = min( 10000, max( 1, (int) ( $schema['maxLength'] ?? 2000 ) ) );
		}
		if ( isset( $schema['enum'] ) && ( ! is_array( $schema['enum'] ) || ! $schema['enum'] || count( $schema['enum'] ) > 100 ) ) {
			return new WP_Error( 'rrb_invalid_enum', __( 'Invalid setting choices.', 'registered-render-blocks' ) );
		}
		if ( ! rrb_setting_valid( $schema['default'], $schema ) ) {
			return new WP_Error( 'rrb_invalid_default', __( 'A setting default does not match its schema.', 'registered-render-blocks' ) );
		}
	}
	unset( $schema );
	$assets = array();
	foreach ( array( 'style_handles', 'view_script_handles', 'editor_script_handles' ) as $kind ) {
		$assets[ $kind ] = $args[ $kind ] ?? array();
		if ( ! is_array( $assets[ $kind ] ) ) {
			return new WP_Error( 'rrb_invalid_assets', __( 'Assets must be registered handle lists.', 'registered-render-blocks' ) );
		}
		foreach ( $assets[ $kind ] as $handle ) {
			if ( ! is_string( $handle ) || ! preg_match( '/^[a-zA-Z0-9_.-]+$/D', $handle ) ) {
				return new WP_Error( 'rrb_invalid_assets', __( 'Invalid registered asset handle.', 'registered-render-blocks' ) );
			}
		}
	}
	$aliases = $args['legacy_hooks'] ?? array();
	if ( ! is_array( $aliases ) ) {
		return new WP_Error( 'rrb_invalid_alias', __( 'Legacy aliases must be a list.', 'registered-render-blocks' ) );
	}
	foreach ( $aliases as $alias ) {
		if ( ! is_string( $alias ) || ! preg_match( '/^[a-zA-Z][a-zA-Z0-9_]*$/D', $alias ) ) {
			return new WP_Error( 'rrb_invalid_alias', __( 'Invalid legacy alias.', 'registered-render-blocks' ) );
		}
		foreach ( $registry as $existing ) {
			if ( in_array( $alias, $existing['legacy_hooks'], true ) ) {
				return new WP_Error( 'rrb_duplicate_alias', __( 'Legacy aliases must be unique.', 'registered-render-blocks' ) );
			}
		}
	}
	$registry[ $id ] = array_merge( $assets, array(
		'title'        => $args['title'],
		'description'  => is_string( $args['description'] ?? '' ) ? ( $args['description'] ?? '' ) : '',
		'settings'     => $schemas,
		'callback'     => $args['callback'],
		'legacy_hooks' => $aliases,
	) );
	return true;
}

/** Strict scalar validation; strings are not coerced to numbers or booleans. */
function rrb_setting_valid( $value, $schema ) {
	$type = $schema['type'] ?? '';
	$valid_type = ( 'string' === $type && is_string( $value ) ) || ( 'boolean' === $type && is_bool( $value ) ) || ( 'integer' === $type && is_int( $value ) ) || ( 'number' === $type && ( is_int( $value ) || is_float( $value ) ) && is_finite( (float) $value ) );
	if ( ! $valid_type ) {
		return false;
	}
	if ( isset( $schema['enum'] ) && ! in_array( $value, $schema['enum'], true ) ) {
		return false;
	}
	return true === rest_validate_value_from_schema( $value, $schema, 'settings' );
}

/** Apply defaults while rejecting unknown keys and malformed values. */
function rrb_validate_settings( $settings, $schemas ) {
	if ( ! is_array( $settings ) || array_diff( array_keys( $settings ), array_keys( $schemas ) ) ) {
		return new WP_Error( 'rrb_invalid_settings', __( 'Unrecognized renderer settings.', 'registered-render-blocks' ) );
	}
	$result = array();
	foreach ( $schemas as $key => $schema ) {
		$value = array_key_exists( $key, $settings ) ? $settings[ $key ] : $schema['default'];
		if ( ! rrb_setting_valid( $value, $schema ) ) {
			return new WP_Error( 'rrb_invalid_settings', __( 'A renderer setting has an invalid value.', 'registered-render-blocks' ) );
		}
		$result[ $key ] = $value;
	}
	return $result;
}
