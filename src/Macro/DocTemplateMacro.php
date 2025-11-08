<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

interface DocTemplateMacro
{

	/**
	 * @return non-empty-string
	 */
	public function getName(): string;

	/**
	 * @param non-empty-list<string> $arguments
	 * @throws MacroException
	 */
	public function invoke(DocTemplateMacroContext $context, array $arguments): string;

}
