<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Markdown;

final readonly class MarkdownHeader
{

	public function __construct(
		public string $name,
		public string $value,
	)
	{
	}

}
