( function( mw, $ ) {
	$( function() {
		var availablePages = mw.config.get( 'maAvailablePages' );
		var overview = new mergeArticles.panel.Overview( { pages: availablePages } );
		$( '#merge-articles-overview' ).append( overview.$element );
	} );
} ) ( mediaWiki, jQuery );
