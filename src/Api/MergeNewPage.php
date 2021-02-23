<?php

namespace MergeArticles\Api;

use MergeArticles\Api\MergeBase;

class MergeNewPage extends MergeBase {

	protected function getAllowedParams() {
		return parent::getAllowedParams() + [
			'target' => [
				\ApiBase::PARAM_TYPE => 'string',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_HELP_MSG => 'apihelp-ma-target-help',
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
	 * @return boolean
	 */
	protected function verifyTarget() {
		if( $this->originTitle->getNamespace() === NS_DRAFT ) {
			$target = \Title::newFromText( $this->originTitle->getDBkey() );
		} else if( $this->originTitle->getNamespace() === NS_FILE ) {
			$draftFilePrefix = $this->getConfig()->get( 'MADraftFilePrefix' );
			$stripped = str_replace( $draftFilePrefix, '', $this->originTitle->getDBkey() );
			$target = \Title::makeTitle( NS_FILE, $stripped );
		}

		if( !$target instanceof \Title || ! $this->targetTitle instanceof \Title ) {
			$this->status = \Status::newFatal( 'invalid-target' );
			return false;
		}
		if( $this->targetTitle->equals( $target ) === false ) {
			$this->status = \Status::newFatal( 'target-mismatch' );
			return false;
		}
		if( $this->targetTitle->exists() ) {
			$this->status = \Status::newFatal( 'target-exists' );
			return false;
		}
		return true;
	}

	protected function mergeFile() {
		if( wfFindFile( $this->targetTitle ) ) {
			$this->status = \Status::newFatal( 'target-file-exists' );
			return false;
		}

		return parent::mergeFile();
	}
}
