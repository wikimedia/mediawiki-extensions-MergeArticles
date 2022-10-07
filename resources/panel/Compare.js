( function( mw, $ ) {
	mergeArticles.panel.Compare = function( cfg ) {
		cfg = cfg || {};

		this.originID = cfg.originID;
		this.targetID = cfg.targetID;
		this.diffData = cfg.diffData || {};
		this.fileData = cfg.fileData || {};

		this.$element = cfg.$element || $( '<div>' );
		this.$element.addClass( 'compare' );
		this.$diffContainer = this.$element.find( '#ma-diff' );

		this.enableBeta = mw.config.get( 'maEnableBeta' );
		this.conflicts = { total: 0, resolved: 0 };

		if( !$.isEmptyObject( this.fileData ) ) {
			if ( !this.diffInFile() ) {
				this.fileData.accepted = true;
				this.displayNoFileDiff();
			} else {
				this.makeFileDiff();
			}
		}
		if( $.isEmptyObject( this.diffData ) ) {
			this.displayNoTextDiff();
		} else {
			this.makeDiff();
		}
		$( '.ma-merge-help' ).append( this.getDiscardDraftButton().$element );
	};

	OO.inheritClass( mergeArticles.panel.Compare, mergeArticles.panel.Review );

	mergeArticles.panel.Compare.prototype.makeFileDiff = function() {

		var fileReject = new OO.ui.ButtonWidget( {
			icon: 'close',
			framed: false,
			title: mw.message( 'mergearticles-diff-action-refuse' ).text(),
			flags: [
				'destructive',
				'primary'
			]
		} );

		var fileApprove = new OO.ui.ButtonWidget( {
			icon: 'check',
			framed: false,
			title: mw.message( 'mergearticles-diff-action-accept' ).text(),
			flags: [
				'progressive',
				'primary'
			]
		} );

		var $actions = $( '<div>' ).addClass( 'diff-file-action' ).append( fileReject.$element, fileApprove.$element );
		$( '.ma-diff-header.diff-file' ).children( '.diff-file-action' ).remove();
		$( '.ma-diff-header.diff-file' ).append( $actions );

		fileApprove.on( 'click', function() {
			this.resolveFileChange( true, $actions );
		}.bind( this ) );
		fileReject.on( 'click', function() {
			this.resolveFileChange( false, $actions );
		}.bind( this ) );
	};

	mergeArticles.panel.Compare.prototype.resolveFileChange = function( outcome, $container ) {
		this.fileData.accepted = outcome;
		var label = new OO.ui.LabelWidget( {
			classes: outcome ? [ 'approve' ] : [ 'reject' ],
			label: outcome ? mw.message( "mergearticles-diff-accepted" ).text() : mw.message( "mergearticles-diff-refused" ).text()
		} );
		var undoButton = new OO.ui.ButtonWidget( {
			icon: 'undo',
			framed: false,
			title: mw.message( 'mergearticles-diff-item-undo' ).text()
		} );
		undoButton.connect( this, { click: 'undoFileResolve' } );
		$container.empty().append( label.$element, undoButton.$element );
		if( $.isEmptyObject( this.diffData ) ) {
			// If no text diff - merge right away
			this.$element.append( this.getButtons() );
		} else if( this.diffResolved() ) {
			// If there is text diff but its resolved - show final text
			this.compareDone( true );
		}
	};

	mergeArticles.panel.Compare.prototype.undoFileResolve = function() {
		delete( this.fileData.accepted );
		this.makeFileDiff();
		this.$element.children( '.ma-review-buttons' ).remove();
		this.compareDone( false );
	};

	mergeArticles.panel.Compare.prototype.makeDiff = function() {
		$.each( this.$diffContainer.children(), function( k, diffEl ) {
			var $diffEl = $( diffEl );
			var diffType = $diffEl.data( 'diff' );
			var $wrapper = $( '<div>' )
				.addClass( 'ma-diff-wrapper' );
			if( diffType === 'copy' ) {
				$diffEl.wrap( $wrapper );
				return;
			}
			$wrapper.addClass( 'has-diff' );
			var $actions = this.getDiffActions( $diffEl.data( 'diff-id' ) );
			$diffEl.wrap( $wrapper );
			$actions.insertAfter( $diffEl );

			this.conflicts.total++;
		}.bind( this ) );

		this.updateResolutionCounter();
		this.makeDiffOptions();
	};

	mergeArticles.panel.Compare.prototype.getDiffActions = function( diffID ) {
		var buttonAccept = new OO.ui.ButtonWidget( {
			icon: 'check',
			framed: false,
			title: mw.message( 'mergearticles-diff-action-accept' ).text(),
			flags: [
				'progressive',
				'primary'
			]
		} );
		var buttonRefuse = new OO.ui.ButtonWidget( {
			icon: 'cancel',
			framed: false,
			title: mw.message( 'mergearticles-diff-action-refuse' ).text(),
			flags: [
				'destructive'
			]
		} );
		buttonAccept.$element.addClass( 'action-accept' );
		buttonRefuse.$element.addClass( 'action-refuse' );


		var buttonContainer = $( '<div>' ).addClass( 'ma-diff-item-action' );

		buttonAccept.$element.on( 'click', {
			diffID: diffID,
			accepted: true
		}, this.resolveChange.bind( this ) );
		buttonRefuse.$element.on( 'click', {
			diffID: diffID,
			accepted: false
		}, this.resolveChange.bind( this ) );

		buttonContainer.append( buttonAccept.$element, buttonRefuse.$element );

		if ( this.enableBeta ) {
			var buttonAcceptBoth = new OO.ui.ButtonWidget( {
				icon: 'checkAll',
				framed: false,
				title: mw.message( 'mergearticles-diff-action-accept-both' ).text(),
				flags: [
					'progressive',
					'primary'
				]
			} );
			buttonAcceptBoth.$element.on( 'click', {
				diffID: diffID,
				accepted: true,
				applyToBoth: true
			}, this.resolveChange.bind( this ));

			var buttonRejectBoth = new OO.ui.ButtonWidget( {
				icon: 'block',
				framed: false,
				title: mw.message( 'mergearticles-diff-action-refuse-both' ).text(),
				flags: [
					'destructive'
				]
			} );
			buttonRejectBoth.$element.on( 'click', {
				diffID: diffID,
				accepted: false,
				applyToBoth: true
			}, this.resolveChange.bind( this ));

			buttonContainer.append( buttonAcceptBoth.$element, buttonRejectBoth.$element );
		}

		return buttonContainer;
	};

	mergeArticles.panel.Compare.prototype.updateResolutionCounter = function() {
		if( !this.resolutionCounter ) {
			this.resolutionCounter = new OO.ui.LabelWidget();
			this.resolutionCounter.$element.insertAfter( $( 'span.ma-diff-header-label' ) );
		}
		this.resolutionCounter.setLabel(  mw.message(
				'mergearticles-resolution-counter',
				this.conflicts.total,
				this.conflicts.resolved
			).text()
		);

		if( this.diffResolved() && this.fileResolved() ) {
			this.compareDone( true );
		} else {
			this.compareDone( false );
		}
	};

	mergeArticles.panel.Compare.prototype.makeDiffOptions = function( e, data ) {
		var hideNoDiffCheckbox = new OO.ui.CheckboxInputWidget( {
			selected: false
		} );
		hideNoDiffCheckbox.on( 'change', function( value ) {
			this.hideMatchingBlocks( value );
		}.bind( this ) );
		var hideNoDiffLabel = new OO.ui.LabelWidget( {
			label: mw.message( 'mergearticles-diff-option-hide-identical-blocks-label' ).text(),
			input: hideNoDiffCheckbox
		} );

		var acceptAllButton = new OO.ui.ButtonWidget( {
			framed: false,
			icon: 'check', //'checkAll' in newer version
			label: mw.message( 'mergearticles-diff-option-accept-all-label' ).text()
		} );
		acceptAllButton.$element.addClass( 'ma-diff-option-accept-all' );
		acceptAllButton.on( 'click', this.acceptAllChanges.bind( this ) );
		this.diffOptionsLayout = new OO.ui.HorizontalLayout( {
			items: [
				hideNoDiffCheckbox,
				hideNoDiffLabel,
				acceptAllButton
			]
		} );

		this.diffOptionsLayout.$element.insertAfter( this.resolutionCounter.$element );
	};

	mergeArticles.panel.Compare.prototype.hideMatchingBlocks = function( hide ) {
		var toBeHidden = [];
		if( !hide ) {
			this.$diffContainer.find( '.ma-same-block' ).remove();
			this.$diffContainer.find( '.ma-diff-wrapper:not( .has-diff )' ).show();
			return;
		}

		$.each( this.$diffContainer.children(), function( k, diffEl ) {
			var $diffEl = $( diffEl );
			if( !$diffEl.hasClass( 'has-diff' ) ) {
				toBeHidden.push( $diffEl.find( 'p' ).data( 'diff-id' ) );
				$diffEl.hide();
				if( !$diffEl.is( ":last-child" ) ) {
					return;
				}
			}
			if( toBeHidden.length === 0 ) {
				return;
			}

			var hiddenBlocksButton = new OO.ui.ButtonWidget( {
				label: mw.message( "mergearticles-same-block-label", toBeHidden.length ).text(),
				framed: false
			} );
			hiddenBlocksButton.$element.addClass( 'ma-same-block' );
			hiddenBlocksButton.$element.on( 'click', {
				ids: toBeHidden,
				button: hiddenBlocksButton
			}, function( e ) {
				for( var idx in e.data.ids ) {
					var id = e.data.ids[ idx ];
					this.$diffContainer.find( '.ma-diff-copy[data-diff-id="' + id + '"]' ).parent().show();
				}
				e.data.button.$element.remove();
			}.bind( this ) );
			hiddenBlocksButton.$element.insertBefore( $diffEl );
			toBeHidden = [];
		}.bind( this ) );
	};

	mergeArticles.panel.Compare.prototype.resolveChange = function( e ) {
		if( !this.diffData.hasOwnProperty( e.data.diffID ) ) {
			return;
		}

		var accepted = e.data.accepted || false,
			applyToBoth = e.data.applyToBoth || false;

		var diff = this.diffData[ e.data.diffID ];
		diff.accepted = accepted;
		diff.applyToBoth = applyToBoth;

		var messageKey = accepted ? 'mergearticles-diff-accepted' : 'mergearticles-diff-refused';
		if ( applyToBoth ) {
			messageKey += '-both';
		}

		var $diffWrapper = this.$diffContainer
			.find( '[data-diff-id="' + e.data.diffID + '"]' )
			.parent();
		if ( applyToBoth ) {
			$diffWrapper.addClass( 'ma-diff-both' );
		} else {
			$diffWrapper.removeClass( 'ma-diff-both' );
		}

		$diffWrapper.addClass( accepted ? 'ma-diff-accepted' : 'ma-diff-refused' );

		var buttonContainer = $diffWrapper.find( '.ma-diff-item-action' );
		var label = new OO.ui.LabelWidget( {
			label: mw.message( messageKey ).text()
		} );

		var undoButton = new OO.ui.ButtonWidget( {
			icon: 'undo',
			framed: false,
			title: mw.message( 'mergearticles-diff-item-undo' ).text()
		} );
		undoButton.$element.addClass( 'diff-undo' );
		undoButton.$element.on( 'click', {
			diffID: e.data.diffID,
			diff: diff,
			$wrapper: $diffWrapper,
			$buttonCnt: buttonContainer
		}, this.unResolveChange.bind( this ) );

		buttonContainer.children().remove();
		buttonContainer.append( label.$element, undoButton.$element );

		this.conflicts.resolved++;
		this.updateResolutionCounter();
	};

	mergeArticles.panel.Compare.prototype.unResolveChange = function( e ) {
		delete( e.data.diff.accepted );

		e.data.$wrapper.removeClass( 'ma-diff-accepted ma-diff-refused' );
		e.data.$buttonCnt.empty();
		e.data.$buttonCnt.replaceWith( this.getDiffActions( e.data.diffID ) );

		this.conflicts.resolved--;
		this.updateResolutionCounter();
	};

	mergeArticles.panel.Compare.prototype.acceptAllChanges = function() {
		this.conflicts.resolved = 0;
		 for( var diffID in this.diffData ) {
			 var diff = this.diffData[ diffID ];
			 if( diff.type === 'copy' ) {
				 continue;
			 }
			 this.resolveChange( { data: { diffID: diffID, accepted: true } } );
		}
	};

	mergeArticles.panel.Compare.prototype.compareDone = function( done ) {
		if( !this.showFinalTextLayout ) {
			var button = new OO.ui.ButtonWidget( {
				label: mw.message( 'mergearticles-show-final-text-button-label' ).text()
			} );
			button.on( 'click', this.showComparedText.bind( this ) );
			var icon = new OO.ui.IconWidget( {
				icon: 'check'
			} );
			var label = new OO.ui.LabelWidget( {
				label: mw.message( 'mergearticles-show-final-text-label' ).text()
			} );
			this.showFinalTextLayout = new OO.ui.HorizontalLayout( {
				items: [
					icon, label, button
				]
			} );
			this.showFinalTextLayout.$element.addClass( 'show-final-text-layout' );
			this.$element.append( this.showFinalTextLayout.$element );
		}
		if( done ) {
			$( [document.documentElement, document.body] ).animate( {
				scrollTop: this.showFinalTextLayout.$element.show().offset().top
			}, 1000 );
		} else {
			this.showFinalTextLayout.$element.hide();
		}
	};

	mergeArticles.panel.Compare.prototype.showComparedText = function() {
		$( '.show-final-text-layout' ).remove();
		this.removeDiffActions();
		//this.collapseDiff();
		this.collectText();
		this.makeFinalTextBox();
	};

	mergeArticles.panel.Compare.prototype.removeDiffActions = function() {
		this.hideMatchingBlocks( false );
		this.diffOptionsLayout.$element.remove();
		$( '.ma-diff-item-action' ).children( '.diff-undo' ).remove();
	};

	mergeArticles.panel.Compare.prototype.collapseDiff = function() {
		this.$diffContainer.slideUp( 200, function() {
			var expandButton = new OO.ui.ButtonWidget( {
				framed: false,
				label: mw.message( 'mergearticles-diff-header' ).plain()
			} );
			expandButton.$element.addClass( 'ma-diff-header-expand' );
			expandButton.on( 'click', function() {
				if( this.$diffContainer.is( ':visible' ) ) {
					this.$diffContainer.slideUp( 200 );
				} else {
					this.$diffContainer.slideDown( 200 );
				}
			}.bind( this ) );
			$( '.ma-diff-header-label' ).replaceWith( expandButton.$element );
		}.bind( this ) );
	};

	mergeArticles.panel.Compare.prototype.collectText = function() {
		var finalText = [];
		for( var diffID in this.diffData ) {
			var block = false;
			var diff = this.diffData[ diffID ];
			switch( diff.type ) {
				case 'copy':
					block = diff.old || '';
					break;
				case 'add':
					if( diff.accepted === true ) {
						block = diff.new || '';
					}
					break;
				case 'delete':
					if( diff.accepted === false ) {
						block = diff.old || '';
					}
					break;
				case 'change':
					if( diff.accepted === true ) {
						if ( diff.applyToBoth ) {
							block = diff.old + '\n' + diff.new;
						} else {
							block = diff.new || '';
						}
					} else {
						if ( diff.applyToBoth ) {
							break;
						} else {
							block = diff.old || '';
						}
					}
					break;
			}
			if( block !== false ) {
				finalText.push( block );
			}
		}
		this.finalText = finalText.join( "\n" );
	};

	mergeArticles.panel.Compare.prototype.makeFinalTextBox = function() {
		var $finalTextContainer = $( '<div>' ).addClass( 'final-text-container' );
		var $header = $( '<div>' )
			.addClass( 'final-text-header' )
			.append(
				new OO.ui.LabelWidget( {
					label: mw.message( 'mergearticles-final-text-header-label' ).text()
				} ).$element,
				new OO.ui.LabelWidget( {
					label: new OO.ui.HtmlSnippet(
						'<small> ' +
						mw.message( 'mergearticles-final-text-header-note' ).text() +
						'</small>'
					)
				} ).$element,
			);
		this.finalTextBox = new OO.ui.MultilineTextInputWidget( {
			rows: 25,
			value: this.finalText
		} );

		var $buttons = this.getButtons();

		$finalTextContainer.append(
			$header,
			this.finalTextBox.$element,
			$buttons
		);

		this.$element.append( $finalTextContainer );
		$( [document.documentElement, document.body] ).animate( {
			scrollTop: $finalTextContainer.offset().top
		}, 1000 );
	};

	mergeArticles.panel.Compare.prototype.doMerge = function() {
		var text = this.finalTextBox !== undefined ? this.finalTextBox.getValue() : '';
		this.makeApiRequest( {
			action: 'ma-merge-page-existing',
			pageID: this.originID,
			targetID: this.targetID,
			skipFile: !this.fileData.accepted,
			text: text
		} ).done( function( response ) {
			if( response.success ) {
				var msg = mw.message( 'mergearticles-merge-success-page-label' ).text();
				var $anchor =  $( '<a>' ).attr(  'href', response.targetPage.url ).text( response.targetPage.text );
				msg = msg.replace( '$1', $( '<div>' ).append( $anchor ).html() );

				this.showActionResult(
					true,
					mw.message( 'mergearticles-merge-success-header' ).text(),
					msg
				);
			} else {
				this.showActionResult(
					false,
					mw.message( 'mergearticles-merge-fail-header' ).text(),
					response.error
				);
			}
		}.bind( this ) ).fail( function( error, data ) {
			if ( data.hasOwnProperty( 'error' ) && data.error.hasOwnProperty( 'info' ) ) {
				error = data.error.info;
			}
			this.showActionResult(
				false,
				mw.message( 'mergearticles-merge-fail-header' ).text(),
				error
			);
		}.bind( this ) );
	};

	mergeArticles.panel.Compare.prototype.getButtons = function() {
		this.mergeButton = new OO.ui.ButtonWidget( {
			label: mw.message( 'mergearticles-do-merge-label' ).plain(),
			flags: [
				'primary',
				'progressive'
			]
		} );
		this.mergeButton.on( 'click', this.doMerge.bind( this ) );

		var $buttons = $( '<div>' )
			.addClass( 'ma-review-buttons' )
			.append( this.mergeButton.$element );

		return $buttons;
	};

	mergeArticles.panel.Compare.prototype.displayNoTextDiff = function() {
		this.displayNoDiff( mw.message( 'mergearticles-no-diff-message' ).text() );
	};

	mergeArticles.panel.Compare.prototype.displayNoFileDiff = function() {
		this.displayNoDiff( mw.message( 'mergearticles-no-diff-file-message' ).text(), $( '.ma-diff-header.diff-file' ) );
	};

	mergeArticles.panel.Compare.prototype.displayNoDiff = function( message, $element ) {
		$element = $element || this.$element;
		var label = new OO.ui.LabelWidget( {
			label: message
		} );
		label.$element.addClass( 'ma-no-diff-message' );
		$element.append( label.$element );
	};

	mergeArticles.panel.Compare.prototype.fileResolved = function() {
		if( $.isEmptyObject( this.fileData ) ) {
			return true;
		}
		return this.fileData.hasOwnProperty( 'accepted' );
	};

	mergeArticles.panel.Compare.prototype.diffResolved = function() {
		if( $.isEmptyObject( this.diffData ) ) {
			return true;
		}
		return this.conflicts.total === this.conflicts.resolved;
	};

	mergeArticles.panel.Compare.prototype.diffInFile = function() {
		return this.fileData.origin.sha1 !== this.fileData.target.sha1;
	};

} ) ( mediaWiki, jQuery );
