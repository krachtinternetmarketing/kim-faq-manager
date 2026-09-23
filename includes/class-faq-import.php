<?php
defined( 'ABSPATH' ) || exit;

class FAQM_Import {

    public static function init(): void {
        add_action( 'admin_menu',      [ __CLASS__, 'add_import_page' ] );
        add_action( 'admin_post_faqm_import_csv', [ __CLASS__, 'handle_import' ] );
    }

    public static function add_import_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . FAQM_POST_TYPE,
            __( 'FAQ Importeren', 'faq-manager' ),
            __( 'Importeren', 'faq-manager' ),
            'manage_options',
            'faqm-import',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function render_page(): void {
        $result = get_transient( 'faqm_import_result_' . get_current_user_id() );
        if ( $result ) {
            delete_transient( 'faqm_import_result_' . get_current_user_id() );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'FAQ Importeren', 'faq-manager' ); ?></h1>

            <?php if ( $result ) : ?>
                <div class="notice notice-<?php echo $result['type'] === 'success' ? 'success' : 'error'; ?> is-dismissible">
                    <p><?php echo esc_html( $result['message'] ); ?></p>
                </div>
            <?php endif; ?>

            <div class="card" style="max-width:600px;padding:20px;margin-top:20px;">
                <h2 style="margin-top:0;"><?php esc_html_e( 'CSV bestand uploaden', 'faq-manager' ); ?></h2>
                <p><?php esc_html_e( 'Upload een CSV-bestand met de volgende kolommen:', 'faq-manager' ); ?></p>
                <code style="display:block;padding:10px;background:#f6f7f7;border:1px solid #ddd;border-radius:4px;margin-bottom:16px;">
                    vraag, lang_antwoord, kort_antwoord
                </code>
                <ul style="list-style:disc;padding-left:20px;color:#646970;font-size:13px;">
                    <li><?php esc_html_e( 'Eerste rij = kolomkoppen (wordt overgeslagen)', 'faq-manager' ); ?></li>
                    <li><?php esc_html_e( 'Scheidingsteken: komma of puntkomma', 'faq-manager' ); ?></li>
                    <li><?php esc_html_e( 'Encoding: UTF-8', 'faq-manager' ); ?></li>
                    <li><?php esc_html_e( 'HTML in antwoorden is toegestaan', 'faq-manager' ); ?></li>
                    <li><?php esc_html_e( 'Bestaande vragen met dezelfde tekst worden overgeslagen', 'faq-manager' ); ?></li>
                </ul>

                <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="faqm_import_csv">
                    <?php wp_nonce_field( 'faqm_import_csv', 'faqm_import_nonce' ); ?>
                    <table class="form-table" style="margin-top:0;">
                        <tr>
                            <th style="padding:10px 0 0;"><label for="faqm_csv_file"><?php esc_html_e( 'CSV bestand', 'faq-manager' ); ?></label></th>
                            <td style="padding:10px 0 0;"><input type="file" name="faqm_csv_file" id="faqm_csv_file" accept=".csv" required></td>
                        </tr>
                        <tr>
                            <th style="padding:10px 0 0;"><label for="faqm_update_existing"><?php esc_html_e( 'Bestaande bijwerken', 'faq-manager' ); ?></label></th>
                            <td style="padding:10px 0 0;">
                                <input type="checkbox" name="faqm_update_existing" id="faqm_update_existing" value="1">
                                <label for="faqm_update_existing" style="font-weight:normal;"><?php esc_html_e( 'Overschrijf bestaande FAQ-vragen met dezelfde vraagtekst', 'faq-manager' ); ?></label>
                            </td>
                        </tr>
                    </table>
                    <p style="margin-top:16px;">
                        <?php submit_button( __( 'Importeren', 'faq-manager' ), 'primary', 'submit', false ); ?>
                    </p>
                </form>

                <hr>
                <h3><?php esc_html_e( 'Voorbeeld CSV downloaden', 'faq-manager' ); ?></h3>
                <p><a href="<?php echo esc_url( admin_url( 'admin-post.php?action=faqm_download_example&_wpnonce=' . wp_create_nonce( 'faqm_download_example' ) ) ); ?>" class="button"><?php esc_html_e( 'Download voorbeeld CSV', 'faq-manager' ); ?></a></p>
            </div>
        </div>
        <?php
    }

