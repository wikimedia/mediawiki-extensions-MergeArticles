<?php

namespace MergeArticles\Special;

use MediaWiki\MediaWikiServices;
use MergeArticles\IPageFilter;
use MergeArticles\PageFilterFactory;
use Title;

class MergeArticles extends \SpecialPage {
	protected const TYPE_ARTICLE = 'article';
	protected const TYPE_CATEGORY = 'category';
	protected const TYPE_TEMPLATE = 'template';
	protected const TYPE_FILE = 'file';

	protected const ACTION_REVIEW = 'review';
	protected const ACTION_COMPARE = 'compare';

	protected $originTitle;
	protected $targetTitle;

	protected $dbr;

	public function __construct() {
		parent::__construct( "MergeArticles", "merge-articles" );
	}

	/**
	 *
	 * @param string $action
	 */
	public function execute( $action ) {
		parent::execute( $action );

		$this->getOutput()->enableOOUI();
		$this->getOutput()->addJsConfigVars( 'maBaseURL', $this->getPageTitle()->getLocalURL() );

		if ( !$action ) {
			$this->addOverview();
		} elseif ( $action === static::ACTION_REVIEW ) {
			$this->addReview();
		} elseif ( $action === static::ACTION_COMPARE ) {
			$this->addComparison();
		} else {
			$this->displayUnknownAction();
		}
	}

	protected function addOverview() {
		$this->getOutput()->addModules( 'ext.mergearticles.overview' );
		$this->getOutput()->addJsConfigVars( 'maAvailablePages', $this->getAvailablePages() );

		/** @var PageFilterFactory $filterFactory */
		$filterFactory = MediaWikiServices::getInstance()->getService(
			'MergeArticlesPageFilterFactory'
		);
		$this->getOutput()->addJsConfigVars( 'maFilterModules', $filterFactory->getRLModules() );
		$this->getOutput()->addJsConfigVars( 'maFilters', $filterFactory->getFiltersForClient() );
		$this->getOutput()->addHTML( \Html::element( 'div', [ 'id' => 'merge-articles-overview' ] ) );
	}

