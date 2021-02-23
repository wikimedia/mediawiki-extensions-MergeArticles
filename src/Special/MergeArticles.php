<?php

namespace MergeArticles\Special;

class MergeArticles extends \SpecialPage {
	const TYPE_ARTICLE = 'article';
	const TYPE_CATEGORY = 'category';
	const TYPE_TEMPLATE = 'template';
	const TYPE_FILE = 'file';

	const ACTION_REVIEW = 'review';
	const ACTION_COMPARE = 'compare';

	protected $originTitle;
	protected $targetTitle;

	protected $dbr;

	public function __construct() {
		parent::__construct( "MergeArticles", "merge-articles" );
	}

	public function execute( $action ) {
		parent::execute( $action );

		$this->getOutput()->enableOOUI();
		$this->getOutput()->addJsConfigVars( 'maBaseURL', $this->getPageTitle()->getLocalURL() );

		if( !$action ) {
			$this->addOverview();
		} else if( $action === static::ACTION_REVIEW ) {
			$this->addReview();
		} else if( $action === static::ACTION_COMPARE ) {
			$this->addComparison();
		} else {
			$this->displayUnknownAction();
		}
	}

	protected function addOverview() {
		$this->getOutput()->addModules( 'ext.mergearticles.overview' );
		$this->getOutput()->addJsConfigVars( 'maAvailablePages', $this->getAvailablePages() );
		$this->getOutput()->addHTML( \Html::element( 'div', [ 'id' => 'merge-articles-overview' ] ) );
	}

	protected function addReview() {
		$this->getOutput()->setPageTitle( wfMessage( 'ma-sp-review' )->plain() );

		$originID = $this->getRequest()->getInt( 'originID', 0 );
		$targetText = $this->getRequest()->getText( 'targetText', '' );
		if( !$originID || !$targetText ) {
			return $this->displayInvalid();
		}
		$this->originTitle = \Title::newFromID( $originID );
		$this->targetTitle = \Title::newFromText( $targetText );
		if( $this->verifyTitles() === false ) {
			return $this->displayInvalid();
		}
		if( $this->isFile() && !$this->isValidFile() ) {
			return $this->displayInvalid();
		}

		$reviewData = [
			'originID' => $this->originTitle->getArticleID(),
			'targetText' => $targetText,
			'originContent' => $this->getPageContentText( $this->originTitle )
		];

		$this->getOutput()->addHTML( \Html::openElement( 'div', [
			'id' => 'merge-articles-review',
			'class' => 'merge-articles'
		] ) );

		$this->getOutput()->addHTML( $this->getPageNamesHTML() );
		$this->getOutput()->addHTML( $this->getHelpHTML() );
		if( $this->isFile() ) {
			$reviewData[ 'fileData' ] = $this->getFileInfo();
			$this->getOutput()->addHTML( $this->getFileReviewHeader() );
			$this->getOutput()->addHTML( $this->getFileReviewHTML() );
		}
		$this->getOutput()->addHTML( $this->getReviewHeader() );
		$this->getOutput()->addHTML( \Html::closeElement( 'div' ) );

		$this->getOutput()->addModules( 'ext.mergearticles.review' );

		$this->getOutput()->addJsConfigVars( 'maReviewData', $reviewData );
	}

