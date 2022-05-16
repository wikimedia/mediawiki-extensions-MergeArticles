<?php

namespace MergeArticles\Hooks;

use Title;

interface MergeArticlesBeforeMergePageHook {

	/**
	 * @param string &$text
	 * @param Title &$target
	 * @param Title $origin
	 */
	public function onMergeArticlesBeforeMergePage( &$text, Title &$target, Title $origin ): void;
}
