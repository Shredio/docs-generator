<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;

interface ContentProcessor
{

	/**
	 * @throws GeneratingFailedException
	 */
	public function process(string $content, ProcessContext $context): string;

	/**
	 * @throws GeneratingFailedException
	 */
	public function postprocess(string $content, PostprocessContext $context): string;

}
