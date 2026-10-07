<?php
/** Disposable WordPress only, with the real Asia Mannequin measurement adapter loaded. */
if ( ! defined( 'ABSPATH' ) || ! class_exists( '\AMBL\Product_Measurements' ) || ! function_exists( 'chb_register_hook' ) ) { throw new RuntimeException( 'Load WordPress, merged Custom Hook Block and the site measurement adapter.' ); }
$results = array();
$check = static function ( $name, $pass ) use ( &$results ) { $results[] = array( 'test' => $name, 'pass' => (bool) $pass ); };
$registry = rrb_registry();
$check( 'Existing site adapter registered through retained rrb action/API', isset( $registry['am-product-dimensions'] ) );
if ( ! post_type_exists( 'product' ) ) { register_post_type( 'product', array( 'public' => true ) ); }
$ids = array();
$old_user = get_current_user_id();
try {
    foreach ( array( 'CHB adapter fixture A', 'CHB adapter fixture B' ) as $title ) {
        $id = wp_insert_post( array( 'post_type' => 'product', 'post_status' => 'publish', 'post_title' => $title ), true );
        if ( is_wp_error( $id ) ) { throw new RuntimeException( $id->get_error_message() ); }
        $ids[] = $id;
    }
    foreach ( array( array( $ids[0], 'height', '180.5', 'verified', 'cm' ), array( $ids[0], 'waist', '75', 'disputed', 'cm' ), array( $ids[1], 'width', '90', 'verified', 'mm' ) ) as $row ) {
        list( $id, $measure, $value, $status, $unit ) = $row;
        foreach ( array( 'value' => $value, 'status' => $status, 'unit' => $unit, 'source' => 'INTERNAL test source', 'verified_on' => '20261007', 'note' => 'PRIVATE test note' ) as $key => $v ) { update_post_meta( $id, 'am_measure_' . $measure . '_' . $key, $v ); }
    }
    $render = static function ( $name, $id, $settings = array() ) {
        return ( new WP_Block( array( 'blockName' => $name, 'attrs' => array( 'renderer' => 'am-product-dimensions', 'settings' => $settings ), 'innerBlocks' => array(), 'innerHTML' => '', 'innerContent' => array() ), array( 'postId' => $id, 'postType' => 'product' ) ) )->render();
    };
    wp_set_current_user( 0 );
    $canonical = $render( 'mytheme/custom-hook-block', $ids[0] );
    $legacy = $render( 'registered-render-blocks/renderer', $ids[0] );
    $check( 'Canonical block renders real adapter', false !== strpos( $canonical, '180.5 cm' ) );
    $check( 'Saved RRB block still renders real adapter', false !== strpos( $legacy, '180.5 cm' ) );
    $check( 'Both names omit disputed measurements', false === strpos( $canonical . $legacy, '75 cm' ) );
    $check( 'Both names hide evidence/date/notes', false === strpos( $canonical . $legacy, 'INTERNAL' ) && false === strpos( $canonical . $legacy, 'PRIVATE' ) && false === strpos( $canonical . $legacy, '20261007' ) );
    $other = $render( 'mytheme/custom-hook-block', $ids[1] );
    $check( 'Current product context and unit correct', false !== strpos( $other, '90 mm' ) && false === strpos( $other, '180.5' ) );
    $list = $render( 'mytheme/custom-hook-block', $ids[0], array( 'layout' => 'list', 'heading' => '<script>fixture</script>' ) );
    $check( 'Typed adapter settings and safe heading preserved', false !== strpos( $list, '<dl' ) && false !== strpos( $list, '&lt;script&gt;' ) && false === strpos( $list, '<script>' ) );
    $hidden = $render( 'mytheme/custom-hook-block', $ids[0], array( 'showHeight' => false ) );
    $check( 'Hidden-only data leaves no public output', '' === $hidden );
    wp_update_post( array( 'ID' => $ids[0], 'post_password' => 'fixture-test' ) );
    $check( 'Protected product withheld through canonical block', '' === $render( 'mytheme/custom-hook-block', $ids[0] ) );
    wp_update_post( array( 'ID' => $ids[0], 'post_password' => '', 'post_status' => 'private' ) );
    $check( 'Private product withheld through compatibility block', '' === $render( 'registered-render-blocks/renderer', $ids[0] ) );
} finally {
    foreach ( $ids as $id ) { wp_delete_post( $id, true ); }
    wp_set_current_user( $old_user );
}
$failed = count( array_filter( $results, static function ( $row ) { return ! $row['pass']; } ) );
echo wp_json_encode( array( 'passed' => count( $results ) - $failed, 'failed' => $failed, 'results' => $results ), JSON_PRETTY_PRINT ) . "\n";
if ( $failed && defined( 'WP_CLI' ) && WP_CLI ) { WP_CLI::halt( 1 ); }
