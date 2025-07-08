<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;

final class InlineDocTemplateCommand implements DocTemplateCommandInterface
{
	/**
	 * @param callable(DocTemplateContext, non-empty-list<string>): string $callback
	 */
	public function __construct(
		private readonly string $name,
		private readonly mixed $callback,
	)
	{
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		return ($this->callback)($context, $args);
	}

	public function reset(): void
	{
		// no need to reset state for this command
	}
}
