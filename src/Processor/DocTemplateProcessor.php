<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor;

use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Exception\LogicException;
use Shredio\DocsGenerator\Markdown\MarkdownHeader;
use Shredio\DocsGenerator\Markdown\MarkdownHeaderParser;
use Shredio\DocsGenerator\Markdown\MarkdownSnippetParser;
use Shredio\DocsGenerator\Markdown\MarkdownVariableParser;
use Shredio\DocsGenerator\Processor\Command\ComposerPackageDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\ContextDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\DumpClassDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\ImportDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\IncludeDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\IncludeOnceDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\PrintClassCodeBlockDocTemplateCommand;
use Shredio\DocsGenerator\Processor\Command\PrintClassDocTemplateCommand;

final class DocTemplateProcessor
{

	/** @var array<string, DocTemplateCommandInterface> */
	private array $commands = [];

	/** @var array<string, string> */
	private array $cache = [];

	/** @var array<string, bool> */
	private array $processing = [];

	public function __construct(
		private readonly string $rootDir,
		private readonly ?string $claudeCommandsDir = null,
	)
	{
		$this->initializeCommands();
	}

	public function addCommand(DocTemplateCommandInterface $command): void
	{
		$this->commands[$command->getName()] = $command;
	}

	/**
	 * @param array<string, string> $parameters
	 * @return iterable<string>
	 */
	public function processTemplates(string $directory, array $parameters = []): iterable
	{
		if (!is_dir($directory)) {
			throw new LogicException(sprintf('Directory "%s" does not exist.', $directory));
		}

		$parameters = $this->loadParametersFromFile($directory, $parameters);

		yield from $this->parseFiles(
			Finder::findFiles('*.md')->from($directory),
			$parameters,
		);
	}

	private function initializeCommands(): void
	{
		$this->addCommand(new DumpClassDocTemplateCommand());
		$this->addCommand(new ContextDocTemplateCommand());
		$this->addCommand(new ComposerPackageDocTemplateCommand());
		$this->addCommand(new ImportDocTemplateCommand());
		$this->addCommand(new IncludeDocTemplateCommand($this));
		$this->addCommand(new IncludeOnceDocTemplateCommand($this));
		$this->addCommand(new PrintClassDocTemplateCommand());
		$this->addCommand(new PrintClassCodeBlockDocTemplateCommand());
	}

	/**
	 * @param array<string, string> $parameters
	 */
	private function parseFile(
		string $filePath,
		string $targetDir,
		array $parameters,
		?string $contentToProcess = null,
	): string
	{
		$filePath = FileSystem::normalizePath($filePath);
		if (isset($this->cache[$filePath])) {
			return $this->cache[$filePath];
		}
		if (isset($this->processing[$filePath])) {
			throw new LogicException(sprintf('Circular inclusion detected for file "%s".', $filePath));
		}

		$this->processing[$filePath] = true;
		$context = new DocTemplateContext(
			dirname($filePath),
			$this->rootDir,
			$targetDir,
			$parameters,
			$this->parseFile(...),
		);

		$parseHeaders = $contentToProcess === null;
		$contentToProcess ??= FileSystem::read($filePath);
		$contents = $this->parseContent($contentToProcess, $context, $parameters, $parseHeaders);

		foreach ($this->commands as $command) {
			$contents = $command->after($contents);
		}
		foreach ($this->commands as $command) {
			$command->reset();
		}

		unset($this->processing[$filePath]);

		return trim($contents);
	}

	/**
	 * @param array<string, string> $parameters
	 * @return iterable<string>
	 */
	private function parseFiles(Finder $finder, array $parameters): iterable
	{
		$knownTargets = [];
		foreach ($finder as $file) {
			$firstTarget = MarkdownHeaderParser::parse($file->read())->getFirstHeaderByName('target')?->value;
			if ($firstTarget !== null && !str_contains($firstTarget, '*')) {
				$knownTargets[$file->getPathname()] = FileSystem::joinPaths($this->rootDir, $firstTarget);
			}
		}

		foreach ($this->commands as $command) {
			if ($command instanceof ImportDocTemplateCommand) {
				$command->setKnownTargets($knownTargets);
			}
		}

		foreach ($finder as $file) {
			try {
				$fileContents = $file->read();
				// Parse markdown headers and get content without headers
				$parsedMarkdown = MarkdownHeaderParser::parse($fileContents);
				$createContent = function (string $targetDir) use ($parameters, $parsedMarkdown, $file): string {
					return $this->parseFile($file->getPathname(), $targetDir, $parameters, $parsedMarkdown->content);
				};

				$targetPaths = $this->getTargetPaths($parsedMarkdown->headers);

				foreach ($targetPaths as $targetPath) {
					FileSystem::write($targetPath, $createContent(dirname($targetPath)) . "\n");
					yield $targetPath;
				}

				// Process claude-command-target headers when claudeCommandsDir is set
				if ($this->claudeCommandsDir !== null) {
					yield from $this->processClaudeCommandTargets(
						$parsedMarkdown->headers,
						$createContent($this->rootDir . '/' . ltrim($this->claudeCommandsDir, '/')),
						$file->getBasename(),
					);
				}
			} catch (LogicException $e) {
				$realPath = $file->getRealPath();
				if ($realPath !== false) {
					$e->sourceFile = $realPath;
				}

				throw $e;
			}
		}
	}

