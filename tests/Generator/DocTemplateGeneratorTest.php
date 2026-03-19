<?php declare(strict_types = 1);

namespace Tests\Generator;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\File\InMemoryFileProvider;
use Shredio\DocsGenerator\File\ProvidedFile;
use Shredio\DocsGenerator\Generator\DocTemplateGenerator;
use Shredio\DocsGenerator\Path\PathFactory;
use Shredio\DocsGenerator\Path\PathType;
use Shredio\DocsGenerator\Path\ResolvedPath;
use Tests\TestCase;

final class DocTemplateGeneratorTest extends TestCase
{

	private PathFactory $pathFactory;

	protected function setUp(): void
	{
		parent::setUp();

		$this->pathFactory = new PathFactory('/project');
	}

	public function testEmptyFileProvider(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();

		$result = $generator->create($fileProvider);

		self::assertSame([], $result);
	}

	public function testSkillFrontmatter(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/my-skill.md'),
			frontmatter: [
				'skill' => [
					'target' => '.claude/skills/my-skill',
					'description' => 'My skill description',
				],
			],
			content: '# My Skill Content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('.claude/skills/my-skill/SKILL.md', $result);
		$content = $result['.claude/skills/my-skill/SKILL.md'];
		self::assertStringContainsString('name: my-skill', $content);
		self::assertStringContainsString('My skill description', $content);
		self::assertStringContainsString('# My Skill Content', $content);
	}

	public function testSkillWithCustomName(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/skill.md'),
			frontmatter: [
				'skill' => [
					'target' => '.claude/skills/my-skill',
					'name' => 'custom-name',
					'description' => 'Description',
				],
			],
			content: 'Content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('.claude/skills/my-skill/SKILL.md', $result);
		self::assertStringContainsString('name: custom-name', $result['.claude/skills/my-skill/SKILL.md']);
	}

	public function testOutputFrontmatter(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/output.md'),
			frontmatter: [
				'output' => [
					'target' => 'docs/output.md',
				],
			],
			content: '# Output Content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('docs/output.md', $result);
		self::assertStringContainsString('# Output Content', $result['docs/output.md']);
	}

	public function testOutputTargetMustBeMarkdown(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/output.md'),
			frontmatter: [
				'output' => [
					'target' => 'docs/output.txt',
				],
			],
			content: 'Content',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Output target must be a markdown file');

		$generator->create($fileProvider);
	}

	public function testDocsFrontmatter(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/my-doc.md'),
			frontmatter: [
				'docs' => [
					'target' => 'getting-started.md',
					'description' => 'Getting started guide',
				],
			],
			content: '# Getting Started',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('docs/getting-started.md', $result);
		self::assertStringContainsString('# Getting Started', $result['docs/getting-started.md']);
	}

	public function testDocsTargetMustBeMarkdown(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc.md'),
			frontmatter: [
				'docs' => [
					'target' => 'guide.txt',
					'description' => 'Guide',
				],
			],
			content: 'Content',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Docs target must be a markdown file');

		$generator->create($fileProvider);
	}

	public function testDocsRequiresDocsPath(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc.md'),
			frontmatter: [
				'docs' => [
					'target' => 'guide.md',
					'description' => 'Guide',
				],
			],
			content: 'Content',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Docs base path is not set');

		$generator->create($fileProvider);
	}

