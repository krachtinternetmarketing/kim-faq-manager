/* FAQ Manager — Frontend JS
   - Zoekfunctie
   - Standalone accordeon-toggle (data-faqm-toggle) voor sites zonder Bootstrap 5;
     in Bootstrap 5-modus regelt Bootstrap zelf de accordeon via data-bs-toggle
*/
( function () {
    'use strict';

    function initSearch() {
        var searchInput = document.getElementById( 'faqm-search' );
        if ( ! searchInput ) return;

        searchInput.addEventListener( 'input', function () {
            var q = this.value.trim().toLowerCase();

            document.querySelectorAll( '.faqm-accordion-item' ).forEach( function ( item ) {
                var question = ( item.dataset.question || '' ).toLowerCase();
                var match    = ! q || question.includes( q );
                item.style.display = match ? '' : 'none';
            } );

            // Verberg lettergroepen waarvan alle items verborgen zijn
            document.querySelectorAll( '.faqm-letter-group' ).forEach( function ( group ) {
                var visible = group.querySelectorAll( '.faqm-accordion-item:not([style*="display: none"])' ).length;
                group.style.display = visible === 0 && q ? 'none' : '';
            } );
        } );
    }

    document.addEventListener( 'DOMContentLoaded', function () {
        initSearch();
        initStandaloneAccordion();
    } );

    /* Standalone accordeon (modus zonder Bootstrap).
       Haakt alleen aan op knoppen met data-faqm-toggle, dus de Bootstrap 5
       accordeon (data-bs-toggle) wordt nooit geraakt. */
    function initStandaloneAccordion() {
        var buttons = document.querySelectorAll( '[data-faqm-toggle]' );
        if ( ! buttons.length ) return;

        function panelFor( btn ) {
            return document.getElementById( btn.getAttribute( 'aria-controls' ) );
        }

        function closePanel( btn ) {
            var panel = panelFor( btn );
            btn.setAttribute( 'aria-expanded', 'false' );
            btn.classList.remove( 'is-open' );
            if ( panel ) {
                panel.classList.remove( 'is-open' );
                panel.style.maxHeight = null;
            }
        }

        function openPanel( btn ) {
            var panel = panelFor( btn );
            if ( ! panel ) return;
            btn.setAttribute( 'aria-expanded', 'true' );
            btn.classList.add( 'is-open' );
            panel.classList.add( 'is-open' );
            panel.style.maxHeight = panel.scrollHeight + 'px';
        }

        buttons.forEach( function ( btn ) {
            btn.addEventListener( 'click', function () {
                var isOpen = btn.getAttribute( 'aria-expanded' ) === 'true';
                var acc    = btn.closest( '[data-faqm-acc]' );

                // Net als Bootstrap data-bs-parent: open er telkens maar één per accordeon
                if ( acc && ! isOpen ) {
                    acc.querySelectorAll( '[data-faqm-toggle][aria-expanded="true"]' ).forEach( function ( other ) {
                        if ( other !== btn ) closePanel( other );
                    } );
                }

                if ( isOpen ) {
                    closePanel( btn );
                } else {
                    openPanel( btn );
                }
            } );
        } );

        // Herbereken hoogte van open panelen bij resize (bijv. tekst die herverdeelt)
        window.addEventListener( 'resize', function () {
            document.querySelectorAll( '.faqm-acc__panel.is-open' ).forEach( function ( panel ) {
                panel.style.maxHeight = panel.scrollHeight + 'px';
            } );
        } );
    }

} )();
