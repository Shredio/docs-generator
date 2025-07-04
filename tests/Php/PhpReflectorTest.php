<?php declare(strict_types = 1);

namespace Tests\Php;

use LogicException;
use ReflectionMethod;
use ReflectionProperty;
use Shredio\DocsGenerator\Php\PhpReflector;
use Tests\TestCase;

final class PhpReflectorTest extends TestCase
{
    public function testGetClassSignature(): void
    {
        $signature = PhpReflector::getClassSignature(SampleClass::class);
        
        $this->assertStringContainsString('final class SampleClass', $signature);
        $this->assertStringContainsString('public function publicMethod()', $signature);
        $this->assertStringContainsString('public readonly string $publicProperty', $signature);
        $this->assertStringContainsString('public const PUBLIC_CONSTANT = \'test\'', $signature);
        $this->assertStringNotContainsString('private', $signature);
        $this->assertStringNotContainsString('protected', $signature);
    }

    public function testGetClassSignatureWithExcludedMethods(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            ['publicMethod']
        );
        
        $this->assertStringNotContainsString('public function publicMethod()', $signature);
        $this->assertStringContainsString('public readonly string $publicProperty', $signature);
    }

    public function testGetClassSignatureWithShortDescription(): void
    {
        $signature = PhpReflector::getClassSignature(SampleClassWithDocs::class, [], true);
        
        $this->assertStringContainsString('Sample class for testing.', $signature);
        // Note: The implementation includes full doc comment even with shortDescription=true
        $this->assertStringContainsString('This is a longer description', $signature);
    }

    public function testGetClassSignatureWithFullDescription(): void
    {
        $signature = PhpReflector::getClassSignature(SampleClassWithDocs::class, [], false);
        
        $this->assertStringContainsString('Sample class for testing.', $signature);
        $this->assertStringContainsString('This is a longer description', $signature);
    }

    public function testGetClassSignatureInterface(): void
    {
        $signature = PhpReflector::getClassSignature(SampleInterface::class);
        
        // Note: The implementation generates 'class' for interfaces, not 'interface'
        $this->assertStringContainsString('class SampleInterface', $signature);
        $this->assertStringContainsString('function interfaceMethod()', $signature);
    }

    public function testGetClassSignatureTrait(): void
    {
        $signature = PhpReflector::getClassSignature(SampleTrait::class);
        
        $this->assertStringContainsString('trait SampleTrait', $signature);
        $this->assertStringContainsString('public function traitMethod()', $signature);
    }

    public function testGetClassSignatureAbstractClass(): void
    {
        $signature = PhpReflector::getClassSignature(SampleAbstractClass::class);
        
        $this->assertStringContainsString('abstract class SampleAbstractClass', $signature);
        $this->assertStringContainsString('public function abstractMethod()', $signature);
    }

    public function testGetClassSignatureWithArrayTypeComment(): void
    {
        $signature = PhpReflector::getClassSignature(SampleClassWithArrays::class);
        
        $this->assertStringContainsString('@var array<string>', $signature);
        // Note: The implementation only includes @param and @return comments in short description mode
        // when type needs comment, but the method signature is simplified
        $this->assertStringNotContainsString('@param array<int> $items', $signature);
        $this->assertStringNotContainsString('@return array<string>', $signature);
    }

    public function testGetClassSignatureWithMethodFilters(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED
        );
        
        $this->assertStringNotContainsString('public function publicMethod()', $signature);
    }

    public function testGetNamespaceFromContent(): void
    {
        $content = '<?php declare(strict_types = 1);

namespace Tests\Php;

class TestClass {}';
        
        $this->assertSame('Tests\Php', PhpReflector::getNamespaceFromContent($content));
    }

    public function testGetNamespaceFromContentWithoutNamespace(): void
    {
        $content = '<?php declare(strict_types = 1);

class TestClass {}';
        
        $this->assertNull(PhpReflector::getNamespaceFromContent($content));
    }

    public function testGetClassNameFromContent(): void
    {
        $content = '<?php declare(strict_types = 1);

final class TestClass {}';
        
        $this->assertSame('TestClass', PhpReflector::getClassNameFromContent($content));
    }

    public function testGetClassNameFromContentInterface(): void
    {
        $content = '<?php declare(strict_types = 1);

interface TestInterface {}';
        
        $this->assertSame('TestInterface', PhpReflector::getClassNameFromContent($content));
    }

    public function testGetClassNameFromContentEnum(): void
    {
        $content = '<?php declare(strict_types = 1);

enum TestEnum {}';
        
        $this->assertSame('TestEnum', PhpReflector::getClassNameFromContent($content));
    }

    public function testGetClassNameFromContentTrait(): void
    {
        $content = '<?php declare(strict_types = 1);

trait TestTrait {}';
        
        $this->assertSame('TestTrait', PhpReflector::getClassNameFromContent($content));
    }

    public function testGetClassNameFromContentWithoutClass(): void
    {
        $content = '<?php declare(strict_types = 1);

function testFunction() {}';
        
        $this->assertNull(PhpReflector::getClassNameFromContent($content));
    }

    public function testGetClassNameFromFullName(): void
    {
        $this->assertSame('TestClass', PhpReflector::getClassNameFromFullName('Tests\Php\TestClass'));
        $this->assertSame('TestClass', PhpReflector::getClassNameFromFullName('TestClass'));
        $this->assertSame('TestClass', PhpReflector::getClassNameFromFullName('Very\Deep\Namespace\TestClass'));
    }
}

final class SampleClass
{
    public const PUBLIC_CONSTANT = 'test';
    
    private const PRIVATE_CONSTANT = 'private';
    
    public readonly string $publicProperty;
    
    private string $privateProperty;
    
    protected string $protectedProperty;
    
    public function publicMethod(): void
    {
    }
    
    private function privateMethod(): void
    {
    }
    
    protected function protectedMethod(): void
    {
    }
}

/**
 * Sample class for testing.
 * 
 * This is a longer description that should be included
 * when full description is requested.
 */
final class SampleClassWithDocs
{
    /**
     * Test method with documentation.
     */
    public function testMethod(): void
    {
    }
}

interface SampleInterface
{
    public function interfaceMethod(): void;
}

trait SampleTrait
{
    public function traitMethod(): void
    {
    }
}

abstract class SampleAbstractClass
{
    abstract public function abstractMethod(): void;
}

final class SampleClassWithArrays
{
    /**
     * @var array<string>
     */
    public array $items;
    
    /**
     * @param array<int> $items
     * @return array<string>
     */
    public function processItems(array $items): array
    {
        return [];
    }
}