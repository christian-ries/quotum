<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Normalize a comma-separated tag string for consistent storage.
 *
 * Tag names are preserved, but whitespace around commas is standardized
 * and duplicate tags within one quotation are removed case-insensitively.
 */
function quotum_normalize_tags( $tags ) {
    $tags = sanitize_text_field( (string) $tags );

    if ( '' === trim( $tags ) ) {
        return '';
    }

    $normalized = array();

    foreach ( explode( ',', $tags ) as $tag ) {
        $tag = trim( preg_replace( '/\s+/u', ' ', $tag ) );

        if ( '' === $tag ) {
            continue;
        }

        $duplicate = false;
        foreach ( $normalized as $existing_tag ) {
            if ( function_exists( 'mb_strtolower' ) ) {
                $same = mb_strtolower( $existing_tag, 'UTF-8' ) === mb_strtolower( $tag, 'UTF-8' );
            } else {
                $same = strtolower( $existing_tag ) === strtolower( $tag );
            }

            if ( $same ) {
                $duplicate = true;
                break;
            }
        }

        if ( ! $duplicate ) {
            $normalized[] = $tag;
        }
    }

    return implode( ', ', $normalized );
}

/**
 * Retrieve a random active quotation.
 */
function quotum_get_random_quotes( $tags = array(), $limit = 1 ) {
    global $wpdb;

    $table = $wpdb->prefix . 'quotum';
    $limit = max( 1, min( 20, absint( $limit ) ) );

    $sql = "SELECT id, quote, author_original, source FROM %i WHERE active = 1";
    $values = array( $table );

    // If tags were supplied, match quotations containing at least one of them.
    if ( ! empty( $tags ) ) {
        $conditions = array();

        foreach ( $tags as $tag ) {
            $conditions[] = "FIND_IN_SET(%s, REPLACE(tags, ', ', ',')) > 0";
            $values[] = $tag;
        }

        $sql .= ' AND (' . implode( ' OR ', $conditions ) . ')';
    }

    $sql .= ' ORDER BY RAND() LIMIT %d';
    $values[] = $limit;

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL template is prepared below; all dynamic values are passed as placeholders.
    return $wpdb->get_results( $wpdb->prepare( $sql, ...$values ) );
}

function quotum_get_random_quote( $tags = array() ) {
    global $wpdb;

    $table = $wpdb->prefix . 'quotum';

    $sql = "SELECT id, quote, author_original, source FROM %i WHERE active = 1";
    $values = array( $table );

    // If tags were supplied, match quotations containing at least one of them.
    if ( ! empty( $tags ) ) {
        $conditions = array();

        foreach ( $tags as $tag ) {
            $conditions[] = "FIND_IN_SET(%s, REPLACE(tags, ', ', ',')) > 0";
            $values[] = $tag;
        }

        $sql .= ' AND (' . implode( ' OR ', $conditions ) . ')';
    }

    $sql .= ' ORDER BY RAND() LIMIT 1';

    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The SQL template is prepared below; all dynamic values are passed as placeholders.
    return $wpdb->get_row( $wpdb->prepare( $sql, ...$values ) );
}

/**
 * Render a quotation.
 */
function quotum_render_quote(
    $quote,
    $show_author = true,
    $show_source = true,
    $show_line = false
) {
    if ( ! $quote ) {
        return '';
    }

    $class = 'quotum-display';

    if ( $show_line ) {
        $class .= ' quotum-display--line';
    }

    $output = '<div class="' . esc_attr( $class ) . '">';

    $output .= '<blockquote class="quotum-text">';
    $output .= wpautop( esc_html( $quote->quote ) );
    $output .= '</blockquote>';

    if ( $show_author && ! empty( $quote->author_original ) ) {
        $output .= '<div class="quotum-author">';
        $output .= '&mdash; ' . esc_html( $quote->author_original );
        $output .= '</div>';
    }

    if ( $show_source && ! empty( $quote->source ) ) {
        $output .= '<div class="quotum-source">';
        $output .= esc_html( $quote->source );
        $output .= '</div>';
    }

    $output .= '</div>';

    return $output;
}

/**
 * Register the shortcode [quotum].
 */
add_shortcode( 'quotum', 'quotum_shortcode' );



/** Output one quotation with an optional accessible refresh control. */
function quotum_render_refreshable( $quote, $show_author, $show_source, $show_line, $refresh = false, $refresh_text = '', $tags = array() ) {
    $markup = quotum_render_quote( $quote, $show_author, $show_source, $show_line );
    if ( ! $refresh ) {
        return $markup;
    }
    if ( '' === $refresh_text ) {
        $refresh_text = __( 'Next quote »', 'quotum' );
    }
    $data = array(
        'data-quote-id' => absint( $quote->id ?? 0 ),
        'data-author' => $show_author ? '1' : '0',
        'data-source' => $show_source ? '1' : '0',
        'data-line' => $show_line ? '1' : '0',
        'data-tags' => implode( ',', $tags ),
    );
    $attrs = '';
    foreach ( $data as $name => $value ) {
        $attrs .= ' ' . $name . '="' . esc_attr( $value ) . '"';
    }
    return '<div class="quotum-refreshable"' . $attrs . '><div class="quotum-refresh-content" aria-live="polite" aria-atomic="true">' . $markup . '</div><button type="button" class="quotum-refresh">' . esc_html( $refresh_text ) . '</button></div>';
}
