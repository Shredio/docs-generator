<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Macro;

use Shredio\DocsGenerator\Exception\MacroException;

final readonly class SkillReferenceDocTemplateMacro implements DocTemplateMacro
{

	public function getName(): string
	{
		return 'skill-reference';
	}

	public function invoke(DocTemplateMacroContext $context, array $arguments): string
	{
		if (count($arguments) !== 1) {
			throw new MacroException(sprintf(
				'Macro "%s" expects exactly 1 argument, %d given.',
				$this->getName(),
				count($arguments),
			));
		}

		if ($arguments[0] === '') {
			throw new MacroException(sprintf(
				'Macro "%s" expects non-empty skill name as argument.',
				$this->getName(),
			));
		}

		$context->referenceChecker->checkSkill($context->sourcePath, $arguments[0]);

		return sprintf('`%s`', $arguments[0]);
	}

}
