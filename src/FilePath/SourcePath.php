<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\FilePath;

use Symfony\Component\Filesystem\Path;

final readonly class SourcePath
{

	/** @var non-empty-string */
	public string $path;

	/** @var non-empty-string */
	public string $relativePathFromRoot;

	public function __construct(string $path, RootPath $rootPath)
	{
		if (!Path::isAbsolute($path) || $path === '') {
			throw new \InvalidArgumentException('Path must be absolute.');
		}

		$normalizedPath = Path::normalize($path);
		if (!str_starts_with($normalizedPath, $rootPath->path)) {
			throw new \InvalidArgumentException(sprintf('Path "%s" is not inside root path "%s".', $normalizedPath, $rootPath->path));
		}
		if ($normalizedPath === '') {
			throw new \InvalidArgumentException('Path must not be empty.');
		}

		$this->path = $normalizedPath;
		$relativePathFromRoot = Path::makeRelative($normalizedPath, $rootPath->path);
		if ($relativePathFromRoot === '' || $relativePathFromRoot === '.') {
			throw new \InvalidArgumentException('Path must not be the root path itself.');
		}

		$this->relativePathFromRoot = $relativePathFromRoot;
	}

}
