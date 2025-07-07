<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use Nette\Utils\FileSystem;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Exception\LogicException;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;
use Shredio\DocsGenerator\Processor\DocTemplateProcessor;

final class IncludeDocTemplateCommand implements DocTemplateCommandInterface
{
	public function __construct(
		private readonly DocTemplateProcessor $processor,
	)
	{
	}

	public function getName(): string
	{
		return 'include';
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		$file = $this->resolveFilePath($args[0], $context);

		if (!is_file($file)) {
			throw new LogicException(sprintf('File "%s" does not exist.', $file));
		}

		return $this->processor->parseContent(FileSystem::read($file), $context->withWorkingDir(dirname($file)), $context->parameters);
	}

	private function resolveFilePath(string $file, DocTemplateContext $context): string
	{
		if (str_starts_with($file, '../')) {
			return $context->workingDir . '/' . $file;
		}

		if (str_starts_with($file, './')) {
			return $context->workingDir . '/' . ltrim($file, './');
		}

		if (str_starts_with($file, '/')) {
			return $context->rootDir . ltrim($file, '/');
		}

		return $context->workingDir . '/' . $file;
	}
}
