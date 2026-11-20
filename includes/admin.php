<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

add_action( 'admin_enqueue_scripts', 'quotum_admin_styles' );

function quotum_admin_styles( $hook_suffix ) {

    if ( 'toplevel_page_quotum' !== $hook_suffix ) {
        return;
    }

    wp_enqueue_style(
        'quotum-admin',
        QUOTUM_PLUGIN_URL . 'assets/quotum-admin.css',
        array(),
                     QUOTUM_VERSION
    );

    wp_enqueue_script(
        'quotum-admin-bulk',
        QUOTUM_PLUGIN_URL . 'assets/quotum-admin-bulk.js',
        array(),
                      QUOTUM_VERSION,
                      true
    );

    wp_localize_script(
        'quotum-admin-bulk',
        'quotumAdminI18n',
        array(
            'chooseAction' => __(
                'Please choose a bulk action.',
                'quotum'
            ),

            'selectQuotation' => __(
                'Please select at least one quotation.',
                'quotum'
            ),

            // translators: %1$d is the number of selected quotations.
            'deleteQuestionSingular' => _n(
                "Permanently delete %1\$d selected quotation?\n\nThis cannot be undone.",
                "Permanently delete %1\$d selected quotations?\n\nThis cannot be undone.",
                1,
                'quotum'
            ),

            // translators: %1$d is the number of selected quotations.
            'deleteQuestionPlural' => _n(
                "Permanently delete %1\$d selected quotation?\n\nThis cannot be undone.",
                "Permanently delete %1\$d selected quotations?\n\nThis cannot be undone.",
                2,
                'quotum'
            ),

            // translators: %1$d is the number of selected quotations.
            'activateQuestionSingular' => _n(
                'Activate %1$d selected quotation?',
                'Activate %1$d selected quotations?',
                1,
                'quotum'
            ),

            // translators: %1$d is the number of selected quotations.
            'activateQuestionPlural' => _n(
                'Activate %1$d selected quotation?',
                'Activate %1$d selected quotations?',
                2,
                'quotum'
            ),

            // translators: %1$d is the number of selected quotations.
            'deactivateQuestionSingular' => _n(
                'Deactivate %1$d selected quotation?',
                'Deactivate %1$d selected quotations?',
                1,
                'quotum'
            ),

            // translators: %1$d is the number of selected quotations.
            'deactivateQuestionPlural' => _n(
                'Deactivate %1$d selected quotation?',
                'Deactivate %1$d selected quotations?',
                2,
                'quotum'
            ),
        )
    );

    wp_enqueue_script(
        'quotum-admin-preview',
        QUOTUM_PLUGIN_URL . 'assets/quotum-admin-preview.js',
        array(),
                      QUOTUM_VERSION,
                      true
    );
}


/**
 * Register administration menu.
 */
add_action( 'admin_menu', 'quotum_admin_menu' );

function quotum_admin_menu() {
    add_menu_page(
        'Quotum',
        'Quotum',
        'manage_options',
        'quotum',
        'quotum_admin_page',
        'dashicons-format-quote',
        65
    );

    add_submenu_page(
        'quotum',
        __( 'Manage Quotes', 'quotum' ),
        __( 'Manage Quotes', 'quotum' ),
        'manage_options',
        'quotum',
        'quotum_admin_page'
    );

    add_submenu_page(
        'quotum',
        'Export / Import',
        'Export / Import',
        'manage_options',
        'quotum-export-import',
        'quotum_export_import_page'
    );
}

/**
 * Save a quotation.
 */
add_action( 'admin_post_quotum_save', 'quotum_handle_save' );

function quotum_handle_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_save_quote' );

    global $wpdb;

    $table = $wpdb->prefix . 'quotum';

    $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

    $quote = isset( $_POST['quote'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['quote'] ) )
        : '';

    $author = isset( $_POST['author'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['author'] ) )
        : '';

    $source = isset( $_POST['source'] )
        ? sanitize_textarea_field( wp_unslash( $_POST['source'] ) )
        : '';

    $tags = isset( $_POST['tags'] )
        ? quotum_normalize_tags( sanitize_textarea_field( wp_unslash( $_POST['tags'] ) ) )
        : '';

    $active = isset( $_POST['active'] ) ? 1 : 0;

    if ( $quote === '' ) {
        wp_die( esc_html__( 'The quotation cannot be empty.', 'quotum' ) );
    }

    $data = array(
        'quote'          => $quote,
        'author_original' => $author,
        'source'         => $source,
        'tags'           => $tags,
        'active'         => $active,
        'updated_at'     => current_time( 'mysql' ),
    );

    if ( $id > 0 ) {
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM %i WHERE id = %d",
                $table,
                $id
            )
        );

        if ( ! $exists ) {
            wp_die( esc_html__( 'Quotation not found.', 'quotum' ) );
        }

        $result = $wpdb->update(
            $table,
            $data,
            array( 'id' => $id )
        );

        if ( $result === false ) {
            wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
        }

        $message = 'updated';

    } else {
        $data['original_id'] = null;
        $data['created_at'] = current_time( 'mysql' );

        $result = $wpdb->insert( $table, $data );

        if ( $result === false ) {
            wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
        }

        $message = 'created';
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'    => 'quotum',
                'message' => $message,
            ),
            admin_url( 'admin.php' )
        )
    );

    exit;
}

