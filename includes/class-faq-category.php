<?php
defined( 'ABSPATH' ) || exit;

/**
 * FAQM_Category
 *
 * Koppelt FAQ-vragen aan WooCommerce productcategorieën.
 * - Toont een checklist op het categorie-bewerkscherm
 * - Toont gekoppelde FAQ's automatisch op de categoriepagina (indien ingeschakeld)
 * - Biedt de shortcode [faq_category] voor handmatige plaatsing
 */
class FAQM_Category {

    public static function init(): void {
        // Metavelden op categorie-bewerkscherm
        add_action( 'product_cat_add_form_fields',  [ __CLASS__, 'render_add_form' ] );
        add_action( 'product_cat_edit_form_fields', [ __CLASS__, 'render_edit_form' ] );
        add_action( 'created_product_cat',          [ __CLASS__, 'save_meta' ] );
        add_action( 'edited_product_cat',           [ __CLASS__, 'save_meta' ] );

        // Shortcode
        add_shortcode( 'faq_category', [ __CLASS__, 'shortcode' ] );
    }

    // -------------------------------------------------------------------------
    // Categorie-bewerkscherm: nieuw formulier (tabblad "Categorie toevoegen")
    // -------------------------------------------------------------------------
    public static function render_add_form(): void {
        $faqs = self::get_all_faqs();
        if ( empty( $faqs ) ) return;
        ?>
        <div class="form-field">
            <label><?php esc_html_e( 'Gekoppelde FAQ-vragen', 'faq-manager' ); ?></label>
            <?php self::render_checklist( [], $faqs ); ?>
            <p class="description"><?php esc_html_e( 'Kies welke FAQ-vragen bij deze categorie horen.', 'faq-manager' ); ?></p>
        </div>
        <?php
        wp_nonce_field( 'faqm_save_cat_meta', 'faqm_cat_nonce' );
    }

    // -------------------------------------------------------------------------
    // Categorie-bewerkscherm: bestaande categorie
    // -------------------------------------------------------------------------
    public static function render_edit_form( WP_Term $term ): void {
        $faqs   = self::get_all_faqs();
        $linked = self::get_linked( $term->term_id );
        if ( empty( $faqs ) ) return;
        ?>
        <tr class="form-field">
            <th scope="row">
                <label><?php esc_html_e( 'Gekoppelde FAQ-vragen', 'faq-manager' ); ?></label>
            </th>
            <td>
                <?php self::render_checklist( $linked, $faqs ); ?>
                <p class="description"><?php esc_html_e( 'Kies welke FAQ-vragen bij deze categorie horen. Het korte antwoord wordt getoond op de categoriepagina.', 'faq-manager' ); ?></p>
            </td>
        </tr>
        <?php
        wp_nonce_field( 'faqm_save_cat_meta', 'faqm_cat_nonce' );
    }

    // -------------------------------------------------------------------------
    // Gedeelde checklist HTML
    // -------------------------------------------------------------------------
    private static function render_checklist( array $linked, array $faqs ): void {
        ?>
        <input type="search"
               class="faqm-faq-search"
               id="faqm-cat-faq-search"
               placeholder="<?php esc_attr_e( 'Zoek een FAQ-vraag...', 'faq-manager' ); ?>"
               autocomplete="off"
               style="width:100%;margin-bottom:6px;">
        <ul class="faqm-faq-checklist" id="faqm-cat-faq-checklist"
            style="max-height:220px;overflow-y:auto;border:1px solid #ddd;padding:8px 12px;margin:0;list-style:none;background:#fff;">
            <?php foreach ( $faqs as $faq ) :
                $checked = in_array( $faq->ID, array_map( 'intval', $linked ) ); ?>
                <li class="faqm-faq-item" style="padding:4px 0;">
                    <label style="display:flex;align-items:center;gap:8px;cursor:pointer;">
                        <input type="checkbox"
                               name="faqm_cat_linked_faqs[]"
                               value="<?php echo esc_attr( $faq->ID ); ?>"
                               <?php checked( $checked ); ?>>
                        <span><?php echo esc_html( $faq->post_title ); ?></span>
                    </label>
                </li>
            <?php endforeach; ?>
        </ul>
        <script>
        (function() {
            var input = document.getElementById('faqm-cat-faq-search');
            if (!input) return;
            input.addEventListener('input', function() {
                var q = this.value.toLowerCase().trim();
                document.querySelectorAll('#faqm-cat-faq-checklist .faqm-faq-item').forEach(function(li) {
                    li.style.display = (!q || li.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
                });
            });
        })();
        </script>
        <?php
    }

    // -------------------------------------------------------------------------
    // Opslaan
    // -------------------------------------------------------------------------
    public static function save_meta( int $term_id ): void {
        if ( ! isset( $_POST['faqm_cat_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faqm_cat_nonce'] ) ), 'faqm_save_cat_meta' )
        ) {
            return;
        }

