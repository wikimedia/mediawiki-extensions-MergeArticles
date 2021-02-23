<?php

namespace MergeArticles\Api;

class SetRelatedTitle extends \ApiBase {
	protected $targetTitle;
	protected $relatedTo;

	public function execute() {
		$this->status = \Status::newGood();

		$this->readInParameters();
		$this->setPageProps();
		$this->returnResults();
		
	}

	protected function getAllowedParams() {
		return [
			'pageID' => [
				\ApiBase::PARAM_TYPE => 'integer',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_HELP_MSG => '',
			],
			'relatedTo' => [
				\ApiBase::PARAM_TYPE => 'integer',
				\ApiBase::PARAM_REQUIRED => true,
				\ApiBase::PARAM_HELP_MSG => '',
			]
		];
	}

	protected function readInParameters() {
		$pageID = $this->getParameter( 'pageID' );
		$this->targetTitle = \Title::newFromID( $pageID );

		$this->relatedTo = $this->getParameter( 'relatedTo' );
	}

	protected function setPageProps() {
		if( !$this->targetTitle instanceof \Title || ! $this->targetTitle->exists() ) {
			$this->status = \Status::newFatal( 'invalid-origin' );
			return false;
		}
		if( !$this->targetTitle->userCan( 'merge-articles' ) ) {
			$this->status = \Status::newFatal( 'permissiondenied' );
			return false;
		}
		$relatedTitle = \Title::newFromID( $this->relatedTo );
		if( !$this->relatedTitle instanceof \Title || ! $this->relatedTitle->exists() ) {
			$this->status = \Status::newFatal( 'invalid-related-title' );
			return false;
		}
		$db = $this->getDB();
		if( $db->selectRow(
			'pageprops', [ '*' ],
			[
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto'
			]
		) ) {
			if( !$db->update( 'pageprops', [
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto',
				'pp_value' => $relatedTitle->getArticleID()
			], [
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto'
			] ) ) {
				$this->status = \Status::newFatal( 'db-error' );
				return false;
			}
		} else {
			if( !$db->insert( 'pageprops', [
				'pp_page' => $this->targetTitle->getArticleID(),
				'pp_propname' => 'relatedto',
				'pp_value' => $relatedTitle->getArticleID()
			] ) ) {
				$this->status = \Status::newFatal( 'db-error' );
				return false;
			}
		}
		return true;
	}

	protected function returnResults() {
		$result = $this->getResult();

		if( $this->status->isGood() ) {
			$result->addValue( null , 'success', 1 );
		} else {
			$result->addValue( null , 'success', 0 );
			$result->addValue( null , 'error', $this->status->getMessage() );
		}
	}
}
