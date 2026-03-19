<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\File;

use Shredio\DocsGenerator\Path\ResolvedPath;

final readonly class ProvidedFile
{

	public bool $isMainFile;

	/**
	 * @param mixed[] $frontmatter
	 * @param non-empty-string $content
	 */
	public function __construct(
		public ResolvedPath $path,
		public array $frontmatter,
		public string $content,
		public int $priority,
	)
	{
		$this->isMainFile = $this->priority === -1;
	}

}