	protected function addComparison() {
		$this->getOutput()->setPageTitle( wfMessage( 'ma-sp-compare' )->plain() );

		$originID = $this->getRequest()->getInt( 'originID', 0 );
		$targetID = $this->getRequest()->getInt( 'targetID', 0 );
		if( !$originID || !$targetID ) {
			return $this->displayInvalid();
		}

		$this->originTitle = \Title::newFromID( $originID );
		$this->targetTitle = \Title::newFromID( $targetID );
		if( $this->verifyTitles() === false ) {
			return $this->displayInvalid();
		}

		if( $this->isFile() && !$this->isValidFile() ) {
			return $this->displayInvalid();
		}

		$this->getOutput()->addHTML( \Html::openElement( 'div', [
			'id' => 'merge-articles-compare',
			'class' => 'merge-articles'
		] ) );
		$this->getOutput()->addHTML( $this->getPageNamesHTML() );
		$this->getOutput()->addHTML( $this->getHelpHTML( true ) );
		$this->getOutput()->addModules( 'ext.mergearticles.compare' );

		$compareData = [
			'originID' => $this->originTitle->getArticleID(),
			'targetID' => $this->targetTitle->getArticleID()
		];

		if( $this->isFile() ) {
			$compareData[ 'fileData' ] = $this->getFileInfo();
			$this->getOutput()->addHTML( $this->getFileDiffHeader() );
			$this->getOutput()->addHTML( $this->getFileDiffHTML() );
		}

		$diff = $this->getDiff();
		if( $diff->isEmpty() === false ) {
			$diffFormatter = new \MergeArticles\HTMLDiffFormatter();
			$formatted = $diffFormatter->format( $diff, !$this->getConfig()->get( 'MAUseLineByLineDiff' ) );

			$compareData[ 'diffData' ] = $diffFormatter->getArrayData();
			$this->getOutput()->addHTML( $this->getDiffHeader( $diffFormatter->getChangeCount() ) );
			$this->getOutput()->addHTML( $formatted );
		}

		$this->getOutput()->addJsConfigVars(
			'maEnableBeta', $this->getConfig()->get( 'MAEnableBetaFeatures' )
		);
		$this->getOutput()->addHTML( \Html::closeElement( 'div' ) );
		$this->getOutput()->addJsConfigVars( 'maCompareData', $compareData );
	}

	protected function getPageNamesHTML() {
		$origin = new \OOUI\LabelWidget( [
			'label' => $this->originTitle->getPrefixedText()
		] );
		$target = new \OOUI\LabelWidget( [
			'label' => $this->targetTitle->getPrefixedText()
		] );
		$icon = new \OOUI\IconWidget( [
			'icon' => 'next'
		] );
		$pageNames = \Html::openElement( 'div', [ 'class' => 'ma-page-names' ] );
		$pageNames .= $this->getOverviewButton();
		$pageNames .= $origin;
		$pageNames .= $icon;
		$pageNames .= $target;
		$pageNames .= \Html::closeElement( 'div' );

		return $pageNames;
	}

	protected function getOverviewButton() {
		$button = new \OOUI\ButtonWidget( [
			'infusable' => true,
			'framed' => false,
			'icon' => 'previous', //make "arrowPrevious" in newer OOJS,
			'title' => wfMessage( 'ma-back-to-overview' )->plain(),
			'id' => 'ma-overview-button',
			'href' => $this->getPageTitle()->getLocalURL()
		] );
		$button->addClasses( [ 'ma-back-to-overview-button' ] );
		return $button;
	}

	protected function getHelpHTML( $exists = false ) {
		$targetPage = $this->targetTitle->getPrefixedText();
		$icon = new \OOUI\IconWidget( [
			'icon' => 'info'
		] );
		$labelKey = 'ma-merge-new-help';
		if( $exists ) {
			$labelKey = 'ma-merge-existing-help';
		}
		$label = new \OOUI\LabelWidget( [
			'label' => wfMessage( $labelKey, $targetPage )->plain()
		] );

		$help = \Html::openElement( 'div', [ 'class' => 'ma-merge-help' ] );
		$help .= $icon;
		$help .= $label;
		$help .= \Html::closeElement( 'div' );

		return $help;
	}

	protected function getDiffHeader( $stats ) {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-diff-header' ] );
		$header .= \Html::element( 'span', [
			'class' => 'ma-diff-header-label'
		], wfMessage( 'ma-diff-header' )->escaped() );
		$header .= \Html::openElement( 'span', [ 'class' => 'ma-diff-header-stats' ] );
		$header .= \Html::element( 'span', [
			'class' => 'ma-diff-header-added'
		], $stats[ 'add' ] );
		$header .= \Html::element( 'span', [
			'class' => 'ma-diff-header-deleted'
		], $stats[ 'delete' ] );
		$header .= \Html::closeElement( 'span' );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	protected function getReviewHeader() {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-review-header' ] );
		$header .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-review-header' )->plain()
		] );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	/**Files**/

