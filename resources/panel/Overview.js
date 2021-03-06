( function( mw, $ ) {
	mergeArticles.panel.Overview = function mergeArticlesOverview( cfg ) {
		cfg = cfg || {};

		this.$element = $( '<div>' );
		this.$element.addClass( 'merge-articles-overview' );

		this.filters = cfg.filters || {};

		this.pages = cfg.pages || {};
		this.selectedTypes = [];
		this.currentlyDisplayed = 0;

		this.makeTypeLayout();

		this.criteriaLayout = new OO.ui.HorizontalLayout( {
			items: [
				this.typeLayout,
			].concat( this.makeFilterLayout() )
		} );
		this.criteriaLayout.$element.addClass( 'merge-articles-criterial-layout' );

		this.noPagesMessage = new OO.ui.LabelWidget( {
			label: mw.message( 'mergearticles-no-pages-available' ).text()
		} );
		this.noPagesMessage.$element.addClass( 'ma-no-pages-available' );
		this.pageLayout = new OO.ui.HorizontalLayout( {
			items: [ this.noPagesMessage ]
		} );
		this.pageLayout.$element.addClass( 'merge-articles-page-layout' );

		// Select Articles by default
		this.articlesTypeButton.emit( 'click' );

		this.$element.append( this.criteriaLayout.$element, this.pageLayout.$element );
	};

	OO.initClass( mergeArticles.panel.Overview );

	mergeArticles.panel.Overview.prototype.makeTypeLayout = function() {
		this.articlesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-article' ).text()
		} );
		this.articlesTypeButton.on( 'click', function() {
			this.onTypeChange( 'article', this.articlesTypeButton.getValue() );
		}.bind( this ) );
		this.categoriesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-category' ).text()
		} );
		this.categoriesTypeButton.on( 'click', function() {
			this.onTypeChange( 'category', this.categoriesTypeButton.getValue() );
		}.bind( this ) );
		this.templatesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-template' ).text()
		} );
		this.templatesTypeButton.on( 'click', function() {
			this.onTypeChange( 'template', this.templatesTypeButton.getValue() );
		}.bind( this ) );
		this.filesTypeButton = new OO.ui.ToggleButtonWidget( {
			label: mw.message( 'mergearticles-type-file' ).text()
		} );
		this.filesTypeButton.on( 'click', function() {
			this.onTypeChange( 'file', this.filesTypeButton.getValue() );
		}.bind( this ) );

		this.typePicker = new OO.ui.ButtonGroupWidget( {
			items: [
				this.articlesTypeButton,
				this.categoriesTypeButton,
				this.templatesTypeButton,
				this.filesTypeButton
			]
		} );

		this.typeLayout = new OO.ui.FieldsetLayout( {
			id: 'merge-articles-type-layout',
			label: 'Type',
			align: 'top',
			items: [
				this.typePicker
			]
		} );
	};

	mergeArticles.panel.Overview.prototype.makeFilterLayout = function() {
		var instances = {}, layouts = [];
		for ( var name in this.filters ) {
			if ( !this.filters.hasOwnProperty( name ) ) {
				continue;
			}
			var filter = this.filters[name],
				widgetClass = this.stringToCallback( filter.widgetClass ),
				widget = new widgetClass( $.extend( {}, true, {
					id: filter.id,
				}, filter.widgetData || {} ) ),
				layout = new OO.ui.FieldLayout( widget.getWidget(), {
					align: 'top',
					label: filter.displayName
				} );
			widget.connect( this, { change: 'onFilter' } );
			layouts.push( layout );
			instances[name] = widget;
		}

		this.filters = instances;

		return layouts;
	};

	mergeArticles.panel.Overview.prototype.onTypeChange = function( type, value ) {
		if( value ) {
			this.selectedTypes.push( type );
		} else {
			var idx = this.selectedTypes.indexOf( type );
			this.selectedTypes.splice( idx, 1 );
		}

		this.updatePages();
	};

	mergeArticles.panel.Overview.prototype.updatePages = function() {
		this.currentlyDisplayed = 0;
		this.pageLayout.$element
			.children( '.ma-page-item' ).remove();

		var pages = [];
		for( var idx in this.selectedTypes ) {
			var type = this.selectedTypes[ idx ];
			Array.prototype.push.apply( pages, this.applyFilter( this.pages[ type ] ) );
		}

		this.addPages( pages );
		this.checkPagesShown();
	};

	mergeArticles.panel.Overview.prototype.addPages = function( pages ) {
		for( var idx in pages ) {
			var page = pages[ idx ];
			var pageItemWidget = new mergeArticles.ui.PageItemWidget( page );
			pageItemWidget.on( 'actionClick', this.onPageAction.bind( this ) );

			this.pageLayout.$element.append( pageItemWidget.$element );
			this.currentlyDisplayed ++;
		}
	};

	mergeArticles.panel.Overview.prototype.onPageAction = function( data ) {
		var target = mw.config.get( 'maBaseURL' );
		target += '/' + data.action;

		var form = $( '<form>' ).attr( 'method', 'GET' ).attr( 'action', target );
		form.css( 'display', 'none' ).append(
			$( '<input>' ).attr( 'name', 'originID' ).val( data.origin.id ),
			$( '<input>' ).attr( 'name', 'targetText' ).val( data.target.text )
		);
		if( data.action === 'compare' ) {
			form.append(
				$( '<input>' ).attr( 'name', 'targetID' ).val( data.target.id )
			);
		}

		$( 'body' ).append( form );
		form.submit();
	};

	mergeArticles.panel.Overview.prototype.onFilter = function() {
		this.updatePages();
	};

	mergeArticles.panel.Overview.prototype.applyFilter = function( pages ) {
		var filteredPages = pages;
		for ( var name in this.filters ) {
			if ( !this.filters.hasOwnProperty( name ) ) {
				continue;
			}
			filteredPages = this.filters[name].filter( filteredPages );
		}

		return filteredPages;
	};

	mergeArticles.panel.Overview.prototype.showNoPages = function() {
		this.noPagesMessage.$element.show();
	};

	mergeArticles.panel.Overview.prototype.hideNoPages = function() {
		this.noPagesMessage.$element.hide();
	};

	mergeArticles.panel.Overview.prototype.checkPagesShown = function() {
		if( this.currentlyDisplayed === 0 ) {
			this.showNoPages();
		} else {
			this.hideNoPages();
		}
	};

	mergeArticles.panel.Overview.prototype.stringToCallback = function ( cls ) {
		var parts = cls.split( '.' );
		var func = window[parts[0]];
		for( var i = 1; i < parts.length; i++ ) {
			func = func[parts[i]];
		}

		return func;
	};
} ) ( mediaWiki, jQuery );
