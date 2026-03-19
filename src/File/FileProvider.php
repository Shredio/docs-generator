<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\File;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;

interface FileProvider
{

	/**
	 * @return iterable<int, ProvidedFile>
	 *
	 * @throws GeneratingFailedException
	 */
	public function provide(): iterable;

}