	/**
	 * @param list<MarkdownHeader> $headers
	 * @return iterable<string>
	 */
	private function getTargetPaths(array $headers): iterable
	{
		foreach ($headers as $header) {
			if ($header->name === 'target') {
				if (!str_contains($header->value, '*')) {
					yield $this->rootDir . '/' . ltrim($header->value, '/');

					continue;
				}

				yield from $this->getTargetPathsByPattern($header->value);
			}
		}
	}

	/**
	 * @param string $pattern
	 * @return iterable<string>
	 */
	private function getTargetPathsByPattern(string $pattern): iterable
	{
		$pos = strrpos($pattern, '*');

		if ($pos === false) {
			return;
		}

		$path = substr($pattern, 0, $pos);
		$suffix = substr($pattern, $pos + 1);

		foreach (Finder::findDirectories('*')->in($this->rootDir . '/' . $path) as $directory) {
			yield $directory->getPathname() . $suffix;
		}
	}

	/**
	 * @param list<MarkdownHeader> $headers
	 * @return iterable<string>
	 */
	private function processClaudeCommandTargets(array $headers, string $contents, string $originalFilename): iterable
	{
		$claudeCommandTargets = [];
		$claudeCommandPrompt = null;
		$promptCount = 0;

		foreach ($headers as $header) {
			if ($header->name === 'claude-command-target') {
				$claudeCommandTargets[] = $header->value;
			} elseif ($header->name === 'claude-command-prompt') {
				$promptCount++;
				$claudeCommandPrompt = $header->value;
			} elseif ($header->name === 'claude-command') {
				$parts = explode('--', $header->value, 2);
				$target = trim($parts[0]);
				$prompt = $parts[1] ?? null;

				if ($prompt === null) {
					throw new LogicException(sprintf(
						'Invalid claude-command header "%s". It must contain a prompt after "--".',
						$header->value
					));
				}

				yield $this->writeClaudeCommand($contents, trim($prompt), $target);
			}
		}

		$targetCount = count($claudeCommandTargets);

		if ($targetCount === 0 && $promptCount === 0) {
			return; // No claude-command-target or claude-command-prompt headers found
		}

		if ($promptCount > 1) {
			throw new LogicException('Multiple claude-command-prompt headers found. Only one is allowed.');
		}

		if ($targetCount > 0 && $claudeCommandPrompt === null) {
			throw new LogicException('claude-command-prompt header is required when claude-command-target headers are present.');
		}

		if ($targetCount > 0) {
			foreach ($claudeCommandTargets as $target) {
				yield $this->writeClaudeCommand($contents, $claudeCommandPrompt, $target);
			}
		} elseif ($claudeCommandPrompt !== null) {
			yield $this->writeClaudeCommand($contents, $claudeCommandPrompt, $originalFilename);
		}
	}

	private function writeClaudeCommand(string $contents, ?string $prompt, string $target): string
	{
		$targetPath = $this->claudeCommandsDir . '/' . ltrim($target, '/');

		if ($prompt !== null) {
			$finalContents = $prompt . "\n\n" . $contents;
		} else {
			$finalContents = $contents;
		}

		FileSystem::write($targetPath, trim($finalContents) . "\n");

		return $targetPath;
	}

	/**
	 * @param array<string, string> $parameters
	 */
	public function parseContent(string $contents, DocTemplateContext $context, array $parameters = [], bool $parseHeaders = true): string
	{
		if ($parseHeaders) {
			$contents = MarkdownHeaderParser::parse($contents)->content;
		}

		// Replace variables first
		$variables = MarkdownVariableParser::parse($contents);
		if ($variables) {
			// Process variables in reverse order to maintain correct positions
			foreach (array_reverse($variables) as $variable) {
				if (!isset($parameters[$variable->name])) {
					throw new LogicException(sprintf('Variable "%s" not found in parameters.', $variable->name));
				}

				$contents = substr_replace(
					$contents,
					$parameters[$variable->name],
					$variable->startPosition,
					$variable->endPosition - $variable->startPosition
				);
			}
		}

		$snippets = MarkdownSnippetParser::parse($contents);

		// Process snippets in reverse order to maintain correct positions
		foreach (array_reverse($snippets) as $snippet) {
			if (isset($this->commands[$snippet->name])) {
				$command = $this->commands[$snippet->name];
				$replacement = $command->invoke($context, $snippet->arguments);

				$contents = substr_replace(
					$contents,
					$replacement,
					$snippet->startPosition,
					$snippet->endPosition - $snippet->startPosition
				);
			}
		}

		return trim($contents);
	}

	/**
	 * @param array<string, string> $parameters
	 * @return array<string, string>
	 */
	private function loadParametersFromFile(string $directory, array $parameters): array
	{
		$parametersFile = $directory . '/docs-parameters.json';

		if (!file_exists($parametersFile)) {
			return $parameters;
		}

		$fileContents = FileSystem::read($parametersFile);
		/** @var array<string, string>|null $fileParameters */
		$fileParameters = json_decode($fileContents, true);

		if (!is_array($fileParameters)) {
			throw new LogicException(sprintf('Invalid JSON in parameters file "%s".', $parametersFile));
		}

		return array_merge($fileParameters, $parameters);
	}

	public function finish(string $rootDir): void
	{
		foreach ($this->commands as $command) {
			if ($command instanceof DocTemplateFinishCommand) {
				$command->finish($rootDir);
			}
		}
	}

}
