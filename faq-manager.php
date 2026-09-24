<?php
/**
 * Plugin Name: Kracht Internet Marketing - FAQ Manager
 * Plugin URI:  https://www.krachtinternetmarketing.nl
 * Description: Beheer FAQ-vragen met lange én korte antwoorden. Koppel vragen aan WooCommerce-producten. Volledig voorzien van Schema.org FAQPage markup voor Google en AI.
 * Version:     3.13.0
 * Author:      Kracht Internet Marketing
 * Author URI:  https://www.krachtinternetmarketing.nl
 * Text Domain: faq-manager
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP:      8.0
 * Update URI:  https://github.com/krachtinternetmarketing/kim-faq-manager
 */

defined( 'ABSPATH' ) || exit;
/**
 * Changelog
 *
 * 3.13.0 - 2026
 * - Update-checker: "Opnieuw controleren" (force-check) en WP-CLI slaan nu de
 *   eigen cache over en halen direct een verse release op — geen wachten of
 *   handmatig transient legen meer nodig.
 * - Cache-duur van de release-check verlaagd van 6 uur naar 1 uur.
 * - Per verzoek hooguit één GitHub-call (in-request memo).
 *
 * 3.12.0 - 2026
 * - Nieuw: automatische updates via GitHub-releases. WordPress meldt een
 *   update onder Plugins → Updates zodra er een nieuwere release is; met
 *   één klik bijwerken op elke site. Geen externe library nodig.
 * - Repo: github.com/krachtinternetmarketing/kim-faq-manager (publiek).
 *   Voor een private repo: definieer FAQM_GITHUB_TOKEN in wp-config.php.
 *
 * 3.11.0 - 2026
 * - Nieuw: [faq_overview] kan filteren op FAQ-categorie via category="slug"
 *   (slug of id, komma-gescheiden). Toont alleen vragen uit die categorie(en).
 *   Handig om op een WooCommerce-categoriepagina alleen de relevante vragen
 *   te tonen i.p.v. alle. Zonder treffers wordt niets getoond.
 *
 * 3.10.0 - 2026
 * - Nieuw: FAQ's koppelen aan willekeurige posttypes (bijv. een CPT
 *   'trainingen'). Instelling "FAQ's koppelen aan posttypes" waarmee je
 *   posttypes aanvinkt; daar verschijnt dan een FAQ-keuzelijst-metabox.
 * - Gekoppelde FAQ's worden automatisch onder de inhoud getoond (optioneel)
 *   of handmatig via de helper faqm_render_faqs( get_the_ID() ).
 * - Weergave hergebruikt de [faq_overview]-shortcode: Bootstrap 5 én
 *   standalone en Schema.org-markup komen automatisch mee.
 * - WooCommerce-producten blijven ongewijzigd via de eigen koppeling lopen.
 *
 * 3.9.0 - 2026
 * - Nieuw: FAQ-vragen kunnen worden ingedeeld in categorieën via de
 *   taxonomie 'faq_category' (categorie-metabox op het bewerkscherm).
 * - Overzichtspagina kan groeperen per categorie i.p.v. per beginletter,
 *   via instelling "Groepering overzicht" of shortcode group="category".
 * - Vragen zonder categorie vallen automatisch onder "Algemeen" (bovenaan).
 * - Categorie-kolom toegevoegd in het admin-overzicht van FAQ-vragen.
 * - Werkt in zowel de Bootstrap 5- als de standalone-weergave.
 *
 * 3.8.0 - 2026
 * - Nieuw: standalone accordeon-modus voor sites zonder Bootstrap 5
 *   (bijv. Bootstrap 3). Werkt zonder Bootstrap- of Font Awesome-afhankelijkheid.
 * - Instelling "Accordeon-modus" (Bootstrap 5 / Standalone) toegevoegd
 * - Shortcode [faq_overview] uitgebreid met framework="bs5|standalone"
 * - Eigen toggle + CSS-icoon (faqm-acc__*) in frontend.js / frontend.css
 * - Bootstrap 5-output blijft de standaard en is ongewijzigd
 *
 * 3.7.2 - 2026
 * - CSS: accordion-body kleurvariabele gecorrigeerd van --bs-black naar --black
 *   (consistent met de thema-variabelen uit 3.6.0)
 *
 * 3.7.0 - 2026
 * - Shortcode [faq_overview] uitgebreid met answer="short"|"long" parameter
 * - faqm_render_selected() uitgebreid met $answer parameter
 *
 * 3.6.0 - 2026
 * - CSS: fallback-waarden toegevoegd aan alle var() kleurvariabelen
 *   zodat de plugin werkt zonder thema-variabelen (--grey, --white, --black, --orange)
 *
 * 3.5.0 - 2026
 * - ACF Flexible Content integratie: relationship-veld voor FAQ-selectie per module
 * - ACF: toggle voor zoekbalk per module (toon_zoekbalk)
 * - Nieuw: faqm_render_selected() template-helperfunctie
 * - Shortcode [faq_overview] uitgebreid met ids="1,2,3" parameter
 *
 * 3.2.0 - 2026
 * - Categoriepagina-ondersteuning via class-faq-category.php
 * - Shortcode [faq_category] voor handmatige plaatsing op categoriepagina's
 * - Metaveld op WooCommerce categorie-bewerkscherm (checklist + zoekbalk)
 * - Instellingen uitgebreid met sectietitel voor categoriepagina
 *
 * 3.1.0 - 2026
 * - Instellingenpagina toegevoegd (automatisch tonen, sectietitel)
 * - Shortcode [faq_overview show_search="no"] verbergt nu ook lettergroepen
 * - WooCommerce wrapper voorzien van klasse faqm-overview voor CSS-consistentie
 * - CSS: alle px-waarden omgezet naar rem (basis 20px = 1rem)
 * - CSS: Bootstrap-variabelen (--bs-*) gebruikt in plaats van hardcoded kleuren
 *
 * 3.0.0 - 2026
 * - Bootstrap 5 accordeon-output voor zowel shortcode als WooCommerce productpagina
 * - Icoon gewijzigd naar fa-plus met rotatie-animatie via CSS
 * - Fix: DOMDocument UTF-8 encoding correctie (ë werd Ã«)
 * - Fix: lege Quill-paragrafen (<p><br></p>) worden gefilterd bij opslaan
 * - Fix: dubbele class-definitie en dubbele init()-aanroepen verwijderd
 * - Automatisch tonen WooCommerce productpagina aan/uit instelbaar
 * - Plugin hernoemd naar: Kracht Internet Marketing - FAQ Manager
 */