	protected function getFileDiffHeader() {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-diff-header diff-file' ] );
		$header .= \Html::element( 'span', [
			'class' => 'ma-file-diff-header-label'
		], wfMessage( 'ma-file-diff-header' )->escaped() );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	protected function getFileReviewHeader() {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-review-header review-file' ] );
		$header .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-layout-label' )->plain()
		] );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	protected function getFileDiffHTML() {
		$fileInfo = $this->getFileInfo();

		$html = \Html::openElement( 'div', [ 'class' => 'ma-file-diff' ] );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-origin-file' ] );
		$originLabel = new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-origin-header' )->plain()
		] );
		$originLabel->addClasses( [ 'ma-file-header-label' ] );
		$html .= $originLabel;
		$html .= $this->fileHTMLFromInfo( $fileInfo[ 'origin' ] );
		$html .= \Html::closeElement( 'div' );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-target-file' ] );
		$targetLabel = new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-target-header' )->plain()
		] );
		$targetLabel->addClasses( [ 'ma-file-header-label' ] );
		$html .= $targetLabel;
		$html .= $this->fileHTMLFromInfo( $fileInfo[ 'target' ] );
		$html .= \Html::closeElement( 'div' );
		$html .= \Html::closeElement( 'div' );

		return $html;
	}

	protected function getFileReviewHTML() {
		$fileInfo = $this->getFileInfo();

		$html = \Html::openElement( 'div', [ 'class' => 'ma-file-diff' ] );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-origin-file' ] );
		$html .= $this->fileHTMLFromInfo( $fileInfo[ 'origin' ] );
		$html .= \Html::closeElement( 'div' );
		$html .= \Html::closeElement( 'div' );

		return $html;
	}

	protected function fileHTMLFromInfo( $info ) {
		$html = \Html::openElement( 'div', [ 'class' => 'ma-file-layout' ] );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-review-file-info' ] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-info-name', $info[ 'name' ] )->plain()
		] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-info-extension', $info[ 'extension' ] )->plain()
		] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-info-mime', $info[ 'mime_type' ] )->plain()
		] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'ma-file-info-size', $info[ 'size' ] )->plain()
		] );
		$html .= \Html::closeElement( 'div' );
		if( strpos( $info[ 'mime_type' ], 'image' ) !== false ) {
			$html .= \Html::element( 'div', [
				'class' => 'ma-file-preview-image',
				'style' => 'background-image: url( ' . $info[ 'url' ] . ' )'
			] );
		}
		$html .= \Html::closeElement( 'div' );
		return $html;
	}

	/**Overview**/

	protected function getAvailablePages() {
		$this->dbr = wfGetDB( DB_SLAVE );

		$availablePages = [];
		$this->getPages( $availablePages );
		$this->getFiles( $availablePages );

		return $availablePages;
	}

	protected function getPages( &$availablePages ) {
		$res = $this->dbr->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => NS_DRAFT ]
		);

		foreach( $res as $row ) {
			$draftTitle = \Title::makeTitle( NS_DRAFT, $row->page_title );
			$title = \Title::newFromText( $row->page_title );

			if( $title instanceof \Title === false ) {
				continue;
			}

			$type = static::TYPE_ARTICLE;
			switch( $title->getNamespace() ) {
				case NS_TEMPLATE:
					$type = static::TYPE_TEMPLATE;
					break;
				case NS_CATEGORY:
					$type = static::TYPE_CATEGORY;
					break;
			}

			$availablePages[ $type ][] = [
				'origin' => [
					'id' => $draftTitle->getArticleID(),
					'text' => $draftTitle->getPrefixedText(),
					'url' => $draftTitle->getLocalURL()
				],
				'target' => [
					'id' => $title->getArticleID(),
					'exists' => $title->exists(),
					'text' => $title->getPrefixedText(),
					'url' => $title->getLocalURL()
				],
				'type' => $type
			];
		}
	}

	protected function getFiles( &$availablePages ) {
		$draftFilePrefix = $this->getConfig()->get( 'MADraftFilePrefix' );

		$res = $this->dbr->select(
			'page',
			[ 'page_id', 'page_title' ],
			[
				"page_namespace" => NS_FILE,
				"page_title " . $this->dbr->buildLike(
					$draftFilePrefix,
					$this->dbr->anyString()
				)
			]
		);

		foreach( $res as $row ) {
			$draftTitle = \Title::makeTitle( NS_FILE, $row->page_title );
			$stripped = str_replace( $draftFilePrefix, '', $row->page_title );
			$title = \Title::makeTitle( NS_FILE, $stripped );

			if( $title instanceof \Title === false ) {
				continue;
			}

			$availablePages[ static::TYPE_FILE ][] = [
				'origin' => [
					'id' => $draftTitle->getArticleID(),
					'text' => $draftTitle->getPrefixedText(),
					'url' => $draftTitle->getLocalURL()
				],
				'target' => [
					'id' => $title->getArticleID(),
					'exists' => $title->exists(),
					'text' => $title->getPrefixedText(),
					'url' => $title->getLocalURL()
				],
				'type' => static::TYPE_FILE
			];
		}
	}

	/**Utility functions**/

	protected function getDiff() {
		$originContent = $this->getPageContentText( $this->originTitle );
		$targetContent = $this->getPageContentText( $this->targetTitle );

		$diff = new \Diff(
			explode( "\n", $targetContent ),
			explode( "\n", $originContent )
		);
		return $diff;
	}

	protected function isFile() {
		if( $this->originTitle->getNamespace() !== NS_FILE ) {
			return false;
		}
		if( $this->targetTitle && $this->targetTitle->getNamespace() !== NS_FILE ) {
			return false;
		}
		return true;
	}

	protected function isValidFile() {
		$file = wfFindFile( $this->originTitle );
		if( !$file ) {
			return false;
		}
		if( $this->targetTitle instanceof \Title && $this->targetTitle->exists() ) {
			$file = wfFindFile( $this->targetTitle );
			if( !$file ) {
				return false;
			}
		}
		return true;
	}

	protected function getFileInfo() {
		$file = wfFindFile( $this->originTitle );

		$fileInfo = [
			'origin' => [
				'name' => $file->getName(),
				'url' => $file->getUrl(),
				'extension' => $file->getExtension(),
				'mime_type' => $file->getMimeType(),
				'size' => $this->prettySize( $file->getSize() ),
				'sha1' => $file->getSha1()
			]
		];
		if( $this->targetTitle instanceof \Title && $this->targetTitle->exists() ) {
			$targetFile = wfFindFile( $this->targetTitle );
			$fileInfo[ 'target' ] = [
				'name' => $targetFile->getName(),
				'url' => $targetFile->getUrl(),
				'extension' => $targetFile->getExtension(),
				'mime_type' => $targetFile->getMimeType(),
				'size' => $this->prettySize( $targetFile->getSize() ),
				'sha1' => $targetFile->getSha1()
			];
		}

		return $fileInfo;
	}

	// Isn't there already a method for this?
	protected function prettySize( $size ) {
		$size = $size / 1024;
		$unit = 'KB';
		if( $size > 1024 ) {
			$size = $size / 1024;
			$unit = 'MB';
		}
		$size = round( $size, 2 );
		return $size . $unit;
	}

	protected function getPageContentText( $title ) {
		$wikipage = \WikiPage::factory( $title );
		$content = $wikipage->getContent();
		return $content->getNativeData();
	}

	protected function verifyTitles() {
		if( ! $this->originTitle instanceof \Title ) {
			return false;
		}
		if( ! $this->targetTitle instanceof \Title ) {
			return false;
		}
		return true;
	}

	protected function displayInvalid() {
		$html = \Html::element( 'h3', [], wfMessage( 'ma-request-invalid' )->plain() );
		$html .= \Html::element( 'a',[
			'href' => $this->getTitle()->getLocalURL()
		], wfMessage( 'ma-back-to-overview' )->escaped() );
		$this->getOutput()->addHTML( $html );
	}

	protected function displayUnknownAction() {
		$html = \Html::element( 'h3', [], wfMessage( 'ma-action-unknown' )->plain() );
		$html .= \Html::element( 'a',[
			'href' => $this->getTitle()->getLocalURL()
		], wfMessage( 'ma-back-to-overview' )->escaped() );

		$this->getOutput()->addHTML( $html );
	}
}