	protected function addReview() {
		$this->getOutput()->setPageTitle( wfMessage( 'mergearticles-sp-review' )->plain() );

		$originID = $this->getRequest()->getInt( 'originID', 0 );
		$targetText = $this->getRequest()->getText( 'targetText', '' );
		if ( !$originID || !$targetText ) {
			$this->displayInvalid();
			return;
		}
		$this->originTitle = \Title::newFromID( $originID );
		$this->targetTitle = \Title::newFromText( $targetText );
		if ( $this->verifyTitles() === false ) {
			$this->displayInvalid();
			return;
		}
		if ( $this->isFile() && !$this->isValidFile() ) {
			$this->displayInvalid();
			return;
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
		if ( $this->isFile() ) {
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
		$this->getOutput()->setPageTitle( wfMessage( 'mergearticles-sp-compare' )->plain() );

		$originID = $this->getRequest()->getInt( 'originID', 0 );
		$targetID = $this->getRequest()->getInt( 'targetID', 0 );
		if ( !$originID || !$targetID ) {
			$this->displayInvalid();
			return;
		}

		$this->originTitle = \Title::newFromID( $originID );
		$this->targetTitle = \Title::newFromID( $targetID );
		if ( $this->verifyTitles() === false ) {
			$this->displayInvalid();
			return;
		}

		if ( $this->isFile() && !$this->isValidFile() ) {
			$this->displayInvalid();
			return;
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

		if ( $this->isFile() ) {
			$compareData[ 'fileData' ] = $this->getFileInfo();
			$this->getOutput()->addHTML( $this->getFileDiffHeader() );
			$this->getOutput()->addHTML( $this->getFileDiffHTML() );
		}

		$diff = $this->getDiff();
		if ( $diff->isEmpty() === false ) {
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

	/**
	 *
	 * @return string
	 */
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

	/**
	 *
	 * @return \OOUI\ButtonWidget
	 */
	protected function getOverviewButton() {
		$button = new \OOUI\ButtonWidget( [
			'infusable' => true,
			'framed' => false,
			'icon' => 'previous',
			// make "arrowPrevious" in newer OOJS,
			'title' => wfMessage( 'mergearticles-back-to-overview' )->plain(),
			'id' => 'ma-overview-button',
			'href' => $this->getPageTitle()->getLocalURL()
		] );
		$button->addClasses( [ 'ma-back-to-overview-button' ] );
		return $button;
	}

	/**
	 *
	 * @param bool|false $exists
	 * @return string
	 */
	protected function getHelpHTML( $exists = false ) {
		$targetPage = $this->targetTitle->getPrefixedText();
		$icon = new \OOUI\IconWidget( [
			'icon' => 'info'
		] );
		$labelKey = 'mergearticles-merge-new-help';
		if ( $exists ) {
			$labelKey = 'mergearticles-merge-existing-help';
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

	/**
	 *
	 * @param array $stats
	 * @return string
	 */
	protected function getDiffHeader( $stats ) {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-diff-header' ] );
		$header .= \Html::element( 'span', [
			'class' => 'ma-diff-header-label'
		], wfMessage( 'mergearticles-diff-header' )->escaped() );
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

	/**
	 *
	 * @return string
	 */
	protected function getReviewHeader() {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-review-header' ] );
		$header .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-review-header' )->plain()
		] );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	/**
	 * Files
	 * @return string
	 */
	protected function getFileDiffHeader() {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-diff-header diff-file' ] );
		$header .= \Html::element( 'span', [
			'class' => 'ma-file-diff-header-label'
		], wfMessage( 'mergearticles-file-diff-header' )->escaped() );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	/**
	 *
	 * @return string
	 */
	protected function getFileReviewHeader() {
		$header = \Html::openElement( 'div', [ 'class' => 'ma-review-header review-file' ] );
		$header .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-layout-label' )->plain()
		] );
		$header .= \Html::closeElement( 'div' );
		return $header;
	}

	/**
	 *
	 * @return string
	 */
	protected function getFileDiffHTML() {
		$fileInfo = $this->getFileInfo();

		$html = \Html::openElement( 'div', [ 'class' => 'ma-file-diff' ] );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-origin-file' ] );
		$originLabel = new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-origin-header' )->plain()
		] );
		$originLabel->addClasses( [ 'ma-file-header-label' ] );
		$html .= $originLabel;
		$html .= $this->fileHTMLFromInfo( $fileInfo[ 'origin' ] );
		$html .= \Html::closeElement( 'div' );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-target-file' ] );
		$targetLabel = new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-target-header' )->plain()
		] );
		$targetLabel->addClasses( [ 'ma-file-header-label' ] );
		$html .= $targetLabel;
		$html .= $this->fileHTMLFromInfo( $fileInfo[ 'target' ] );
		$html .= \Html::closeElement( 'div' );
		$html .= \Html::closeElement( 'div' );

		return $html;
	}

	/**
	 *
	 * @return string
	 */
	protected function getFileReviewHTML() {
		$fileInfo = $this->getFileInfo();

		$html = \Html::openElement( 'div', [ 'class' => 'ma-file-diff' ] );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-origin-file' ] );
		$html .= $this->fileHTMLFromInfo( $fileInfo[ 'origin' ] );
		$html .= \Html::closeElement( 'div' );
		$html .= \Html::closeElement( 'div' );

		return $html;
	}

	/**
	 *
	 * @param array $info
	 * @return string
	 */
	protected function fileHTMLFromInfo( $info ) {
		$html = \Html::openElement( 'div', [ 'class' => 'ma-file-layout' ] );
		$html .= \Html::openElement( 'div', [ 'class' => 'ma-review-file-info' ] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-info-name', $info[ 'name' ] )->plain()
		] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-info-extension', $info[ 'extension' ] )->plain()
		] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-info-mime', $info[ 'mime_type' ] )->plain()
		] );
		$html .= new \OOUI\LabelWidget( [
			'label' => wfMessage( 'mergearticles-file-info-size', $info[ 'size' ] )->plain()
		] );
		$html .= \Html::closeElement( 'div' );
		if ( strpos( $info[ 'mime_type' ], 'image' ) !== false ) {
			$html .= \Html::element( 'div', [
				'class' => 'ma-file-preview-image',
				'style' => 'background-image: url( ' . $info[ 'url' ] . ' )'
			] );
		}
		$html .= \Html::closeElement( 'div' );
		return $html;
	}

	/**
	 * Overview
	 * @return array
	 */
	protected function getAvailablePages() {
		$this->dbr = wfGetDB( DB_REPLICA );

		$availablePages = [];
		$this->getPages( $availablePages );
		$this->getFiles( $availablePages );

		return $availablePages;
	}

	/**
	 *
	 * @param array &$availablePages
	 */
	protected function getPages( &$availablePages ) {
		$res = $this->dbr->select(
			'page',
			[ 'page_title' ],
			[ 'page_namespace' => NS_DRAFT ]
		);

		foreach ( $res as $row ) {
			$draftTitle = \Title::makeTitle( NS_DRAFT, $row->page_title );
			$title = \Title::newFromText( $row->page_title );

			if ( $title instanceof \Title === false ) {
				continue;
			}

			$type = static::TYPE_ARTICLE;
			switch ( $title->getNamespace() ) {
				case NS_TEMPLATE:
					$type = static::TYPE_TEMPLATE;
					break;
				case NS_CATEGORY:
					$type = static::TYPE_CATEGORY;
					break;
			}

			$data = $this->getItemData( $draftTitle, $title );
			$data['type'] = $type;
			$availablePages[$type][] = $data;
		}
	}

	/**
	 *
	 * @param array &$availablePages
	 */
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

		foreach ( $res as $row ) {
			$draftTitle = \Title::makeTitle( NS_FILE, $row->page_title );
			$stripped = str_replace( $draftFilePrefix, '', $row->page_title );
			$title = \Title::makeTitle( NS_FILE, $stripped );

			if ( $title instanceof \Title === false ) {
				continue;
			}

			$data = $this->getItemData( $draftTitle, $title );
			$data['type'] = static::TYPE_FILE;
			$availablePages[static::TYPE_FILE][] = $data;
		}
	}

	/**
	 * Get client-side data for the page
	 *
	 * @param Title $draftTitle
	 * @param Title $title
	 * @return array
	 */
	private function getItemData( $draftTitle, $title ) {
		/** @var PageFilterFactory $filterFactory */
		$filterFactory = MediaWikiServices::getInstance()->getService(
			'MergeArticlesPageFilterFactory'
		);
		$originData = [
			'id' => $draftTitle->getArticleID(),
			'text' => $draftTitle->getPrefixedText(),
			'url' => $draftTitle->getLocalURL()
		];
		/**
		 * @var string $name
		 * @var IPageFilter $filter
		 */
		foreach ( $filterFactory->getFilters() as $name => $filter ) {
			$originData = array_merge(
				$filter->getFilterableData( $draftTitle ), $originData
			);
		}
		return [
			'origin' => $originData,
			'target' => [
				'id' => $title->getArticleID(),
				'exists' => $title->exists(),
				'text' => $title->getPrefixedText(),
				'url' => $title->getLocalURL()
			]
		];
	}

	/**
	 * Utility functions
	 * @return \Diff
	 */
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
		if ( $this->originTitle->getNamespace() !== NS_FILE ) {
			return false;
		}
		if ( $this->targetTitle && $this->targetTitle->getNamespace() !== NS_FILE ) {
			return false;
		}
		return true;
	}

	protected function isValidFile() {
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$fileRepo = MediaWikiServices::getInstance()->getRepoGroup();
		} else {
			$fileRepo = \RepoGroup::singleton();
		}
		$file = $fileRepo->findFile( $this->originTitle );
		if ( !$file ) {
			return false;
		}
		if ( $this->targetTitle instanceof \Title && $this->targetTitle->exists() ) {
			$file = $fileRepo->findFile( $this->targetTitle );
			if ( !$file ) {
				return false;
			}
		}
		return true;
	}

	/**
	 *
	 * @return array
	 */
	protected function getFileInfo() {
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$fileRepo = MediaWikiServices::getInstance()->getRepoGroup();
		} else {
			$fileRepo = \RepoGroup::singleton();
		}
		$file = $fileRepo->findFile( $this->originTitle );

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
		if ( $this->targetTitle instanceof \Title && $this->targetTitle->exists() ) {
			$targetFile = $fileRepo->findFile( $this->targetTitle );
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

	/**
	 *
	 * @param int $size
	 * @return string
	 */
	protected function prettySize( $size ) {
		$size = $size / 1024;
		$unit = 'KB';
		if ( $size > 1024 ) {
			$size = $size / 1024;
			$unit = 'MB';
		}
		$size = round( $size, 2 );
		return $size . $unit;
	}

	/**
	 *
	 * @param Title $title
	 * @return string
	 */
	protected function getPageContentText( $title ) {
		if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
			// MW 1.36+
			$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
		} else {
			$wikipage = \WikiPage::factory( $title );
		}
		/** @var \TextContent $content */
		$content = $wikipage->getContent();
		if ( !$content instanceof \TextContent ) {
			return '';
		}
		return $content->getNativeData();
	}

	protected function verifyTitles() {
		if ( !$this->originTitle instanceof \Title ) {
			return false;
		}
		if ( !$this->targetTitle instanceof \Title ) {
			return false;
		}
		return true;
	}

	protected function displayInvalid() {
		$html = \Html::element( 'h3', [], wfMessage( 'mergearticles-request-invalid' )->plain() );
		$html .= \Html::element( 'a', [
			'href' => $this->getPageTitle()->getLocalURL()
		], wfMessage( 'mergearticles-back-to-overview' )->escaped() );
		$this->getOutput()->addHTML( $html );
	}

	protected function displayUnknownAction() {
		$html = \Html::element( 'h3', [], wfMessage( 'mergearticles-action-unknown' )->plain() );
		$html .= \Html::element( 'a', [
			'href' => $this->getPageTitle()->getLocalURL()
		], wfMessage( 'mergearticles-back-to-overview' )->escaped() );

		$this->getOutput()->addHTML( $html );
	}
}
