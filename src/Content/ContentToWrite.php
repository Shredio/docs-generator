<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Content;

final readonly class ContentToWrite
{

	public function __construct(
		public string $relativePath,
		public string $content,
	)
	{
	}

}
