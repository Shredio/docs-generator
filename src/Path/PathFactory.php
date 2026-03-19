<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Path;

use InvalidArgumentException;
use Symfony\Component\Filesystem\Path;

final readonly class PathFactory
{

	/** @var non-empty-string */
	private string $rootDir;

	/**
	 * @param non-empty-string $rootDir
	 */
	public function __construct(string $rootDir)
	{
		$rootDir = $this->normalizePath($rootDir);

		if (!Path::isAbsolute($rootDir)) {
			throw new InvalidArgumentException('Path must be absolute.');
		}

		$this->rootDir = $rootDir;
	}

	public function createFileFromAbsolute(string $absolutePath): ResolvedPath
	{
		return $this->createResolvedPath($absolutePath, PathType::File);
	}

	public function createDirectoryFromAbsolute(string $absolutePath): ResolvedPath
	{
		return $this->createResolvedPath($absolutePath, PathType::Directory);
	}

	public function createFromAbsolute(string $absolutePath): ResolvedPath
	{
		$normalizedPath = $this->normalizePath($absolutePath);
		$type = is_dir($normalizedPath) ? PathType::Directory : PathType::File;

		return $this->createResolvedPath($absolutePath, $type);
	}

	public function createFileFromRelative(string $relativePath): ResolvedPath
	{
		return $this->createResolvedPathFromRelative($relativePath, PathType::File);
	}

	public function createDirectoryFromRelative(string $relativePath): ResolvedPath
	{
		return $this->createResolvedPathFromRelative($relativePath, PathType::Directory);
	}

	public function createFromRelative(string $relativePath): ResolvedPath
	{
		$absolutePath = Path::join($this->rootDir, $relativePath);
		$type = is_dir($absolutePath) ? PathType::Directory : PathType::File;

		return $this->createResolvedPathFromRelative($relativePath, $type);
	}

	private function createResolvedPathFromRelative(string $relativePath, PathType $type): ResolvedPath
	{
		if ($relativePath === '') {
			throw new InvalidArgumentException('Path must not be empty.');
		}

		if (Path::isAbsolute($relativePath)) {
			throw new InvalidArgumentException('Path must be relative.');
		}

		$normalized = Path::canonicalize($relativePath);

		if ($normalized === '' || $normalized === '.') {
			throw new InvalidArgumentException('Path must not be empty.');
		}

		if (str_starts_with($normalized, '..')) {
			throw new InvalidArgumentException(sprintf('Path "%s" is not within the root directory.', $relativePath));
		}

		$absolutePath = sprintf('%s/%s', $this->rootDir, $normalized);

		return new ResolvedPath($normalized, $absolutePath, $type);
	}

	private function createResolvedPath(string $absolutePath, PathType $type): ResolvedPath
	{
		$absolutePath = $this->normalizePath($absolutePath);

		if (!Path::isAbsolute($absolutePath)) {
			throw new InvalidArgumentException('Path must be absolute.');
		}

		$relativePath = Path::makeRelative($absolutePath, $this->rootDir);

		if ($relativePath === '' || $relativePath === '.') {
			throw new InvalidArgumentException(sprintf('Path "%s" must not be the root directory itself.', $absolutePath));
		}

		if (str_starts_with($relativePath, '..')) {
			throw new InvalidArgumentException(sprintf('Path "%s" is not within the root directory "%s".', $absolutePath, $this->rootDir));
		}

		return new ResolvedPath($relativePath, $absolutePath, $type);
	}

	/**
	 * @return non-empty-string
	 */
	private function normalizePath(string $path): string
	{
		$path = rtrim(Path::normalize($path), '/');

		if ($path === '/' || $path === '') {
			throw new InvalidArgumentException('Directory cannot be empty string or "/" path.');
		}

		return $path;
	}

}