( function( mw, $ ) {
	$( function() {
		var availablePages = mw.config.get( 'maAvailablePages' );
		var overview = new mergeArticles.panel.Overview( {
			pages: availablePages,
			filters: mw.config.get( 'maFilters' ),
			filterModules: mw.config.get( 'maFilterModules' )
		} );
		$( '#merge-articles-overview' ).append( overview.$element );
	} );
} ) ( mediaWiki, jQuery );
