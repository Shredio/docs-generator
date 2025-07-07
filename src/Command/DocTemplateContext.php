<?php

declare(strict_types=1);

namespace Shredio\DocsGenerator\Command;

final readonly class DocTemplateContext
{

	/**
	 * @param array<string, string> $parameters
	 */
    public function __construct(
        public string $workingDir,
        public string $rootDir,
		public array $parameters = [],
    ) {
    }

	public function withWorkingDir(string $workingDir): self
	{
		return new self(
			$workingDir,
			$this->rootDir,
		);
	}

}
