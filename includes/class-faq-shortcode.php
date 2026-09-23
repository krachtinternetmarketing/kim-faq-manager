<?php
defined( 'ABSPATH' ) || exit;

class FAQM_Shortcode {

    public static function init(): void {
        add_shortcode( 'faq_overview', [ __CLASS__, 'render' ] );
        add_shortcode( 'faq_manager',  [ __CLASS__, 'render' ] );
    }

    public static function render( array $atts ): string {
        $atts = shortcode_atts( [
            'show_search' => 'yes',
            'ids'         => '',     // Komma-gescheiden lijst van FAQ-IDs (optioneel)
            'answer'      => 'long', // 'long' of 'short'
            'framework'   => '',     // '' = volg instelling, 'bs5' of 'standalone'
            'group'       => '',     // '' = volg instelling, 'letter' of 'category'
            'category'    => '',     // Filter op faq_category (slug of id, komma-gescheiden)
        ], $atts, 'faq_overview' );

        // Als ids zijn opgegeven: toon alleen die FAQ's in de opgegeven volgorde
        if ( ! empty( $atts['ids'] ) ) {
            $ids  = array_filter( array_map( 'intval', explode( ',', $atts['ids'] ) ) );
            $faqs = [];
            foreach ( $ids as $id ) {
                $post = get_post( $id );
                if ( $post && $post->post_type === FAQM_POST_TYPE && $post->post_status === 'publish' ) {
                    $faqs[] = $post;
                }
            }
        } else {
            $args = [
                'post_type'   => FAQM_POST_TYPE,
                'post_status' => 'publish',
                'numberposts' => -1,
                'orderby'     => 'title',
                'order'       => 'ASC',
            ];

            // Optioneel filteren op FAQ-categorie (slug of id, komma-gescheiden)
            if ( ! empty( $atts['category'] ) ) {
                $terms   = array_filter( array_map( 'trim', explode( ',', $atts['category'] ) ) );
                $slugs   = array_filter( $terms, fn( $t ) => ! ctype_digit( $t ) );
                $ids     = array_filter( array_map( 'intval', array_filter( $terms, 'ctype_digit' ) ) );
                $tax_query = [];
                if ( $slugs ) {
                    $tax_query[] = [ 'taxonomy' => FAQM_Taxonomy::TAXONOMY, 'field' => 'slug', 'terms' => array_values( $slugs ) ];
                }
                if ( $ids ) {
                    $tax_query[] = [ 'taxonomy' => FAQM_Taxonomy::TAXONOMY, 'field' => 'term_id', 'terms' => array_values( $ids ) ];
                }
                if ( $tax_query ) {
                    if ( count( $tax_query ) > 1 ) {
                        $tax_query['relation'] = 'OR';
                    }
                    $args['tax_query'] = $tax_query;
                }
            }

            $faqs = get_posts( $args );
        }

        if ( empty( $faqs ) ) {
            // Bij een categorie-filter zonder treffers: niets tonen (geen melding).
            if ( ! empty( $atts['category'] ) ) {
                return '';
            }
            return '<p class="faqm-no-results">' . esc_html__( 'Nog geen FAQ-vragen beschikbaar.', 'faq-manager' ) . '</p>';
        }

        $group_mode = FAQM_Settings::grouping( $atts['group'] );

        // Groepeer op categorie of op beginletter
        if ( $group_mode === 'category' ) {
            $grouped = FAQM_Taxonomy::group_by_category( $faqs );
        } else {
            $grouped = [];
            foreach ( $faqs as $faq ) {
                $letter               = strtoupper( mb_substr( $faq->post_title, 0, 1 ) );
                $grouped[ $letter ][] = $faq;
            }
            ksort( $grouped );
        }

        $unique_id    = 'accordion-' . substr( md5( uniqid() ), 0, 16 );
        $show_search  = ( $atts['show_search'] === 'yes' );
        $schema_items = [];

        // Standalone-modus: render zonder Bootstrap-afhankelijkheid en stop hier.
        // De Bootstrap 5-output hieronder blijft de standaard en ongewijzigd.
        if ( FAQM_Settings::framework( $atts['framework'] ) === 'standalone' ) {
            return FAQM_Accordion::render_overview( $grouped, $show_search, $unique_id, $atts['answer'], $group_mode );
        }

        ob_start();

        // Zoekbalk
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
        <?php endif;

        // Navigatie (alleen samen met zoekbalk)
        if ( $show_search && $group_mode === 'category' ) : ?>
        <nav class="faqm-letter-nav faqm-cat-nav mb-4" aria-label="<?php esc_attr_e( 'Categorie-navigatie', 'faq-manager' ); ?>">
            <?php foreach ( array_keys( $grouped ) as $cat ) : ?>
                <a href="#faqm-cat-<?php echo esc_attr( sanitize_title( $cat ) ); ?>" class="faqm-letter-link faqm-cat-link"><?php echo esc_html( $cat ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php elseif ( $show_search ) : ?>
        <nav class="faqm-letter-nav mb-4" aria-label="<?php esc_attr_e( 'Alfabetische navigatie', 'faq-manager' ); ?>">
            <?php foreach ( array_keys( $grouped ) as $letter ) : ?>
                <a href="#faqm-letter-<?php echo esc_attr( $letter ); ?>" class="faqm-letter-link"><?php echo esc_html( $letter ); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php endif; ?>

        <div class="faqm-overview" itemscope itemtype="https://schema.org/FAQPage">

        <?php if ( $group_mode === 'category' ) : ?>

            <?php foreach ( $grouped as $cat => $items ) :
                $cat_anchor   = 'faqm-cat-' . sanitize_title( $cat );
                $accordion_id = esc_attr( $unique_id . '-' . sanitize_title( $cat ) );
            ?>
                <section class="faqm-letter-group faqm-cat-group mb-4" id="<?php echo esc_attr( $cat_anchor ); ?>">
                    <h2 class="faqm-letter-heading faqm-cat-heading"><?php echo esc_html( $cat ); ?></h2>
                    <div class="accordion" id="<?php echo $accordion_id; ?>">
                    <?php foreach ( $items as $faq ) :
                        $meta_key    = ( $atts['answer'] === 'short' ) ? '_faqm_short_answer' : '_faqm_long_answer';
                        $long_answer = apply_filters( 'faqm_answer_output', get_post_meta( $faq->ID, $meta_key, true ) );
                        $slug        = sanitize_title( $faq->post_title );
                        $heading_id  = 'heading-' . $slug;
                        $collapse_id = 'collapse-' . $slug;
                        $schema_items[] = [
                            '@type' => 'Question',
                            'name'  => $faq->post_title,
                            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $long_answer ) ],
                        ];
                    ?>
                        <div class="accordion-item faqm-accordion-item"
                             data-question="<?php echo esc_attr( strtolower( $faq->post_title ) ); ?>"
                             itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">
                            <div class="accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?php echo esc_attr( $collapse_id ); ?>"
                                        aria-expanded="false"
                                        aria-controls="<?php echo esc_attr( $collapse_id ); ?>">
                                    <span class="accordion-button__title" itemprop="name"><?php echo esc_html( $faq->post_title ); ?></span>
                                    <span class="accordion-button__arrow ms-auto transition"><i class="fa-sharp fa-regular fa-plus"></i></span>
                                </button>
                            </div>
                            <div id="<?php echo esc_attr( $collapse_id ); ?>"
                                 class="accordion-collapse collapse"
                                 aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
                                 data-bs-parent="#<?php echo $accordion_id; ?>">
                                <div class="accordion-body" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                                    <div itemprop="text"><?php echo wp_kses_post( $long_answer ); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div><!-- .accordion -->
                </section>
            <?php endforeach; ?>

