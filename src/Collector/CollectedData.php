<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Collector;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;

final readonly class CollectedData
{

	/**
	 * @param array<non-empty-string, non-empty-string> $docs relativePath => description
	 */
	private function __construct(
		public array $docs,
	)
	{
	}

	/**
	 * @param array<non-empty-string, non-empty-string> $docs relativePath => description
	 *
	 * @throws GeneratingFailedException
	 */
	public static function create(array $docs, ?string $docsBasePath): self
	{
		if ($docs === []) {
			return new self([]);
		}

		if ($docsBasePath === null) {
			throw new GeneratingFailedException('Docs base path must be provided when there are collected docs.');
		}

		$newDocs = [];
		foreach ($docs as $path => $description) {
			$relativePath = trim($docsBasePath, '/') . '/' . ltrim($path, '/');
			$newDocs[$relativePath] = $description;
		}

		return new self($newDocs);
	}

}
