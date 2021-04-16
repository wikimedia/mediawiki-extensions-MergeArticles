<?php

namespace MergeArticles\Api;

class MergeExistingPage extends MergeBase {
	protected $editFlag = 2;

	/**
	 *
	 * @return array
	 */
	protected function getAllowedParams() {
		return parent::getAllowedParams() + [
			'targetID' => [
				static::PARAM_TYPE => 'integer',
				static::PARAM_REQUIRED => true,
				static::PARAM_HELP_MSG => 'mergearticles-apihelp-param-targetid',
			],
		];
	}

	protected function readInParameters() {
		parent::readInParameters();

		$targetID = $this->getParameter( 'targetID' );
		$this->targetTitle = \Title::newFromID( $targetID );
	}

	protected function verifyTarget() {
		if ( $this->targetTitle->exists() === false ) {
			$this->status = \Status::newFatal( 'target-does-not-exist' );
			return false;
		}
		return true;
	}
}
