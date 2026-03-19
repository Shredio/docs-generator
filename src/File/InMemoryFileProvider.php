<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\File;

final class InMemoryFileProvider implements FileProvider
{

	/** @var list<ProvidedFile> */
	private array $files = [];

	public function add(ProvidedFile $file): void
	{
		$this->files[] = $file;
	}

	public function provide(): iterable
	{
		return $this->files;
	}

}
