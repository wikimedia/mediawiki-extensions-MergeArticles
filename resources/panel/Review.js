( function( mw, $ ) {
	mergeArticles.panel.Review = function( cfg ) {
		cfg = cfg || {};

		this.originID = cfg.originID;
		this.originContent = cfg.originContent || '';
		this.targetText = cfg.targetText;
		this.fileData = cfg.fileData || {};
		this.isFile = $.isEmptyObject( this.fileData );

		this.$element = cfg.$element || $( '<div>' );
		this.$element.addClass( 'review' );

		this.makeContent();
		this.makeButtons();

		$( '.ma-merge-help' ).append( this.getDiscardDraftButton().$element );
	};

	OO.initClass( mergeArticles.panel.Review );

	mergeArticles.panel.Review.prototype.makeContent = function() {
		if( this.originContent === '' ) {
			this.makeNoTextContent();
		}
		this.contentInput = new OO.ui.MultilineTextInputWidget( {
			rows: 25,
			value: this.originContent
		} );
		this.$element.append( this.contentInput.$element );
	};

	mergeArticles.panel.Review.prototype.makeNoTextContent = function() {
		var label = new OO.ui.LabelWidget( {
			label: mw.message( "ma-no-content-message" ).text()
		} );
		label.$element.addClass( 'ma-review-no-content' );
		this.$element.append( label.$element );
	};

	mergeArticles.panel.Review.prototype.makeBackButton = function() {
		var button = new OO.ui.ButtonWidget( {
			framed: false,
			href: mw.config.get( 'maBaseURL' ),
			label: mw.message( 'ma-back-to-overview' ).text()
		} );
		button.$element.addClass( 'ma-back-to-overview-button' );

		this.$element.prepend( button.$element );
	};

	mergeArticles.panel.Review.prototype.makeButtons = function() {
		this.mergeButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'ma-do-merge-label' ).plain(),
			flags: [
				'primary',
				'progressive'
			]
		} );
		this.mergeButton.on( 'click', this.doMerge.bind( this ) );

		var $buttons = $( '<div>' )
			.addClass( 'ma-review-buttons' )
			.append( this.mergeButton.$element );

		this.$element.append( $buttons );
	};

	mergeArticles.panel.Review.prototype.doMerge = function() {
		this.makeApiRequest( {
			action: 'ma-merge-page-new',
			pageID: this.originID,
			target: this.targetText,
			text: this.contentInput.getValue()
		}).done( function( response ) {
			if( response.success ) {
				var msg = mw.message( 'ma-merge-success-page-label' ).text();
				var anchor = "<a href='" + response.targetPage.url + "'>" + response.targetPage.text + "</a>";
				msg = msg.replace( '$1', anchor );

				this.showActionResult(
					true,
					mw.message( 'ma-merge-success-header' ).text(),
					msg
				);
			} else {
				this.showActionResult(
					false,
					mw.message( 'ma-merge-fail-header' ).text(),
					response.error
				);
			}
		}.bind( this ) ).fail( function( error ) {
			this.showActionResult(
				false,
				mw.message( 'ma-merge-fail-header' ).text(),
				error
			);
		}.bind( this ) );
	};

	mergeArticles.panel.Review.prototype.makeApiRequest = function( params ) {
		var api = new mw.Api();
		return api.postWithToken( 'edit', params );
	}

	mergeArticles.panel.Review.prototype.showActionResult = function( success, header, text ) {
		text = text || '';

		var icon = new OO.ui.IconWidget( {
			icon: success ? 'check' : 'close'
		} );
		var headerLabel = new OO.ui.LabelWidget( {
			label: header
		} );
		var textLabel = new OO.ui.LabelWidget( {
			label: new OO.ui.HtmlSnippet( text )
		} );

		var $panel = $( '<div>' ).addClass( 'ma-action-result' )
			.append(
				$( '<div>' ).append(
					icon.$element,
					headerLabel.$element
				),
				textLabel.$element
			);

		this.$element.html( $panel );
		this.makeBackButton();
	};

	mergeArticles.panel.Review.prototype.getDiscardDraftButton = function() {
		var discardButton = new OO.ui.ButtonWidget( {
			icon: 'close',
			framed: false,
			label: mw.message( 'ma-discard-draft' ).text()
		} );
		discardButton.on( 'click', function() {
			OO.ui.confirm( mw.message( 'ma-discard-draft-help' ).text() )
				.done( function( confirmed ) {
					if( !confirmed ) {
						return;
					}
					var api = new mw.Api();
					api.postWithToken( 'edit', {
						action: 'ma-discard-draft',
						pageID: this.originID
					} ).done( function( response ) {
						this.showActionResult(
							true,
							mw.message( 'ma-draft-discard-success-header' ).text(),
							mw.message( 'ma-draft-discard-success-text', response.title ).text(),
						);
					}.bind( this ) ).fail( function( code, response ) {
						this.showActionResult(
							false,
							mw.message( 'ma-draft-discard-fail-header' ).text(),
							response.exception || response.error.info
						);
					}.bind( this ) );
				}.bind( this ) );
		}.bind( this ) );

		return discardButton;
	};
} ) ( mediaWiki, jQuery );
