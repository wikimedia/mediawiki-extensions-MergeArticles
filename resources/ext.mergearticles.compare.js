( function( mw, $ ) {
	$( function() {
		var data = mw.config.get( 'maCompareData' );
		var compare = new mergeArticles.panel.Compare( {
			originID: data.originID,
			targetID: data.targetID,
			diffData: data.diffData || {},
			fileData: data.fileData || {},
			$element: $( '#merge-articles-compare' )
		} );
	} );
} ) ( mediaWiki, jQuery );
