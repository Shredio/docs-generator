<?php

declare(strict_types=1);

namespace Shredio\DocsGenerator\Command;

use LogicException;

final readonly class DocTemplateContext
{

	/** @var callable(string $filePath, string $targetDir, array<string, string> $parameters, ?string $contentToProcess): string */
	private mixed $parse;

	/**
	 * @param array<string, string> $parameters
	 * @param callable(string $filePath, string $targetDir, array<string, string> $parameters, ?string $contentToProcess): string $parse
	 */
	public function __construct(
		public string $workingDir,
		public string $rootDir,
		public string $targetDir,
		public array $parameters,
		callable $parse,
	) {
		$this->parse = $parse;
	}

	public function withWorkingDir(string $workingDir): self
	{
		return new self(
			$workingDir,
			$this->rootDir,
			$this->targetDir,
			$this->parameters,
			$this->parse,
		);
	}

	public function parse(string $filePath, string $targetDir, ?string $contentToProcess = null): string
	{
		return ($this->parse)($filePath, $targetDir, $this->parameters, $contentToProcess);
	}

	public function getFileImportFromRoot(string $path): string
	{
		if (str_starts_with($path, '/')) {
			throw new LogicException(sprintf('Path "%s" is not relative.', $path));
		}

		$level = $this->getRelativeLevel($this->rootDir, $this->targetDir);
		if ($level === 0) {
			return '@' . $path;
		}

		return '@' . str_repeat('../', $level) . ltrim($path, '/');
	}

	private function getRelativeLevel(string $rootDir, string $path): int
	{
		if (!str_starts_with($path, $rootDir)) {
			throw new LogicException(sprintf('Path "%s" is not in root directory "%s".', $path, $rootDir));
		}

		$relative = substr($path, strlen($rootDir));
		$relative = trim($relative, '/');
		if ($relative === '') {
			return 0;
		}

		$parts = explode('/', $relative);
		return count($parts);
	}

}
