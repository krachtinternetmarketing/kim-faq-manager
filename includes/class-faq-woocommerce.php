<?php
defined( 'ABSPATH' ) || exit;

class FAQM_WooCommerce {

    public static function init(): void {
        add_action( 'add_meta_boxes',     [ __CLASS__, 'add_product_meta_box' ] );
        add_action( 'save_post_product',  [ __CLASS__, 'save_product_meta' ], 10, 2 );

        // Automatisch tonen alleen als instelling aan staat (altijd na de tabbladen)
        if ( FAQM_Settings::get( 'woo_auto_display', '1' ) === '1' ) {
            add_action( 'woocommerce_after_single_product_summary', [ __CLASS__, 'render_product_faqs' ], 20 );
        }
    }

    public static function add_product_meta_box(): void {
        add_meta_box(
            'faqm_product_faqs',
            __( 'Gekoppelde FAQ-vragen', 'faq-manager' ),
            [ __CLASS__, 'render_product_meta_box' ],
            'product',
            'normal',
            'default'
        );
    }

    public static function render_product_meta_box( WP_Post $post ): void {
        $linked = get_post_meta( $post->ID, '_faqm_linked_faqs', true );
        $linked = is_array( $linked ) ? $linked : [];

        $faqs = get_posts( [
            'post_type'   => FAQM_POST_TYPE,
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby'     => 'title',
            'order'       => 'ASC',
        ] );

        wp_nonce_field( 'faqm_save_product_meta', 'faqm_product_nonce' );

        if ( empty( $faqs ) ) {
            echo '<p>' . esc_html__( 'Er zijn nog geen FAQ-vragen aangemaakt. Maak eerst FAQ-items aan via het FAQ Manager menu.', 'faq-manager' ) . '</p>';
            return;
        }

        echo '<div class="faqm-product-faqs">';
        echo '<p class="faqm-help">' . esc_html__( 'Kies welke FAQ-vragen bij dit product horen. Het korte antwoord wordt getoond op de productpagina.', 'faq-manager' ) . '</p>';
        echo '<input type="search" id="faqm-faq-search" class="faqm-faq-search" placeholder="' . esc_attr__( 'Zoek een FAQ-vraag...', 'faq-manager' ) . '" autocomplete="off">';
        echo '<ul class="faqm-faq-checklist" id="faqm-faq-checklist">';

        foreach ( $faqs as $faq ) {
            $checked = in_array( $faq->ID, array_map( 'intval', $linked ) ) ? 'checked' : '';
            printf(
                '<li class="faqm-faq-item">
                    <label>
                        <input type="checkbox" name="faqm_linked_faqs[]" value="%d" %s>
                        <span>%s</span>
                    </label>
                </li>',
                $faq->ID,
                $checked,
                esc_html( $faq->post_title )
            );
        }

        echo '</ul></div>';
        echo '<script>
(function() {
    var input = document.getElementById("faqm-faq-search");
    if (!input) return;
    input.addEventListener("input", function() {
        var q = this.value.toLowerCase().trim();
        var items = document.querySelectorAll("#faqm-faq-checklist li.faqm-faq-item");
        var found = 0;
        items.forEach(function(li) {
            var text = li.textContent.toLowerCase();
            if (!q || text.indexOf(q) !== -1) { li.style.display = ""; found++; }
            else { li.style.display = "none"; }
        });
        var msg = document.getElementById("faqm-no-results-msg");
        if (!msg) {
            msg = document.createElement("li");
            msg.id = "faqm-no-results-msg";
            msg.style.cssText = "padding:10px 12px;font-size:13px;color:#787c82;font-style:italic;";
            msg.textContent = "Geen vragen gevonden.";
            document.getElementById("faqm-faq-checklist").appendChild(msg);
        }
        msg.style.display = (found === 0 && q) ? "" : "none";
    });
})();
</script>';
    }

    public static function save_product_meta( int $post_id ): void {
        if ( ! isset( $_POST['faqm_product_nonce'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faqm_product_nonce'] ) ), 'faqm_save_product_meta' )
        ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['faqm_linked_faqs'] ) ) {
            $ids = array_map( 'intval', (array) $_POST['faqm_linked_faqs'] );
            update_post_meta( $post_id, '_faqm_linked_faqs', $ids );
        } else {
            delete_post_meta( $post_id, '_faqm_linked_faqs' );
        }
    }

    /**
     * Render gekoppelde FAQ-vragen op de WooCommerce productpagina.
     * Bootstrap 5 accordeon-formaat met Schema.org FAQPage JSON-LD.
     */
    public static function render_product_faqs(): void {
        global $post;

        if ( ! $post ) {
            return;
        }

        $linked = get_post_meta( $post->ID, '_faqm_linked_faqs', true );
        if ( empty( $linked ) || ! is_array( $linked ) ) {
            return;
        }

        $faqs = [];
        foreach ( $linked as $faq_id ) {
            $faq = get_post( intval( $faq_id ) );
            if ( $faq && $faq->post_status === 'publish' ) {
                $faqs[] = $faq;
            }
        }

        if ( empty( $faqs ) ) {
            return;
        }

        // Sorteer alfabetisch op vraagtitel
        usort( $faqs, fn( $a, $b ) => strcmp( $a->post_title, $b->post_title ) );

        $accordion_id = 'accordion-faq-' . $post->ID;
        $schema_items = [];

        $faq_title = FAQM_Settings::get( 'woo_title', __( 'Veelgestelde vragen over dit product', 'faq-manager' ) );

        $mode = FAQM_Settings::framework();

        echo '<div class="faqm-product-faq-section faqm-overview">';

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
            $heading_id   = 'heading-' . $slug . '-' . $post->ID;
            $collapse_id  = 'collapse-' . $slug . '-' . $post->ID;

            $schema_items[] = [
                '@type' => 'Question',
                'name'  => $faq->post_title,
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

        echo '</div>'; // .faqm-product-faq-section
    }
}
