( function( mw, $ ) {
	$( function() {
		var data = mw.config.get( 'maReviewData' );
		var review = new mergeArticles.panel.Review( {
			originID: data.originID,
			originContent: data.originContent,
			targetText: data.targetText,
			fileData: data.fileData || {},
			$element: $( '#merge-articles-review' )
		} );
	} );
} ) ( mediaWiki, jQuery );
