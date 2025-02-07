<?php

namespace MergeArticles\Api;

use ContentHandler;
use MediaWiki\Api\ApiBase;
use MediaWiki\Api\ApiMain;
use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\HookContainer\HookContainer;
use MediaWiki\MediaWikiServices;
use MediaWiki\Message\Message;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;
use Throwable;
use Wikimedia\ParamValidator\ParamValidator;

class MergeBase extends ApiBase {
	protected $originTitle;
	protected $targetTitle;
	protected $text;
	protected $status;

	protected $editFlag = 1;

	/** @var bool */
	protected $skipFile = false;

	/** @var HookContainer */
	private $hookContainer;

	/**
	 * @param ApiMain $mainModule
	 * @param string $moduleName
	 * @param HookContainer $hookContainer
	 */
	public function __construct(
		ApiMain $mainModule, $moduleName, HookContainer $hookContainer
	) {
		parent::__construct( $mainModule, $moduleName, '' );
		$this->hookContainer = $hookContainer;
	}

	public function execute() {
		$this->status = Status::newGood();

		$this->readInParameters();
		if ( !$this->verifyOrigin() ) {
			$this->returnResults();
			return;
		}
		if ( !$this->verifyTarget() ) {
			$this->returnResults();
			return;
		}
		$this->verifyPermissions();

		if ( !$this->merge() ) {
			$this->returnResults();
			return;
		}
		$this->removeOrigin();
		$this->returnResults();
	}

	/**
	 *
	 * @return array
	 */
	protected function getAllowedParams() {
		return [
			'pageID' => [
				ParamValidator::PARAM_TYPE => 'integer',
				ParamValidator::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-pageid',
			],
			'text' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => false,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-text',
			],
			'skipFile' => [
				ParamValidator::PARAM_TYPE => 'boolean',
				ParamValidator::PARAM_REQUIRED => false,
				ParamValidator::PARAM_DEFAULT => false,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-skipfile',
			]
		];
	}

	protected function readInParameters() {
		$pageID = $this->getParameter( 'pageID' );
		$this->originTitle = Title::newFromID( $pageID );

		$this->text = $this->getParameter( 'text' );
		$this->skipFile = $this->getParameter( 'skipFile' );
	}

	protected function verifyOrigin() {
		if ( !$this->originTitle instanceof Title || !$this->originTitle->exists() ) {
			$this->status = Status::newFatal( 'invalid-origin' );
			return false;
		}
		return true;
	}

	protected function verifyTarget() {
		if ( $this->targetTitle instanceof Title === false ) {
			$this->status = Status::newFatal( 'target-invalid' );
			return false;
		}
		return true;
	}

	protected function merge() {
		if ( $this->isFile() && $this->shouldMergeFile() ) {
			$this->mergeFile();
		}

		$text = $this->text;
		$targetTitle = $this->targetTitle;
		$this->hookContainer->run( 'MergeArticlesBeforeMergePage', [
			&$text,
			&$targetTitle,
			$this->originTitle
		] );
		$content = ContentHandler::makeContent( $text, $targetTitle );
		$status = null;
		try {
			$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $targetTitle );
			$updater = $wikipage->newPageUpdater( $this->getUser() );
			$updater->setContent( SlotRecord::MAIN, $content );
			$rev = $updater->saveRevision(
				CommentStoreComment::newUnsavedComment( 'Merge articles' ), $this->editFlag
			);
			$status = $updater->getStatus();
		} catch ( Throwable $ex ) {
			$rev = null;
		}
		if ( !$rev ) {
			if ( $status ) {
				$errors = $status->getErrors();
				if ( $errors && isset( $errors[0]['message'] ) && $errors[0]['message'] === 'edit-no-change' ) {
					$this->status = Status::newGood();
					return true;
				}
			}
			$this->status = $status ?? Status::newFatal( 'mergearticles-merge-fail-header' );
			return false;
		}

		$this->hookContainer->run( 'MergeArticlesAfterMergePage', [
			$targetTitle,
			$this->originTitle
		] );
		return true;
	}

	protected function isFile(): bool {
		if ( $this->originTitle->getNamespace() === NS_FILE ) {
			return true;
		}
		return false;
	}

	protected function mergeFile() {
		$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->originTitle );
		if ( !$file ) {
			$this->status = Status::newFatal( 'invalid-file' );
			return false;
		}

		return $this->uploadFile( $file );
	}

	/**
	 *
	 * @param \LocalFile $file
	 * @return bool
	 */
	protected function uploadFile( \LocalFile $file ) {
		$services = MediaWikiServices::getInstance();
		$localFileRepo = $services->getRepoGroup()->getLocalRepo();

		$uploadStash = new \UploadStash( $localFileRepo, $this->getUser() );
		$uploadFile = $uploadStash->stashFile( $file->getLocalRefPath(), "file" );
		$targetFileName = $this->targetTitle->getDBkey();

		if ( $uploadFile === false ) {
			$this->status = Status::newFatal( 'upload-file-creation-error' );
			return false;
		}

		$uploadFromStash = new \UploadFromStash( $this->getUser(), $uploadStash, $localFileRepo );
		$uploadFromStash->initialize( $uploadFile->getFileKey(), $targetFileName );
		$status = $uploadFromStash->performUpload( 'Merge articles upload', $this->text, true, $this->getUser() );
		$uploadFromStash->cleanupTempFile();

		$newFile = $localFileRepo->newFile( $targetFileName );
		if ( !$status->isGood() ) {
			$errors = $status->getErrors();
			if ( !empty( $errors ) && $errors[0]['message'] === 'fileexists-no-change' ) {
				return true;
			}
			$this->status = Status::newFatal( 'upload-error' );
			return false;
		}
		return true;
	}

	protected function removeOrigin() {
		if ( $this->isFile() ) {
			$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->originTitle );
			$file->deleteFile( 'Article merge', $this->getUser() );
		}

		$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $this->originTitle );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$wikipage,
			$this->getUser()
		);

		return $deletePage
			->setSuppress( true )
			->keepLegacyHookErrorsSeparate()
			->deleteUnsafe( 'Article merge' );
	}

	protected function returnResults() {
		$result = $this->getResult();

		if ( $this->status->isGood() ) {
			$targetPage = [
				'text' => $this->targetTitle->getPrefixedText(),
				'url' => $this->targetTitle->getLocalURL()
			];

			$result->addValue( null, 'success', 1 );
			$result->addValue( null, 'targetPage', $targetPage );
		} else {
			$result->addValue( null, 'success', 0 );
			$result->addValue( null, 'error', $this->status->getMessage() );
		}
	}

	private function shouldMergeFile() {
		return !$this->skipFile;
	}

	protected function verifyPermissions() {
		if ( !$this->getUser()->isAllowed( 'merge-articles' ) ) {
			$this->dieWithError(
				Message::newFromKey( 'apierror-permissiondenied' )
					->params( 'merge-articles' )
			);
		}
	}
}
