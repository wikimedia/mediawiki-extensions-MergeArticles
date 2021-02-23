( function() {
	OO.ui.MultilineTextInputWidget = function OoUiMultilineTextInputWidget( config ) {
		config = $.extend( {
			type: 'text'
		}, config );
		config.multiline = false;
		// Parent constructor
		OO.ui.MultilineTextInputWidget.parent.call( this, config );

		// Properties
		this.multiline = true;
		this.autosize = !!config.autosize;
		this.minRows = config.rows !== undefined ? config.rows : '';
		this.maxRows = config.maxRows || Math.max( 2 * ( this.minRows || 0 ), 10 );

		// Clone for resizing
		if ( this.autosize ) {
			this.$clone = this.$input
				.clone()
				.insertAfter( this.$input )
				.attr( 'aria-hidden', 'true' )
				.addClass( 'oo-ui-element-hidden' );
		}

		// Events
		this.connect( this, {
			change: 'onChange'
		} );

		// Initialization
		if ( this.multiline && config.rows ) {
			this.$input.attr( 'rows', config.rows );
		}
		if ( this.autosize ) {
			this.$input.addClass( 'oo-ui-textInputWidget-autosized' );
			this.isWaitingToBeAttached = true;
			this.installParentChangeDetector();
		}
	};

	/* Setup */

	OO.inheritClass( OO.ui.MultilineTextInputWidget, OO.ui.TextInputWidget );

	/* Static Methods */

	/**
	 * @inheritdoc
	 */
	OO.ui.MultilineTextInputWidget.static.gatherPreInfuseState = function ( node, config ) {
		var state = OO.ui.MultilineTextInputWidget.parent.static.gatherPreInfuseState( node, config );
		state.scrollTop = config.$input.scrollTop();
		return state;
	};

	/* Methods */

	/**
	 * @inheritdoc
	 */
	OO.ui.MultilineTextInputWidget.prototype.onElementAttach = function () {
		OO.ui.MultilineTextInputWidget.parent.prototype.onElementAttach.call( this );
		this.adjustSize();
	};

	/**
	 * Handle change events.
	 *
	 * @private
	 */
	OO.ui.MultilineTextInputWidget.prototype.onChange = function () {
		this.adjustSize();
	};

	/**
	 * @inheritdoc
	 */
	OO.ui.MultilineTextInputWidget.prototype.updatePosition = function () {
		OO.ui.MultilineTextInputWidget.parent.prototype.updatePosition.call( this );
		this.adjustSize();
	};

	/**
	 * @inheritdoc
	 *
	 * Modify to emit 'enter' on Ctrl/Meta+Enter, instead of plain Enter
	 */
	OO.ui.MultilineTextInputWidget.prototype.onKeyPress = function ( e ) {
		if (
			( e.which === OO.ui.Keys.ENTER && ( e.ctrlKey || e.metaKey ) ) ||
			// Some platforms emit keycode 10 for ctrl+enter in a textarea
			e.which === 10
		) {
			this.emit( 'enter', e );
		}
	};

	/**
	 * Automatically adjust the size of the text input.
	 *
	 * This only affects multiline inputs that are {@link #autosize autosized}.
	 *
	 * @chainable
	 * @fires resize
	 */
	OO.ui.MultilineTextInputWidget.prototype.adjustSize = function () {
		var scrollHeight, innerHeight, outerHeight, maxInnerHeight, measurementError,
			idealHeight, newHeight, scrollWidth, property;

		if ( this.$input.val() !== this.valCache ) {
			if ( this.autosize ) {
				this.$clone
					.val( this.$input.val() )
					.attr( 'rows', this.minRows )
					// Set inline height property to 0 to measure scroll height
					.css( 'height', 0 );

				this.$clone.removeClass( 'oo-ui-element-hidden' );

				this.valCache = this.$input.val();

				scrollHeight = this.$clone[ 0 ].scrollHeight;

				// Remove inline height property to measure natural heights
				this.$clone.css( 'height', '' );
				innerHeight = this.$clone.innerHeight();
				outerHeight = this.$clone.outerHeight();

				// Measure max rows height
				this.$clone
					.attr( 'rows', this.maxRows )
					.css( 'height', 'auto' )
					.val( '' );
				maxInnerHeight = this.$clone.innerHeight();

				// Difference between reported innerHeight and scrollHeight with no scrollbars present.
				// This is sometimes non-zero on Blink-based browsers, depending on zoom level.
				measurementError = maxInnerHeight - this.$clone[ 0 ].scrollHeight;
				idealHeight = Math.min( maxInnerHeight, scrollHeight + measurementError );

				this.$clone.addClass( 'oo-ui-element-hidden' );

				// Only apply inline height when expansion beyond natural height is needed
				// Use the difference between the inner and outer height as a buffer
				newHeight = idealHeight > innerHeight ? idealHeight + ( outerHeight - innerHeight ) : '';
				if ( newHeight !== this.styleHeight ) {
					this.$input.css( 'height', newHeight );
					this.styleHeight = newHeight;
					this.emit( 'resize' );
				}
			}
			scrollWidth = this.$input[ 0 ].offsetWidth - this.$input[ 0 ].clientWidth;
			if ( scrollWidth !== this.scrollWidth ) {
				property = this.$element.css( 'direction' ) === 'rtl' ? 'left' : 'right';
				// Reset
				this.$label.css( { right: '', left: '' } );
				this.$indicator.css( { right: '', left: '' } );

				if ( scrollWidth ) {
					this.$indicator.css( property, scrollWidth );
					if ( this.labelPosition === 'after' ) {
						this.$label.css( property, scrollWidth );
					}
				}

				this.scrollWidth = scrollWidth;
				this.positionLabel();
			}
		}
		return this;
	};

	/**
	 * @inheritdoc
	 * @protected
	 */
	OO.ui.MultilineTextInputWidget.prototype.getInputElement = function () {
		return $( '<textarea>' );
	};

	/**
	 * Check if the input supports multiple lines.
	 *
	 * @return {boolean}
	 */
	OO.ui.MultilineTextInputWidget.prototype.isMultiline = function () {
		return !!this.multiline;
	};

	/**
	 * Check if the input automatically adjusts its size.
	 *
	 * @return {boolean}
	 */
	OO.ui.MultilineTextInputWidget.prototype.isAutosizing = function () {
		return !!this.autosize;
	};

	/**
	 * @inheritdoc
	 */
	OO.ui.MultilineTextInputWidget.prototype.restorePreInfuseState = function ( state ) {
		OO.ui.MultilineTextInputWidget.parent.prototype.restorePreInfuseState.call( this, state );
		if ( state.scrollTop !== undefined ) {
			this.$input.scrollTop( state.scrollTop );
		}
	};
} )();