<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

final readonly class TestSubmoduleNamespaceDocTemplateMacro implements DocTemplateMacro
{

	public function getName(): string
	{
		return 'test-submodule-namespace';
	}

	public function invoke(DocTemplateMacroContext $context, array $arguments): string
	{
		if (count($arguments) !== 3) {
			throw new MacroException(sprintf('Macro `%s` expects exactly 3 arguments, %d given.', $this->getName(), count($arguments)));
		}

		return sprintf('`Tests\%sModule\%%ModuleName%%\%s\%%SubmoduleName%%\%s`', $arguments[0], $arguments[1], $arguments[2]);
	}

}