define( 'FAQM_VERSION',   '3.13.0' );
define( 'FAQM_PATH',      plugin_dir_path( __FILE__ ) );
define( 'FAQM_URL',       plugin_dir_url( __FILE__ ) );
define( 'FAQM_POST_TYPE', 'faq_item' );

require_once FAQM_PATH . 'includes/class-faq-post-type.php';
require_once FAQM_PATH . 'includes/class-faq-meta.php';
require_once FAQM_PATH . 'includes/class-faq-taxonomy.php';
require_once FAQM_PATH . 'includes/class-faq-accordion.php';
require_once FAQM_PATH . 'includes/class-faq-shortcode.php';
require_once FAQM_PATH . 'includes/class-faq-schema.php';
require_once FAQM_PATH . 'includes/class-faq-import.php';
require_once FAQM_PATH . 'includes/class-faq-settings.php';
require_once FAQM_PATH . 'includes/class-faq-posttypes.php';
require_once FAQM_PATH . 'includes/class-faq-updater.php';
require_once FAQM_PATH . 'includes/class-faq-category.php';

if ( class_exists( 'WooCommerce' ) || in_array(
    'woocommerce/woocommerce.php',
    apply_filters( 'active_plugins', get_option( 'active_plugins' ) )
) ) {
    require_once FAQM_PATH . 'includes/class-faq-woocommerce.php';
}

// Boot
add_action( 'plugins_loaded', function () {
    FAQM_Post_Type::init();
    FAQM_Meta::init();
    FAQM_Taxonomy::init();
    FAQM_Shortcode::init();
    FAQM_Schema::init();
    FAQM_Import::init();
    FAQM_Settings::init();
    FAQM_PostTypes::init();
    FAQM_Updater::init();

    if ( class_exists( 'FAQM_WooCommerce' ) ) {
        FAQM_WooCommerce::init();
        FAQM_Category::init();
    }
} );


/**
 * Template helper: render geselecteerde FAQ-vragen vanuit ACF flexible content.
 *
 * Gebruik in je thema-template:
 *
 *   <?php
 *   $selected = get_sub_field('faq_selectie'); // array van WP_Post objecten
 *   $show_search = get_sub_field('toon_zoekbalk'); // true/false
 *   faqm_render_selected( $selected, $show_search );
 *   ?>
 *
 * @param array $posts   Array van WP_Post objecten of post-IDs
 * @param bool  $show_search Zoekbalk en letternavigatie tonen
 */
function faqm_render_selected( array $posts = [], bool $show_search = false, string $answer = 'long' ): void {
    if ( empty( $posts ) ) {
        // Geen selectie: toon alle FAQ's
        echo do_shortcode( '[faq_overview show_search="' . ( $show_search ? 'yes' : 'no' ) . '" answer="' . esc_attr( $answer ) . '"]' );
        return;
    }

    // Bouw ids-string op uit WP_Post objecten of integers
    $ids = implode( ',', array_map( function( $p ) {
        return is_object( $p ) ? $p->ID : intval( $p );
    }, $posts ) );

    echo do_shortcode( '[faq_overview ids="' . esc_attr( $ids ) . '" show_search="' . ( $show_search ? 'yes' : 'no' ) . '" answer="' . esc_attr( $answer ) . '"]' );
}

