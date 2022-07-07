<?php

namespace MergeArticles\Api;

use MediaWiki\MediaWikiServices;

class MergeNewPage extends MergeBase {

	/**
	 *
	 * @return array
	 */
	protected function getAllowedParams() {
		return parent::getAllowedParams() + [
			'target' => [
				static::PARAM_TYPE => 'string',
				static::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-target',
			],
		];
	}

	protected function readInParameters() {
		parent::readInParameters();

		$targetText = $this->getParameter( 'target' );
		$this->targetTitle = \Title::newFromText( $targetText );
		// Cannot skip file on new page
		$this->skipFile = false;
	}

	/**
	 * This might be an overkill, because we have target from the params,
	 * and we calculate it here
	 *
	 * @return bool
	 */
	protected function verifyTarget() {
		if ( $this->originTitle->getNamespace() === NS_DRAFT ) {
			$target = \Title::newFromText( $this->originTitle->getDBkey() );
		} elseif ( $this->originTitle->getNamespace() === NS_FILE ) {
			$draftFilePrefix = $this->getConfig()->get( 'MADraftFilePrefix' );
			$stripped = str_replace( $draftFilePrefix, '', $this->originTitle->getDBkey() );
			$target = \Title::makeTitle( NS_FILE, $stripped );
		}

		if ( !$target instanceof \Title || !$this->targetTitle instanceof \Title ) {
			$this->status = \Status::newFatal( 'invalid-target' );
			return false;
		}
		if ( $this->targetTitle->equals( $target ) === false ) {
			$this->status = \Status::newFatal( 'target-mismatch' );
			return false;
		}
		if ( $this->targetTitle->exists() ) {
			$this->status = \Status::newFatal( 'target-exists' );
			return false;
		}
		return true;
	}

	protected function mergeFile() {
		if ( method_exists( MediaWikiServices::class, 'getRepoGroup' ) ) {
			// MediaWiki 1.34+
			$file = MediaWikiServices::getInstance()->getRepoGroup()->findFile( $this->targetTitle );
		} else {
			$file = wfFindFile( $this->targetTitle );
		}
		if ( $file ) {
			$this->status = \Status::newFatal( 'target-file-exists' );
			return false;
		}

		return parent::mergeFile();
	}
}
