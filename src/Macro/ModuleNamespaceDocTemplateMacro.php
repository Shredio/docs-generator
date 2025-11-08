<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

final readonly class ModuleNamespaceDocTemplateMacro implements DocTemplateMacro
{

	public function getName(): string
	{
		return 'module-namespace';
	}

	public function invoke(DocTemplateMacroContext $context, array $arguments): string
	{
		if (count($arguments) !== 1) {
			throw new MacroException(sprintf('Macro `%s` expects exactly 1 argument, %d given.', $this->getName(), count($arguments)));
		}

		return sprintf('`Module\%%ModuleName%%\%s`', $arguments[0]);
	}

}
