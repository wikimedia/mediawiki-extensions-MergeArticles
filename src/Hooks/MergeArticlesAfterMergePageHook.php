<?php

namespace MergeArticles\Hooks;

use Title;

interface MergeArticlesAfterMergePageHook {

	/**
	 * @param Title $target
	 * @param Title $origin
	 */
	public function onMergeArticlesAfterMergePage( Title $target, Title $origin ): void;
}