/**
 * Delete a quotation.
 */
add_action( 'admin_post_quotum_delete', 'quotum_handle_delete' );

function quotum_handle_delete() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_delete_quote' );

    global $wpdb;

    $table = $wpdb->prefix . 'quotum';

    $id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;

    if ( $id > 0 ) {
        $result = $wpdb->delete(
            $table,
            array( 'id' => $id ),
            array( '%d' )
        );

        if ( $result === false ) {
            wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
        }
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'    => 'quotum',
                'message' => 'deleted',
            ),
            admin_url( 'admin.php' )
        )
    );

    exit;
}

/**
 * Apply a bulk status change to selected quotations.
 */
add_action( 'admin_post_quotum_bulk_status', 'quotum_handle_bulk_status' );

function quotum_handle_bulk_status() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_bulk_status' );

    $bulk_action = isset( $_POST['bulk_action'] )
        ? sanitize_key( wp_unslash( $_POST['bulk_action'] ) )
        : '';

    if ( ! in_array( $bulk_action, array( 'activate', 'deactivate', 'delete' ), true ) ) {
        wp_die( esc_html__( 'Please choose a valid bulk action.', 'quotum' ) );
    }

    $ids = isset( $_POST['quote_ids'] ) && is_array( $_POST['quote_ids'] )
        ? array_values( array_unique( array_filter( array_map( 'absint', wp_unslash( $_POST['quote_ids'] ) ) ) ) )
        : array();

    if ( empty( $ids ) ) {
        wp_die( esc_html__( 'Please select at least one quotation.', 'quotum' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'quotum';
    $id_placeholders = implode( ', ', array_fill( 0, count( $ids ), '%d' ) );
    if ( 'delete' === $bulk_action ) {
        $query = "DELETE FROM %i WHERE id IN (" . $id_placeholders . ")";
        $values = array_merge( array( $table ), $ids );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The query is prepared below; the SQL template contains only internally generated clauses and placeholders.
        $result = $wpdb->query( $wpdb->prepare( $query, ...$values ) );
    } else {
        $active = ( 'activate' === $bulk_action ) ? 1 : 0;
        $query = "UPDATE %i SET active = %d, updated_at = %s WHERE id IN (" . $id_placeholders . ")";
        $values = array_merge( array( $table, $active, current_time( 'mysql' ) ), $ids );
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, PluginCheck.Security.DirectDB.UnescapedDBParameter -- The query is prepared below; the SQL template contains only internally generated clauses and placeholders.
        $result = $wpdb->query( $wpdb->prepare( $query, ...$values ) );
    }

    if ( $result === false ) {
        wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
    }

    $redirect_args = array(
        'page'    => 'quotum',
        'message' => 'bulk_' . $bulk_action,
    );

    foreach ( array( 's', 'tag', 'status', 'paged' ) as $filter ) {
        if ( isset( $_POST[ $filter ] ) ) {
            $redirect_args[ $filter ] = sanitize_text_field( wp_unslash( $_POST[ $filter ] ) );
        }
    }

    // Keep the status filter consistent with the new status.
    // Otherwise activating items while viewing "Inactive" would immediately
    // hide those items from the result, which looks like the bulk action failed.
    if ( 'activate' === $bulk_action ) {
        $redirect_args['status'] = 'active';
    } elseif ( 'deactivate' === $bulk_action ) {
        $redirect_args['status'] = 'inactive';
    }

    wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
    exit;
}

/**
 * Rename a tag throughout the quotation library.
 */
add_action( 'admin_post_quotum_rename_tag', 'quotum_handle_rename_tag' );

function quotum_handle_rename_tag() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_rename_tag' );

    $old_tag = isset( $_POST['old_tag'] )
        ? sanitize_text_field( wp_unslash( $_POST['old_tag'] ) )
        : '';

    $new_tag = isset( $_POST['new_tag'] )
        ? trim( sanitize_text_field( wp_unslash( $_POST['new_tag'] ) ) )
        : '';

    if ( $old_tag === '' || $new_tag === '' || strpos( $new_tag, ',' ) !== false ) {
        wp_die( esc_html__( 'Please provide a valid tag name. Tags cannot contain commas.', 'quotum' ) );
    }

    if ( strtolower( $old_tag ) === strtolower( $new_tag ) ) {
        wp_safe_redirect( admin_url( 'admin.php?page=quotum' ) );
        exit;
    }

    global $wpdb;
    $table = $wpdb->prefix . 'quotum';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, tags FROM %i WHERE tags IS NOT NULL AND tags <> ''",
            $table
        )
    );

    foreach ( $rows as $row ) {
        $tags = array();
        $found = false;

        foreach ( explode( ',', $row->tags ) as $tag ) {
            $tag = trim( $tag );

            if ( $tag === '' ) {
                continue;
            }

            if ( strtolower( $tag ) === strtolower( $old_tag ) ) {
                $tag = $new_tag;
                $found = true;
            }

            // Avoid duplicate tags in a quotation after a rename.
            $already_added = false;
            foreach ( $tags as $existing_tag ) {
                if ( strtolower( $existing_tag ) === strtolower( $tag ) ) {
                    $already_added = true;
                    break;
                }
            }

            if ( ! $already_added ) {
                $tags[] = $tag;
            }
        }

        if ( $found ) {
            $result = $wpdb->update(
                $table,
                array(
                    'tags'       => implode( ', ', $tags ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $row->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( $result === false ) {
                wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
            }
        }
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'    => 'quotum',
                'message' => 'tag_renamed',
            ),
            admin_url( 'admin.php' )
        )
    );
    exit;
}

