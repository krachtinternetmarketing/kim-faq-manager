<?php
defined( 'ABSPATH' ) || exit;

/**
 * FAQM_PostTypes
 *
 * Generieke FAQ-koppeling voor willekeurige posttypes (bijv. een CPT
 * 'trainingen'). Voor elk in de instellingen aangevinkt posttype verschijnt
 * op het bewerkscherm een metabox waarin FAQ-vragen aangevinkt kunnen worden.
 * De selectie wordt opgeslagen in dezelfde meta-key als de WooCommerce-
 * koppeling (_faqm_linked_faqs) en op de frontend getoond.
 *
 * De weergave hergebruikt de [faq_overview]-shortcode, zodat automatisch
 * zowel de Bootstrap 5- als de standalone-modus en de Schema.org-markup
 * worden meegenomen. WooCommerce-producten blijven via FAQM_WooCommerce lopen
 * en staan hier bewust buiten.
 */
class FAQM_PostTypes {

    const META_KEY = '_faqm_linked_faqs';

    public static function init(): void {
        add_action( 'add_meta_boxes', [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post',      [ __CLASS__, 'save' ] );
        add_filter( 'the_content',    [ __CLASS__, 'maybe_append_content' ], 20 );
    }

    /**
     * Posttypes die in de instellingen gekozen kunnen worden:
     * alle met een bewerk-UI, behalve bijlagen, producten (WooCommerce
     * regelt die apart) en het FAQ-posttype zelf.
     *
     * @return WP_Post_Type[]
     */
    public static function selectable_post_types(): array {
        // Alleen publieke, zelf-aangemaakte posttypes (geen ingebouwde zoals
        // bericht/pagina, en geen interne types van WordPress/ACF/WooCommerce).
        $types   = get_post_types( [ 'public' => true, '_builtin' => false ], 'objects' );
        $exclude = [ 'product', FAQM_POST_TYPE ];
        $types   = array_filter( $types, fn( $t ) => ! in_array( $t->name, $exclude, true ) );

        // Zo nodig aan te passen via een filter.
        return apply_filters( 'faqm_selectable_post_types', $types );
    }

    /** Aangevinkte posttypes uit de instellingen. @return string[] */
    public static function enabled_types(): array {
        $val = FAQM_Settings::get( 'cpt_enabled', [] );
        return is_array( $val ) ? array_values( array_filter( array_map( 'strval', $val ) ) ) : [];
    }

    public static function add_meta_boxes(): void {
        foreach ( self::enabled_types() as $type ) {
            add_meta_box(
                'faqm_cpt_faqs',
                __( 'Gekoppelde FAQ-vragen', 'faq-manager' ),
                [ __CLASS__, 'render_meta_box' ],
                $type,
                'normal',
                'default'
            );
        }
    }

    public static function render_meta_box( WP_Post $post ): void {
        $linked = array_map( 'intval', (array) get_post_meta( $post->ID, self::META_KEY, true ) );

        $faqs = get_posts( [
            'post_type'   => FAQM_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ] );

        wp_nonce_field( 'faqm_save_cpt_meta', 'faqm_cpt_nonce' );

        if ( empty( $faqs ) ) {
            echo '<p>' . esc_html__( 'Er zijn nog geen FAQ-vragen aangemaakt. Maak eerst FAQ-items aan via het FAQ Manager menu.', 'faq-manager' ) . '</p>';
            return;
        }

        echo '<div class="faqm-product-faqs">';
        echo '<p class="faqm-help">' . esc_html__( 'Kies welke FAQ-vragen bij dit item horen.', 'faq-manager' ) . '</p>';
        echo '<input type="search" id="faqm-cpt-search" class="faqm-faq-search" placeholder="' . esc_attr__( 'Zoek een FAQ-vraag...', 'faq-manager' ) . '" autocomplete="off">';
        echo '<ul class="faqm-faq-checklist" id="faqm-cpt-checklist">';

        foreach ( $faqs as $faq ) {
            printf(
                '<li class="faqm-faq-item">
                    <label>
                        <input type="checkbox" name="faqm_linked_faqs[]" value="%d" %s>
                        <span>%s</span>
                    </label>
                </li>',
                $faq->ID,
                checked( in_array( $faq->ID, $linked, true ), true, false ),
                esc_html( $faq->post_title )
            );
        }

        echo '</ul></div>';
        echo '<script>
(function() {
    var input = document.getElementById("faqm-cpt-search");
    if (!input) return;
    input.addEventListener("input", function() {
        var q = this.value.toLowerCase().trim();
        document.querySelectorAll("#faqm-cpt-checklist li.faqm-faq-item").forEach(function(li) {
            li.style.display = (!q || li.textContent.toLowerCase().indexOf(q) !== -1) ? "" : "none";
        });
    });
})();
</script>';
    }

    public static function save( int $post_id ): void {
        if ( ! isset( $_POST['faqm_cpt_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faqm_cpt_nonce'] ) ), 'faqm_save_cpt_meta' )
        ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }
        if ( ! in_array( get_post_type( $post_id ), self::enabled_types(), true ) ) {
            return;
        }

        if ( ! empty( $_POST['faqm_linked_faqs'] ) ) {
            update_post_meta( $post_id, self::META_KEY, array_map( 'intval', (array) $_POST['faqm_linked_faqs'] ) );
        } else {
            delete_post_meta( $post_id, self::META_KEY );
        }
    }

    /**
     * Voeg de gekoppelde FAQ's automatisch onder de content toe
     * (als "automatisch tonen" aan staat).
     */
    public static function maybe_append_content( string $content ): string {
        if ( FAQM_Settings::get( 'cpt_auto_display', '1' ) !== '1' ) {
            return $content;
        }
        $types = self::enabled_types();
        if ( empty( $types ) || ! is_singular( $types ) || ! in_the_loop() || ! is_main_query() ) {
            return $content;
        }
        return $content . self::render_faqs( (int) get_the_ID() );
    }

    /**
     * Render de gekoppelde FAQ's van een post. Bruikbaar in templates via
     * de helper faqm_render_faqs(). Geeft lege string terug zonder selectie.
     */
    public static function render_faqs( int $post_id ): string {
        $linked = array_filter( array_map( 'intval', (array) get_post_meta( $post_id, self::META_KEY, true ) ) );
        if ( empty( $linked ) ) {
            return '';
        }

        $title  = FAQM_Settings::get( 'cpt_faq_title', __( 'Veelgestelde vragen', 'faq-manager' ) );
        $answer = FAQM_Settings::get( 'cpt_faq_answer', 'short' ) === 'long' ? 'long' : 'short';

        $out = '<div class="faqm-cpt-faq-section">';
        if ( $title ) {
            $out .= '<h3 class="faqm-product-faq-title">' . esc_html( $title ) . '</h3>';
        }
        // Platte accordeon (group="letter" + show_search="no"), ongeacht de
        // globale groepering-instelling. Shortcode regelt BS5/standalone + schema.
        $out .= do_shortcode( sprintf(
            '[faq_overview ids="%s" show_search="no" group="letter" answer="%s"]',
            esc_attr( implode( ',', $linked ) ),
            $answer
        ) );
        $out .= '</div>';

        return $out;
    }
}
