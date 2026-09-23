<?php
defined( 'ABSPATH' ) || exit;

class FAQM_Settings {

    const OPTION = 'faqm_settings';

    public static function init(): void {
        add_action( 'admin_menu',    [ __CLASS__, 'add_page' ] );
        add_action( 'admin_post_faqm_save_settings', [ __CLASS__, 'save' ] );
    }

    public static function get( string $key, $default = '' ) {
        $options = get_option( self::OPTION, [] );
        return $options[ $key ] ?? $default;
    }

    /**
     * Bepaal welke accordeon-modus gebruikt moet worden.
     *
     * @param string $override Optionele shortcode-waarde ('bs5' of 'standalone').
     *                         Leeg = volg de plugin-instelling.
     * @return string 'bs5' (Bootstrap 5, standaard) of 'standalone'.
     */
    public static function framework( string $override = '' ): string {
        $override = strtolower( trim( $override ) );
        if ( $override !== '' ) {
            return ( strpos( $override, 'standalone' ) !== false || $override === 'none' ) ? 'standalone' : 'bs5';
        }
        return self::get( 'framework', 'bs5' ) === 'standalone' ? 'standalone' : 'bs5';
    }

    /**
     * Bepaal de groepering op de overzichtspagina.
     *
     * @param string $override Optionele shortcode-waarde ('letter' of 'category').
     *                         Leeg = volg de plugin-instelling.
     * @return string 'letter' (standaard) of 'category'.
     */
    public static function grouping( string $override = '' ): string {
        $override = strtolower( trim( $override ) );
        if ( $override !== '' ) {
            return $override === 'category' ? 'category' : 'letter';
        }
        return self::get( 'grouping', 'letter' ) === 'category' ? 'category' : 'letter';
    }

    public static function add_page(): void {
        add_submenu_page(
            'edit.php?post_type=' . FAQM_POST_TYPE,
            __( 'Instellingen', 'faq-manager' ),
            __( 'Instellingen', 'faq-manager' ),
            'manage_options',
            'faqm-settings',
            [ __CLASS__, 'render_page' ]
        );
    }

    public static function save(): void {
        if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Geen toegang' );
        check_admin_referer( 'faqm_save_settings', 'faqm_settings_nonce' );

        $options = [
            'woo_auto_display'   => isset( $_POST['woo_auto_display'] ) ? '1' : '0',
            'woo_title'          => sanitize_text_field( $_POST['woo_title'] ?? 'Veelgestelde vragen over dit product' ),
            'cat_title'          => sanitize_text_field( $_POST['cat_title'] ?? 'Veelgestelde vragen over deze categorie' ),
            'framework'          => ( ( $_POST['framework'] ?? 'bs5' ) === 'standalone' ) ? 'standalone' : 'bs5',
            'grouping'           => ( ( $_POST['grouping'] ?? 'letter' ) === 'category' ) ? 'category' : 'letter',
            'cpt_enabled'        => isset( $_POST['cpt_enabled'] ) ? array_map( 'sanitize_key', (array) $_POST['cpt_enabled'] ) : [],
            'cpt_auto_display'   => isset( $_POST['cpt_auto_display'] ) ? '1' : '0',
            'cpt_faq_title'      => sanitize_text_field( $_POST['cpt_faq_title'] ?? 'Veelgestelde vragen' ),
            'cpt_faq_answer'     => ( ( $_POST['cpt_faq_answer'] ?? 'short' ) === 'long' ) ? 'long' : 'short',
        ];

        update_option( self::OPTION, $options );

        wp_safe_redirect( admin_url( 'edit.php?post_type=' . FAQM_POST_TYPE . '&page=faqm-settings&saved=1' ) );
        exit;
    }

    public static function render_page(): void {
        $saved            = isset( $_GET['saved'] );
        $auto_display     = self::get( 'woo_auto_display', '1' );
        $title            = self::get( 'woo_title', 'Veelgestelde vragen over dit product' );
        $cat_title         = self::get( 'cat_title', 'Veelgestelde vragen over deze categorie' );
        $framework         = self::get( 'framework', 'bs5' );
        $grouping          = self::get( 'grouping', 'letter' );
        $cpt_enabled       = (array) self::get( 'cpt_enabled', [] );
        $cpt_auto          = self::get( 'cpt_auto_display', '1' );
        $cpt_faq_title     = self::get( 'cpt_faq_title', 'Veelgestelde vragen' );
        $cpt_faq_answer    = self::get( 'cpt_faq_answer', 'short' );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'FAQ Manager — Instellingen', 'faq-manager' ); ?></h1>

