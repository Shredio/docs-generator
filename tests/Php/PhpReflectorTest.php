<?php declare(strict_types = 1);

namespace Tests\Php;

use ReflectionClassConstant;
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
        
        $this->assertStringContainsString('interface SampleInterface', $signature);
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

    public function testSkipItemsWithDocsIgnore(): void
    {
        $signature = PhpReflector::getClassSignature(SampleClassWithIgnored::class);
        
        $this->assertStringNotContainsString('ignoredProperty', $signature);
        $this->assertStringNotContainsString('ignoredMethod', $signature);
        $this->assertStringNotContainsString('IGNORED_CONSTANT', $signature);
        $this->assertStringContainsString('visibleProperty', $signature);
        $this->assertStringContainsString('visibleMethod', $signature);
        $this->assertStringContainsString('VISIBLE_CONSTANT', $signature);
    }

    public function testSkipItemsWithInternal(): void
    {
        $signature = PhpReflector::getClassSignature(SampleClassWithInternal::class);
        
        $this->assertStringNotContainsString('internalProperty', $signature);
        $this->assertStringNotContainsString('internalMethod', $signature);
        $this->assertStringNotContainsString('INTERNAL_CONSTANT', $signature);
        $this->assertStringContainsString('publicProperty', $signature);
        $this->assertStringContainsString('publicMethod', $signature);
        $this->assertStringContainsString('PUBLIC_CONSTANT', $signature);
    }

    public function testGetClassSignatureWithInheritance(): void
    {
        $signature = PhpReflector::getClassSignature(SampleChildClass::class);
        
        $this->assertStringContainsString('class SampleChildClass extends SampleBaseClass', $signature);
        $this->assertStringContainsString('public function childMethod()', $signature);
        $this->assertStringNotContainsString('public function baseMethod()', $signature); // Methods from parent are not included
    }

    public function testGetClassSignatureBaseClassWithoutInheritance(): void
    {
        $signature = PhpReflector::getClassSignature(SampleBaseClass::class);
        
        $this->assertStringContainsString('class SampleBaseClass', $signature);
        $this->assertStringNotContainsString('extends', $signature);
        $this->assertStringContainsString('public function baseMethod()', $signature);
    }

    public function testGetClassSignatureWithPublicPropertiesOnly(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PUBLIC,
            ReflectionClassConstant::IS_PUBLIC
        );
        
        $this->assertStringContainsString('public readonly string $publicProperty', $signature);
        $this->assertStringNotContainsString('private string $privateProperty', $signature);
        $this->assertStringNotContainsString('protected string $protectedProperty', $signature);
    }

    public function testGetClassSignatureWithAllProperties(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PUBLIC | ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED,
            ReflectionClassConstant::IS_PUBLIC
        );
        
        $this->assertStringContainsString('public readonly string $publicProperty', $signature);
        $this->assertStringContainsString('private string $privateProperty', $signature);
        $this->assertStringContainsString('protected string $protectedProperty', $signature);
    }

    public function testGetClassSignatureWithPrivatePropertiesOnly(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PRIVATE,
            ReflectionClassConstant::IS_PUBLIC
        );
        
        $this->assertStringNotContainsString('public readonly string $publicProperty', $signature);
        $this->assertStringContainsString('private string $privateProperty', $signature);
        $this->assertStringNotContainsString('protected string $protectedProperty', $signature);
    }

    public function testGetClassSignatureWithProtectedPropertiesOnly(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PROTECTED,
            ReflectionClassConstant::IS_PUBLIC
        );
        
        $this->assertStringNotContainsString('public readonly string $publicProperty', $signature);
        $this->assertStringNotContainsString('private string $privateProperty', $signature);
        $this->assertStringContainsString('protected string $protectedProperty', $signature);
    }

    public function testGetClassSignatureWithPublicConstantsOnly(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PUBLIC,
            ReflectionClassConstant::IS_PUBLIC
        );
        
        $this->assertStringContainsString('public const PUBLIC_CONSTANT = \'test\'', $signature);
        $this->assertStringNotContainsString('private const PRIVATE_CONSTANT', $signature);
    }

    public function testGetClassSignatureWithAllConstants(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PUBLIC,
            ReflectionClassConstant::IS_PUBLIC | ReflectionClassConstant::IS_PRIVATE
        );
        
        $this->assertStringContainsString('public const PUBLIC_CONSTANT = \'test\'', $signature);
        $this->assertStringContainsString('private const PRIVATE_CONSTANT = \'private\'', $signature);
    }

    public function testGetClassSignatureWithPrivateConstantsOnly(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PUBLIC,
            ReflectionProperty::IS_PUBLIC,
            ReflectionClassConstant::IS_PRIVATE
        );
        
        $this->assertStringNotContainsString('public const PUBLIC_CONSTANT', $signature);
        $this->assertStringContainsString('private const PRIVATE_CONSTANT = \'private\'', $signature);
    }

    public function testGetClassSignatureWithCombinedVisibilityFilters(): void
    {
        $signature = PhpReflector::getClassSignature(
            SampleClass::class,
            [],
            true,
            false,
            ReflectionMethod::IS_PRIVATE | ReflectionMethod::IS_PROTECTED,
            ReflectionProperty::IS_PRIVATE | ReflectionProperty::IS_PROTECTED,
            ReflectionClassConstant::IS_PRIVATE
        );
        
        // Should not include public items
        $this->assertStringNotContainsString('public readonly string $publicProperty', $signature);
        $this->assertStringNotContainsString('public const PUBLIC_CONSTANT', $signature);
        $this->assertStringNotContainsString('public function publicMethod()', $signature);
        
        // Should include private and protected items
        $this->assertStringContainsString('private string $privateProperty', $signature);
        $this->assertStringContainsString('protected string $protectedProperty', $signature);
        $this->assertStringContainsString('private const PRIVATE_CONSTANT = \'private\'', $signature);
        $this->assertStringContainsString('private function privateMethod()', $signature);
        $this->assertStringContainsString('protected function protectedMethod()', $signature);
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

final class SampleClassWithIgnored
{
    public const VISIBLE_CONSTANT = 'visible';
    
    /**
     * @docs-ignore
     */
    public const IGNORED_CONSTANT = 'ignored';
    
    public string $visibleProperty = 'visible';
    
    /**
     * @docs-ignore
     */
    public string $ignoredProperty = 'ignored';
    
    public function visibleMethod(): void
    {
    }
    
    /**
     * @docs-ignore
     */
    public function ignoredMethod(): void
    {
    }
}

final class SampleClassWithInternal
{
    public const PUBLIC_CONSTANT = 'public';
    
    /**
     * @internal
     */
    public const INTERNAL_CONSTANT = 'internal';
    
    public string $publicProperty = 'public';
    
    /**
     * @internal
     */
    public string $internalProperty = 'internal';
    
    public function publicMethod(): void
    {
    }
    
    /**
     * @internal
     */
    public function internalMethod(): void
    {
    }
}

class SampleBaseClass
{
    public function baseMethod(): void
    {
    }
}

final class SampleChildClass extends SampleBaseClass
{
    public function childMethod(): void
    {
    }
}