        <?php elseif ( $show_search ) : ?>

            <?php foreach ( $grouped as $letter => $items ) :
                $accordion_id = esc_attr( $unique_id . '-' . $letter );
            ?>
                <section class="faqm-letter-group mb-4" id="faqm-letter-<?php echo esc_attr( $letter ); ?>">
                    <h2 class="faqm-letter-heading"><?php echo esc_html( $letter ); ?></h2>
                    <div class="accordion" id="<?php echo $accordion_id; ?>">
                    <?php foreach ( $items as $faq ) :
                        $meta_key    = ( $atts['answer'] === 'short' ) ? '_faqm_short_answer' : '_faqm_long_answer';
                    $long_answer = apply_filters( 'faqm_answer_output', get_post_meta( $faq->ID, $meta_key, true ) );
                        $slug        = sanitize_title( $faq->post_title );
                        $heading_id  = 'heading-' . $slug;
                        $collapse_id = 'collapse-' . $slug;
                        $schema_items[] = [
                            '@type' => 'Question',
                            'name'  => $faq->post_title,
                            'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $long_answer ) ],
                        ];
                    ?>
                        <div class="accordion-item faqm-accordion-item"
                             data-question="<?php echo esc_attr( strtolower( $faq->post_title ) ); ?>"
                             itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">
                            <div class="accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
                                <button class="accordion-button collapsed" type="button"
                                        data-bs-toggle="collapse"
                                        data-bs-target="#<?php echo esc_attr( $collapse_id ); ?>"
                                        aria-expanded="false"
                                        aria-controls="<?php echo esc_attr( $collapse_id ); ?>">
                                    <span class="accordion-button__title" itemprop="name"><?php echo esc_html( $faq->post_title ); ?></span>
                                    <span class="accordion-button__arrow ms-auto transition"><i class="fa-sharp fa-regular fa-plus"></i></span>
                                </button>
                            </div>
                            <div id="<?php echo esc_attr( $collapse_id ); ?>"
                                 class="accordion-collapse collapse"
                                 aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
                                 data-bs-parent="#<?php echo $accordion_id; ?>">
                                <div class="accordion-body" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                                    <div itemprop="text"><?php echo wp_kses_post( $long_answer ); ?></div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    </div><!-- .accordion -->
                </section>
            <?php endforeach; ?>

