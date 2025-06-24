<?php

declare(strict_types=1);

namespace Shredio\DocsGenerator\Command;

final readonly class DocTemplateContext
{
    public function __construct(
        public string $workingDir,
        public string $rootDir,
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
