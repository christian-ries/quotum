<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function quotum_shortcode( $atts ) {

    $atts = shortcode_atts(
        array(
            'author' => 'yes',
            'source' => 'yes',
            'line'   => 'no',
            'tags'   => '',
            'count'  => 1,
            'refresh' => 'no',
            'refresh_text' => '',
        ),
        $atts,
        'quotum'
    );

    $tags = array_filter(
        array_map(
            'trim',
            explode( ',', sanitize_text_field( $atts['tags'] ) )
        )
    );

    quotum_enqueue_styles();

    $count = absint( $atts['count'] );

    if ( $count < 1 ) {
        $count = 1;
    }

    $count = min( 20, $count );

    $quotes = quotum_get_random_quotes( $tags, $count );

    if ( empty( $quotes ) ) {
        return '';
    }

    $output = '';

    foreach ( $quotes as $quote ) {
        $refresh = 'yes' === $atts['refresh'];
        if ( $refresh ) {
            quotum_enqueue_refresh();
        }
        $output .= quotum_render_refreshable(
            $quote,
            $atts['author'] === 'yes',
            $atts['source'] === 'yes',
            $atts['line'] === 'yes',
            $refresh,
            sanitize_text_field( $atts['refresh_text'] ),
            $tags
        );
    }

    return $output;
}

/**
 * Register the Quotum widget.
 */
add_action( 'widgets_init', 'quotum_register_widget' );


