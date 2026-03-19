<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Path;

use Symfony\Component\Filesystem\Path as SymfonyPath;

final readonly class ResolvedPath
{

	/**
	 * @param non-empty-string $relativePath
	 * @param non-empty-string $absolutePath
	 */
	public function __construct(
		private string $relativePath,
		private string $absolutePath,
		private PathType $type,
	)
	{
	}

	/**
	 * @return non-empty-string
	 */
	public function getRelativePathToRootDir(): string
	{
		return $this->relativePath;
	}

	/**
	 * @return non-empty-string
	 */
	public function getAbsolutePath(): string
	{
		return $this->absolutePath;
	}

	public function getType(): PathType
	{
		return $this->type;
	}

	public function isFile(): bool
	{
		return $this->type === PathType::File;
	}

	public function isDirectory(): bool
	{
		return $this->type === PathType::Directory;
	}

	public function relativeTo(self $other): string
	{
		$basePath = $other->isFile() ? dirname($other->absolutePath) : $other->absolutePath;

		return SymfonyPath::makeRelative($this->absolutePath, $basePath);
	}

}