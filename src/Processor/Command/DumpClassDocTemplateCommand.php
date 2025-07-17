<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use ReflectionMethod;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Exception\LogicException;
use Shredio\DocsGenerator\Php\PhpReflector;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;

final class DumpClassDocTemplateCommand implements DocTemplateCommandInterface
{
	public function getName(): string
	{
		return 'dump';
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		$fullClassName = $args[0];
		$methods = $args[1] ?? '';

		$methodsToPrint = $this->parseMethodVisibility($methods);

		if (!class_exists($fullClassName) && !trait_exists($fullClassName) && !interface_exists($fullClassName)) {
			throw new LogicException(sprintf('Class "%s" does not exist.', $fullClassName));
		}

		$signature = PhpReflector::getClassSignature(
			$fullClassName,
			['__construct'],
			false,
			methodsToPrint: $methodsToPrint,
		);

		return sprintf(
			"Code snippet of **%s**:\n" .
			"```php\n%s\n```",
			$fullClassName,
			trim($signature),
		);
	}

	private function parseMethodVisibility(string $methods): int
	{
		if ($methods === '') {
			return ReflectionMethod::IS_PUBLIC;
		}

		$methodsToPrint = 0;
		$methodsToPrint |= str_contains($methods, 'public') ? ReflectionMethod::IS_PUBLIC : 0;
		$methodsToPrint |= str_contains($methods, 'protected') ? ReflectionMethod::IS_PROTECTED : 0;
		$methodsToPrint |= str_contains($methods, 'private') ? ReflectionMethod::IS_PRIVATE : 0;

		return $methodsToPrint;
	}

	public function reset(): void
	{
		// no need to reset state for this command
	}

	public function after(string $contents): string
	{
		return $contents;
	}

}
