<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;

final class ComposerPackageDocTemplateCommand implements DocTemplateCommandInterface
{

	private const array Files = ['CLAUDE.md', 'AGENTS.md', 'README.md', 'readme.md'];
	private const string DefaultDescription = 'Below are documentations of 3rd party libraries for further knowledge. ' .
											  'List contains BaseNamespace - documentation file';

	/** @var array<string, string> */
	private array $stack = [];

	public function __construct(
		private readonly string $description = self::DefaultDescription,
	)
	{
	}

	public function getName(): string
	{
		return '@composer-package';
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		if (isset($this->stack[$args[0]])) {
			return '';
		}
		$relativePath = 'vendor/' . $args[0] . '/';
		foreach (self::Files as $file) {
			$fullPath = $context->rootDir . '/' . $relativePath . $file;
			if (is_file($fullPath)) {
				$this->stack[$args[0]] = sprintf('- %s - %s', $args[1], $context->getFileImport($relativePath . $file));
				return '';
			}
		}

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

		return sprintf("%s\n\n## Documentations\n%s\n\n%s", $contents, $this->description, implode("\n", $this->stack));
	}

}
