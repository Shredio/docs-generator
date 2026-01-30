<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

final readonly class DocsListDocTemplateMacro implements DocTemplateMacro
{

	public function getName(): string
	{
		return 'docs-list';
	}

	public function invoke(DocTemplateMacroContext $context, array $arguments): string
	{
		if ($context->collectedData === null) {
			throw new MacroException(sprintf(
				'Macro "%s" can be used only in the main documentation file.',
				$this->getName(),
			));
		}

		$str = '';
		$pos = 1;
		foreach ($context->collectedData->docs as $relativePath => $description) {
			$str .= sprintf('%d. [%s](%s) - %s' . "\n", $pos, basename($relativePath), $relativePath, $description);
			$pos++;
		}

		return rtrim($str);
	}

}
