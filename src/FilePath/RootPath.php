<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\FilePath;

use Symfony\Component\Filesystem\Path;

final readonly class RootPath
{

	public string $path;

	public function __construct(string $absolutePath)
	{
		if (!Path::isAbsolute($absolutePath)) {
			throw new \InvalidArgumentException('Path must be absolute.');
		}

		$this->path = Path::normalize($absolutePath);
	}

	public function getAbsolutePath(string $relativeFilePath): string
	{
		return Path::join($this->path, $relativeFilePath);
	}

}
