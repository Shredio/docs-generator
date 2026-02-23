<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Generator;

use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Json;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionMethod;
use ReflectionProperty;
use Shredio\DocsGenerator\Collector\CollectedData;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Exception\MacroException;
use Shredio\DocsGenerator\FilePath\RootPath;
use Shredio\DocsGenerator\FilePath\SourcePath;
use Shredio\DocsGenerator\Macro\ClassNameDocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocsListDocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocsReferenceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocTemplateMacro;
use Shredio\DocsGenerator\Macro\DocTemplateMacroContext;
use Shredio\DocsGenerator\Macro\ModuleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\SkillReferenceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\SubmoduleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\TestModuleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Macro\TestSubmoduleNamespaceDocTemplateMacro;
use Shredio\DocsGenerator\Markdown\MarkdownFrontmatterParser;
use Shredio\DocsGenerator\Markdown\MarkdownSnippetParser;
use Shredio\DocsGenerator\Php\PhpReflector;
use Shredio\DocsGenerator\Reference\ReferenceChecker;
use Shredio\TypeSchema\Exception\AssertException;
use Shredio\TypeSchema\Types\Type;
use Shredio\TypeSchema\TypeSchema;
use Shredio\TypeSchema\TypeSchemaProcessor;
use Throwable;

final class DocTemplateGenerator
{

	private readonly TypeSchemaProcessor $schemaProcessor;

	private readonly RootPath $rootPath;

	private readonly ?SourcePath $docsPath;

	/** @var array<non-empty-string, DocTemplateMacro> */
	private array $macros = [];

	/** @var array<non-empty-string, non-empty-string> relativePath => description */
	private array $docs = [];

	private ReferenceChecker $referenceChecker;

	public function __construct(string $rootDir, ?string $docsDir = null)
	{
		$this->rootPath = new RootPath($rootDir);
		$this->docsPath = $docsDir === null ? null : SourcePath::fromRelativeOrAbsolute($docsDir, $this->rootPath);
		$this->schemaProcessor = TypeSchemaProcessor::createDefault();
		$this->addMacro(new ClassNameDocTemplateMacro());
		$this->addMacro(new SubmoduleNamespaceDocTemplateMacro());
		$this->addMacro(new ModuleNamespaceDocTemplateMacro());
		$this->addMacro(new TestModuleNamespaceDocTemplateMacro());
		$this->addMacro(new TestSubmoduleNamespaceDocTemplateMacro());
		$this->addMacro(new SkillReferenceDocTemplateMacro());
		$this->addMacro(new DocsReferenceDocTemplateMacro());
		$this->addMacro(new DocsListDocTemplateMacro());
	}

	public function addMacro(DocTemplateMacro $macro): void
	{
		$this->macros[$macro->getName()] = $macro;
	}

	/**
	 * @throws GeneratingFailedException
	 */
	public function generate(string $templatesDirectory): void
	{
		if (!is_dir($templatesDirectory)) {
			throw new GeneratingFailedException(sprintf('Directory "%s" does not exist.', $templatesDirectory));
		}

		$this->referenceChecker = new ReferenceChecker();

		// Collect files
		$filesToProcess = [];
		foreach (Finder::findFiles('*.md')->from($templatesDirectory) as $file) {
			if ($file->getBasename() === 'CLAUDE.md') {
				continue; // Skip Claude.md files
			}

			$sourcePath = new SourcePath($file->getPathname(), $this->rootPath);
			$content = $file->read();
			$frontmatter = MarkdownFrontmatterParser::extract($content);
			if ($frontmatter === []) {
				throw new GeneratingFailedException(
					sprintf('File "%s" does not contain frontmatter.', $sourcePath->relativePathFromRoot)
				);
			}

			$fileMetadata = $this->getMetadataFromFrontmatter($frontmatter);

			$filesToProcess[$fileMetadata['priority']][] = [
				'sourcePath' => $sourcePath,
				'frontmatter' => $frontmatter,
				'content' => $content,
			];
		}

		krsort($filesToProcess, SORT_NUMERIC);

		// Process files by priority
		$filesToCreate = [];
		foreach ($filesToProcess as $key => $files) {
			foreach ($files as ['sourcePath' => $sourcePath, 'frontmatter' => $frontmatter, 'content' => $content]) {
				$content = $this->generateContent($content, $sourcePath, $frontmatter);

				foreach ($this->createFiles($content, $sourcePath, $frontmatter) as $relativeFilePath => $fileContent) {
					$filesToCreate[$relativeFilePath] = $fileContent;
				}
			}

			unset($filesToProcess[$key]);
		}

		$this->referenceChecker->validate();

		// Create new files
		$oldMetadata = $this->loadMetadata($templatesDirectory);
		$metadata = [
			'files' => [],
		];
		foreach ($filesToCreate as $relativeFilePath => $fileContent) {
			$absoluteFilePath = $this->rootPath->getAbsolutePath($relativeFilePath);
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
				$absoluteFilePath = $this->rootPath->getAbsolutePath($oldFile);
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
	 * @param mixed[] $frontmatter
	 * @throws GeneratingFailedException
	 */
	private function generateContent(string $contents, SourcePath $sourcePath, array $frontmatter): string
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'main' => $s->optional($s->bool()),
			'macros' => $s->optional($s->arrayShape([
				'disabled' => $s->optional($s->bool()),
			])),
		], true);

