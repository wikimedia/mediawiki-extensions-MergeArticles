<?php

namespace MergeArticles\Api;

use MergeArticles\Api\MergeBase;

class MergeExistingPage extends MergeBase {
	protected $editFlag = 2;

	protected function getAllowedParams() {
		return parent::getAllowedParams() + [
			'targetID' => [
				\ApiBase::PARAM_TYPE => 'integer',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_HELP_MSG => 'apihelp-ma-target-help',
			],
		];
	}

	protected function readInParameters() {
		parent::readInParameters();

		$targetID = $this->getParameter( 'targetID' );
		$this->targetTitle = \Title::newFromID( $targetID );
	}

	protected function verifyTarget() {
		if( $this->targetTitle->exists() === false ) {
			$this->status = \Status::newFatal( 'target-does-not-exist' );
			return false;
		}
		return true;
	}
}
