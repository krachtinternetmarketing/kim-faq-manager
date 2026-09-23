<?php
defined( 'ABSPATH' ) || exit;

/**
 * FAQM_Taxonomy
 *
 * Registreert de hiërarchische taxonomie 'faq_category' voor FAQ-vragen.
 * Hiermee kunnen vragen ingedeeld worden in categorieën. Op de
 * overzichtspagina kan met group="category" per categorie gegroepeerd
 * worden; vragen zonder categorie vallen onder 'Algemeen'.
 *
 * Doordat de taxonomie met show_ui = true wordt geregistreerd, verschijnt
 * de categorie-metabox automatisch op het bewerkscherm van een FAQ-vraag
 * en komt er een kolom in de lijstweergave.
 */
class FAQM_Taxonomy {

    const TAXONOMY = 'faq_category';

    public static function init(): void {
        add_action( 'init', [ __CLASS__, 'register' ] );
    }

    public static function register(): void {
        $labels = [
            'name'              => __( 'FAQ-categorieën', 'faq-manager' ),
            'singular_name'     => __( 'FAQ-categorie', 'faq-manager' ),
            'search_items'      => __( 'Categorieën zoeken', 'faq-manager' ),
            'all_items'         => __( 'Alle categorieën', 'faq-manager' ),
            'parent_item'       => __( 'Hoofdcategorie', 'faq-manager' ),
            'parent_item_colon' => __( 'Hoofdcategorie:', 'faq-manager' ),
            'edit_item'         => __( 'Categorie bewerken', 'faq-manager' ),
            'update_item'       => __( 'Categorie bijwerken', 'faq-manager' ),
            'add_new_item'      => __( 'Nieuwe categorie toevoegen', 'faq-manager' ),
            'new_item_name'     => __( 'Naam nieuwe categorie', 'faq-manager' ),
            'menu_name'         => __( 'Categorieën', 'faq-manager' ),
        ];

        register_taxonomy( self::TAXONOMY, FAQM_POST_TYPE, [
            'labels'            => $labels,
            'hierarchical'      => true,
            'public'            => false,
            'show_ui'           => true,
            'show_in_menu'      => true,
            'show_admin_column' => true,
            'show_in_rest'      => false,
            'rewrite'           => false,
        ] );
    }

    /**
     * Geef de naam van de (primaire) categorie van een FAQ-vraag terug.
     * Bij meerdere categorieën wordt de eerste (alfabetisch) gebruikt.
     * Zonder categorie: 'Algemeen'.
     */
    public static function primary_category_name( int $post_id ): string {
        $terms = get_the_terms( $post_id, self::TAXONOMY );
        if ( $terms && ! is_wp_error( $terms ) ) {
            usort( $terms, fn( $a, $b ) => strcasecmp( $a->name, $b->name ) );
            return $terms[0]->name;
        }
        return __( 'Algemeen', 'faq-manager' );
    }

    /**
     * Groepeer een lijst FAQ-posts op categorie.
     * Categorieën alfabetisch gesorteerd, 'Algemeen' altijd bovenaan.
     *
     * @param WP_Post[] $faqs
     * @return array<string, WP_Post[]>
     */
    public static function group_by_category( array $faqs ): array {
        $algemeen = __( 'Algemeen', 'faq-manager' );
        $grouped  = [];

        foreach ( $faqs as $faq ) {
            $grouped[ self::primary_category_name( $faq->ID ) ][] = $faq;
        }

        uksort( $grouped, function ( $a, $b ) use ( $algemeen ) {
            if ( $a === $algemeen ) return -1;  // 'Algemeen' bovenaan
            if ( $b === $algemeen ) return 1;
            return strcasecmp( $a, $b );
        } );

        return $grouped;
    }
}
