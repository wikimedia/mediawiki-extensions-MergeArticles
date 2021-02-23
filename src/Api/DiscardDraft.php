<?php

namespace MergeArticles\Api;

class DiscardDraft extends MergeBase {

	public function execute() {
		$this->status = \Status::newGood();

		$this->readInParameters();
		if( !$this->verifyOrigin() ) {
			return $this->returnResults();
		}
		$this->verifyPermissions();
		$this->removeOrigin();
		$this->returnResults();

	}

	protected function getAllowedParams() {
		return [
			'pageID' => [
				\ApiBase::PARAM_TYPE => 'integer',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_HELP_MSG => 'apihelp-ma-page-id-help',
			]
		];
	}

	protected function readInParameters() {
		$pageID = $this->getParameter( 'pageID' );
		$this->originTitle = \Title::newFromID( $pageID );
	}

	protected function returnResults() {
		$result = $this->getResult();
		if( $this->status->isGood() ) {
			$result->addValue( null , 'success', 1 );
			$result->addValue( null, 'title', $this->originTitle->getPrefixedText() );
		} else {
			$result->addValue( null , 'success', 0 );
			$result->addValue( null , 'error', $this->status->getMessage() );
		}
	}
}