            <?php if ( $saved ) : ?>
                <div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Instellingen opgeslagen.', 'faq-manager' ); ?></p></div>
            <?php endif; ?>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:20px;align-items:start;">

                <!-- Instellingen -->
                <div class="card" style="padding:20px;">
                    <h2 style="margin-top:0;"><?php esc_html_e( 'WooCommerce productpagina', 'faq-manager' ); ?></h2>
                    <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                        <input type="hidden" name="action" value="faqm_save_settings">
                        <?php wp_nonce_field( 'faqm_save_settings', 'faqm_settings_nonce' ); ?>

                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th style="padding:12px 0 0;width:220px;">
                                    <label><?php esc_html_e( 'Automatisch tonen', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                                        <input type="checkbox" name="woo_auto_display" value="1" <?php checked( $auto_display, '1' ); ?>>
                                        <span><?php esc_html_e( 'FAQ automatisch tonen op productpagina\'s', 'faq-manager' ); ?></span>
                                    </label>
                                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Schakel dit uit als je de FAQ liever handmatig via een shortcode of template plaatst.', 'faq-manager' ); ?></p>
                                </td>
                            </tr>

                            <tr>
                                <th style="padding:16px 0 0;">
                                    <label for="faqm_woo_title"><?php esc_html_e( 'Sectietitel', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:16px 0 0;">
                                    <input type="text" name="woo_title" id="faqm_woo_title" value="<?php echo esc_attr( $title ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'Koptekst boven de FAQ-accordeon op de productpagina. Leeg laten om geen titel te tonen.', 'faq-manager' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h2 style="font-size:14px;font-weight:600;color:#1d2327;margin:24px 0 0;padding-top:20px;border-top:1px solid #dcdcde;"><?php esc_html_e( 'WooCommerce categoriepagina', 'faq-manager' ); ?></h2>

                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th style="padding:12px 0 0;width:220px;">
                                    <label for="faqm_cat_title"><?php esc_html_e( 'Sectietitel', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <input type="text" name="cat_title" id="faqm_cat_title" value="<?php echo esc_attr( $cat_title ); ?>" class="regular-text">
                                    <p class="description"><?php esc_html_e( 'Koptekst boven de FAQ-accordeon op de categoriepagina. Leeg laten om geen titel te tonen.', 'faq-manager' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h2 style="font-size:14px;font-weight:600;color:#1d2327;margin:24px 0 0;padding-top:20px;border-top:1px solid #dcdcde;"><?php esc_html_e( 'Weergave / framework', 'faq-manager' ); ?></h2>

                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th style="padding:12px 0 0;width:220px;">
                                    <label for="faqm_framework"><?php esc_html_e( 'Accordeon-modus', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <select name="framework" id="faqm_framework">
                                        <option value="bs5" <?php selected( $framework, 'bs5' ); ?>><?php esc_html_e( 'Bootstrap 5 (standaard)', 'faq-manager' ); ?></option>
                                        <option value="standalone" <?php selected( $framework, 'standalone' ); ?>><?php esc_html_e( 'Standalone (geen Bootstrap nodig)', 'faq-manager' ); ?></option>
                                    </select>
                                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Kies "Standalone" op sites zonder Bootstrap 5 (bijv. Bootstrap 3). De accordeon werkt dan zonder Bootstrap- of Font Awesome-afhankelijkheid. Per shortcode te overschrijven met framework="standalone".', 'faq-manager' ); ?></p>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0 0;width:220px;">
                                    <label for="faqm_grouping"><?php esc_html_e( 'Groepering overzicht', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <select name="grouping" id="faqm_grouping">
                                        <option value="letter" <?php selected( $grouping, 'letter' ); ?>><?php esc_html_e( 'Per beginletter (standaard)', 'faq-manager' ); ?></option>
                                        <option value="category" <?php selected( $grouping, 'category' ); ?>><?php esc_html_e( 'Per categorie', 'faq-manager' ); ?></option>
                                    </select>
                                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Bepaalt hoe vragen op de overzichtspagina worden gegroepeerd. Bij "Per categorie" worden vragen ingedeeld op hun FAQ-categorie; vragen zonder categorie vallen onder "Algemeen". Per shortcode te overschrijven met group="category".', 'faq-manager' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <h2 style="font-size:14px;font-weight:600;color:#1d2327;margin:24px 0 0;padding-top:20px;border-top:1px solid #dcdcde;"><?php esc_html_e( 'FAQ\'s koppelen aan posttypes', 'faq-manager' ); ?></h2>
                        <p class="description" style="margin:6px 0 0;"><?php esc_html_e( 'Vink de posttypes aan waarbij je FAQ-vragen wilt kunnen koppelen. Op het bewerkscherm van die items verschijnt dan een keuzelijst met FAQ-vragen. (WooCommerce-producten worden apart geregeld hierboven.)', 'faq-manager' ); ?></p>

                        <table class="form-table" style="margin-top:0;">
                            <tr>
                                <th style="padding:12px 0 0;width:220px;vertical-align:top;">
                                    <?php esc_html_e( 'Posttypes', 'faq-manager' ); ?>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <?php
                                    $selectable = class_exists( 'FAQM_PostTypes' ) ? FAQM_PostTypes::selectable_post_types() : [];
                                    if ( empty( $selectable ) ) {
                                        echo '<p class="description">' . esc_html__( 'Geen geschikte posttypes gevonden.', 'faq-manager' ) . '</p>';
                                    } else {
                                        foreach ( $selectable as $pt ) {
                                            printf(
                                                '<label style="display:block;margin-bottom:4px;"><input type="checkbox" name="cpt_enabled[]" value="%s" %s> %s <code style="opacity:.6;">%s</code></label>',
                                                esc_attr( $pt->name ),
                                                checked( in_array( $pt->name, $cpt_enabled, true ), true, false ),
                                                esc_html( $pt->labels->singular_name ),
                                                esc_html( $pt->name )
                                            );
                                        }
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0 0;">
                                    <label for="faqm_cpt_title"><?php esc_html_e( 'Titel boven de FAQ\'s', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <input type="text" name="cpt_faq_title" id="faqm_cpt_title" class="regular-text" value="<?php echo esc_attr( $cpt_faq_title ); ?>">
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0 0;">
                                    <label for="faqm_cpt_answer"><?php esc_html_e( 'Welk antwoord tonen', 'faq-manager' ); ?></label>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <select name="cpt_faq_answer" id="faqm_cpt_answer">
                                        <option value="short" <?php selected( $cpt_faq_answer, 'short' ); ?>><?php esc_html_e( 'Kort antwoord', 'faq-manager' ); ?></option>
                                        <option value="long" <?php selected( $cpt_faq_answer, 'long' ); ?>><?php esc_html_e( 'Lang antwoord', 'faq-manager' ); ?></option>
                                    </select>
                                </td>
                            </tr>
                            <tr>
                                <th style="padding:12px 0 0;">
                                    <?php esc_html_e( 'Automatisch tonen', 'faq-manager' ); ?>
                                </th>
                                <td style="padding:12px 0 0;">
                                    <label>
                                        <input type="checkbox" name="cpt_auto_display" value="1" <?php checked( $cpt_auto, '1' ); ?>>
                                        <?php esc_html_e( 'FAQ\'s automatisch onder de inhoud tonen', 'faq-manager' ); ?>
                                    </label>
                                    <p class="description" style="margin-top:6px;"><?php esc_html_e( 'Uit? Plaats de FAQ\'s dan zelf in de template met faqm_render_faqs( get_the_ID() ).', 'faq-manager' ); ?></p>
                                </td>
                            </tr>
                        </table>

                        <p style="margin-top:20px;">
                            <?php submit_button( __( 'Instellingen opslaan', 'faq-manager' ), 'primary', 'submit', false ); ?>
                        </p>
                    </form>
                </div>

                <!-- Shortcode documentatie -->
                <div>
                    <div class="card" style="padding:20px;margin-bottom:16px;">
                        <h2 style="margin-top:0;"><?php esc_html_e( 'Beschikbare shortcodes', 'faq-manager' ); ?></h2>

                        <h3 style="font-size:14px;margin-bottom:4px;">[faq_overview]</h3>
                        <p style="margin-top:0;color:#646970;font-size:13px;"><?php esc_html_e( 'Toont alle FAQ-vragen op een pagina, alfabetisch gegroepeerd met zoekbalk en letternavigatie.', 'faq-manager' ); ?></p>
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <thead>
                                <tr style="border-bottom:1px solid #dcdcde;">
                                    <th style="text-align:left;padding:6px 8px;color:#646970;font-weight:500;">Parameter</th>
                                    <th style="text-align:left;padding:6px 8px;color:#646970;font-weight:500;">Waarden</th>
                                    <th style="text-align:left;padding:6px 8px;color:#646970;font-weight:500;">Standaard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:6px 8px;"><code>show_search</code></td>
                                    <td style="padding:6px 8px;"><code>yes</code> / <code>no</code></td>
                                    <td style="padding:6px 8px;"><code>yes</code></td>
                                </tr>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:6px 8px;"><code>framework</code></td>
                                    <td style="padding:6px 8px;"><code>bs5</code> / <code>standalone</code></td>
                                    <td style="padding:6px 8px;"><em><?php esc_html_e( 'instelling', 'faq-manager' ); ?></em></td>
                                </tr>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:6px 8px;"><code>group</code></td>
                                    <td style="padding:6px 8px;"><code>letter</code> / <code>category</code></td>
                                    <td style="padding:6px 8px;"><em><?php esc_html_e( 'instelling', 'faq-manager' ); ?></em></td>
                                </tr>
                                <tr style="border-bottom:1px solid #f0f0f0;">
                                    <td style="padding:6px 8px;"><code>category</code></td>
                                    <td style="padding:6px 8px;"><?php esc_html_e( 'slug of id', 'faq-manager' ); ?></td>
                                    <td style="padding:6px 8px;">—</td>
                                </tr>
                            </tbody>
                        </table>

                        <div style="margin-top:12px;">
                            <p style="margin:4px 0;font-size:13px;"><strong><?php esc_html_e( 'Voorbeelden:', 'faq-manager' ); ?></strong></p>
                            <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;margin-top:4px;">[faq_overview]</code>
                            <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;margin-top:4px;">[faq_overview show_search="no"]</code>
                        </div>
                    </div>

                    <div class="card" style="padding:20px;margin-bottom:16px;">
                        <h2 style="margin-top:0;"><?php esc_html_e( 'Categoriepagina shortcode', 'faq-manager' ); ?></h2>

                        <h3 style="font-size:14px;margin-bottom:4px;">[faq_category]</h3>
                        <p style="margin-top:0;color:#646970;font-size:13px;"><?php esc_html_e( 'Toont de FAQ-vragen die aan de huidige categorie zijn gekoppeld. Werkt automatisch op categoriepagina\'s.', 'faq-manager' ); ?></p>
                        <table style="width:100%;font-size:13px;border-collapse:collapse;">
                            <thead>
                                <tr style="border-bottom:1px solid #dcdcde;">
                                    <th style="text-align:left;padding:6px 8px;color:#646970;font-weight:500;">Parameter</th>
                                    <th style="text-align:left;padding:6px 8px;color:#646970;font-weight:500;">Omschrijving</th>
                                    <th style="text-align:left;padding:6px 8px;color:#646970;font-weight:500;">Standaard</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td style="padding:6px 8px;"><code>id</code></td>
                                    <td style="padding:6px 8px;">Categorie-ID (optioneel, auto-detect op categoriepagina)</td>
                                    <td style="padding:6px 8px;"><em>auto</em></td>
                                </tr>
                            </tbody>
                        </table>
                        <div style="margin-top:12px;">
                            <p style="margin:4px 0;font-size:13px;"><strong><?php esc_html_e( 'Voorbeelden:', 'faq-manager' ); ?></strong></p>
                            <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;margin-top:4px;">[faq_category]</code>
                            <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;margin-top:4px;">[faq_category id="12"]</code>
                        </div>
                    </div>

                    <div class="card" style="padding:20px;margin-bottom:16px;">
                        <h3 style="margin-top:0;font-size:14px;"><?php esc_html_e( 'Handmatig tonen op productpagina', 'faq-manager' ); ?></h3>
                        <p style="color:#646970;font-size:13px;"><?php esc_html_e( 'Zet automatisch tonen uit en gebruik deze code in je thema-template:', 'faq-manager' ); ?></p>
                        <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;font-size:12px;white-space:pre-wrap;">&lt;?php
if ( function_exists( 'faqm_render_product_faqs' ) ) {
    faqm_render_product_faqs( get_the_ID() );
}
?&gt;</code>
                    </div>

                    <div class="card" style="padding:20px;">
                        <h3 style="margin-top:0;font-size:14px;">PHP / do_shortcode</h3>
                        <p style="color:#646970;font-size:13px;"><?php esc_html_e( 'Gebruik in PHP-templates:', 'faq-manager' ); ?></p>
                        <code style="display:block;padding:8px;background:#f6f7f7;border-radius:4px;font-size:12px;white-space:pre-wrap;">&lt;?php echo do_shortcode( '[faq_overview show_search="no"]' ); ?&gt;</code>
                    </div>
                </div>

            </div>
        </div>
        <?php
    }
}
