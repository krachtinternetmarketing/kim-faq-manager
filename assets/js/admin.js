/* FAQ Manager — Admin JS */
( function ( $ ) {
    'use strict';

    // ---------------------------------------------------------------
    // TinyMCE sync-fix
    // WordPress synchroniseert TinyMCE-inhoud niet automatisch naar
    // het onderliggende textarea voor het formulier wordt verstuurd.
    // Zonder deze fix wordt de inhoud van de editors niet opgeslagen.
    // ---------------------------------------------------------------
    $( '#post' ).on( 'submit', function () {
        if ( typeof tinymce !== 'undefined' ) {
            tinymce.triggerSave();
        }
    } );

    // Zelfde fix voor de "Publiceren/Bijwerken"-knop (die soms een
    // eigen submit-event triggert buiten het standaard form-submit om).
    $( '#publish, #save-post' ).on( 'click', function () {
        if ( typeof tinymce !== 'undefined' ) {
            tinymce.triggerSave();
        }
    } );

    // ---------------------------------------------------------------
    // Checkbox-items klikbaar maken (visueel)
    // ---------------------------------------------------------------
    $( document ).on( 'click', '.faqm-checkbox-item', function () {
        const $item  = $( this );
        const $input = $item.find( 'input[type="checkbox"]' );
        const checked = ! $input.prop( 'checked' );

        $input.prop( 'checked', checked );
        $item.toggleClass( 'is-checked', checked );
    } );

    // Voorkomen dat klik op de verborgen input dubbel telt
    $( document ).on( 'click', '.faqm-checkbox-item input[type="checkbox"]', function ( e ) {
        e.stopPropagation();
    } );

    // ---- Live zoeken in FAQ-lijst op productpagina ----
    $( document ).on( 'input', '#faqm-faq-search', function () {
        var q = $( this ).val().toLowerCase().trim();
        var $list = $( '#faqm-faq-checklist' );
        var $items = $list.find( 'li.faqm-faq-item' );
        var found = 0;

        $items.each( function () {
            var text = $( this ).text().toLowerCase();
            if ( ! q || text.indexOf( q ) !== -1 ) {
                $( this ).show();
                found++;
            } else {
                $( this ).hide();
            }
        } );

        var $noResults = $( '#faqm-faq-no-results' );
        if ( ! $noResults.length ) {
            $noResults = $( '<li id="faqm-faq-no-results" class="faqm-faq-no-results">Geen vragen gevonden.</li>' );
            $list.append( $noResults );
        }
        if ( found === 0 && q ) {
            $noResults.show();
        } else {
            $noResults.hide();
        }
    } );

} )( jQuery );