/**
 * Remove a tag from all quotations without deleting the quotations.
 */
add_action( 'admin_post_quotum_remove_tag', 'quotum_handle_remove_tag' );

function quotum_handle_remove_tag() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_remove_tag' );

    $tag_to_remove = isset( $_POST['tag'] )
        ? sanitize_text_field( wp_unslash( $_POST['tag'] ) )
        : '';

    if ( $tag_to_remove === '' ) {
        wp_die( esc_html__( 'No tag was specified.', 'quotum' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'quotum';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, tags FROM %i WHERE tags IS NOT NULL AND tags <> ''",
            $table
        )
    );

    foreach ( $rows as $row ) {
        $tags = array();
        $found = false;

        foreach ( explode( ',', $row->tags ) as $tag ) {
            $tag = trim( $tag );

            if ( $tag === '' ) {
                continue;
            }

            if ( strtolower( $tag ) === strtolower( $tag_to_remove ) ) {
                $found = true;
                continue;
            }

            $tags[] = $tag;
        }

        if ( $found ) {
            $result = $wpdb->update(
                $table,
                array(
                    'tags'       => implode( ', ', $tags ),
                    'updated_at' => current_time( 'mysql' ),
                ),
                array( 'id' => (int) $row->id ),
                array( '%s', '%s' ),
                array( '%d' )
            );

            if ( $result === false ) {
                wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
            }
        }
    }

    wp_safe_redirect(
        add_query_arg(
            array(
                'page'    => 'quotum',
                'message' => 'tag_removed',
            ),
            admin_url( 'admin.php' )
        )
    );
    exit;
}

/**
 * Return the tags currently used and the number of quotations using each tag.
 * Tag matching is case-insensitive.
 */
function quotum_get_tag_summary( $table ) {
    global $wpdb;

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT tags FROM %i WHERE tags IS NOT NULL AND tags <> ''",
            $table
        )
    );

    $summary = array();

    foreach ( $rows as $row ) {
        $seen_in_this_quote = array();

        foreach ( explode( ',', $row->tags ) as $tag ) {
            $tag = trim( $tag );

            if ( $tag === '' ) {
                continue;
            }

            $key = strtolower( $tag );

            if ( isset( $seen_in_this_quote[ $key ] ) ) {
                continue;
            }

            $seen_in_this_quote[ $key ] = true;

            if ( ! isset( $summary[ $key ] ) ) {
                $summary[ $key ] = array(
                    'name'  => $tag,
                    'count' => 0,
                );
            }

            $summary[ $key ]['count']++;
        }
    }

    uasort(
        $summary,
        function ( $a, $b ) {
            return strcasecmp( $a['name'], $b['name'] );
        }
    );

    return $summary;
}

/**
 * Display the administration page.
 */
