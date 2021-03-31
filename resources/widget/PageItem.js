( function( mw, $ ) {
	mergeArticles.ui.PageItemWidget = function( cfg ) {
		mergeArticles.ui.PageItemWidget.parent.call( this, cfg );

		this.type = cfg.type;
		this.origin = cfg.origin;
		this.target = cfg.target;

		this.$dataContainer = $( '<div>' ).addClass( 'ma-page-item-data' );
		this.$actionContainer = $( '<div>' ).addClass( 'ma-page-item-action' );

		this.makeType();
		this.makeOrigin();
		this.makeTarget();
		this.makeActions();

		this.$element.addClass( 'ma-page-item' );
		this.$element.addClass( this.type );

		this.$element.append( this.$dataContainer, this.$actionContainer );
	};

	OO.inheritClass( mergeArticles.ui.PageItemWidget, OO.ui.Widget );

	mergeArticles.ui.PageItemWidget.prototype.makeType = function() {
		// mergearticles-type-file
		// mergearticles-type-article
		// mergearticles-type-template
		// mergearticles-type-category
		var msgKey = 'mergearticles-type-' + this.type;
		var typeLabel = new OO.ui.LabelWidget( {
			label: mw.message( msgKey ).text()
		} );
		typeLabel.$element.addClass( 'ma-page-item-type-label' );
		this.$dataContainer.append( typeLabel.$element );
	};

	mergeArticles.ui.PageItemWidget.prototype.makeOrigin = function() {
		var $origin = $( '<div>' ).addClass( 'ma-page-item-data-origin' );
		var $anchor = $( '<a>' )
			.attr( 'href', this.origin.url )
			.data( 'id', this.origin.id )
			.append(
				new OO.ui.LabelWidget( {
					label: this.origin.text
				} ).$element
			);

		$origin.append( $anchor );
		this.$dataContainer.append( $origin );
	};

	mergeArticles.ui.PageItemWidget.prototype.makeTarget = function() {
		var $target = $( '<div>' ).addClass( 'ma-page-item-data-target' );

		if( this.target.exists ) {
			var text = mw.message( 'mergearticles-target-exists-label' ).escaped();
			var anchor = '<a href="' + this.target.url + '">' + this.target.text + '</a>';
			text = text.replace( '$1', anchor );

			$target
				.addClass( 'existing' )
				.data( 'id', this.target.id )
				.html( text );
		} else {
			var text = mw.message( 'mergearticles-target-new-label' ).escaped();
			$target
				.addClass( 'new' )
				.html( text );
		}

		this.$dataContainer.append( $target );
	};

	mergeArticles.ui.PageItemWidget.prototype.makeActions = function() {
		var labelText;
		if( this.target.exists ) {
			this.action = 'compare';
			labelText = mw.message( 'mergearticles-page-item-action-compare' ).text();
		} else {
			this.action = 'review';
			labelText = mw.message( 'mergearticles-page-item-action-review' ).text();
		}

		var button = new OO.ui.ButtonWidget( {
			title: labelText,
			icon: 'next',
			flags: [
				'progressive',
				'primary'
			]
		} );
		button.on( 'click', this.onAction.bind( this ) );

		this.$actionContainer.append( button.$element );
	};

	mergeArticles.ui.PageItemWidget.prototype.onAction = function() {
		this.emit( 'actionClick', {
			action: this.action,
			origin: this.origin,
			target: this.target
		} );
	};

	mergeArticles.ui.PageItemWidget.static.tagName = 'div';
} ) ( mediaWiki, jQuery );
