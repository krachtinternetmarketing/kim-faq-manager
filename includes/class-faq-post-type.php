<?php
defined( 'ABSPATH' ) || exit;

class FAQM_Post_Type {

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register' ] );
        add_filter( 'manage_' . FAQM_POST_TYPE . '_posts_columns',       [ __CLASS__, 'columns' ] );
        add_action( 'manage_' . FAQM_POST_TYPE . '_posts_custom_column', [ __CLASS__, 'column_content' ], 10, 2 );
    }

    public static function register(): void {
        $labels = [
            'name'               => __( 'FAQ Vragen', 'faq-manager' ),
            'singular_name'      => __( 'FAQ Vraag', 'faq-manager' ),
            'add_new'            => __( 'Nieuwe vraag', 'faq-manager' ),
            'add_new_item'       => __( 'Nieuwe FAQ-vraag toevoegen', 'faq-manager' ),
            'edit_item'          => __( 'FAQ-vraag bewerken', 'faq-manager' ),
            'new_item'           => __( 'Nieuwe FAQ-vraag', 'faq-manager' ),
            'view_item'          => __( 'Bekijk FAQ-vraag', 'faq-manager' ),
            'search_items'       => __( 'FAQ-vragen zoeken', 'faq-manager' ),
            'not_found'          => __( 'Geen FAQ-vragen gevonden', 'faq-manager' ),
            'not_found_in_trash' => __( 'Niets gevonden in de prullenbak', 'faq-manager' ),
            'menu_name'          => __( 'FAQ Manager', 'faq-manager' ),
        ];

        register_post_type( FAQM_POST_TYPE, [
            'labels'          => $labels,
            'public'          => false,
            'show_ui'         => true,
            'show_in_menu'    => true,
            'menu_icon'       => 'dashicons-editor-help',
            'menu_position'   => 25,
            'supports'        => [ 'title' ],   // Alleen titel — geen editor support nodig
            'capability_type' => 'post',
            'has_archive'     => false,
            'rewrite'         => false,
            'show_in_rest'    => false,
        ] );
    }

    public static function columns( array $columns ): array {
        return [
            'cb'                                      => $columns['cb'],
            'title'                                   => __( 'Vraag', 'faq-manager' ),
            'faqm_short'                              => __( 'Kort antwoord (preview)', 'faq-manager' ),
            'faqm_products'                           => __( 'Gekoppelde producten', 'faq-manager' ),
            'taxonomy-' . FAQM_Taxonomy::TAXONOMY     => __( 'Categorie', 'faq-manager' ),
            'date'                                    => $columns['date'],
        ];
    }

    public static function column_content( string $column, int $post_id ): void {
        if ( $column === 'faqm_short' ) {
            $short = get_post_meta( $post_id, '_faqm_short_answer', true );
            echo wp_trim_words( wp_strip_all_tags( $short ), 12, '…' );
        }
        if ( $column === 'faqm_products' ) {
            $products = get_posts( [
                'post_type'   => 'product',
                'numberposts' => -1,
                'meta_query'  => [ [ 'key' => '_faqm_linked_faqs', 'value' => 'i:' . $post_id . ';', 'compare' => 'LIKE' ] ],
            ] );
            if ( $products ) {
                echo implode( ', ', array_map( fn($p) => '<a href="' . get_edit_post_link( $p->ID ) . '">' . esc_html( $p->post_title ) . '</a>', $products ) );
            } else {
                echo '—';
            }
        }
    }
}
