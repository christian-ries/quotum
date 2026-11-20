<?php

if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * Export the quotation library as CSV.
 */
add_action( 'admin_post_quotum_export', 'quotum_handle_export' );

function quotum_handle_export() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_export' );

    global $wpdb;
    $table = $wpdb->prefix . 'quotum';

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            'SELECT quote, author_original, source, tags, active FROM %i ORDER BY id ASC',
            $table
        ),
        ARRAY_A
    );

    if ( $rows === null ) {
        wp_die( esc_html__( 'Database error: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
    }

    nocache_headers();
    header( 'Content-Type: text/csv; charset=utf-8' );
    header( 'Content-Disposition: attachment; filename="quotum-export-' . gmdate( 'Y-m-d' ) . '.csv"' );

    $csv = "\xEF\xBB\xBF";
    $csv .= quotum_csv_line( array( 'Quote', 'Author', 'Source', 'Tags', 'Active' ) );

    foreach ( $rows as $row ) {
        $csv .= quotum_csv_line( array(
            $row['quote'],
            $row['author_original'],
            $row['source'],
            $row['tags'],
            (int) $row['active'],
        ) );
    }

    // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CSV must be sent as raw file content; HTML escaping would corrupt the CSV.
    echo $csv;
    exit;
}

/**
 * Convert an array of values into one RFC 4180-compatible CSV line.
 */
function quotum_csv_line( $fields ) {
    $escaped = array();

    foreach ( $fields as $field ) {
        $escaped[] = '"' . str_replace( '"', '""', (string) $field ) . '"';
    }

    return implode( ',', $escaped ) . "\r\n";
}

/**
 * Parse an RFC 4180-compatible CSV string, including quoted multiline fields.
 */
function quotum_parse_csv( $contents ) {
    $rows = array();
    $row = array();
    $field = '';
    $in_quotes = false;
    $length = strlen( $contents );

    for ( $i = 0; $i < $length; $i++ ) {
        $char = $contents[ $i ];

        if ( $in_quotes ) {
            if ( '"' === $char ) {
                if ( $i + 1 < $length && '"' === $contents[ $i + 1 ] ) {
                    $field .= '"';
                    $i++;
                } else {
                    $in_quotes = false;
                }
            } else {
                $field .= $char;
            }
            continue;
        }

        if ( '"' === $char && '' === $field ) {
            $in_quotes = true;
        } elseif ( ',' === $char ) {
            $row[] = $field;
            $field = '';
        } elseif ( "\r" === $char ) {
            if ( $i + 1 < $length && "\n" === $contents[ $i + 1 ] ) {
                $i++;
            }
            $row[] = $field;
            $field = '';
            $rows[] = $row;
            $row = array();
        } elseif ( "\n" === $char ) {
            $row[] = $field;
            $field = '';
            $rows[] = $row;
            $row = array();
        } else {
            $field .= $char;
        }
    }

    if ( '' !== $field || ! empty( $row ) ) {
        $row[] = $field;
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Normalize a value for duplicate detection.
 * Case and whitespace differences are ignored.
 */
function quotum_normalize_duplicate_value( $value ) {
    $value = trim( (string) $value );
    $value = preg_replace( '/\s+/u', ' ', $value );

    if ( function_exists( 'mb_strtolower' ) ) {
        return mb_strtolower( $value, 'UTF-8' );
    }

    return strtolower( $value );
}

/**
 * Build a duplicate key from quote and author.
 */
function quotum_duplicate_key( $quote, $author ) {
    return hash( 'sha256',
        quotum_normalize_duplicate_value( $quote ) . "\0" .
        quotum_normalize_duplicate_value( $author )
    );
}

/**
 * Import quotations from a CSV file.
 * Existing quotations are never deleted or modified.
 */
add_action( 'admin_post_quotum_import', 'quotum_handle_import' );

function quotum_handle_import() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }

    check_admin_referer( 'quotum_import' );

    if ( empty( $_FILES['quotes_csv']['tmp_name'] ) ) {
        wp_safe_redirect( add_query_arg(
            array(
                'page'    => 'quotum-export-import',
                'message' => 'import_no_file',
            ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- The uploaded temporary path is server-generated and is validated with is_uploaded_file() before reading.
    $file = $_FILES['quotes_csv'];

    if ( ! empty( $file['error'] ) || ! is_uploaded_file( $file['tmp_name'] ) ) {
        wp_die( esc_html__( 'The CSV file could not be uploaded.', 'quotum' ) );
    }

    if ( filesize( $file['tmp_name'] ) > 5 * 1024 * 1024 ) {
        wp_die( esc_html__( 'The CSV file is too large. The maximum size is 5 MB.', 'quotum' ) );
    }

    global $wpdb;
    $table = $wpdb->prefix . 'quotum';

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    $filesystem = new WP_Filesystem_Direct( false );
    $contents = $filesystem->get_contents( $file['tmp_name'] );

    if ( false === $contents ) {
        wp_die( esc_html__( 'The CSV file could not be read.', 'quotum' ) );
    }

    // Remove a UTF-8 BOM before parsing, including before the first quoted header.
    $contents = preg_replace( "/^\xEF\xBB\xBF/", "", $contents );
    $rows = quotum_parse_csv( $contents );
    $header = array_shift( $rows );

    if ( false === $header || empty( $header ) ) {
        wp_die( esc_html__( 'The CSV file is empty.', 'quotum' ) );
    }

    $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
    $expected_header = array( 'Quote', 'Author', 'Source', 'Tags', 'Active' );

    if ( $header !== $expected_header ) {
        wp_safe_redirect( add_query_arg(
            array(
                'page'    => 'quotum-export-import',
                'message' => 'import_invalid_csv',
            ),
            admin_url( 'admin.php' )
        ) );
        exit;
    }

    $import_status = isset( $_POST['import_status'] )
        ? sanitize_key( wp_unslash( $_POST['import_status'] ) )
        : 'inactive';

    if ( ! in_array( $import_status, array( 'csv', 'active', 'inactive' ), true ) ) {
        $import_status = 'inactive';
    }

    $result = quotum_import_rows( $rows, $import_status );
    $imported = $result['imported'];
    $duplicates = $result['duplicates'];
    $skipped = $result['skipped'];

    wp_safe_redirect( add_query_arg(
        array(
            'page'          => 'quotum',
            'message'       => 'imported',
            'imported'      => $imported,
            'duplicates'    => $duplicates,
            'skipped'       => $skipped,
            'import_status' => $import_status,
        ),
        admin_url( 'admin.php' )
    ) );
    exit;
}

/**
 * Import parsed CSV rows. Used by both uploaded and bundled CSV imports.
 * Existing quotations are never modified; duplicate keys are quote + author.
 */
function quotum_import_rows( $rows, $import_status = 'csv' ) {
    global $wpdb;
    $table = $wpdb->prefix . 'quotum';
    $imported = 0;
    $duplicates = 0;
    $skipped = 0;
    $line_number = 1;

    // Build normalized duplicate keys for the existing library.
    // Duplicate matching ignores case and differences in whitespace.
    $existing_rows = $wpdb->get_results(
        $wpdb->prepare( 'SELECT quote, author_original FROM %i', $table ),
        ARRAY_A
    );

    if ( null === $existing_rows ) {
        wp_die( esc_html__( 'Database error while checking for duplicates: ', 'quotum' ) . esc_html( $wpdb->last_error ) );
    }

    $existing_duplicate_keys = array();

    foreach ( $existing_rows as $existing_row ) {
        $existing_duplicate_keys[
            quotum_duplicate_key( $existing_row['quote'], $existing_row['author_original'] )
        ] = true;
    }

    foreach ( $rows as $row ) {
        $line_number++;

        if ( count( $row ) === 1 && trim( $row[0] ) === '' ) {
            continue;
        }

        if ( count( $row ) < 5 ) {
            $skipped++;
            continue;
        }

        $quote  = sanitize_textarea_field( $row[0] );
        $author = sanitize_textarea_field( $row[1] );
        $source = sanitize_textarea_field( $row[2] );
        $tags   = quotum_normalize_tags( $row[3] );

        if ( 'active' === $import_status ) {
            $active = 1;
        } elseif ( 'inactive' === $import_status ) {
            $active = 0;
        } else {
            $active = ( '1' === trim( $row[4] ) ) ? 1 : 0;
        }

        if ( '' === trim( $quote ) ) {
            $skipped++;
            continue;
        }

        $duplicate_key = quotum_duplicate_key( $quote, $author );

        if ( isset( $existing_duplicate_keys[ $duplicate_key ] ) ) {
            $duplicates++;
            continue;
        }

        // Also remember newly imported rows so duplicates within the same
        // CSV file are skipped as well.
        $existing_duplicate_keys[ $duplicate_key ] = true;

        $result = $wpdb->insert(
            $table,
            array(
                'original_id'     => null,
                'quote'           => $quote,
                'author_original' => $author,
                'source'          => $source,
                'tags'            => $tags,
                'active'          => $active,
                'created_at'      => current_time( 'mysql' ),
                'updated_at'      => current_time( 'mysql' ),
            ),
            array( '%d', '%s', '%s', '%s', '%s', '%d', '%s', '%s' )
        );

        if ( false === $result ) {
            wp_die( esc_html__( 'Database error while importing line ', 'quotum' ) . esc_html( $line_number ) . esc_html__( ': ', 'quotum' ) . esc_html( $wpdb->last_error ) );
        }

        $imported++;
    }

    return array( 'imported' => $imported, 'duplicates' => $duplicates, 'skipped' => $skipped );
}

/**
 * Import the optional bundled sample CSV, using the normal import logic.
 */
add_action( 'admin_post_quotum_import_samples', 'quotum_import_samples' );
function quotum_import_samples() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }
    check_admin_referer( 'quotum_import_samples' );

    $path = QUOTUM_PLUGIN_DIR . 'examples/sample-quotes.csv';
    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
    require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
    $filesystem = new WP_Filesystem_Direct( false );
    $contents = $filesystem->get_contents( $path );
    if ( false === $contents ) {
        wp_die( esc_html__( 'Sample quotations file could not be read.', 'quotum' ) );
    }
    $rows = quotum_parse_csv( $contents );
    $header = array_shift( $rows );
    if ( ! $header ) {
        wp_die( esc_html__( 'Sample quotations file is empty.', 'quotum' ) );
    }
    $header[0] = preg_replace( '/^\xEF\xBB\xBF/', '', $header[0] );
    if ( $header !== array( 'Quote', 'Author', 'Source', 'Tags', 'Active' ) ) {
        wp_die( esc_html__( 'Invalid sample CSV header.', 'quotum' ) );
    }

    $result = quotum_import_rows( $rows, 'inactive' );
    update_option( 'quotum_samples_choice', 'imported', false );
    wp_safe_redirect( add_query_arg( array(
        'page' => 'quotum',
        'message' => 'imported',
        'imported' => $result['imported'],
        'duplicates' => $result['duplicates'],
        'skipped' => $result['skipped'],
        'import_status' => 'inactive',
    ), admin_url( 'admin.php' ) ) );
    exit;
}

add_action( 'admin_post_quotum_dismiss_samples', 'quotum_dismiss_samples' );
function quotum_dismiss_samples() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You are not allowed to do this.', 'quotum' ) );
    }
    check_admin_referer( 'quotum_dismiss_samples' );
    update_option( 'quotum_samples_choice', 'dismissed', false );
    wp_safe_redirect( admin_url( 'admin.php?page=quotum' ) );
    exit;
}

add_action( 'admin_notices', 'quotum_sample_welcome_notice' );
function quotum_sample_welcome_notice() {
    if ( ! current_user_can( 'manage_options' ) || 'offer' !== get_option( 'quotum_samples_choice' ) ) {
        return;
    }
    $screen = get_current_screen();
    if ( ! $screen || ! in_array( $screen->id, array( 'dashboard', 'toplevel_page_quotum', 'quotum_page_quotum-export-import' ), true ) ) {
        return;
    }
    echo '<div class="notice notice-info"><p><strong>' . esc_html__( 'Welcome to Quotum!', 'quotum' ) . '</strong> ' . esc_html__( 'Would you like to import three sample quotations by Victor Hugo, Dag Hammarskjöld and Franz Kafka?', 'quotum' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 8px 12px 0;">';
    echo '<input type="hidden" name="action" value="quotum_import_samples">';
    wp_nonce_field( 'quotum_import_samples' );
    echo '<button class="button button-primary" type="submit">' . esc_html__( 'Import sample quotations', 'quotum' ) . '</button></form>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" style="display:inline-block;margin:0 0 12px;">';
    echo '<input type="hidden" name="action" value="quotum_dismiss_samples">';
    wp_nonce_field( 'quotum_dismiss_samples' );
    echo '<button class="button" type="submit">' . esc_html__( 'No thanks', 'quotum' ) . '</button></form></div>';
}

/**
 * Render import result on Manage Quotes (or legacy import result URLs).
 * Counts in the URL are display-only and cannot change quotation records.
 */
function quotum_render_import_notice() {
    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result.
    $message = isset( $_GET['message'] ) ? sanitize_key( wp_unslash( $_GET['message'] ) ) : '';
    if ( 'imported' !== $message ) {
        return;
    }
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result parameter; no state change.
        $imported   = isset( $_GET['imported'] ) ? absint( $_GET['imported'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result parameter; no state change.
        $duplicates = isset( $_GET['duplicates'] ) ? absint( $_GET['duplicates'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result parameter; no state change.
        $skipped    = isset( $_GET['skipped'] ) ? absint( $_GET['skipped'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result parameter; no state change.
        $errors        = isset( $_GET['errors'] ) ? absint( $_GET['errors'] ) : 0;
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only import result parameter; no state change.
        $import_status = isset( $_GET['import_status'] )
            ? sanitize_key( wp_unslash( $_GET['import_status'] ) )
            : 'csv';

        echo '<div class="notice notice-success is-dismissible"><p>';
        $status_message = '';
        if ( 'active' === $import_status ) {
            $status_message = ' ' . __( 'as active', 'quotum' );
        } elseif ( 'inactive' === $import_status ) {
            $status_message = ' ' . __( 'as inactive', 'quotum' );
        }

        // translators: %1$d is the number of imported quotations; %2$s is an optional status suffix.
        echo esc_html( sprintf( _n( '%1$d quotation imported successfully%2$s.', '%1$d quotations imported successfully%2$s.', $imported, 'quotum' ), $imported, $status_message ) );
        // translators: %1$d is the number of duplicate quotations skipped.
        echo ' ' . esc_html( sprintf( _n( '%1$d duplicate was skipped.', '%1$d duplicates were skipped.', $duplicates, 'quotum' ), $duplicates ) );
        // translators: %1$d is the number of invalid or empty rows skipped.
        echo ' ' . esc_html( sprintf( _n( '%1$d invalid or empty row was skipped.', '%1$d invalid or empty rows were skipped.', $skipped, 'quotum' ), $skipped ) );
        if ( $errors > 0 ) {
            // translators: %1$d is the number of errors encountered.
            echo ' ' . esc_html( sprintf( _n( '%1$d error occurred.', '%1$d errors occurred.', $errors, 'quotum' ), $errors ) );
        }
        echo '</p></div>';
}

/**
 * Display the Export / Import administration page.
 */
function quotum_export_import_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        return;
    }

    // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read-only admin notice parameter; no state change.
    $message = isset( $_GET['message'] )
        ? sanitize_key( wp_unslash( $_GET['message'] ) )
        : '';

    echo '<div class="wrap">';
    echo '<h1>' . esc_html__( 'Quotum — Export / Import', 'quotum' ) . '</h1>';

    if ( 'imported' === $message ) {
        quotum_render_import_notice();    } elseif ( 'import_no_file' === $message ) {
        echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Please choose a CSV file to import.', 'quotum' ) . '</p></div>';
    } elseif ( 'import_invalid_csv' === $message ) {
        echo '<div class="notice notice-error is-dismissible"><p><strong>' . esc_html__( 'Import aborted:', 'quotum' ) . '</strong> ' . esc_html__( 'Invalid CSV format. Expected the columns: Quote, Author, Source, Tags, Active. Please use a CSV exported by Quotum.', 'quotum' ) . '</p></div>';
    }

    echo '<h2>' . esc_html__( 'Export', 'quotum' ) . '</h2>';
    echo '<p>' . esc_html__( 'Export all quotations to a UTF-8 CSV file. The export contains the quote, author, source, tags and active status.', 'quotum' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="quotum_export">';
    wp_nonce_field( 'quotum_export' );
    echo '<button type="submit" class="button button-primary">' . esc_html__( 'Export Quotations', 'quotum' ) . '</button>';
    echo '</form>';

    echo '<hr style="margin:30px 0;">';

    echo '<h2>' . esc_html__( 'Import', 'quotum' ) . '</h2>';
    echo '<p>' . esc_html__( 'Import quotations from a CSV file previously exported by Quotum.', 'quotum' ) . '</p>';
    echo '<p><strong>' . esc_html__( 'Important:', 'quotum' ) . '</strong> ' . esc_html__( 'Importing is additive. Existing quotations are not deleted or modified. Quotations with the same Quote + Author are skipped as duplicates. Duplicate matching ignores capitalization and differences in whitespace.', 'quotum' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '" enctype="multipart/form-data">';
    echo '<input type="hidden" name="action" value="quotum_import">';
    wp_nonce_field( 'quotum_import' );
    echo '<input type="file" name="quotes_csv" accept=".csv,text/csv" required>';

    echo '<p><label for="quotum-import-status"><strong>' . esc_html__( 'Import status:', 'quotum' ) . '</strong></label><br>';
    echo '<select name="import_status" id="quotum-import-status">';
    echo '<option value="inactive">' . esc_html__( 'Import all as Inactive (default)', 'quotum' ) . '</option>';
    echo '<option value="csv">' . esc_html__( 'Keep status from CSV', 'quotum' ) . '</option>';
    echo '<option value="active">' . esc_html__( 'Import all as Active', 'quotum' ) . '</option>';
    echo '</select></p>';
    echo '<p class="description">' . esc_html__( 'Choose whether imported quotations start inactive, retain their CSV status, or become active.', 'quotum' ) . '</p>';

    echo '<p><button type="submit" class="button button-primary">' . esc_html__( 'Import Quotations', 'quotum' ) . '</button></p>';
    echo '</form>';

    echo '<hr>';
    echo '<h2>' . esc_html__( 'Sample quotations', 'quotum' ) . '</h2>';
    echo '<p>' . esc_html__( 'Import three optional example quotations as inactive from the bundled CSV. Existing quotations are preserved and duplicates are skipped.', 'quotum' ) . '</p>';
    echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
    echo '<input type="hidden" name="action" value="quotum_import_samples">';
    wp_nonce_field( 'quotum_import_samples' );
    echo '<button type="submit" class="button">' . esc_html__( 'Import sample quotations', 'quotum' ) . '</button>';
    echo '</form>';

    echo '</div>';
}


