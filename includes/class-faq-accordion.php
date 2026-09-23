<?php
defined( 'ABSPATH' ) || exit;

/**
 * FAQM_Accordion
 *
 * Framework-loze ("standalone") accordeon-markup voor sites die geen
 * Bootstrap 5 draaien (bijv. Bootstrap 3, of helemaal geen Bootstrap).
 *
 * - Gebruikt uitsluitend faqm-geprefixte klassen, zodat er geen botsing is
 *   met Bootstrap-klassen van welke versie dan ook.
 * - Heeft geen afhankelijkheid van Font Awesome: het plus/min-icoon is een
 *   CSS-pseudo-element.
 * - Open/dicht wordt geregeld door de eigen frontend.js (data-faqm-toggle).
 *
 * De bestaande Bootstrap 5-output blijft de standaard en wordt door deze
 * class niet aangeraakt.
 */
class FAQM_Accordion {

    /**
     * Open de accordeon-container.
     *
     * @param string $accordion_id Unieke id voor deze accordeon.
     * @param bool   $schema_root  Zet FAQPage-itemscope op de container
     *                             (gebruikt op product-/categoriepagina's).
     */
    public static function standalone_open( string $accordion_id, bool $schema_root = false ): string {
        $schema = $schema_root ? ' itemscope itemtype="https://schema.org/FAQPage"' : '';
        return '<div class="faqm-acc" id="' . esc_attr( $accordion_id ) . '" data-faqm-acc' . $schema . '>';
    }

    public static function standalone_close(): string {
        return '</div><!-- .faqm-acc -->';
    }

    /**
     * Render één accordeon-item.
     *
     * @param array $a {
     *     @type string $title         Vraagtitel (wordt ge-escaped).
     *     @type string $answer        Antwoord-HTML (al door faqm_answer_output gehaald).
     *     @type string $heading_id    Id voor de header.
     *     @type string $collapse_id   Id voor het paneel.
     *     @type string $data_question Optioneel: zoekwaarde (kleine letters).
     * }
     */
    public static function standalone_item( array $a ): string {
        $title       = $a['title'] ?? '';
        $answer_html = $a['answer'] ?? '';
        $heading_id  = $a['heading_id'] ?? '';
        $collapse_id = $a['collapse_id'] ?? '';
        $data_q      = ( isset( $a['data_question'] ) && $a['data_question'] !== '' )
            ? ' data-question="' . esc_attr( $a['data_question'] ) . '"'
            : '';

        return sprintf(
            '<div class="faqm-acc__item faqm-accordion-item"%1$s itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">
                <div class="faqm-acc__header" id="%2$s">
                    <button class="faqm-acc__button" type="button" data-faqm-toggle aria-expanded="false" aria-controls="%3$s">
                        <span class="faqm-acc__title" itemprop="name">%4$s</span>
                        <span class="faqm-acc__icon" aria-hidden="true"></span>
                    </button>
                </div>
                <div id="%5$s" class="faqm-acc__panel" role="region" aria-labelledby="%6$s">
                    <div class="faqm-acc__body" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                        <div itemprop="text">%7$s</div>
                    </div>
                </div>
            </div>',
            $data_q,
            esc_attr( $heading_id ),
            esc_attr( $collapse_id ),
            esc_html( $title ),
            esc_attr( $collapse_id ),
            esc_attr( $heading_id ),
            wp_kses_post( $answer_html )
        );
    }

