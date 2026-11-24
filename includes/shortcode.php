<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Interpret documented boolean values and legacy numeric values.
 */
function quotum_shortcode_boolean( $value ) {
    return in_array( strtolower( trim( (string) $value ) ), array( 'yes', '1', 'true', 'on' ), true );
}

function quotum_shortcode( $atts ) {

    $atts = shortcode_atts(
        array(
            'show_author' => null,
            'show_source' => null,
            'show_line' => null,
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
        $refresh = quotum_shortcode_boolean( $atts['refresh'] );
        if ( $refresh ) {
            quotum_enqueue_refresh();
        }
        $output .= quotum_render_refreshable(
            $quote,
            quotum_shortcode_boolean( $atts['show_author'] ?? $atts['author'] ),
            quotum_shortcode_boolean( $atts['show_source'] ?? $atts['source'] ),
            quotum_shortcode_boolean( $atts['show_line'] ?? $atts['line'] ),
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


