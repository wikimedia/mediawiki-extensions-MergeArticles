<?php

namespace MergeArticles\Api;

use MediaWiki\MediaWikiServices;

class MergeBase extends \ApiBase {
	protected $originTitle;
	protected $targetTitle;
	protected $text;
	protected $status;

	protected $editFlag = 1;

	/** @var bool */
	protected $skipFile = false;

	public function execute() {
		$this->status = \Status::newGood();

		$this->readInParameters();
		if ( !$this->verifyOrigin() ) {
			return $this->returnResults();
		}
		if ( !$this->verifyTarget() ) {
			return $this->returnResults();
		}
		$this->verifyPermissions();
		if ( !$this->merge() ) {
			return $this->returnResults();
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
				static::PARAM_TYPE => 'integer',
				static::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-pageid',
			],
			'text' => [
				static::PARAM_TYPE => 'string',
				static::PARAM_REQUIRED => false,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-text',
			],
			'skipFile' => [
				static::PARAM_TYPE => 'boolean',
				static::PARAM_REQUIRED => false,
				static::PARAM_DFLT => false,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-skipfile',
			]
		];
	}

	protected function readInParameters() {
		$pageID = $this->getParameter( 'pageID' );
		$this->originTitle = \Title::newFromID( $pageID );

		$this->text = $this->getParameter( 'text' );
		$this->skipFile = $this->getParameter( 'skipFile' );
	}

	protected function verifyOrigin() {
		if ( !$this->originTitle instanceof \Title || !$this->originTitle->exists() ) {
			$this->status = \Status::newFatal( 'invalid-origin' );
			return false;
		}
		return true;
	}

	protected function verifyTarget() {
		if ( $this->targetTitle instanceof \Title === false ) {
			$this->status = \Status::newFatal( 'target-invalid' );
			return false;
		}
		return true;
	}

	protected function merge() {
		if ( $this->isFile() && $this->shouldMergeFile() ) {
			$this->mergeFile();
		}

		$content = \ContentHandler::makeContent( $this->text, $this->targetTitle );
		$wikipage = \WikiPage::factory( $this->targetTitle );
		$status = $wikipage->doEditContent(
			$content,
			"Merge articles",
			$this->editFlag,
			false,
			$this->getUser()
		);
		if ( $status->isOK() === false ) {
			$this->status = $status;
			return false;
		}
		return true;
	}

	protected function isFile() {
		if ( $this->originTitle->getNamespace() === NS_FILE ) {
			return true;
		}
		return false;
	}

	protected function mergeFile() {
		$file = wfFindFile( $this->originTitle );
		if ( !$file ) {
			$this->status = \Status::newFatal( 'invalid-file' );
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
		if ( method_exists( $services, 'getRepoGroup' ) ) {
			// MW 1.34+
			$localFileRepo = $services->getRepoGroup()->getLocalRepo();
		} else {
			$localFileRepo = \RepoGroup::singleton()->getLocalRepo();
		}

		$uploadStash = new \UploadStash( $localFileRepo, $this->getUser() );
		$uploadFile = $uploadStash->stashFile( $file->getLocalRefPath(), "file" );
		$targetFileName = $this->targetTitle->getDBkey();

		if ( $uploadFile === false ) {
			$this->status = \Status::newFatal( 'upload-file-creation-error' );
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
			$this->status = \Status::newFatal( 'upload-error' );
			return false;
		}
		return true;
	}

	protected function removeOrigin() {
		$article = \Article::newFromTitle( $this->originTitle, $this->getContext() );
		if ( $this->isFile() ) {
			$file = wfFindFile( $this->originTitle );
			$file->delete( 'Article merge' );
		}
		return $article->doDeleteArticle( 'Article merged' );
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
				\Message::newFromKey( 'apierror-permissiondenied' )
					->params( 'merge-articles' )
			);
		}
	}
}
