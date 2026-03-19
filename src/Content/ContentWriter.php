<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;

interface ContentWriter
{

	/**
	 * @return list<ContentToWrite>
	 *
	 * @throws GeneratingFailedException
	 */
	public function write(string $content, ContentWriterContext $context): array;

}
