<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor;

use Shredio\DocsGenerator\Command\DocTemplateContext;

interface DocTemplateCommandInterface
{
	public function getName(): string;

	public function reset(): void;

	/**
	 * @param non-empty-list<string> $args
	 */
	public function invoke(DocTemplateContext $context, array $args): string;
}
