<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

function quotum_register_widget() {
    register_widget( 'Quotum_Widget' );
}

/**
 * Quotum widget class.
 */
class Quotum_Widget extends WP_Widget {

    public function __construct() {
        parent::__construct(
            'quotum_widget',
            __( 'Quotum', 'quotum' ),
            array(
                'description' => __( 'Displays random quotations from Quotum.', 'quotum' ),
            )
        );
    }

    public function widget( $args, $instance ) {

        $title = ! empty( $instance['title'] )
            ? $instance['title']
            : '';

        $show_author = ! isset( $instance['show_author'] )
            || $instance['show_author'];

        $show_source = ! isset( $instance['show_source'] )
            || $instance['show_source'];

        $show_line = ! empty( $instance['show_line'] );
        $show_refresh = ! empty( $instance['show_refresh'] );
        $refresh_text = isset( $instance['refresh_text'] ) ? $instance['refresh_text'] : __( 'Next quote »', 'quotum' );

        $count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 1;

        if ( $count < 1 ) {
            $count = 1;
        }

        $count = min( 20, $count );

        quotum_enqueue_styles();
        if ( $show_refresh ) { quotum_enqueue_refresh(); }

        $quotes = quotum_get_random_quotes( array(), $count );

        if ( empty( $quotes ) ) {
            return;
        }

        // WordPress supplies this wrapper markup through the widget API.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WordPress widget wrapper markup.
        echo $args['before_widget'];

        if ( $title !== '' ) {
            // WordPress supplies this wrapper markup through the widget API.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WordPress widget wrapper markup.
            echo $args['before_title'];
            echo esc_html( $title );
            // WordPress supplies this wrapper markup through the widget API.
            // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WordPress widget wrapper markup.
            echo $args['after_title'];
        }

        foreach ( $quotes as $quote ) {
            echo wp_kses_post( quotum_render_refreshable(
                $quote,
                $show_author,
                $show_source,
                $show_line,
                $show_refresh,
                $refresh_text
            ) );
        }

        // WordPress supplies this wrapper markup through the widget API.
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Trusted WordPress widget wrapper markup.
        echo $args['after_widget'];
    }

    public function form( $instance ) {

        $title = isset( $instance['title'] )
            ? $instance['title']
            : __( 'Quotation', 'quotum' );

        $show_author = ! isset( $instance['show_author'] )
            || $instance['show_author'];

        $show_source = ! isset( $instance['show_source'] )
            || $instance['show_source'];

        $show_line = ! empty( $instance['show_line'] );
        $show_refresh = ! empty( $instance['show_refresh'] );
        $refresh_text = isset( $instance['refresh_text'] ) ? $instance['refresh_text'] : __( 'Next quote »', 'quotum' );

        $count = isset( $instance['count'] ) ? absint( $instance['count'] ) : 1;

        if ( $count < 1 ) {
            $count = 1;
        }

        $count = min( 20, $count );

        ?>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>">
                <?php echo esc_html__( 'Title:', 'quotum' ); ?>
            </label>

            <input
                class="widefat"
                id="<?php echo esc_attr( $this->get_field_id( 'title' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'title' ) ); ?>"
                type="text"
                value="<?php echo esc_attr( $title ); ?>"
            >
        </p>

        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>">
                <?php echo esc_html__( 'Number of quotations:', 'quotum' ); ?>
            </label>

            <input
                class="small-text"
                id="<?php echo esc_attr( $this->get_field_id( 'count' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'count' ) ); ?>"
                type="number"
                min="1"
                max="20"
                step="1"
                value="<?php echo esc_attr( $count ); ?>"
            >
        </p>

        <p>
            <input
                type="checkbox"
                id="<?php echo esc_attr( $this->get_field_id( 'show_author' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'show_author' ) ); ?>"
                value="1"
                <?php checked( $show_author ); ?>
            >

            <label for="<?php echo esc_attr( $this->get_field_id( 'show_author' ) ); ?>">
                <?php echo esc_html__( 'Show author', 'quotum' ); ?>
            </label>
        </p>

        <p>
            <input
                type="checkbox"
                id="<?php echo esc_attr( $this->get_field_id( 'show_source' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'show_source' ) ); ?>"
                value="1"
                <?php checked( $show_source ); ?>
            >

            <label for="<?php echo esc_attr( $this->get_field_id( 'show_source' ) ); ?>">
                <?php echo esc_html__( 'Show source', 'quotum' ); ?>
            </label>
        </p>

        <p>
            <input
                type="checkbox"
                id="<?php echo esc_attr( $this->get_field_id( 'show_line' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'show_line' ) ); ?>"
                value="1"
                <?php checked( $show_line ); ?>
            >

            <label for="<?php echo esc_attr( $this->get_field_id( 'show_line' ) ); ?>">
                <?php echo esc_html__( 'Show vertical line', 'quotum' ); ?>
            </label>
        </p>

        <p>
            <input type="checkbox" id="<?php echo esc_attr( $this->get_field_id( 'show_refresh' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'show_refresh' ) ); ?>" value="1" <?php checked( $show_refresh ); ?>>
            <label for="<?php echo esc_attr( $this->get_field_id( 'show_refresh' ) ); ?>"><?php echo esc_html__( 'Show Next Quote link', 'quotum' ); ?></label>
        </p>
        <p>
            <label for="<?php echo esc_attr( $this->get_field_id( 'refresh_text' ) ); ?>"><?php echo esc_html__( 'Next Quote link text:', 'quotum' ); ?></label>
            <input class="widefat" type="text" id="<?php echo esc_attr( $this->get_field_id( 'refresh_text' ) ); ?>"
                name="<?php echo esc_attr( $this->get_field_name( 'refresh_text' ) ); ?>" value="<?php echo esc_attr( $refresh_text ); ?>">
        </p>
        <?php
    }

    public function update( $new_instance, $old_instance ) {

        return array(
            'title'       => sanitize_text_field( $new_instance['title'] ?? '' ),
            'count'       => max( 1, min( 20, absint( $new_instance['count'] ?? 1 ) ) ),
            'show_author' => ! empty( $new_instance['show_author'] ),
            'show_source' => ! empty( $new_instance['show_source'] ),
            'show_line'   => ! empty( $new_instance['show_line'] ),
            'show_refresh' => ! empty( $new_instance['show_refresh'] ),
            'refresh_text' => sanitize_text_field( $new_instance['refresh_text'] ?? '' ),
        );
    }
}
