<?php

use MediaWiki\Context\RequestContext;
use MediaWiki\MediaWikiServices;
use MergeArticles\PageFilterFactory;

return [
	'MergeArticlesPageFilterFactory' => static function ( MediaWikiServices $services ) {
		return new PageFilterFactory(
			ExtensionRegistry::getInstance()->getAttribute( 'MergeArticlesPageFilters' ),
			RequestContext::getMain()
		);
	}
];
