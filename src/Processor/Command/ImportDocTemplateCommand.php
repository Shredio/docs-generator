<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Processor\Command;

use Nette\Utils\FileSystem;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Processor\DocTemplateCommandInterface;
use Shredio\DocsGenerator\Processor\DocTemplateFinishCommand;

final class ImportDocTemplateCommand implements DocTemplateCommandInterface, DocTemplateFinishCommand
{

	/** @var array<string, string> */
	private array $cache = [];

	/** @var array<string, string> */
	private array $contents = [];

	public function __construct(
		private readonly string $path = 'ai-docs/imports',
	)
	{
	}

	public function getName(): string
	{
		return '@import';
	}

	public function invoke(DocTemplateContext $context, array $args): string
	{
		$absolutePath = FileSystem::resolvePath($context->workingDir, $args[0]); // template file to render
		if (!is_file($absolutePath)) {
			throw new \RuntimeException(sprintf('File "%s" not found.', $absolutePath));
		}

		if (!isset($this->cache[$absolutePath])) {
			$baseFileName = $fileName = basename($absolutePath);
			$i = 0;
			while (in_array($fileName, $this->cache, true)) {
				$fileName = sprintf('%s-%d.%s', pathinfo($baseFileName, PATHINFO_FILENAME), ++$i, pathinfo($baseFileName, PATHINFO_EXTENSION));
			}

			$targetFilePath = FileSystem::resolvePath($context->rootDir, $this->path) . '/' . $fileName;
			$this->contents[$targetFilePath] = $context->parse($absolutePath, FileSystem::resolvePath($context->rootDir, $this->path));
			$this->cache[$absolutePath] = $targetFilePath;
		} else {
			$targetFilePath = $this->cache[$absolutePath];
		}

		return $this->getReference($context, (new \Symfony\Component\Filesystem\Filesystem())->makePathRelative(
			dirname($targetFilePath),
			$context->targetDir,
		) . basename($targetFilePath));
	}

	public function reset(): void
	{
	}

	public function after(string $contents): string
	{
		return $contents;
	}

	public function finish(string $rootDir): void
	{
		// cleanup old files
		$importDir = FileSystem::resolvePath($rootDir, $this->path);
		if (is_dir($importDir)) {
			$files = glob($importDir . '/*');
			if (is_array($files)) {
				foreach ($files as $file) {
					if (is_file($file) && !isset($this->contents[$file])) {
						unlink($file);
					}
				}
			}
		}

		foreach ($this->contents as $file => $content) {
			FileSystem::write($file, $content);
		}
		$this->contents = $this->cache = [];
	}

	private function getReference(DocTemplateContext $context, string $path): string
	{
		return '@' . $path;
	}

	/**
	 * @param array<string, string> $knownTargets absoluteTemplateFilePath => absoluteTargetFilePath
	 */
	public function setKnownTargets(array $knownTargets): void
	{
		$this->cache = $knownTargets;
	}

}