        <?php else : ?>

            <?php // Geen zoekbalk: één enkele accordeon zonder lettergroepen
                $accordion_id = esc_attr( $unique_id );
            ?>
            <div class="accordion" id="<?php echo $accordion_id; ?>">
            <?php foreach ( $grouped as $letter => $items ) :
                foreach ( $items as $faq ) :
                    $meta_key    = ( $atts['answer'] === 'short' ) ? '_faqm_short_answer' : '_faqm_long_answer';
                    $long_answer = apply_filters( 'faqm_answer_output', get_post_meta( $faq->ID, $meta_key, true ) );
                    $slug        = sanitize_title( $faq->post_title );
                    $heading_id  = 'heading-' . $slug;
                    $collapse_id = 'collapse-' . $slug;
                    $schema_items[] = [
                        '@type' => 'Question',
                        'name'  => $faq->post_title,
                        'acceptedAnswer' => [ '@type' => 'Answer', 'text' => wp_strip_all_tags( $long_answer ) ],
                    ];
            ?>
                    <div class="accordion-item faqm-accordion-item"
                         data-question="<?php echo esc_attr( strtolower( $faq->post_title ) ); ?>"
                         itemprop="mainEntity" itemscope itemtype="https://schema.org/Question">
                        <div class="accordion-header" id="<?php echo esc_attr( $heading_id ); ?>">
                            <button class="accordion-button collapsed" type="button"
                                    data-bs-toggle="collapse"
                                    data-bs-target="#<?php echo esc_attr( $collapse_id ); ?>"
                                    aria-expanded="false"
                                    aria-controls="<?php echo esc_attr( $collapse_id ); ?>">
                                <span class="accordion-button__title" itemprop="name"><?php echo esc_html( $faq->post_title ); ?></span>
                                <span class="accordion-button__arrow ms-auto transition"><i class="fa-sharp fa-regular fa-plus"></i></span>
                            </button>
                        </div>
                        <div id="<?php echo esc_attr( $collapse_id ); ?>"
                             class="accordion-collapse collapse"
                             aria-labelledby="<?php echo esc_attr( $heading_id ); ?>"
                             data-bs-parent="#<?php echo $accordion_id; ?>">
                            <div class="accordion-body" itemprop="acceptedAnswer" itemscope itemtype="https://schema.org/Answer">
                                <div itemprop="text"><?php echo wp_kses_post( $long_answer ); ?></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endforeach; ?>
            </div><!-- .accordion -->

        <?php endif; ?>

        </div><!-- .faqm-overview -->

        <?php
        // JSON-LD schema
        $schema = [
            '@context'   => 'https://schema.org',
            '@type'      => 'FAQPage',
            'mainEntity' => $schema_items,
        ];
        echo '<script type="application/ld+json">' . wp_json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT ) . '</script>';

        return ob_get_clean();
    }
}