/**
 * Template helper: render FAQ voor een specifiek product.
 * Gebruik in je thema als automatisch tonen uitstaat:
 *
 *   <?php faqm_render_product_faqs( get_the_ID() ); ?>
 */
function faqm_render_product_faqs( int $product_id = 0 ): void {
    if ( ! $product_id ) {
        $product_id = get_the_ID();
    }
    if ( class_exists( 'FAQM_WooCommerce' ) ) {
        global $post;
        $original = $post;
        $post     = get_post( $product_id );
        setup_postdata( $post );
        FAQM_WooCommerce::render_product_faqs();
        $post = $original;
        wp_reset_postdata();
    }
}

/**
 * Template helper: render de gekoppelde FAQ's van een post (voor posttypes
 * die je onder Instellingen hebt aangevinkt). Gebruik in je thema als
 * "automatisch tonen" uitstaat:
 *
 *   <?php faqm_render_faqs( get_the_ID() ); ?>
 */
function faqm_render_faqs( int $post_id = 0 ): void {
    if ( ! $post_id ) {
        $post_id = get_the_ID();
    }
    if ( $post_id && class_exists( 'FAQM_PostTypes' ) ) {
        echo FAQM_PostTypes::render_faqs( $post_id );
    }
}

// Verwijder conflicterende scripts van andere plugins op FAQ-schermen
add_action( 'admin_enqueue_scripts', function () {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== FAQM_POST_TYPE ) {
        return;
    }
    global $wp_scripts;
    if ( ! isset( $wp_scripts->registered ) ) {
        return;
    }
    foreach ( $wp_scripts->registered as $handle => $script ) {
        if ( strpos( $script->src, 'ai-generator' ) !== false ) {
            wp_dequeue_script( $handle );
            wp_deregister_script( $handle );
        }
    }
}, 999 );

// Admin assets
add_action( 'admin_enqueue_scripts', function () {
    $screen = get_current_screen();

    $on_faq     = ( $screen && $screen->post_type === FAQM_POST_TYPE );
    $on_product = ( $screen && $screen->post_type === 'product' );
    $on_cpt     = ( $screen && class_exists( 'FAQM_PostTypes' )
                    && in_array( $screen->post_type, FAQM_PostTypes::enabled_types(), true ) );

    if ( $on_faq || $on_product || $on_cpt ) {
        wp_enqueue_style(
            'faqm-admin',
            FAQM_URL . 'assets/css/admin.css',
            [],
            FAQM_VERSION
        );
        wp_enqueue_script(
            'faqm-admin',
            FAQM_URL . 'assets/js/admin.js',
            [ 'jquery' ],
            FAQM_VERSION,
            true
        );
        wp_localize_script( 'faqm-admin', 'faqmData', [
            'nonce' => wp_create_nonce( 'faqm_nonce' ),
        ] );
    }
} );

// Voorbeeld CSV download
add_action( 'admin_post_faqm_download_example', [ 'FAQM_Import', 'download_example_csv' ] );

// Frontend assets altijd laden (shortcode-detectie werkt niet betrouwbaar in ACF-blokken)
add_action( 'wp_enqueue_scripts', function () {
    wp_enqueue_style( 'faqm-frontend', FAQM_URL . 'assets/css/frontend.css', [], FAQM_VERSION );
    wp_enqueue_script( 'faqm-frontend', FAQM_URL . 'assets/js/frontend.js', [], FAQM_VERSION, true );
} );

// Verwijder target="_blank" en rel van links in FAQ-antwoorden
// Gebruikt DOMDocument met expliciete UTF-8 encoding om tekencorruptie te voorkomen
add_filter( 'faqm_answer_output', function ( string $html ): string {
    if ( empty( $html ) || strpos( $html, 'target' ) === false ) {
        return $html;
    }

    $doc = new DOMDocument();
    libxml_use_internal_errors( true );
    $doc->loadHTML( '<?xml encoding="UTF-8"><div>' . $html . '</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
    libxml_clear_errors();

    // Verwijder de tijdelijke xml PI-node
    foreach ( $doc->childNodes as $node ) {
        if ( $node->nodeType === XML_PI_NODE ) {
            $doc->removeChild( $node );
            break;
        }
    }

    foreach ( $doc->getElementsByTagName( 'a' ) as $a ) {
        $a->removeAttribute( 'target' );
        $a->removeAttribute( 'rel' );
    }

    $inner = $doc->saveHTML( $doc->getElementsByTagName( 'div' )->item( 0 ) );
    return preg_replace( '/^<div>|<\/div>$/', '', $inner );
} );

// Activation / deactivation hooks
register_activation_hook( __FILE__, function () {
    FAQM_Post_Type::register();
    flush_rewrite_rules();
} );

register_deactivation_hook( __FILE__, function () {
    flush_rewrite_rules();
} );