        if ( isset( $_POST['faqm_cat_linked_faqs'] ) ) {
            $ids = array_map( 'intval', (array) $_POST['faqm_cat_linked_faqs'] );
            update_term_meta( $term_id, '_faqm_linked_faqs', $ids );
        } else {
            delete_term_meta( $term_id, '_faqm_linked_faqs' );
        }
    }

    // -------------------------------------------------------------------------
    // Shortcode: [faq_category] of [faq_category id="12"]
    // -------------------------------------------------------------------------
    public static function shortcode( array $atts ): string {
        $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'faq_category' );

        $term_id = intval( $atts['id'] );

        // Geen id opgegeven? Probeer de huidige categoriepagina
        if ( ! $term_id && is_product_category() ) {
            $term = get_queried_object();
            if ( $term instanceof WP_Term ) {
                $term_id = $term->term_id;
            }
        }

        if ( ! $term_id ) {
            return '';
        }

        ob_start();
        self::render( $term_id );
        return ob_get_clean();
    }

    // -------------------------------------------------------------------------
    // Gedeelde render-functie
    // -------------------------------------------------------------------------
    public static function render( int $term_id ): void {
        $linked = self::get_linked( $term_id );
        if ( empty( $linked ) ) {
            return;
        }

        $faqs = [];
        foreach ( $linked as $faq_id ) {
            $faq = get_post( $faq_id );
            if ( $faq && $faq->post_status === 'publish' ) {
                $faqs[] = $faq;
            }
        }

        if ( empty( $faqs ) ) {
            return;
        }

        usort( $faqs, fn( $a, $b ) => strcmp( $a->post_title, $b->post_title ) );

        $accordion_id = 'accordion-cat-faq-' . $term_id;
        $schema_items = [];

        $faq_title = FAQM_Settings::get( 'cat_title', __( 'Veelgestelde vragen over deze categorie', 'faq-manager' ) );

        $mode = FAQM_Settings::framework();

        echo '<div class="faqm-category-faq-section faqm-overview">';

        if ( $faq_title ) {
            echo '<h3 class="faqm-product-faq-title">' . esc_html( $faq_title ) . '</h3>';
        }

        if ( $mode === 'standalone' ) {
            echo FAQM_Accordion::standalone_open( $accordion_id, true );
        } else {
            echo '<div class="accordion" id="' . esc_attr( $accordion_id ) . '" itemscope itemtype="https://schema.org/FAQPage">';
        }

        foreach ( $faqs as $faq ) {
            $short_answer = apply_filters( 'faqm_answer_output', get_post_meta( $faq->ID, '_faqm_short_answer', true ) );
            $slug         = sanitize_title( $faq->post_title );
            $heading_id   = 'heading-cat-' . $slug . '-' . $term_id;
            $collapse_id  = 'collapse-cat-' . $slug . '-' . $term_id;

            $schema_items[] = [
                '@type'          => 'Question',
                'name'           => $faq->post_title,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $short_answer ),
                ],
            ];

            if ( $mode === 'standalone' ) {
                echo FAQM_Accordion::standalone_item( [
                    'title'       => $faq->post_title,
                    'answer'      => $short_answer,
                    'heading_id'  => $heading_id,
                    'collapse_id' => $collapse_id,
                ] );
                continue;
            }

            printf(
                '<div class="accordion-item" itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">
                    <div class="accordion-header" id="%s">
                        <button class="accordion-button collapsed" type="button"
                                data-bs-toggle="collapse"
                                data-bs-target="#%s"
                                aria-expanded="false"
                                aria-controls="%s">
                            <span class="accordion-button__title" itemprop="name">%s</span>
                            <span class="accordion-button__arrow ms-auto transition"><i class="fa-sharp fa-regular fa-plus"></i></span>
                        </button>
                    </div>
                    <div id="%s" class="accordion-collapse collapse"
                         aria-labelledby="%s"
                         data-bs-parent="#%s">
                        <div class="accordion-body" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                            <div itemprop="text">%s</div>
                        </div>
                    </div>
                </div>',
                esc_attr( $heading_id ),
                esc_attr( $collapse_id ),
                esc_attr( $collapse_id ),
                esc_html( $faq->post_title ),
                esc_attr( $collapse_id ),
                esc_attr( $heading_id ),
                esc_attr( $accordion_id ),
                wp_kses_post( $short_answer )
            );
        }

        echo $mode === 'standalone' ? FAQM_Accordion::standalone_close() : '</div>'; // .accordion / .faqm-acc

        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema_items,
        ];
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>';

        echo '</div>'; // .faqm-category-faq-section
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------
    private static function get_all_faqs(): array {
        return get_posts( [
            'post_type'   => FAQM_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ] );
    }

    private static function get_linked( int $term_id ): array {
        $linked = get_term_meta( $term_id, '_faqm_linked_faqs', true );
        return is_array( $linked ) ? $linked : [];
    }
}