function quotum_admin_page() {
    global $wpdb;

    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $table = $wpdb->prefix . 'quotum';

    $table_exists = $wpdb->get_var(
        $wpdb->prepare(
            'SHOW TABLES LIKE %s',
            $wpdb->esc_like( $table )
        )
    );

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Quotum', 'quotum' ) . ' — ' . esc_html__( 'Manage Quotes', 'quotum' ) . '</h1>';

    if ( $table_exists !== $table ) {
        echo '<div class="notice notice-error"><p>' . esc_html__( 'Database table not found.', 'quotum' ) . '</p></div>';
        echo '</div>';
        return;
    }

    // Notifications.
    $messages = array(
        'created' => __( 'Quotation added successfully.', 'quotum' ),
        'updated' => __( 'Quotation updated successfully.', 'quotum' ),
        'deleted'     => __( 'Quotation deleted.', 'quotum' ),
        'tag_renamed' => __( 'Tag renamed throughout the quotation library.', 'quotum' ),
        'tag_removed' => __( 'Tag removed from all quotations.', 'quotum' ),
        'bulk_activate' => __( 'Selected quotations activated.', 'quotum' ),
        'bulk_deactivate' => __( 'Selected quotations deactivated.', 'quotum' ),
        'bulk_delete' => __( 'Selected quotations permanently deleted.', 'quotum' ),
    );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice parameter; no state change.
    $message = isset( $_GET['message'] )
        ? sanitize_key( wp_unslash( $_GET['message'] ) )
        : '';

    if ( isset( $messages[ $message ] ) ) {
        echo '<div class="notice notice-success is-dismissible"><p>';
        echo esc_html( $messages[ $message ] );
        echo '</p></div>';
    }

    // Show import results after returning from Export / Import.
    quotum_render_import_notice();

    // Check whether the editor should be displayed.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin navigation parameter; no state change.
    $edit_id = isset( $_GET['edit'] ) ? absint( $_GET['edit'] ) : 0;

    if ( isset( $_GET['add'] ) || $edit_id > 0 ) {
        quotum_edit_form( $table, $edit_id );
        echo '</div>';
        return;
    }

    // Search and tag filter.
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only search filter; no state change.
    $search = isset( $_GET['s'] )
        ? sanitize_text_field( wp_unslash( $_GET['s'] ) )
        : '';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter; no state change.
    $selected_tag = isset( $_GET['tag'] )
        ? sanitize_text_field( wp_unslash( $_GET['tag'] ) )
        : '';

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only filter; no state change.
    $selected_status = isset( $_GET['status'] )
        ? sanitize_key( wp_unslash( $_GET['status'] ) )
        : '';

    if ( ! in_array( $selected_status, array( 'active', 'inactive' ), true ) ) {
        $selected_status = '';
    }

    // Sorting.
    $allowed_orderby = array(
        'id'     => 'id',
        'quote'  => 'quote',
        'author' => 'author_original',
        'source' => 'source',
        'tags'   => 'tags',
        'status' => 'active',
    );

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameter; no state change.
    $selected_orderby = isset( $_GET['orderby'] )
        ? sanitize_key( wp_unslash( $_GET['orderby'] ) )
        : 'id';

    if ( ! isset( $allowed_orderby[ $selected_orderby ] ) ) {
        $selected_orderby = 'id';
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only sorting parameter; no state change.
    $selected_order = isset( $_GET['order'] )
        ? strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) )
        : 'desc';

    if ( ! in_array( $selected_order, array( 'asc', 'desc' ), true ) ) {
        $selected_order = 'desc';
    }

    $order_sql = $allowed_orderby[ $selected_orderby ] . ' ' . strtoupper( $selected_order );

    // Get the available tags for the filter dropdown.
    $tag_summary = quotum_get_tag_summary( $table );

    // Retrieve all quotations with one fixed prepared query. Filtering,
    // sorting and pagination are handled below in PHP so the SQL remains
    // completely static and can be validated by Plugin Check.
    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT id, quote, author_original, source, tags, active FROM %i',
            $table
        )
    );

    $rows = is_array( $rows ) ? $rows : array();

    // Apply search filter.
    if ( $search !== '' ) {
        $search_lower = strtolower( $search );

        $rows = array_filter(
            $rows,
            static function ( $row ) use ( $search_lower ) {
                return false !== stripos( (string) $row->quote, $search_lower )
                    || false !== stripos( (string) $row->author_original, $search_lower )
                    || false !== stripos( (string) $row->source, $search_lower )
                    || false !== stripos( (string) $row->tags, $search_lower );
            }
        );
    }

    // Apply tag filter. Tags are stored as comma-separated values.
    if ( $selected_tag !== '' && isset( $tag_summary[ strtolower( $selected_tag ) ] ) ) {
        $selected_tag_lower = strtolower( $selected_tag );

        $rows = array_filter(
            $rows,
            static function ( $row ) use ( $selected_tag_lower ) {
                $tags = array_map( 'trim', explode( ',', (string) $row->tags ) );

                foreach ( $tags as $tag ) {
                    if ( strtolower( $tag ) === $selected_tag_lower ) {
                        return true;
                    }
                }

                return false;
            }
        );
    }

    // Apply status filter.
    if ( 'active' === $selected_status ) {
        $rows = array_filter(
            $rows,
            static function ( $row ) {
                return (int) $row->active === 1;
            }
        );
    } elseif ( 'inactive' === $selected_status ) {
        $rows = array_filter(
            $rows,
            static function ( $row ) {
                return (int) $row->active === 0;
            }
        );
    }

    // Sort the filtered rows.
    $sort_property = $allowed_orderby[ $selected_orderby ];
    $sort_ascending = 'asc' === $selected_order;

    usort(
        $rows,
        static function ( $a, $b ) use ( $sort_property, $sort_ascending ) {
            if ( 'active' === $sort_property ) {
                $comparison = (int) $a->active <=> (int) $b->active;
            } elseif ( 'id' === $sort_property ) {
                $comparison = (int) $a->id <=> (int) $b->id;
            } else {
                $comparison = strcasecmp(
                    (string) $a->{$sort_property},
                    (string) $b->{$sort_property}
                );
            }

            return $sort_ascending ? $comparison : -$comparison;
        }
    );

    $total = count( $rows );

    // Pagination.
    $per_page = 20;

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only pagination parameter; no state change.
    $current_page = isset( $_GET['paged'] )
        ? max( 1, absint( $_GET['paged'] ) )
        : 1;

    $total_pages = max( 1, (int) ceil( $total / $per_page ) );
    $offset = ( $current_page - 1 ) * $per_page;
    $rows = array_slice( array_values( $rows ), $offset, $per_page );

    // Add quotation button.
    echo '<p>';
    echo '<a href="' . esc_url( admin_url( 'admin.php?page=quotum&add=1' ) ) . '" class="button button-primary">';
    echo esc_html__( 'Add New Quotation', 'quotum' );
    echo '</a>';
    echo '</p>';

    // Helper for sortable table headers.
    $sort_url = function ( $column ) use ( $search, $selected_tag, $selected_status, $selected_orderby, $selected_order ) {
        $next_order = ( $selected_orderby === $column && 'asc' === $selected_order ) ? 'desc' : 'asc';

        return add_query_arg(
            array(
                'page'    => 'quotum',
                's'       => $search,
                'tag'     => $selected_tag,
                'status'  => $selected_status,
                'orderby' => $column,
                'order'   => $next_order,
                'paged'   => 1,
            ),
            admin_url( 'admin.php' )
        );
    };

    $sort_indicator = function ( $column ) use ( $selected_orderby, $selected_order ) {
        if ( $selected_orderby !== $column ) {
            return '';
        }

        return 'asc' === $selected_order ? ' &uarr;' : ' &darr;';
    };

    // Search and tag filter form.
    echo '<form method="get" style="margin:20px 0;display:flex;flex-wrap:wrap;gap:8px;align-items:center;">';
    echo '<input type="hidden" name="page" value="quotum">';
    echo '<input type="hidden" name="orderby" value="' . esc_attr( $selected_orderby ) . '">';
    echo '<input type="hidden" name="order" value="' . esc_attr( $selected_order ) . '">';
    echo '<input type="search" name="s" value="' . esc_attr( $search ) . '" placeholder="' . esc_attr__( 'Search quotations, authors, sources and tags', 'quotum' ) . '" style="min-width:280px;">';

    echo '<select name="tag">';
    echo '<option value="">' . esc_html__( 'All tags', 'quotum' ) . '</option>';

    foreach ( $tag_summary as $tag_key => $tag_data ) {
        echo '<option value="' . esc_attr( $tag_data['name'] ) . '" ' . selected( $selected_tag, $tag_data['name'], false ) . '>';
        echo esc_html( $tag_data['name'] . ' (' . number_format_i18n( $tag_data['count'] ) . ')' );
        echo '</option>';
    }

    echo '</select>';

    echo '<select name="status">';
    echo '<option value="">' . esc_html__( 'All statuses', 'quotum' ) . '</option>';
    echo '<option value="active" ' . selected( $selected_status, 'active', false ) . '>' . esc_html__( 'Active', 'quotum' ) . '</option>';
    echo '<option value="inactive" ' . selected( $selected_status, 'inactive', false ) . '>' . esc_html__( 'Inactive', 'quotum' ) . '</option>';
    echo '</select>';

    submit_button( __( 'Apply filters', 'quotum' ), 'secondary', '', false );

    if ( $search !== '' || $selected_tag !== '' || $selected_status !== '' ) {
        echo '<a class="button" href="' . esc_url( admin_url( 'admin.php?page=quotum' ) ) . '">' . esc_html__( 'Clear filters', 'quotum' ) . '</a>';
    }

    echo '</form>';

    echo '<p>' . esc_html__( 'Quotations found:', 'quotum' ) . ' <strong>';
    echo esc_html( number_format_i18n( $total ) );
    echo '</strong></p>';


    if ( empty( $rows ) ) {
        echo '<p>' . esc_html__( 'No quotations found.', 'quotum' ) . '</p>';
    } else {
        // Bulk action form is separate from row-level delete forms.
        echo '<form id="quotum-bulk-form" method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
        echo '<input type="hidden" name="action" value="quotum_bulk_status">';
        wp_nonce_field( 'quotum_bulk_status' );
        echo '<input type="hidden" name="s" value="' . esc_attr( $search ) . '">';
        echo '<input type="hidden" name="tag" value="' . esc_attr( $selected_tag ) . '">';
        echo '<input type="hidden" name="status" value="' . esc_attr( $selected_status ) . '">';
        echo '<input type="hidden" name="paged" value="' . esc_attr( $current_page ) . '">';
        echo '<label for="quotum-bulk-action" class="screen-reader-text">' . esc_html__( 'Select bulk action', 'quotum' ) . '</label>';
        echo '<select name="bulk_action" id="quotum-bulk-action">';
        echo '<option value="">' . esc_html__( 'Bulk actions', 'quotum' ) . '</option>';
        echo '<option value="activate">' . esc_html__( 'Activate', 'quotum' ) . '</option>';
        echo '<option value="deactivate">' . esc_html__( 'Deactivate', 'quotum' ) . '</option>';
        echo '<option value="delete">' . esc_html__( 'Delete permanently', 'quotum' ) . '</option>';
        echo '</select> ';
        echo '<button type="submit" class="button">' . esc_html__( 'Apply', 'quotum' ) . '</button>';
        echo '</form>';

        echo '<table class="widefat striped">';
        echo '<thead><tr>';
        echo '<th style="width:3%;"><input type="checkbox" id="quotum-select-all" aria-label="' . esc_attr__( 'Select all quotations on this page', 'quotum' ) . '"></th>';

        echo '<th style="width:27%;"><a href="' . esc_url( $sort_url( 'quote' ) ) . '">' . esc_html__( 'Quote', 'quotum' ) . esc_html( $sort_indicator( 'quote' ) ) . '</a></th>';
        echo '<th style="width:18%;"><a href="' . esc_url( $sort_url( 'author' ) ) . '">' . esc_html__( 'Author', 'quotum' ) . esc_html( $sort_indicator( 'author' ) ) . '</a></th>';
        echo '<th style="width:22%;"><a href="' . esc_url( $sort_url( 'source' ) ) . '">' . esc_html__( 'Source', 'quotum' ) . esc_html( $sort_indicator( 'source' ) ) . '</a></th>';
        echo '<th style="width:15%;"><a href="' . esc_url( $sort_url( 'tags' ) ) . '">' . esc_html__( 'Tags', 'quotum' ) . esc_html( $sort_indicator( 'tags' ) ) . '</a></th>';
        echo '<th style="width:7%;"><a href="' . esc_url( $sort_url( 'status' ) ) . '">' . esc_html__( 'Status', 'quotum' ) . esc_html( $sort_indicator( 'status' ) ) . '</a></th>';
        echo '<th style="width:8%;">' . esc_html__( 'Actions', 'quotum' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $rows as $row ) {
            echo '<tr>';

            // translators: %d is the quotation ID.
            echo '<td><input type="checkbox" value="' . esc_attr( $row->id ) . '" class="quotum-row-checkbox" aria-label="' . esc_attr( sprintf( __( 'Select quotation %d', 'quotum' ), $row->id ) ) . '"></td>';
            echo '<td>' . esc_html( wp_trim_words( $row->quote, 35 ) ) . '</td>';
            echo '<td>' . esc_html( $row->author_original ) . '</td>';
            echo '<td>' . esc_html( wp_trim_words( $row->source, 20 ) ) . '</td>';
            echo '<td>' . esc_html( $row->tags ) . '</td>';

            echo '<td>' . esc_html( $row->active ? __( 'Active', 'quotum' ) : __( 'Inactive', 'quotum' ) ) . '</td>';

            echo '<td>';

            $edit_url = add_query_arg(
                array(
                    'page' => 'quotum',
                    'edit' => $row->id,
                ),
                admin_url( 'admin.php' )
            );

            echo '<a href="' . esc_url( $edit_url ) . '">' . esc_html__( 'Edit', 'quotum' ) . '</a>';

            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline;" onsubmit="return confirm(\'' . esc_js( __( 'Delete this quotation permanently?', 'quotum' ) ) . '\');">';

            echo '<input type="hidden" name="action" value="quotum_delete">';
            echo '<input type="hidden" name="id" value="' . esc_attr( $row->id ) . '">';

            wp_nonce_field( 'quotum_delete_quote' );

            echo ' | <button type="submit" class="button-link-delete">' . esc_html__( 'Delete', 'quotum' ) . '</button>';
            echo '</form>';

            echo '</td>';
            echo '</tr>';
        }

        echo '</tbody></table>';

    }

    // Pagination links.
    if ( $total_pages > 1 ) {
        $base_url = add_query_arg(
            array(
                'page'  => 'quotum',
                's'      => $search,
                'tag'    => $selected_tag,
                'status'  => $selected_status,
                'orderby' => $selected_orderby,
                'order'   => $selected_order,
                'paged'   => '%#%',
            ),
            admin_url( 'admin.php' )
        );

        echo '<div style="margin-top:20px;">';

        echo wp_kses_post(
            paginate_links(
                array(
                    'base'      => $base_url,
                    'format'    => '',
                    'current'   => $current_page,
                    'total'     => $total_pages,
                    'prev_text' => '&laquo; Previous',
                    'next_text' => 'Next &raquo;',
                )
            )
        );

        echo '</div>';
    }

    // Tag management, placed after the quotations table and pagination.
    echo '<h2>' . esc_html__( 'Tag Management', 'quotum' ) . '</h2>';

    if ( empty( $tag_summary ) ) {
        echo '<p>' . esc_html__( 'No tags are currently assigned to quotations.', 'quotum' ) . '</p>';
    } else {
        echo '<p>' . esc_html__( 'Tags are shared across quotations. Renaming or removing a tag affects every quotation using it, but never deletes quotations.', 'quotum' ) . '</p>';
        echo '<table class="widefat striped" style="max-width:1000px;">';
        echo '<thead><tr>';
        echo '<th style="width:20%;">' . esc_html__( 'Tag', 'quotum' ) . '</th>';
        echo '<th style="width:12%;">' . esc_html__( 'Quotations', 'quotum' ) . '</th>';
        echo '<th>' . esc_html__( 'Rename tag', 'quotum' ) . '</th>';
        echo '<th style="width:12%;">' . esc_html__( 'Remove', 'quotum' ) . '</th>';
        echo '</tr></thead><tbody>';

        foreach ( $tag_summary as $tag_data ) {
            $tag_name = $tag_data['name'];

            echo '<tr>';
            echo '<td><strong>' . esc_html( $tag_name ) . '</strong></td>';
            echo '<td>' . esc_html( number_format_i18n( $tag_data['count'] ) ) . '</td>';

            echo '<td>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:flex;gap:8px;align-items:center;">';
            echo '<input type="hidden" name="action" value="quotum_rename_tag">';
            echo '<input type="hidden" name="old_tag" value="' . esc_attr( $tag_name ) . '">';
            wp_nonce_field( 'quotum_rename_tag' );
            echo '<input type="text" name="new_tag" value="' . esc_attr( $tag_name ) . '" required>';
            submit_button( __( 'Rename', 'quotum' ), 'secondary', 'submit', false );
            echo '</form>';
            echo '</td>';

            echo '<td>';
            echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" onsubmit="return confirm(\'' . esc_js( __( 'Remove this tag from all quotations?', 'quotum' ) ) . '\');">';
            echo '<input type="hidden" name="action" value="quotum_remove_tag">';
            echo '<input type="hidden" name="tag" value="' . esc_attr( $tag_name ) . '">';
            wp_nonce_field( 'quotum_remove_tag' );
            echo '<button type="submit" class="button-link-delete">' . esc_html__( 'Remove', 'quotum' ) . '</button>';
            echo '</form>';
            echo '</td>';

            echo '</tr>';
        }

        echo '</tbody></table>';
    }

    echo '<hr style="margin:25px 0;">';

    echo '</div>';
}

/**
 * Display the add/edit form.
 */
function quotum_edit_form( $table, $id = 0 ) {
    global $wpdb;

    $quote = (object) array(
        'quote'           => '',
        'author_original' => '',
        'source'          => '',
        'tags'            => '',
        'active'          => 1,
    );

    if ( $id > 0 ) {
        $record = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM %i WHERE id = %d",
                $table,
                $id
            )
        );

        if ( ! $record ) {
            echo '<div class="notice notice-error"><p>' . esc_html__( 'Quotation not found.', 'quotum' ) . '</p></div>';
            return;
        }

        $quote = $record;
    }

    // Collect existing tags for suggestions.
    $tag_rows = $wpdb->get_col(
        $wpdb->prepare(
            "SELECT tags FROM %i WHERE tags IS NOT NULL AND tags <> ''",
            $table
        )
    );

    $existing_tags = array();

    foreach ( $tag_rows as $tag_string ) {
        foreach ( explode( ',', $tag_string ) as $tag ) {
            $tag = trim( $tag );

            if ( $tag !== '' ) {
                $existing_tags[] = $tag;
            }
        }
    }

    $existing_tags = array_unique( $existing_tags );

    echo '<h2>' . esc_html( $id > 0 ? __( 'Edit Quotation', 'quotum' ) : __( 'Add New Quotation', 'quotum' ) ) . '</h2>';

    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';

    echo '<input type="hidden" name="action" value="quotum_save">';
    echo '<input type="hidden" name="id" value="' . esc_attr( $id ) . '">';

    wp_nonce_field( 'quotum_save_quote' );

    echo '<table class="form-table quotum-edit-form">';

    // Quote.
    echo '<tr><th><label for="quote">' . esc_html__( 'Quote', 'quotum' ) . '</label></th><td>';
    echo '<textarea name="quote" id="quote" rows="6" class="large-text" required>';
    echo esc_textarea( $quote->quote );
    echo '</textarea>';
    echo '</td></tr>';

    // Author.
    echo '<tr><th><label for="author">' . esc_html__( 'Author', 'quotum' ) . '</label></th><td>';
    echo '<textarea name="author" id="author" rows="2" class="large-text">';
    echo esc_textarea( $quote->author_original );
    echo '</textarea>';
    echo '</td></tr>';

    // Source.
    echo '<tr><th><label for="source">' . esc_html__( 'Source', 'quotum' ) . '</label></th><td>';
    echo '<textarea name="source" id="source" rows="3" class="large-text">';
    echo esc_textarea( $quote->source );
    echo '</textarea>';
    echo '</td></tr>';

    // Tags.
    echo '<tr><th><label for="tags">' . esc_html__( 'Tags', 'quotum' ) . '</label></th><td>';
    echo '<input type="text" name="tags" id="tags" list="quotum-tags" class="large-text" value="' . esc_attr( $quote->tags ) . '">';
    echo '<datalist id="quotum-tags">';

    foreach ( $existing_tags as $tag ) {
        echo '<option value="' . esc_attr( $tag ) . '">';
    }

    echo '</datalist>';
    echo '<p class="description">' . esc_html__( 'Separate multiple tags with commas. Existing tags are suggested while typing.', 'quotum' ) . '</p>';
    echo '</td></tr>';

    // Active.
    echo '<tr><th scope="row">' . esc_html__( 'Status', 'quotum' ) . '</th><td>';
    echo '<label><input type="checkbox" name="active" value="1" ' . checked( $quote->active, 1, false ) . '> ' . esc_html__( 'Active (available for public display)', 'quotum' ) . '</label>';
    echo '</td></tr>';

    echo '</table>';

    // Live preview of the public quotation display.
    echo '<h3>' . esc_html__( 'Live Preview', 'quotum' ) . '</h3>';
    echo '<p class="description">' . esc_html__( 'This preview updates as you type. Tags and status are not displayed publicly.', 'quotum' ) . '</p>';
    echo '<div id="quotum-live-preview" class="quotum-display" style="margin:1em 0;padding:0.8em 0;border:0;background:transparent;max-width:800px;">';
    echo '<div id="quotum-preview-quote" class="quotum-text" style="margin:0 0 0.8em;font-size:1.1em;font-style:italic;line-height:1.6;white-space:pre-wrap;"></div>';
    echo '<div id="quotum-preview-author" class="quotum-author" style="margin-top:0.6em;font-weight:600;font-size:0.95em;"></div>';
    echo '<div id="quotum-preview-source" class="quotum-source" style="margin-top:0.3em;font-size:0.85em;color:#646970;"></div>';
    echo '</div>';

    submit_button( $id > 0 ? __( 'Save Changes', 'quotum' ) : __( 'Add Quotation', 'quotum' ) );

    echo ' <a href="' . esc_url( admin_url( 'admin.php?page=quotum' ) ) . '" class="button">' . esc_html__( 'Cancel', 'quotum' ) . '</a>';

    echo '</form>';

    // Update the preview safely using textContent (never interpret user text as HTML).

}