		$options = $this->processSchema($schema, $frontmatter)['macros'] ?? [];
		$isMain = $frontmatter['main'] ?? false;
		$collectedData = $isMain ? CollectedData::create($this->docs, $this->docsPath?->relativePathFromRoot) : null;

		$disabled = $options['disabled'] ?? false;
		if ($disabled !== true) {
			$macros = MarkdownSnippetParser::parse($contents);
			// Process macros in reverse order to maintain correct positions
			foreach (array_reverse($macros) as $macro) {
				if (isset($this->macros[$macro->name])) {
					$instance = $this->macros[$macro->name];
					try {
						$replacement = $instance->invoke(new DocTemplateMacroContext(
							$sourcePath,
							$this->referenceChecker,
							$collectedData,
						), $macro->arguments);
					} catch (MacroException $exception) {
						throw new GeneratingFailedException(
							sprintf(
								'Generating failed for macro "%s" in file "%s": %s',
								$macro->name,
								$sourcePath->relativePathFromRoot,
								$exception->getMessage()
							)
						);
					}

					$contents = substr_replace(
						$contents,
						$replacement,
						$macro->startPosition,
						$macro->endPosition - $macro->startPosition
					);
				}
			}
		}

		return $contents;
	}

	/**
	 * @param mixed[] $frontmatter
	 * @return iterable<string, string> relative file path => file content
	 * @throws GeneratingFailedException
	 */
	private function createFiles(string $content, SourcePath $sourcePath, array $frontmatter): iterable
	{
		$content = trim($content);
		try {
			$content = trim($content . $this->getApi($frontmatter));
			$content = trim($content . $this->getExamples($frontmatter));

			yield from $this->createSkill($content, $sourcePath, $frontmatter);
			yield from $this->createCommands($content, $sourcePath, $frontmatter);
			yield from $this->createOutput($content, $sourcePath, $frontmatter);
			yield from $this->createDocs($content, $sourcePath, $frontmatter);
		} catch (Throwable $exception) {
			throw new GeneratingFailedException(sprintf(
				'Generating files from template "%s" failed: %s',
				$sourcePath->relativePathFromRoot,
				$exception->getMessage(),
			));
		}
	}

	/**
	 * @param mixed[] $frontmatter
	 * @return iterable<string, string> relative file path => file content
	 * @throws GeneratingFailedException
	 */
	private function createCommands(string $content, SourcePath $sourcePath, array $frontmatter): iterable
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'commands' => $s->optional($s->nonEmptyList($s->arrayShape([
				'name' => $s->nonEmptyString(),
				'prompt' => $s->nonEmptyString(),
			]))),
		], true);

		$values = $this->processSchema($schema, $frontmatter);
		foreach ($values['commands'] ?? [] as $command) {
			if (!str_contains($command['prompt'], '$ARGUMENTS')) {
				throw new GeneratingFailedException('Command prompt must contain "$ARGUMENTS" placeholder.');
			}

			yield sprintf('.claude/commands/%s.md', $command['name']) =>
				$content . "\n\n" .
				$command['prompt'];
		}
	}

	/**
	 * @param mixed[] $frontmatter
	 * @return iterable<string, string> relative file path => file content
	 * @throws GeneratingFailedException
	 */
	private function createSkill(string $content, SourcePath $sourcePath, array $frontmatter): iterable
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'skill' => $s->optional($s->arrayShape([
				'target' => $s->nonEmptyString(),
				'name' => $s->nonEmptyString(),
				'description' => $s->nonEmptyString(),
			])),
		], true);

		$values = $this->processSchema($schema, $frontmatter);
		if (!isset($values['skill'])) {
			return;
		}

		$skillName = basename($values['skill']['target']);
		if ($skillName === '') {
			throw new GeneratingFailedException('Skill target must not be empty.');
		}

		$this->referenceChecker->addSkill($skillName);

		yield $values['skill']['target'] . '/SKILL.md' => MarkdownFrontmatterParser::dump([
			'name' => $values['skill']['name'],
			'description' => $values['skill']['description'],
		]) . $content;
	}

	/**
	 * @param mixed[] $frontmatter
	 * @return iterable<string, string> relative file path => file content
	 * @throws GeneratingFailedException
	 */
	private function createOutput(string $content, SourcePath $sourcePath, array $frontmatter): iterable
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'output' => $s->optional($s->arrayShape([
				'target' => $s->nonEmptyString(),
			])),
		], true);

		$values = $this->processSchema($schema, $frontmatter);
		if (!isset($values['output'])) {
			return;
		}

		if (!str_ends_with($values['output']['target'], '.md')) {
			throw new GeneratingFailedException('Output target must be a markdown file with ".md" extension.');
		}

		yield $values['output']['target'] => $content;
	}

	/**
	 * @param mixed[] $frontmatter
	 * @return iterable<string, string> relative file path => file content
	 * @throws GeneratingFailedException
	 */
	private function createDocs(string $content, SourcePath $sourcePath, array $frontmatter): iterable
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'docs' => $s->optional($s->arrayShape([
				'target' => $s->nonEmptyString(),
				'description' => $s->nonEmptyString(),
			])),
		], true);

		$values = $this->processSchema($schema, $frontmatter);
		if (!isset($values['docs'])) {
			return;
		}

		if (!str_ends_with($values['docs']['target'], '.md')) {
			throw new GeneratingFailedException('Docs target must be a markdown file with ".md" extension.');
		}

		if ($this->docsPath === null) {
			throw new GeneratingFailedException('Docs base path is not set, cannot create docs.');
		}


		$this->referenceChecker->addDoc($values['docs']['target']);

		yield $this->docsPath->relativePathFromRoot . '/' . ltrim($values['docs']['target'], '/') => $content;

		if (isset($this->docs[$values['docs']['target']])) {
			throw new GeneratingFailedException(sprintf(
				'Docs target "%s" is already used for another document.',
				$values['docs']['target'],
			));
		}

		$this->docs[$values['docs']['target']] = $values['docs']['description'];
	}

	/**
	 * @param mixed[] $frontmatter
	 * @throws GeneratingFailedException
	 */
	private function getExamples(array $frontmatter): string
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'examples' => $s->optional($s->nonEmptyList(
				$s->union([
					$s->nonEmptyString(),
					$s->arrayShape([
						'class' => $s->nonEmptyString(),
						'contains' => $s->optional($s->nonEmptyList($s->nonEmptyString())),
					]),
					$s->arrayShape([
						'file' => $s->nonEmptyString(),
					]),
				])
			)),
		], true);

		$values = $this->processSchema($schema, $frontmatter);
		if (!isset($values['examples'])) {
			return '';
		}

		if (count($values['examples']) === 1) {
			$content = "\n\n## Example\n\n";
		} else {
			$content = "\n\n## Examples\n\n";
		}

		$examples = [];
		foreach ($values['examples'] as $value) {
			if (is_string($value) || isset($value['class'])) { // PHP class branch
				$className = is_string($value) ? $value : $value['class'];
				if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
					throw new GeneratingFailedException(sprintf('Example class "%s" does not exist.', $className));
				}

				$reflection = new ReflectionClass($className);
				$fileName = $reflection->getFileName();
				if ($fileName === false || !is_readable($fileName)) {
					throw new GeneratingFailedException(sprintf('Unable to read file for class "%s".', $className));
				}

				$contents = FileSystem::read($fileName);
				$examples[] = "```php\n" . trim($contents) . "\n```";

				if (is_array($value) && isset($value['contains'])) {
					foreach ($value['contains'] as $constraint) {
						if (!str_contains($contents, $constraint)) {
							throw new GeneratingFailedException(sprintf(
								'Example for class "%s" does not contain required string "%s".',
								$className,
								$constraint,
							));
						}
					}
				}

				continue;
			}

			if (isset($value['file'])) {
				// Normal file branch
				$filePath = $this->rootPath->getAbsolutePath($value['file']);
				if (!is_file($filePath) || !is_readable($filePath)) {
					throw new GeneratingFailedException(sprintf('Example file "%s" does not exist or is not readable.', $value['file']));
				}

				$contents = FileSystem::read($filePath);
				$suffix = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
				$format = match ($suffix) {
					'php' => 'php',
					'js' => 'javascript',
					'ts' => 'typescript',
					'md' => 'markdown',
					'tsp' => 'typespec',
					default => throw new GeneratingFailedException(sprintf('Unsupported example file extension "%s".', $suffix)),
				};

				$quotes = '```';
				while (str_contains($contents, $quotes)) {
					$quotes .= '`';
				}
				$examples[] = $quotes . $format . "\n" . trim($contents) . "\n" . $quotes;
			}
		}

		$content .= implode("\n\n", $examples);
		return $content;
	}

	/**
	 * @param mixed[] $frontmatter
	 * @throws GeneratingFailedException
	 */
	private function getApi(array $frontmatter): string
	{
		$s = TypeSchema::get();
		$schema = $s->arrayShape([
			'api' => $s->optional($s->nonEmptyList($s->union([
				$s->nonEmptyString(),
				$s->arrayShape([
					'class' => $s->nonEmptyString(),
					'visibilities' => $s->optional($s->nonEmptyList($s->nonEmptyString())),
				]),
			]))),
		], true);

		$values = $this->processSchema($schema, $frontmatter);
		if (!isset($values['api'])) {
			return '';
		}

		$content = "\n\n## Api Reference\n\n";
		$content .= 'The following short code snippets demonstrate the API:' . "\n\n";

		$apis = [];
		foreach ($values['api'] as $api) {
			if (is_array($api)) {
				$className = $api['class'];
				$visibilities = $api['visibilities'] ?? [];
			} else {
				$className = $api;
				$visibilities = [];
			}
			if (!class_exists($className) && !interface_exists($className) && !trait_exists($className)) {
				throw new GeneratingFailedException(sprintf('Example class "%s" does not exist.', $className));
			}

			$apis[] = PhpReflector::getClassSignature(
				$className,
				['__construct'],
				false,
				methodsToPrint: $this->parseMethodVisibility($visibilities),
				propertiesToPrint: $this->parsePropertyVisibility($visibilities),
				classConstantsToPrint: $this->parseClassConstantVisibility($visibilities),
			);
		}

		$apis = array_map(
			fn (string $code): string => "```php\n" . trim($code) . "\n```",
			$apis,
		);

		$content .= implode("\n\n", $apis);
		return $content;
	}

	/**
	 * @param list<non-empty-string> $methods
	 * @return int
	 */
	private function parseMethodVisibility(array $methods): int
	{
		if ($methods === []) {
			return ReflectionMethod::IS_PUBLIC;
		}

		$methodsToPrint = 0;
		if (in_array('public', $methods, true)) {
			$methodsToPrint |= ReflectionMethod::IS_PUBLIC;
		}
		if (in_array('protected', $methods, true)) {
			$methodsToPrint |= ReflectionMethod::IS_PROTECTED;
		}
		if (in_array('private', $methods, true)) {
			$methodsToPrint |= ReflectionMethod::IS_PRIVATE;
		}

		return $methodsToPrint;
	}

	/**
	 * @param list<non-empty-string> $properties
	 * @return int
	 */
	private function parsePropertyVisibility(array $properties): int
	{
		if ($properties === []) {
			return ReflectionProperty::IS_PUBLIC;
		}

		$propertiesToPrint = 0;
		if (in_array('public', $properties, true)) {
			$propertiesToPrint |= ReflectionProperty::IS_PUBLIC;
		}
		if (in_array('protected', $properties, true)) {
			$propertiesToPrint |= ReflectionProperty::IS_PROTECTED;
		}
		if (in_array('private', $properties, true)) {
			$propertiesToPrint |= ReflectionProperty::IS_PRIVATE;
		}

		return $propertiesToPrint;
	}

	/**
	 * @param list<non-empty-string> $constants
	 * @return int
	 */
	private function parseClassConstantVisibility(array $constants): int
	{
		if ($constants === []) {
			return ReflectionClassConstant::IS_PUBLIC;
		}

		$constantsToPrint = 0;
		if (in_array('public', $constants, true)) {
			$constantsToPrint |= ReflectionClassConstant::IS_PUBLIC;
		}
		if (in_array('protected', $constants, true)) {
			$constantsToPrint |= ReflectionClassConstant::IS_PROTECTED;
		}
		if (in_array('private', $constants, true)) {
			$constantsToPrint |= ReflectionClassConstant::IS_PRIVATE;
		}

		return $constantsToPrint;
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

	/**
	 * @param mixed[] $frontmatter
	 * @return array{priority: int<-1, 10>}
	 *
	 * @throws GeneratingFailedException
	 */
	private function getMetadataFromFrontmatter(array $frontmatter): array
	{
		if (!isset($frontmatter['metadata']) && !isset($frontmatter['main'])) {
			return [
				'priority' => 5,
			];
		}

		$t = TypeSchema::get();
		$schema = $t->arrayShape([
			'priority' => $t->optional($t->intRange(0, 10)),
		]);

		$metadata = $this->processSchema($schema, $frontmatter['metadata'] ?? []);
		if (isset($frontmatter['main']) && $frontmatter['main']) {
			$metadata['priority'] = -1; // Lowest priority
		} else {
			$metadata['priority'] ??= 5;
		}

		return $metadata;
	}

}
