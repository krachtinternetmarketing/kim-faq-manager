<?php
defined( 'ABSPATH' ) || exit;

class FAQM_Meta {

    public static function init(): void {
        add_filter( 'use_block_editor_for_post_type', [ __CLASS__, 'disable_gutenberg' ], 10, 2 );
        add_action( 'add_meta_boxes',              [ __CLASS__, 'add_meta_boxes' ] );
        add_action( 'save_post_' . FAQM_POST_TYPE, [ __CLASS__, 'save' ], 10, 2 );
        add_action( 'admin_enqueue_scripts',       [ __CLASS__, 'enqueue_assets' ] );
        add_action( 'admin_footer-post.php',       [ __CLASS__, 'footer_script' ] );
        add_action( 'admin_footer-post-new.php',   [ __CLASS__, 'footer_script' ] );
    }

    public static function disable_gutenberg( bool $use, string $post_type ): bool {
        return ( $post_type === FAQM_POST_TYPE ) ? false : $use;
    }

    public static function enqueue_assets(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== FAQM_POST_TYPE ) return;

        // Quill editor van cdnjs
        wp_enqueue_style(
            'quill-snow',
            'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.snow.min.css',
            [],
            '1.3.7'
        );
        wp_enqueue_script(
            'quill',
            'https://cdnjs.cloudflare.com/ajax/libs/quill/1.3.7/quill.min.js',
            [],
            '1.3.7',
            true
        );
    }

    public static function add_meta_boxes(): void {
        add_meta_box(
            'faqm_long_answer',
            __( 'Lang antwoord (voor de FAQ-pagina)', 'faq-manager' ),
            [ __CLASS__, 'render_long_answer' ],
            FAQM_POST_TYPE, 'normal', 'high'
        );
        add_meta_box(
            'faqm_short_answer',
            __( 'Kort antwoord', 'faq-manager' ),
            [ __CLASS__, 'render_short_answer' ],
            FAQM_POST_TYPE, 'normal', 'high'
        );
    }

    private static function render_editor( string $id, string $value, int $height = 250 ): void {
        ?>
        <div class="faqm-quill-wrap" data-height="<?php echo esc_attr( $height ); ?>">
            <div class="faqm-quill-tabs">
                <button type="button" class="faqm-tab active" data-tab="visual">Visueel</button>
                <button type="button" class="faqm-tab" data-tab="code">Broncode</button>
            </div>
            <div class="faqm-tab-panel" data-panel="visual">
                <div id="faqm-editor-<?php echo esc_attr( $id ); ?>" style="height:<?php echo esc_attr( $height ); ?>px;"></div>
            </div>
            <div class="faqm-tab-panel" data-panel="code" style="display:none;">
                <textarea class="faqm-code-area" style="width:100%;height:<?php echo esc_attr( $height ); ?>px;font-family:monospace;font-size:13px;padding:10px;box-sizing:border-box;border:1px solid #ddd;"></textarea>
            </div>
            <textarea
                id="<?php echo esc_attr( $id ); ?>"
                name="<?php echo esc_attr( $id ); ?>"
                class="faqm-hidden-input"
                style="display:none;"
            ><?php echo esc_textarea( $value ); ?></textarea>
        </div>
        <?php
    }

    public static function render_long_answer( WP_Post $post ): void {
        wp_nonce_field( 'faqm_save_meta', 'faqm_nonce_meta' );
        $value = get_post_meta( $post->ID, '_faqm_long_answer', true );
        echo '<p class="faqm-help">' . esc_html__( 'Uitgebreid antwoord met volledige opmaak. Dit verschijnt op de FAQ-overzichtspagina.', 'faq-manager' ) . '</p>';
        self::render_editor( 'faqm_long_answer', $value, 300 );
    }

    public static function render_short_answer( WP_Post $post ): void {
        $value = get_post_meta( $post->ID, '_faqm_short_answer', true );
        echo '<p class="faqm-help">' . esc_html__( 'Beknopte versie van het antwoord. Geschikt voor gebruik in overzichten, productpagina\'s en modules.', 'faq-manager' ) . '</p>';
        self::render_editor( 'faqm_short_answer', $value, 180 );
    }

    public static function footer_script(): void {
        $screen = get_current_screen();
        if ( ! $screen || $screen->post_type !== FAQM_POST_TYPE ) return;
        ?>
        <script type="text/javascript">
        (function() {
            'use strict';

            // Wacht tot Quill geladen is
            function faqmInitEditors() {
                if (typeof Quill === 'undefined') {
                    setTimeout(faqmInitEditors, 100);
                    return;
                }

                var toolbarLong = [
                    [{ 'header': [2, 3, 4, false] }],
                    ['bold', 'italic'],
                    ['link'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['clean']
                ];

                var toolbarShort = [
                    ['bold', 'italic'],
                    ['link'],
                    [{ 'list': 'ordered' }, { 'list': 'bullet' }],
                    ['clean']
                ];

                document.querySelectorAll('.faqm-quill-wrap').forEach(function(wrap) {
                    var height    = parseInt(wrap.dataset.height) || 250;
                    var editorDiv = wrap.querySelector('[id^="faqm-editor-"]');
                    var hiddenInput = wrap.querySelector('.faqm-hidden-input');
                    var codeArea  = wrap.querySelector('.faqm-code-area');
                    var tabs      = wrap.querySelectorAll('.faqm-tab');
                    var panels    = wrap.querySelectorAll('.faqm-tab-panel');

                    if (!editorDiv || !hiddenInput) return;

                    var isLong = editorDiv.id === 'faqm-editor-faqm_long_answer';
                    var toolbar = isLong ? toolbarLong : toolbarShort;

                    // Quill initialiseren
                    var quill = new Quill(editorDiv, {
                        theme: 'snow',
                        modules: { toolbar: toolbar },
                        placeholder: isLong ? 'Schrijf hier het uitgebreide antwoord…' : 'Schrijf hier het korte antwoord…'
                    });

                    // Bestaande HTML laden
                    var existing = hiddenInput.value.trim();
                    if (existing) {
                        quill.clipboard.dangerouslyPasteHTML(existing);
                    }

                    // Sync Quill → hidden input bij wijziging
                    // Niet-ASCII tekens als HTML-entiteiten coderen zodat
                    // de POST-verwerking geen encodingproblemen veroorzaakt
                    function faqmEncodeHtml(html) {
                        return html.replace(/[^\x00-\x7F]/g, function(ch) {
                            return '&#' + ch.codePointAt(0) + ';';
                        });
                    }
                    quill.on('text-change', function() {
                        hiddenInput.value = faqmEncodeHtml(quill.root.innerHTML);
                    });

                    // Tab wisselen: Visueel ↔ Broncode
                    tabs.forEach(function(tab) {
                        tab.addEventListener('click', function() {
                            var target = this.dataset.tab;

                            tabs.forEach(function(t) { t.classList.remove('active'); });
                            this.classList.add('active');

                            panels.forEach(function(panel) {
                                if (panel.dataset.panel === target) {
                                    panel.style.display = '';
                                    if (target === 'code') {
                                        // Visueel → Broncode: toon huidige HTML (gedecodeerd voor leesbaarheid)
                                        codeArea.value = quill.root.innerHTML;
                                    }
                                } else {
                                    panel.style.display = 'none';
                                    if (target === 'visual' && panel.dataset.panel === 'code') {
                                        // Broncode → Visueel: parse HTML terug
                                        quill.clipboard.dangerouslyPasteHTML(codeArea.value);
                                        hiddenInput.value = codeArea.value;
                                    }
                                }
                            });
                        });
                    });

                    // Broncode textarea: sync naar hidden input bij typen
                    codeArea.addEventListener('input', function() {
                        hiddenInput.value = faqmEncodeHtml(this.value);
                    });
                });
            }

            // Sync vóór opslaan
            function faqmSync() {
                document.querySelectorAll('.faqm-code-area').forEach(function(area) {
                    // Als broncode-tab actief is, zorg dat hidden input up-to-date is
                    var wrap = area.closest('.faqm-quill-wrap');
                    if (wrap) {
                        var activeTab = wrap.querySelector('.faqm-tab.active');
                        if (activeTab && activeTab.dataset.tab === 'code') {
                            var hiddenInput = wrap.querySelector('.faqm-hidden-input');
                            if (hiddenInput) hiddenInput.value = area.value;
                        }
                    }
                });
            }

            var form = document.getElementById('post');
            if (form) form.addEventListener('submit', faqmSync, true);
            ['publish', 'save-post'].forEach(function(id) {
                var btn = document.getElementById(id);
                if (btn) btn.addEventListener('click', faqmSync, true);
            });

            window.addEventListener('load', faqmInitEditors);
        })();
        </script>
        <?php
    }

    public static function save( int $post_id, WP_Post $post ): void {
        if ( ! isset( $_POST['faqm_nonce_meta'] )
            || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['faqm_nonce_meta'] ) ), 'faqm_save_meta' )
        ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
        if ( ! current_user_can( 'edit_post', $post_id ) ) return;

        foreach ( [ 'faqm_long_answer' => '_faqm_long_answer', 'faqm_short_answer' => '_faqm_short_answer' ] as $key => $meta ) {
            if ( ! isset( $_POST[ $key ] ) ) {
                update_post_meta( $post_id, $meta, '' );
                continue;
            }

            $value = wp_unslash( $_POST[ $key ] );

            // Decodeer HTML-entiteiten (&#235; → ë) die de JS-encoder heeft aangemaakt
            // Dit voorkomt UTF-8 corruptie bij POST-verwerking
            $value = html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' );

            // Verwijder lege Quill-paragrafen (<p><br></p>) die automatisch worden toegevoegd
            $value = preg_replace( '/<p>\s*<br\s*\/?>\s*<\/p>/i', '', $value );

            // Verwijder overbodige witruimte en meerdere opeenvolgende lege regels
            $value = preg_replace( '/(\s*<p>\s*<\/p>\s*)+/i', '', $value );
            $value = trim( $value );

            $value = wp_kses_post( $value );
            update_post_meta( $post_id, $meta, $value );
        }
    }
}
