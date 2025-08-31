<?php declare(strict_types = 1);

namespace Tests\Processor;

use Nette\Utils\FileSystem;
use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Command\DocTemplateContext;
use Shredio\DocsGenerator\Exception\LogicException;  
use Shredio\DocsGenerator\Processor\DocTemplateProcessor;
use Shredio\DocsGenerator\Processor\Command\InlineDocTemplateCommand;

final class DocTemplateProcessorTest extends TestCase
{
    private string $tempDir;
    private string $rootDir;
    private DocTemplateProcessor $processor;

    protected function setUp(): void
    {
        $this->tempDir = __DIR__ . '/../tmp/test_' . uniqid();
        $this->rootDir = $this->tempDir . '/root';
        mkdir($this->tempDir, 0777, true);
        mkdir($this->rootDir, 0777, true);
        
        $this->processor = new DocTemplateProcessor($this->rootDir);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            FileSystem::delete($this->tempDir);
        }
    }

    public function testConstructorSetsRootDir(): void
    {
        $processor = new DocTemplateProcessor('/test/root');
        
        $this->assertInstanceOf(DocTemplateProcessor::class, $processor);
    }

    public function testConstructorWithClaudeCommandsDir(): void
    {
        $processor = new DocTemplateProcessor('/test/root', '/test/claude');
        
        $this->assertInstanceOf(DocTemplateProcessor::class, $processor);
    }

    public function testAddCommand(): void
    {
        $command = new InlineDocTemplateCommand('test', fn(DocTemplateContext $context, array $args): string => 'test result');
        
        $this->processor->addCommand($command);
        
        $this->expectNotToPerformAssertions();
    }

    public function testProcessTemplatesThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Directory "/non/existent/directory" does not exist.');
        
        iterator_to_array($this->processor->processTemplates('/non/existent/directory'));
    }

    public function testProcessTemplatesWithEmptyDirectory(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $this->expectNotToPerformAssertions();
    }

    public function testProcessTemplatesWithSimpleTemplate(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        $this->assertSame("Hello World\n", $content);
    }

    public function testProcessTemplatesWithMultipleTargets(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output1.md\ntarget: output2.md\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile1 = $this->rootDir . '/output1.md';
        $outputFile2 = $this->rootDir . '/output2.md';
        
        $this->assertFileExists($outputFile1);
        $this->assertFileExists($outputFile2);
        $content1 = file_get_contents($outputFile1);
        $content2 = file_get_contents($outputFile2);
        $this->assertSame("Hello World\n", $content1);
        $this->assertSame("Hello World\n", $content2);
    }

    public function testProcessTemplatesWithBuiltinCommands(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\nContent: {{ print: " . DocTemplateProcessor::class . " }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        
        $content = file_get_contents($outputFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('Content:', $content);
        $this->assertStringContainsString('final class DocTemplateProcessor', $content);
    }

    public function testProcessTemplatesWithCustomCommand(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $command = new InlineDocTemplateCommand('test', function(DocTemplateContext $context, array $args): string {
            return 'Custom result: ' . implode(', ', $args);
        });
        $this->processor->addCommand($command);
        
        $templateContent = "---\ntarget: output.md\n---\n\nResult: {{ test: arg1, arg2 }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        $this->assertSame("Result: Custom result: arg1, arg2\n", $content);
    }

    public function testProcessTemplatesWithIncludeCommand(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $includeContent = "This is included content";
        file_put_contents($templateDir . '/include.md', $includeContent);
        
        $templateContent = "---\ntarget: output.md\n---\n\nContent: {{ include: include.md }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        $this->assertSame("Content: This is included content\n", $content);
    }

    public function testProcessTemplatesWithClaudeCommandTarget(): void
    {
        $claudeDir = $this->tempDir . '/claude';
        mkdir($claudeDir);
        
        $processor = new DocTemplateProcessor($this->rootDir, $claudeDir);
        
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\nclaude-command-target: command.md\nclaude-command-prompt: Test prompt\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $claudeFile = $claudeDir . '/command.md';
        
        $this->assertFileExists($outputFile);
        $this->assertFileExists($claudeFile);
        $outputContent = file_get_contents($outputFile);
        $claudeContent = file_get_contents($claudeFile);
        $this->assertSame("Hello World\n", $outputContent);
        $this->assertSame("Test prompt\n\nHello World\n", $claudeContent);
    }

    public function testProcessTemplatesWithClaudeCommandPrompt(): void
    {
        $claudeDir = $this->tempDir . '/claude';
        mkdir($claudeDir);
        
        $processor = new DocTemplateProcessor($this->rootDir, $claudeDir);
        
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\nclaude-command-target: command.md\nclaude-command-prompt: Custom prompt\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($processor->processTemplates($templateDir));
        
        $claudeFile = $claudeDir . '/command.md';
        $this->assertFileExists($claudeFile);
        $claudeContent = file_get_contents($claudeFile);
        $this->assertSame("Custom prompt\n\nHello World\n", $claudeContent);
    }

    public function testProcessTemplatesWithMultipleClaudeCommandTargets(): void
    {
        $claudeDir = $this->tempDir . '/claude';
        mkdir($claudeDir);
        
        $processor = new DocTemplateProcessor($this->rootDir, $claudeDir);
        
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\nclaude-command-target: command1.md\nclaude-command-target: command2.md\nclaude-command-prompt: Test prompt\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $claudeFile1 = $claudeDir . '/command1.md';
        $claudeFile2 = $claudeDir . '/command2.md';
        
        $this->assertFileExists($outputFile);
        $this->assertFileExists($claudeFile1);
        $this->assertFileExists($claudeFile2);
        $outputContent = file_get_contents($outputFile);
        $claudeContent1 = file_get_contents($claudeFile1);
        $claudeContent2 = file_get_contents($claudeFile2);
        $this->assertSame("Hello World\n", $outputContent);
        $this->assertSame("Test prompt\n\nHello World\n", $claudeContent1);
        $this->assertSame("Test prompt\n\nHello World\n", $claudeContent2);
    }

    public function testProcessTemplatesThrowsExceptionForMultipleClaudeCommandPrompts(): void
    {
        $claudeDir = $this->tempDir . '/claude';
        mkdir($claudeDir);
        
        $processor = new DocTemplateProcessor($this->rootDir, $claudeDir);
        
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\nclaude-command-target: command.md\nclaude-command-prompt: Prompt 1\nclaude-command-prompt: Prompt 2\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Multiple claude-command-prompt headers found. Only one is allowed.');
        
        iterator_to_array($processor->processTemplates($templateDir));
    }

    public function testProcessTemplatesThrowsExceptionForTargetWithoutPrompt(): void
    {
        $claudeDir = $this->tempDir . '/claude';
        mkdir($claudeDir);
        
        $processor = new DocTemplateProcessor($this->rootDir, $claudeDir);
        
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\nclaude-command-target: command.md\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('claude-command-prompt header is required when claude-command-target headers are present.');
        
        iterator_to_array($processor->processTemplates($templateDir));
    }

    public function testProcessTemplatesWithPromptOnly(): void
    {
        $claudeDir = $this->tempDir . '/claude';
        mkdir($claudeDir);
        
        $processor = new DocTemplateProcessor($this->rootDir, $claudeDir);
        
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\nclaude-command-prompt: Test prompt\n---\n\nHello World";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $claudeFile = $claudeDir . '/test.md';
        
        $this->assertFileExists($outputFile);
        $this->assertFileExists($claudeFile);
        $outputContent = file_get_contents($outputFile);
        $claudeContent = file_get_contents($claudeFile);
        $this->assertSame("Hello World\n", $outputContent);
        $this->assertSame("Test prompt\n\nHello World\n", $claudeContent);
    }

    public function testDumpCommandThrowsExceptionForNonExistentClass(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\n{{ dump: NonExistentClass }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class "NonExistentClass" does not exist.');
        
        iterator_to_array($this->processor->processTemplates($templateDir));
    }

    public function testPrintCommandThrowsExceptionForNonExistentClass(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\n{{ print: NonExistentClass }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Class "NonExistentClass" does not exist.');
        
        iterator_to_array($this->processor->processTemplates($templateDir));
    }

    public function testIncludeCommandThrowsExceptionForNonExistentFile(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\n{{ include: non-existent.md }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('File "' . $templateDir . '/non-existent.md" does not exist.');
        
        iterator_to_array($this->processor->processTemplates($templateDir));
    }

    public function testProcessTemplatesWithNestedIncludes(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $nestedContent = "Nested content";
        file_put_contents($templateDir . '/nested.md', $nestedContent);
        
        $includeContent = "Include start\n{{ include: nested.md }}\nInclude end";
        file_put_contents($templateDir . '/include.md', $includeContent);
        
        $templateContent = "---\ntarget: output.md\n---\n\n{{ include: include.md }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        $content = file_get_contents($outputFile);
        $this->assertSame("Include start\nNested content\nInclude end\n", $content);
    }

    public function testProcessTemplatesWithDumpCommand(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\n{{ dump: " . DocTemplateProcessor::class . " }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        
        $content = file_get_contents($outputFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('Code snippet of **' . DocTemplateProcessor::class . '**:', $content);
        $this->assertStringContainsString('```php', $content);
    }

    public function testProcessTemplatesWithDumpCommandAndVisibilityFilter(): void
    {
        $templateDir = $this->tempDir . '/templates';
        mkdir($templateDir);
        
        $templateContent = "---\ntarget: output.md\n---\n\n{{ dump: " . DocTemplateProcessor::class . ", \"public,private\" }}";
        file_put_contents($templateDir . '/test.md', $templateContent);
        
        iterator_to_array($this->processor->processTemplates($templateDir));
        
        $outputFile = $this->rootDir . '/output.md';
        $this->assertFileExists($outputFile);
        
        $content = file_get_contents($outputFile);
        $this->assertIsString($content);
        $this->assertStringContainsString('Code snippet of **' . DocTemplateProcessor::class . '**:', $content);
    }
}
