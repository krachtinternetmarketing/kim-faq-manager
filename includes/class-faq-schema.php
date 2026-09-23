<?php
defined( 'ABSPATH' ) || exit;

/**
 * FAQM_Schema
 *
 * Zorgt dat de FAQPage schema-markup correct in de <head> terechtkomt
 * wanneer WordPress dit nodig heeft (bijv. bij de FAQ-pagina via shortcode
 * én op WooCommerce productpagina's).
 *
 * De WooCommerce productpagina-schema wordt al direct inline gerenderd
 * door FAQM_WooCommerce::render_product_faqs(). Deze class voegt de
 * shortcode-schema samen met wp_head via een filter, zodat ook SEO-plugins
 * (Yoast, RankMath) de data kunnen inzien/aanvullen.
 */
class FAQM_Schema {

    public static function init(): void {
        // Geen dubbele output nodig; de shortcode plaatst de JSON-LD zelf inline.
        // Wel: voeg noindex NIET toe aan de FAQ CPT single pages (zijn sowieso niet public).
        add_filter( 'wpseo_canonical', [ __CLASS__, 'prevent_cpt_canonical' ] );
    }

    /**
     * Voorkom dat Yoast SEO een canonical plaatst voor de niet-publieke CPT.
     */
    public static function prevent_cpt_canonical( $canonical ) {
        if ( is_singular( FAQM_POST_TYPE ) ) {
            return false;
        }
        return $canonical;
    }

    /**
     * Helper: bouw een FAQPage schema array op uit een array van WP_Post FAQ items.
     *
     * @param  WP_Post[] $faqs
     * @param  string    $answer_meta '_faqm_long_answer' of '_faqm_short_answer'
     * @return array     Klaar voor wp_json_encode
     */
    public static function build_schema( array $faqs, string $answer_meta = '_faqm_long_answer' ): array {
        $items = [];
        foreach ( $faqs as $faq ) {
            $answer   = get_post_meta( $faq->ID, $answer_meta, true );
            $items[]  = [
                '@type' => 'Question',
                'name'  => $faq->post_title,
                'acceptedAnswer' => [
                    '@type' => 'Answer',
                    'text'  => wp_strip_all_tags( $answer ),
                ],
            ];
        }

        return [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $items,
        ];
    }

    /**
     * Render JSON-LD script tag.
     *
     * @param array $schema
     */
    public static function render_schema( array $schema ): void {
        echo '<script type="application/ld+json">'
            . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT )
            . '</script>' . PHP_EOL;
    }
}
