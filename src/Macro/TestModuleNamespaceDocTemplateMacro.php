<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

final readonly class TestModuleNamespaceDocTemplateMacro implements DocTemplateMacro
{

	public function getName(): string
	{
		return 'test-module-namespace';
	}

	public function invoke(DocTemplateMacroContext $context, array $arguments): string
	{
		if (count($arguments) !== 2) {
			throw new MacroException(sprintf('Macro `%s` expects exactly 2 arguments, %d given.', $this->getName(), count($arguments)));
		}

		return sprintf('`Tests\%s\Module\%%ModuleName%%\%s`', $arguments[0], $arguments[1]);
	}

}
