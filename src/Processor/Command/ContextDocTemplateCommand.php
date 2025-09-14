<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use ReflectionMethod;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Exception\LogicException;
use Shredio\DocsGenerator\Php\PhpReflector;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;

final class ContextDocTemplateCommand implements DocTemplateCommandInterface
{

	private const string DefaultDescription = 'Below are short PHP code snippets. These snippets contain the basic structure of ' .
											  'important classes, including methods, properties, parameters, namespace, class name, ' .
											  'and PHPDoc comments.';
	private DumpClassDocTemplateCommand $dumpCommand;

	/** @var array<string, string> */
	private array $stack = [];

	public function __construct(
		private readonly string $description = self::DefaultDescription,
	)
	{
		$this->dumpCommand = new DumpClassDocTemplateCommand();
	}

	public function getName(): string
	{
		return '@context';
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		$this->stack[$args[0]] = $this->dumpCommand->invoke($context, $args);

		return '';
	}

	public function reset(): void
	{
		$this->stack = [];
	}

	public function after(string $contents): string
	{
		if ($this->stack === []) {
			return $contents;
		}


		return sprintf("%s\n\n## Context\n%s\n\n%s", $contents, $this->description, implode("\n\n", $this->stack));
	}

}
