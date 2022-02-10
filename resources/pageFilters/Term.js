( function( mw, $ ) {
    window.mergeArticles = window.mergeArticles || {};
    window.mergeArticles.widget = window.mergeArticles.widget || {};

    mergeArticles.widget.TermFilter = function( cfg ) {
        mergeArticles.widget.TermFilter.parent.call( this, cfg );
    };

    OO.inheritClass( mergeArticles.widget.TermFilter, mergeArticles.widget.Filter );

    mergeArticles.widget.TermFilter.prototype.makeWidget = function( cfg ) {
        return new OO.ui.TextInputWidget( {
            id: cfg.id,
            icon: 'search'
        } );
    };

    mergeArticles.widget.TermFilter.prototype.filter = function( pages ) {
        if ( !pages ) {
            return pages;
        }
        var filtered = [], value = this.widget.getValue();
        for( var i = 0; i < pages.length; i++ ) {
            var page = pages[i];
            var target = page.target.text.toLowerCase();
            if( !target.includes( value ) ) {
                continue;
            }
            filtered.push( page );
        }

        return filtered;
    };
} ) ( mediaWiki, jQuery );
