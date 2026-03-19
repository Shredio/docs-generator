<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Generator;

use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use Shredio\DocsGenerator\Collector\DataCollector;
use Shredio\DocsGenerator\Content\ApiContentProcessor;
use Shredio\DocsGenerator\Content\CommandsContentWriter;
use Shredio\DocsGenerator\Content\ContentProcessor;
use Shredio\DocsGenerator\Content\ContentWriter;
use Shredio\DocsGenerator\Content\ContentWriterContext;
use Shredio\DocsGenerator\Content\DocsContentProcessor;
use Shredio\DocsGenerator\Content\DocsContentWriter;
use Shredio\DocsGenerator\Content\ExamplesContentProcessor;
use Shredio\DocsGenerator\Content\MacroContextProcessor;
use Shredio\DocsGenerator\Content\OutputContentWriter;
use Shredio\DocsGenerator\Content\PostprocessContext;
use Shredio\DocsGenerator\Content\ProcessContext;
use Shredio\DocsGenerator\Content\SkillContentWriter;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\File\FileProvider;
use Shredio\DocsGenerator\File\MarkdownFileProvider;
use Shredio\DocsGenerator\Macro\ClassNameDocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocsReferenceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocTemplateMacro;
use Shredio\DocsGenerator\Macro\ModuleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\SkillReferenceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\SubmoduleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\TestModuleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\TestSubmoduleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Path\PathFactory;
use Shredio\DocsGenerator\Path\ResolvedPath;
use Shredio\TypeSchema\Exception\AssertException;
use Shredio\TypeSchema\Types\Type;
use Shredio\TypeSchema\TypeSchema;
use Shredio\TypeSchema\TypeSchemaProcessor;

final class DocTemplateGenerator
{

	private readonly TypeSchemaProcessor $schemaProcessor;

	/** @var array<non-empty-string, DocTemplateMacro> */
	private array $macros = [];

	private PathFactory $pathFactory;

	private ?ResolvedPath $docsPath;

	public function __construct(PathFactory $pathFactory, ?ResolvedPath $docsPath = null)
	{
		$this->pathFactory = $pathFactory;
		$this->docsPath = $docsPath;
		$this->schemaProcessor = TypeSchemaProcessor::createDefault();
		$this->addMacro(new ClassNameDocTemplateMacro());
		$this->addMacro(new SubmoduleNamespaceDocTemplateMacro());
		$this->addMacro(new ModuleNamespaceDocTemplateMacro());
		$this->addMacro(new TestModuleNamespaceDocTemplateMacro());
		$this->addMacro(new TestSubmoduleNamespaceDocTemplateMacro());
		$this->addMacro(new SkillReferenceDocTemplateMacro());
		$this->addMacro(new DocsReferenceDocTemplateMacro());
	}

	public function addMacro(DocTemplateMacro $macro): void
	{
		$this->macros[$macro->getName()] = $macro;
	}

	/**
	 * @return array<non-empty-string, string> relative file path to root dir => file content
	 *
	 * @throws GeneratingFailedException
	 */
	public function create(FileProvider $fileProvider): array
	{
		$dataCollector = new DataCollector();

		/** @var list<ContentProcessor> $processors */
		$processors = [
			new MacroContextProcessor($dataCollector, $this->macros),
			new ApiContentProcessor(),
			new ExamplesContentProcessor(),
			new DocsContentProcessor(),
		];

		/** @var list<ContentWriter> $writers */
		$writers = [
			new DocsContentWriter($this->docsPath),
			new CommandsContentWriter(),
			new OutputContentWriter(),
			new SkillContentWriter(),
		];

		// Collect files
		$filesToProcess = [];
		foreach ($fileProvider->provide() as $file) {
			$filesToProcess[$file->priority][] = [
				'sourcePath' => $file->path,
				'frontmatter' => $file->frontmatter,
				'content' => $file->content,
				'isMainFile' => $file->isMainFile,
			];
		}

		krsort($filesToProcess, SORT_NUMERIC);

		// Process files by priority
		$filesToCreate = [];
		foreach ($filesToProcess as $key => $files) {
			foreach ($files as ['sourcePath' => $sourcePath, 'frontmatter' => $frontmatter, 'content' => $content, 'isMainFile' => $isMainFile]) {
				$context = new ProcessContext($sourcePath, $frontmatter, $isMainFile, $this->pathFactory, $this->schemaProcessor);
				foreach ($processors as $processor) {
					$content = trim($processor->process($content, $context));
				}

				$context = new PostprocessContext($sourcePath, $frontmatter, $isMainFile, $dataCollector, $this->pathFactory, $this->schemaProcessor);
				foreach ($processors as $processor) {
					$content = trim($processor->postprocess($content, $context));
				}

				$context = new ContentWriterContext($frontmatter, $isMainFile, $dataCollector, $this->pathFactory, $this->schemaProcessor);
				foreach ($writers as $writer) {
					foreach ($writer->write($content, $context) as $item) {
						if ($item->relativePath === '') {
							throw new GeneratingFailedException(sprintf(
								'Generated file path cannot be empty (source: "%s").',
								$sourcePath->getRelativePathToRootDir(),
							));
						}

						if ($item->content === '') {
							throw new GeneratingFailedException(sprintf(
								'Generated file content cannot be empty (source: "%s", target: "%s").',
								$sourcePath->getRelativePathToRootDir(),
								$item->relativePath,
							));
						}

						$filesToCreate[$item->relativePath] = $item->content;
					}
				}
			}

			unset($filesToProcess[$key]);
		}

		$dataCollector->validate();

		return $filesToCreate;
	}

