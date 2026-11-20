<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function quotum_enqueue_styles() {
    wp_enqueue_style(
        'quotum',
        QUOTUM_PLUGIN_URL . 'assets/quotum.css',
        array(),
        QUOTUM_VERSION
    );
}


/** Load the small public script only where the refresh control is used. */
function quotum_enqueue_refresh() {
    wp_enqueue_script( 'quotum-refresh', QUOTUM_PLUGIN_URL . 'assets/quotum-refresh.js', array(), QUOTUM_VERSION, true );
    wp_add_inline_script( 'quotum-refresh', 'window.quotumRefreshUrl = ' . wp_json_encode( esc_url_raw( rest_url( 'quotum/v1/random' ) ) ) . ';', 'before' );
}

/** Public endpoint: only active quotations can be returned. */
add_action( 'rest_api_init', function () {
    register_rest_route( 'quotum/v1', '/random', array(
        'methods' => 'GET',
        'callback' => 'quotum_rest_random',
        'permission_callback' => '__return_true',
    ) );
} );

function quotum_rest_random( WP_REST_Request $request ) {
    global $wpdb;
    $tags = array_slice( array_filter( array_map( 'sanitize_text_field', explode( ',', (string) $request->get_param( 'tags' ) ) ) ), 0, 10 );
    $exclude = absint( $request->get_param( 'exclude' ) );
    $author = '1' === (string) $request->get_param( 'author' );
    $source = '1' === (string) $request->get_param( 'source' );
    $line = '1' === (string) $request->get_param( 'line' );
    $table = $wpdb->prefix . 'quotum';
    $sql = 'SELECT id, quote, author_original, source FROM %i WHERE active = 1';
    $values = array( $table );
    foreach ( $tags as $index => $tag ) {
        $tags[ $index ] = trim( $tag );
    }
    if ( $tags ) {
        $clauses = array();
        foreach ( $tags as $tag ) {
            $clauses[] = "FIND_IN_SET(%s, REPLACE(tags, ', ', ',')) > 0";
            $values[] = $tag;
        }
        $sql .= ' AND (' . implode( ' OR ', $clauses ) . ')';
    }
    // Exclude the current quotation if another eligible one exists.
    if ( $exclude ) {
        $sql .= ' AND id <> %d';
        $values[] = $exclude;
    }
    $sql .= ' ORDER BY RAND() LIMIT 1';
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- SQL clauses are internal; values use placeholders.
    $quote = $wpdb->get_row( $wpdb->prepare( $sql, ...$values ) );
    if ( ! $quote && $exclude ) {
        $fallback = quotum_get_random_quote( $tags );
        $quote = $fallback;
    }
    if ( ! $quote ) {
        return new WP_Error( 'quotum_no_quotes', __( 'No quotations available.', 'quotum' ), array( 'status' => 404 ) );
    }
    $response = rest_ensure_response( array( 'id' => (int) $quote->id, 'html' => quotum_render_quote( $quote, $author, $source, $line ) ) );
    $response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate' );
    return $response;
}