    public static function handle_import(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Geen toegang' );
        }
        if ( ! isset( $_POST['faqm_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faqm_import_nonce'] ) ), 'faqm_import_csv' ) ) {
            wp_die( 'Ongeldige nonce' );
        }

        // Voorbeeld CSV downloaden
        if ( isset( $_GET['action'] ) && $_GET['action'] === 'faqm_download_example' ) {
            self::download_example();
            return;
        }

        if ( empty( $_FILES['faqm_csv_file']['tmp_name'] ) ) {
            self::redirect_with_result( 'error', __( 'Geen bestand geüpload.', 'faq-manager' ) );
            return;
        }

        $file = $_FILES['faqm_csv_file']['tmp_name'];
        $update = ! empty( $_POST['faqm_update_existing'] );

        $handle = fopen( $file, 'r' );
        if ( ! $handle ) {
            self::redirect_with_result( 'error', __( 'Kon het bestand niet openen.', 'faq-manager' ) );
            return;
        }

        // Detecteer scheidingsteken
        $first_line = fgets( $handle );
        rewind( $handle );
        $delimiter = ( substr_count( $first_line, ';' ) > substr_count( $first_line, ',' ) ) ? ';' : ',';

        $row        = 0;
        $imported   = 0;
        $updated    = 0;
        $skipped    = 0;
        $errors     = 0;

        while ( ( $data = fgetcsv( $handle, 0, $delimiter ) ) !== false ) {
            $row++;

            // Sla de headerrij over
            if ( $row === 1 ) continue;

            // Minimaal 1 kolom (de vraag) vereist
            if ( empty( $data[0] ) ) {
                $skipped++;
                continue;
            }

            $question     = sanitize_text_field( trim( $data[0] ) );
            $long_answer  = wp_kses_post( trim( $data[1] ?? '' ) );
            $short_answer = wp_kses_post( trim( $data[2] ?? '' ) );

            // Zoek bestaande vraag met zelfde titel
            $existing = get_page_by_title( $question, OBJECT, FAQM_POST_TYPE );

            if ( $existing && ! $update ) {
                $skipped++;
                continue;
            }

            if ( $existing && $update ) {
                // Bijwerken
                wp_update_post( [ 'ID' => $existing->ID, 'post_title' => $question, 'post_status' => 'publish' ] );
                update_post_meta( $existing->ID, '_faqm_long_answer',  $long_answer );
                update_post_meta( $existing->ID, '_faqm_short_answer', $short_answer );
                $updated++;
            } else {
                // Nieuw aanmaken
                $post_id = wp_insert_post( [
                    'post_type'   => FAQM_POST_TYPE,
                    'post_title'  => $question,
                    'post_status' => 'publish',
                ] );

                if ( is_wp_error( $post_id ) ) {
                    $errors++;
                    continue;
                }

                update_post_meta( $post_id, '_faqm_long_answer',  $long_answer );
                update_post_meta( $post_id, '_faqm_short_answer', $short_answer );
                $imported++;
            }
        }

        fclose( $handle );

        $msg = sprintf(
            __( 'Import voltooid: %d nieuw toegevoegd, %d bijgewerkt, %d overgeslagen, %d fouten.', 'faq-manager' ),
            $imported, $updated, $skipped, $errors
        );
        self::redirect_with_result( 'success', $msg );
    }

    private static function redirect_with_result( string $type, string $message ): void {
        set_transient( 'faqm_import_result_' . get_current_user_id(), compact( 'type', 'message' ), 60 );
        wp_safe_redirect( admin_url( 'edit.php?post_type=' . FAQM_POST_TYPE . '&page=faqm-import' ) );
        exit;
    }

    public static function download_example_csv(): void {
        if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'faqm_download_example' ) ) {
            wp_die( 'Ongeldige nonce' );
        }
        header( 'Content-Type: text/csv; charset=UTF-8' );
        header( 'Content-Disposition: attachment; filename="faq-voorbeeld.csv"' );
        echo "\xEF\xBB\xBF"; // UTF-8 BOM voor Excel
        $out = fopen( 'php://output', 'w' );
        fputcsv( $out, [ 'vraag', 'lang_antwoord', 'kort_antwoord' ] );
        fputcsv( $out, [
            'Wat is de levertijd?',
            '<p>Bestellingen die voor 15:00 uur worden geplaatst, worden dezelfde werkdag verzonden. De verwachte levertijd is 1-2 werkdagen.</p>',
            '<p>Voor 15:00 besteld = dezelfde dag verzonden. Levertijd 1-2 werkdagen.</p>',
        ] );
        fputcsv( $out, [
            'Kan ik mijn bestelling retourneren?',
            '<p>Ja, u kunt uw bestelling binnen 14 dagen retourneren. Neem contact op via <a href="/contact">ons contactformulier</a>.</p>',
            '<p>Retourneren binnen 14 dagen mogelijk. <a href="/contact">Neem contact op</a>.</p>',
        ] );
        fclose( $out );
        exit;
    }
}