	public function testDuplicateDocsTarget(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();

		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc1.md'),
			frontmatter: [
				'docs' => [
					'target' => 'same.md',
					'description' => 'First',
				],
			],
			content: 'First content',
			priority: 5,
		));
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc2.md'),
			frontmatter: [
				'docs' => [
					'target' => 'same.md',
					'description' => 'Second',
				],
			],
			content: 'Second content',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Doc "same.md" is already defined.');

		$generator->create($fileProvider);
	}

	public function testCommandsFrontmatter(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/command.md'),
			frontmatter: [
				'commands' => [
					[
						'name' => 'my-command',
						'prompt' => 'Run this with $ARGUMENTS',
					],
				],
			],
			content: '# Command Content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('.claude/commands/my-command.md', $result);
		$content = $result['.claude/commands/my-command.md'];
		self::assertStringContainsString('# Command Content', $content);
		self::assertStringContainsString('Run this with $ARGUMENTS', $content);
	}

	public function testCommandPromptMustContainArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/command.md'),
			frontmatter: [
				'commands' => [
					[
						'name' => 'my-command',
						'prompt' => 'Run this without arguments placeholder',
					],
				],
			],
			content: 'Content',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('$ARGUMENTS');

		$generator->create($fileProvider);
	}

	public function testMultipleCommands(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/commands.md'),
			frontmatter: [
				'commands' => [
					[
						'name' => 'cmd-one',
						'prompt' => 'First $ARGUMENTS',
					],
					[
						'name' => 'cmd-two',
						'prompt' => 'Second $ARGUMENTS',
					],
				],
			],
			content: 'Shared content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('.claude/commands/cmd-one.md', $result);
		self::assertArrayHasKey('.claude/commands/cmd-two.md', $result);
	}

	public function testMacrosDisabled(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/no-macros.md'),
			frontmatter: [
				'macros' => [
					'disabled' => true,
				],
				'output' => [
					'target' => 'output.md',
				],
			],
			content: 'Content with {{ className }} macro that should not be expanded',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('output.md', $result);
		self::assertStringContainsString('{{ className }}', $result['output.md']);
	}

	public function testEmptyGeneratedPathThrows(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/empty.md'),
			frontmatter: [],
			content: 'Content without output configuration',
			priority: 5,
		));

		// No output/skill/docs/commands frontmatter means no files generated
		$result = $generator->create($fileProvider);
		self::assertSame([], $result);
	}

	public function testPriorityProcessingOrder(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();

		// Lower priority file
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/low.md'),
			frontmatter: [
				'docs' => [
					'target' => 'low-priority.md',
					'description' => 'Low priority doc',
				],
			],
			content: 'Low priority content',
			priority: 1,
		));

		// Higher priority file
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/high.md'),
			frontmatter: [
				'docs' => [
					'target' => 'high-priority.md',
					'description' => 'High priority doc',
				],
			],
			content: 'High priority content',
			priority: 10,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('docs/low-priority.md', $result);
		self::assertArrayHasKey('docs/high-priority.md', $result);
	}

	public function testSkillAndOutputCombined(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/combined.md'),
			frontmatter: [
				'skill' => [
					'target' => '.claude/skills/combined',
					'description' => 'Combined skill',
				],
				'output' => [
					'target' => 'output/combined.md',
				],
			],
			content: '# Combined Content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('.claude/skills/combined/SKILL.md', $result);
		self::assertArrayHasKey('output/combined.md', $result);
	}

	public function testDocsLeadingSlashTrimmed(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc.md'),
			frontmatter: [
				'docs' => [
					'target' => '/leading-slash.md',
					'description' => 'Doc with leading slash',
				],
			],
			content: 'Content',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('docs/leading-slash.md', $result);
	}

	public function testContentIsTrimmed(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/trimmed.md'),
			frontmatter: [
				'output' => [
					'target' => 'trimmed.md',
				],
			],
			content: "  \n\n# Content with whitespace  \n\n  ",
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertSame('# Content with whitespace', $result['trimmed.md']);
	}

	public function testClassNameMacro(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Use {{ class-name: "Shredio\\DocsGenerator\\Generator\\DocTemplateGenerator" }} class.',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('macro-output.md', $result);
		self::assertStringContainsString('`Shredio\DocsGenerator\Generator\DocTemplateGenerator`', $result['macro-output.md']);
		self::assertStringNotContainsString('{{ class-name:', $result['macro-output.md']);
	}

	public function testClassNameMacroWithNonExistentClass(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Use {{ class-name: "NonExistent\\ClassName" }} class.',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Class NonExistent\ClassName not found');

		$generator->create($fileProvider);
	}

	public function testClassNameMacroWithoutArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Use {{ class-name }} class.',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 1 argument');

		$generator->create($fileProvider);
	}

	public function testModuleNamespaceMacro(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Namespace: {{ module-namespace: "Entity" }}',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertSame('Namespace: `Module\%ModuleName%\Entity`', $result['macro-output.md']);
	}

	public function testModuleNamespaceMacroWrongArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: '{{ module-namespace: "A", "B" }}',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 1 argument');

		$generator->create($fileProvider);
	}

	public function testSubmoduleNamespaceMacro(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Namespace: {{ submodule-namespace: "Entity", "Repository" }}',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertSame('Namespace: `Module\%ModuleName%\Entity\%SubmoduleName%\Repository`', $result['macro-output.md']);
	}

	public function testSubmoduleNamespaceMacroWrongArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: '{{ submodule-namespace: "A" }}',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 2 argument');

		$generator->create($fileProvider);
	}

	public function testTestModuleNamespaceMacro(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Namespace: {{ test-module-namespace: "Unit", "Entity" }}',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertSame('Namespace: `Tests\Unit\Module\%ModuleName%\Entity`', $result['macro-output.md']);
	}

	public function testTestModuleNamespaceMacroWrongArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: '{{ test-module-namespace: "A" }}',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 2 argument');

		$generator->create($fileProvider);
	}

	public function testTestSubmoduleNamespaceMacro(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: 'Namespace: {{ test-submodule-namespace: "Unit", "Entity", "Repository" }}',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertSame('Namespace: `Tests\UnitModule\%ModuleName%\Entity\%SubmoduleName%\Repository`', $result['macro-output.md']);
	}

	public function testTestSubmoduleNamespaceMacroWrongArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/macro.md'),
			frontmatter: [
				'output' => [
					'target' => 'macro-output.md',
				],
			],
			content: '{{ test-submodule-namespace: "A", "B" }}',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 3 argument');

		$generator->create($fileProvider);
	}

	public function testSkillReferenceMacro(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();

		// Register skill first
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/skill.md'),
			frontmatter: [
				'skill' => [
					'target' => '.claude/skills/my-skill',
					'description' => 'Skill description',
				],
			],
			content: 'Skill content',
			priority: 10,
		));

		// Reference skill in another file
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/ref.md'),
			frontmatter: [
				'output' => [
					'target' => 'ref-output.md',
				],
			],
			content: 'Use {{ skill-reference: "my-skill" }} skill.',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertStringContainsString('`my-skill`', $result['ref-output.md']);
		self::assertStringNotContainsString('{{ skill-reference:', $result['ref-output.md']);
	}

	public function testSkillReferenceMacroWithUnknownSkill(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/ref.md'),
			frontmatter: [
				'output' => [
					'target' => 'ref-output.md',
				],
			],
			content: 'Use {{ skill-reference: "non-existent" }} skill.',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('unknown skill');

		$generator->create($fileProvider);
	}

	public function testSkillReferenceMacroWrongArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/ref.md'),
			frontmatter: [
				'output' => [
					'target' => 'ref-output.md',
				],
			],
			content: '{{ skill-reference }}',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 1 argument');

		$generator->create($fileProvider);
	}

	public function testDocsReferenceMacro(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();

		// Register doc first
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc.md'),
			frontmatter: [
				'docs' => [
					'target' => 'getting-started.md',
					'description' => 'Getting started guide',
				],
			],
			content: 'Doc content',
			priority: 10,
		));

		// Reference doc in another file
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/ref.md'),
			frontmatter: [
				'output' => [
					'target' => 'ref-output.md',
				],
			],
			content: 'See {{ docs-reference: "getting-started.md" }} for details.',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertStringContainsString('`getting-started.md`', $result['ref-output.md']);
		self::assertStringNotContainsString('{{ docs-reference:', $result['ref-output.md']);
	}

	public function testDocsReferenceMacroWithUnknownDoc(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/ref.md'),
			frontmatter: [
				'output' => [
					'target' => 'ref-output.md',
				],
			],
			content: 'See {{ docs-reference: "non-existent.md" }} for details.',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('unknown doc');

		$generator->create($fileProvider);
	}

	public function testDocsReferenceMacroWrongArguments(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/ref.md'),
			frontmatter: [
				'output' => [
					'target' => 'ref-output.md',
				],
			],
			content: '{{ docs-reference }}',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('expects exactly 1 argument');

		$generator->create($fileProvider);
	}

	public function testDocsListInMainFile(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();

		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc1.md'),
			frontmatter: [
				'docs' => [
					'target' => 'getting-started.md',
					'description' => 'Getting started guide',
				],
			],
			content: 'Doc 1',
			priority: 10,
		));
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc2.md'),
			frontmatter: [
				'docs' => [
					'target' => 'advanced.md',
					'description' => 'Advanced usage',
				],
			],
			content: 'Doc 2',
			priority: 10,
		));

		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/main.md'),
			frontmatter: [
				'output' => [
					'target' => 'index.md',
				],
			],
			content: '# Main',
			priority: -1,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('index.md', $result);
		$content = $result['index.md'];
		self::assertStringContainsString('## Project Docs', $content);
		self::assertStringContainsString('1. [getting-started.md](docs/getting-started.md) - Getting started guide', $content);
		self::assertStringContainsString('2. [advanced.md](docs/advanced.md) - Advanced usage', $content);
	}

	public function testDocsListNotAddedToNonMainFile(): void
	{
		$docsPath = new ResolvedPath('docs', '/project/docs', PathType::Directory);
		$generator = new DocTemplateGenerator($this->pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();

		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/doc.md'),
			frontmatter: [
				'docs' => [
					'target' => 'getting-started.md',
					'description' => 'Getting started guide',
				],
			],
			content: 'Doc 1',
			priority: 10,
		));

		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/output.md'),
			frontmatter: [
				'output' => [
					'target' => 'output.md',
				],
			],
			content: '# Not Main',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('output.md', $result);
		self::assertStringNotContainsString('## Project Docs', $result['output.md']);
	}

	public function testDocsListEmptyDocsNotAdded(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();

		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/main.md'),
			frontmatter: [
				'output' => [
					'target' => 'index.md',
				],
			],
			content: '# Main',
			priority: -1,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('index.md', $result);
		self::assertStringNotContainsString('## Project Docs', $result['index.md']);
	}

	public function testApiWithStringClass(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					'Shredio\DocsGenerator\Path\PathFactory',
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('api-output.md', $result);
		$content = $result['api-output.md'];
		self::assertStringContainsString('## API Reference', $content);
		self::assertStringContainsString('- (class) `Shredio\DocsGenerator\Path\PathFactory` - [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $content);
	}

	public function testApiWithArrayClass(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					['class' => 'Shredio\DocsGenerator\Path\ResolvedPath'],
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('api-output.md', $result);
		$content = $result['api-output.md'];
		self::assertStringContainsString('## API Reference', $content);
		self::assertStringContainsString('- (class) `Shredio\DocsGenerator\Path\ResolvedPath` - [src/Path/ResolvedPath.php](../src/Path/ResolvedPath.php)', $content);
	}

	public function testApiWithMultipleClasses(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					'Shredio\DocsGenerator\Path\PathFactory',
					['class' => 'Shredio\DocsGenerator\Path\ResolvedPath'],
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		$content = $result['api-output.md'];
		self::assertStringContainsString('- (class) `Shredio\DocsGenerator\Path\PathFactory` - [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $content);
		self::assertStringContainsString('- (class) `Shredio\DocsGenerator\Path\ResolvedPath` - [src/Path/ResolvedPath.php](../src/Path/ResolvedPath.php)', $content);
	}

	public function testApiWithInterface(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					'Shredio\DocsGenerator\Content\ContentProcessor',
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		$content = $result['api-output.md'];
		self::assertStringContainsString('- (interface) `Shredio\DocsGenerator\Content\ContentProcessor` - [src/Content/ContentProcessor.php](../src/Content/ContentProcessor.php)', $content);
	}

	public function testApiWithNonExistentClass(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/api.md'),
			frontmatter: [
				'api' => [
					'NonExistent\ClassName',
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Api class "NonExistent\ClassName" does not exist.');

		$generator->create($fileProvider);
	}

	public function testApiWithComposerPackage(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					['composer' => 'shredio/type-schema'],
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('api-output.md', $result);
		$content = $result['api-output.md'];
		self::assertStringContainsString('## API Reference', $content);
		self::assertStringContainsString('- (composer package) `shredio/type-schema` - [vendor/shredio/type-schema/LLM.md](../vendor/shredio/type-schema/LLM.md)', $content);
	}

	public function testApiWithComposerPackageMixedWithClass(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					['composer' => 'shredio/type-schema'],
					'Shredio\DocsGenerator\Path\PathFactory',
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		$content = $result['api-output.md'];
		self::assertStringContainsString('- (composer package) `shredio/type-schema` - [vendor/shredio/type-schema/LLM.md](../vendor/shredio/type-schema/LLM.md)', $content);
		self::assertStringContainsString('- (class) `Shredio\DocsGenerator\Path\PathFactory` - [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $content);
	}

	public function testApiWithComposerPackageWithoutDocFile(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/api.md', sprintf('%s/templates/api.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					['composer' => 'non-existent/package'],
				],
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# Api Test',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Composer package "non-existent/package" does not have any documentation file');

		$generator->create($fileProvider);
	}

	public function testApiWithoutFrontmatter(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/api.md'),
			frontmatter: [
				'output' => [
					'target' => 'api-output.md',
				],
			],
			content: '# No Api',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('api-output.md', $result);
		self::assertStringNotContainsString('## API Reference', $result['api-output.md']);
	}

	public function testExamplesWithStringClass(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/examples.md', sprintf('%s/templates/examples.md', $rootDir), PathType::File),
			frontmatter: [
				'examples' => [
					'Shredio\DocsGenerator\Path\PathFactory',
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('examples-output.md', $result);
		$content = $result['examples-output.md'];
		self::assertStringContainsString('## Examples', $content);
		self::assertStringContainsString('- `Shredio\DocsGenerator\Path\PathFactory` - [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $content);
	}

	public function testExamplesWithArrayClass(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/examples.md', sprintf('%s/templates/examples.md', $rootDir), PathType::File),
			frontmatter: [
				'examples' => [
					['class' => 'Shredio\DocsGenerator\Path\ResolvedPath'],
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		$content = $result['examples-output.md'];
		self::assertStringContainsString('- `Shredio\DocsGenerator\Path\ResolvedPath` - [src/Path/ResolvedPath.php](../src/Path/ResolvedPath.php)', $content);
	}

	public function testExamplesWithFileReference(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/examples.md', sprintf('%s/templates/examples.md', $rootDir), PathType::File),
			frontmatter: [
				'examples' => [
					['file' => 'src/Path/PathFactory.php'],
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		$content = $result['examples-output.md'];
		self::assertStringContainsString('- [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $content);
	}

	public function testExamplesMultiplePluralHeading(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/examples.md', sprintf('%s/templates/examples.md', $rootDir), PathType::File),
			frontmatter: [
				'examples' => [
					'Shredio\DocsGenerator\Path\PathFactory',
					['class' => 'Shredio\DocsGenerator\Path\ResolvedPath'],
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		$content = $result['examples-output.md'];
		self::assertStringContainsString('## Examples', $content);
		self::assertStringContainsString('- `Shredio\DocsGenerator\Path\PathFactory` - [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $content);
		self::assertStringContainsString('- `Shredio\DocsGenerator\Path\ResolvedPath` - [src/Path/ResolvedPath.php](../src/Path/ResolvedPath.php)', $content);
	}

	public function testExamplesWithContainsValidation(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/examples.md', sprintf('%s/templates/examples.md', $rootDir), PathType::File),
			frontmatter: [
				'examples' => [
					['class' => 'Shredio\DocsGenerator\Path\PathFactory', 'contains' => ['createFileFromAbsolute']],
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertStringContainsString('- `Shredio\DocsGenerator\Path\PathFactory` - [src/Path/PathFactory.php](../src/Path/PathFactory.php)', $result['examples-output.md']);
	}

	public function testExamplesWithContainsValidationFails(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$generator = new DocTemplateGenerator($pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/examples.md', sprintf('%s/templates/examples.md', $rootDir), PathType::File),
			frontmatter: [
				'examples' => [
					['class' => 'Shredio\DocsGenerator\Path\PathFactory', 'contains' => ['nonExistentMethod']],
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('does not contain required string "nonExistentMethod"');

		$generator->create($fileProvider);
	}

	public function testExamplesWithNonExistentClass(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/examples.md'),
			frontmatter: [
				'examples' => [
					'NonExistent\ClassName',
				],
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# Examples Test',
			priority: 5,
		));

		$this->expectException(GeneratingFailedException::class);
		$this->expectExceptionMessage('Example class "NonExistent\ClassName" does not exist.');

		$generator->create($fileProvider);
	}

	public function testExamplesWithoutFrontmatter(): void
	{
		$generator = new DocTemplateGenerator($this->pathFactory);
		$fileProvider = new InMemoryFileProvider();
		$fileProvider->add(new ProvidedFile(
			path: $this->createSourcePath('templates/examples.md'),
			frontmatter: [
				'output' => [
					'target' => 'examples-output.md',
				],
			],
			content: '# No Examples',
			priority: 5,
		));

		$result = $generator->create($fileProvider);

		self::assertArrayHasKey('examples-output.md', $result);
		self::assertStringNotContainsString('## Examples', $result['examples-output.md']);
	}

	public function testComplexGeneration(): void
	{
		$rootDir = dirname(__DIR__, 2);
		$pathFactory = new PathFactory($rootDir);
		$docsPath = new ResolvedPath('docs', sprintf('%s/docs', $rootDir), PathType::Directory);
		$generator = new DocTemplateGenerator($pathFactory, $docsPath);
		$fileProvider = new InMemoryFileProvider();

		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/doc1.md', sprintf('%s/templates/doc1.md', $rootDir), PathType::File),
			frontmatter: [
				'docs' => [
					'target' => 'getting-started.md',
					'description' => 'Getting started guide',
				],
			],
			content: 'Getting started content',
			priority: 10,
		));
		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/doc2.md', sprintf('%s/templates/doc2.md', $rootDir), PathType::File),
			frontmatter: [
				'docs' => [
					'target' => 'advanced.md',
					'description' => 'Advanced usage',
				],
			],
			content: 'Advanced content',
			priority: 10,
		));

		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/skill.md', sprintf('%s/templates/skill.md', $rootDir), PathType::File),
			frontmatter: [
				'skill' => [
					'target' => '.claude/skills/my-skill',
					'description' => 'Project skill',
				],
			],
			content: "# Skill Instructions\n\nFollow the project conventions.",
			priority: 10,
		));

		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/command.md', sprintf('%s/templates/command.md', $rootDir), PathType::File),
			frontmatter: [
				'commands' => [
					[
						'name' => 'run-task',
						'prompt' => 'Run task with $ARGUMENTS',
					],
				],
			],
			content: "# Run Task\n\nExecute the task.",
			priority: 10,
		));

		$fileProvider->add(new ProvidedFile(
			path: new ResolvedPath('templates/main.md', sprintf('%s/templates/main.md', $rootDir), PathType::File),
			frontmatter: [
				'api' => [
					'Shredio\DocsGenerator\Path\PathFactory',
					['class' => 'Shredio\DocsGenerator\Path\ResolvedPath'],
				],
				'examples' => [
					'Shredio\DocsGenerator\Path\PathType',
				],
				'output' => [
					'target' => 'CLAUDE.md',
				],
			],
			content: 'Use {{ class-name: "Shredio\\DocsGenerator\\Path\\PathFactory" }} for path resolution.',
			priority: -1,
		));

		$result = $generator->create($fileProvider);

		$expectedDir = __DIR__ . '/expected';

		self::assertArrayHasKey('CLAUDE.md', $result);
		self::assertSame(file_get_contents(sprintf('%s/complex-main.md', $expectedDir)), $result['CLAUDE.md']);

		self::assertArrayHasKey('.claude/skills/my-skill/SKILL.md', $result);
		self::assertSame(file_get_contents(sprintf('%s/complex-skill.md', $expectedDir)), $result['.claude/skills/my-skill/SKILL.md']);

		self::assertArrayHasKey('.claude/commands/run-task.md', $result);
		self::assertSame(file_get_contents(sprintf('%s/complex-command.md', $expectedDir)), $result['.claude/commands/run-task.md']);

		self::assertArrayHasKey('docs/getting-started.md', $result);
		self::assertSame('Getting started content', $result['docs/getting-started.md']);

		self::assertArrayHasKey('docs/advanced.md', $result);
		self::assertSame('Advanced content', $result['docs/advanced.md']);
	}

	private function createSourcePath(string $relativePath): ResolvedPath
	{
		return new ResolvedPath($relativePath, sprintf('/project/%s', $relativePath), PathType::File);
	}

}