    /**
     * Volledige overzichtspagina in standalone-modus.
     * Spiegelt de structuur van FAQM_Shortcode::render() (zoekbalk,
     * letternavigatie, lettergroepen of platte accordeon) maar dan met
     * framework-loze markup.
     *
     * @param array  $grouped     FAQ's gegroepeerd per beginletter.
     * @param bool   $show_search Zoekbalk + letternavigatie tonen.
     * @param string $unique_id   Basis-id voor de accordeons.
     * @param string $answer      'long' of 'short'.
     * @param string $group_mode  'letter' (standaard) of 'category'.
     */
    public static function render_overview( array $grouped, bool $show_search, string $unique_id, string $answer = 'long', string $group_mode = 'letter' ): string {
        $is_cat       = ( $group_mode === 'category' );
        $meta_key     = ( $answer === 'short' ) ? '_faqm_short_answer' : '_faqm_long_answer';
        $schema_items = [];

        ob_start();

        if ( $show_search ) : ?>
        <div class="faqm-search-wrap mb-4">
            <input
                type="search"
                id="faqm-search"
                class="faqm-search form-control"
                placeholder="<?php esc_attr_e( 'Zoek een vraag…', 'faq-manager' ); ?>"
                aria-label="<?php esc_attr_e( 'Zoek in FAQ', 'faq-manager' ); ?>"
            >
        </div>
        <nav class="faqm-letter-nav <?php echo $is_cat ? 'faqm-cat-nav' : ''; ?> mb-4" aria-label="<?php echo esc_attr( $is_cat ? __( 'Categorie-navigatie', 'faq-manager' ) : __( 'Alfabetische navigatie', 'faq-manager' ) ); ?>">
            <?php foreach ( array_keys( $grouped ) as $key ) :
                $anchor = $is_cat ? 'faqm-cat-' . sanitize_title( $key ) : 'faqm-letter-' . $key;
            ?>
                <a href="#<?php echo esc_attr( $anchor ); ?>" class="faqm-letter-link <?php echo $is_cat ? 'faqm-cat-link' : ''; ?>"><?php echo esc_html( $key ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div class="faqm-overview" itemscope itemtype="https://schema.org/FAQPage">

        <?php if ( $is_cat || $show_search ) : ?>

            <?php foreach ( $grouped as $key => $items ) :
                $anchor       = $is_cat ? 'faqm-cat-' . sanitize_title( $key ) : 'faqm-letter-' . $key;
                $accordion_id = $unique_id . '-' . ( $is_cat ? sanitize_title( $key ) : $key );
            ?>
                <section class="faqm-letter-group <?php echo $is_cat ? 'faqm-cat-group' : ''; ?> mb-4" id="<?php echo esc_attr( $anchor ); ?>">
                    <h2 class="faqm-letter-heading <?php echo $is_cat ? 'faqm-cat-heading' : ''; ?>"><?php echo esc_html( $key ); ?></h2>
                    <?php
                    echo self::standalone_open( $accordion_id );
                    foreach ( $items as $faq ) :
                        $ans  = apply_filters( 'faqm_answer_output', get_post_meta( $faq->ID, $meta_key, true ) );
                        $slug = sanitize_title( $faq->post_title );
                        $schema_items[] = [
                            '@type'          => 'Question',
                            'name'           => $faq->post_title,
                            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $ans ) ],
                        ];
                        echo self::standalone_item( [
                            'title'         => $faq->post_title,
                            'answer'        => $ans,
                            'heading_id'    => 'heading-' . $slug,
                            'collapse_id'   => 'collapse-' . $slug,
                            'data_question' => strtolower( $faq->post_title ),
                        ] );
                    endforeach;
                    echo self::standalone_close();
                    ?>
                </section>
            <?php endforeach; ?>

        <?php else : ?>

            <?php
            echo self::standalone_open( $unique_id );
            foreach ( $grouped as $letter => $items ) :
                foreach ( $items as $faq ) :
                    $ans  = apply_filters( 'faqm_answer_output', get_post_meta( $faq->ID, $meta_key, true ) );
                    $slug = sanitize_title( $faq->post_title );
                    $schema_items[] = [
                        '@type'          => 'Question',
                        'name'           => $faq->post_title,
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $ans ) ],
                    ];
                    echo self::standalone_item( [
                        'title'         => $faq->post_title,
                        'answer'        => $ans,
                        'heading_id'    => 'heading-' . $slug,
                        'collapse_id'   => 'collapse-' . $slug,
                        'data_question' => strtolower( $faq->post_title ),
                    ] );
                endforeach;
            endforeach;
            echo self::standalone_close();
            ?>

        <?php endif; ?>

        </div><!-- .faqm-overview -->

        <?php
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema_items,
        ];
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>';

        return ob_get_clean();
    }
}