	/**
	 * @throws GeneratingFailedException
	 */
	public function generate(string $templatesDirectory): void
	{
		if (!is_dir($templatesDirectory) || $templatesDirectory === '') {
			throw new GeneratingFailedException(sprintf('Directory "%s" does not exist.', $templatesDirectory));
		}

		$fileProvider = new MarkdownFileProvider(
			$this->pathFactory,
			$this->schemaProcessor,
			$templatesDirectory,
			['CLAUDE.md'], // Skip Claude.md files
		);

		$filesToCreate = $this->create($fileProvider);

		// Create new files
		$oldMetadata = $this->loadMetadata($templatesDirectory);
		$metadata = [
			'files' => [],
		];
		foreach ($filesToCreate as $relativeFilePath => $fileContent) {
			$absoluteFilePath = $this->pathFactory->createFileFromRelative($relativeFilePath)->getAbsolutePath();
			$directory = dirname($absoluteFilePath);
			if (!is_dir($directory)) {
				FileSystem::createDir($directory);
			}
			FileSystem::write($absoluteFilePath, $fileContent);
			$metadata['files'][] = $relativeFilePath;
		}

		// Remove old files
		foreach ($oldMetadata['files'] as $oldFile) {
			if (!in_array($oldFile, $metadata['files'], true)) {
				$absoluteFilePath = $this->pathFactory->createFileFromRelative($oldFile)->getAbsolutePath();
				if (is_file($absoluteFilePath)) {
					FileSystem::delete($absoluteFilePath);
					$dirname = dirname($absoluteFilePath);
					// Remove empty directories
					while (is_dir($dirname) && count(scandir($dirname)) === 2) {
						FileSystem::delete($dirname);
						$dirname = dirname($dirname);
					}
				}
			}
		}

		// Save metadata
		$metadataContent = Json::encode($metadata, true);
		FileSystem::write($templatesDirectory . '/.docgen-metadata.json', $metadataContent);
	}

	/**
	 * @throws GeneratingFailedException
	 * @return array{files: list<non-empty-string>}
	 */
	private function loadMetadata(string $directory): array
	{
		if (!is_file($directory . '/.docgen-metadata.json')) {
			return [
				'files' => [],
			];
		}

		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'files' => $s->nonEmptyList($s->nonEmptyString()),
		]);
		$content = FileSystem::read($directory . '/.docgen-metadata.json');
		$metadata = Json::decode($content, true);

		return $this->processSchema($schema, $metadata);
	}

	/**
	 * @template T
	 * @param Type<T> $schema
	 * @param mixed $value
	 * @return T
	 * @throws GeneratingFailedException
	 */
	private function processSchema(Type $schema, mixed $value): mixed
	{
		try {
			return $this->schemaProcessor->process($value, $schema);
		} catch (AssertException $exception) {
			throw new GeneratingFailedException("Invalid frontmatter:\n" . $exception->toPrettyString());
		}
	}

}
