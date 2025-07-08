<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use Nette\Utils\FileSystem;
use ReflectionClass;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Exception\LogicException;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;

final class PrintClassCodeBlockDocTemplateCommand implements DocTemplateCommandInterface
{
	public function getName(): string
	{
		return 'print-code-block';
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		$fullClassName = $args[0];

		if (!class_exists($fullClassName)) {
			throw new LogicException(sprintf('Class "%s" does not exist.', $fullClassName));
		}

		$reflection = new ReflectionClass($fullClassName);
		$fileName = $reflection->getFileName();

		if ($fileName === false) {
			throw new LogicException(sprintf('Class "%s" does not have a file location.', $fullClassName));
		}

		return sprintf("```php\n%s\n```", trim(FileSystem::read($fileName)));
	}

	public function reset(): void
	{
		// no need to reset state for this command
	}
}
