<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

final readonly class ClassNameDocTemplateMacro implements DocTemplateMacro
{

	public function getName(): string
	{
		return 'class-name';
	}

	public function invoke(DocTemplateMacroContext $context, array $arguments): string
	{
		if (!class_exists($arguments[0]) && !interface_exists($arguments[0]) && !trait_exists($arguments[0])) {
			throw new MacroException(sprintf('Class %s not found', $arguments[0]));
		}

		return sprintf('`%s`', $arguments[0]);
	}

}
