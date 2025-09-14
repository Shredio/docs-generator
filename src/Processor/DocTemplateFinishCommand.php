<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor;

interface DocTemplateFinishCommand
{

	public function finish(string $rootDir): void;

}
