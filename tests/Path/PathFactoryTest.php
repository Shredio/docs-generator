<?php declare(strict_types = 1);

namespace Tests\Path;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Path\PathFactory;
use Shredio\DocsGenerator\Path\PathType;

final class PathFactoryTest extends TestCase
{

	public function testConstructorWithRelativePathThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path must be absolute.');

		new PathFactory('relative/path');
	}

	public function testConstructorWithEmptyPathThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Directory cannot be empty string or "/" path.');

		new PathFactory('');
	}

	public function testConstructorWithSlashOnlyThrowsException(): void
	{
		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Directory cannot be empty string or "/" path.');

		new PathFactory('/');
	}

	public function testCreateFileFromAbsoluteWithValidPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createFileFromAbsolute('/home/user/project/src/File.php');

		$this->assertSame('src/File.php', $path->getRelativePathToRootDir());
		$this->assertSame('/home/user/project/src/File.php', $path->getAbsolutePath());
		$this->assertTrue($path->isFile());
		$this->assertFalse($path->isDirectory());
		$this->assertSame(PathType::File, $path->getType());
	}

	public function testCreateDirectoryFromAbsoluteWithValidPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createDirectoryFromAbsolute('/home/user/project/src');

		$this->assertSame('src', $path->getRelativePathToRootDir());
		$this->assertSame('/home/user/project/src', $path->getAbsolutePath());
		$this->assertTrue($path->isDirectory());
		$this->assertFalse($path->isFile());
		$this->assertSame(PathType::Directory, $path->getType());
	}

	public function testCreateFileFromAbsoluteWithFileInRootDir(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createFileFromAbsolute('/home/user/project/file.txt');

		$this->assertSame('file.txt', $path->getRelativePathToRootDir());
		$this->assertSame('/home/user/project/file.txt', $path->getAbsolutePath());
	}

	public function testCreateFileFromAbsoluteWithNestedPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createFileFromAbsolute('/home/user/project/src/deep/nested/File.php');

		$this->assertSame('src/deep/nested/File.php', $path->getRelativePathToRootDir());
	}

	public function testCreateFileFromAbsoluteWithRelativePathThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path must be absolute.');

		$factory->createFileFromAbsolute('relative/path');
	}

	public function testCreateFileFromAbsoluteWithPathOutsideRootThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is not within the root directory');

		$factory->createFileFromAbsolute('/home/user/other/file.txt');
	}

	public function testCreateFileFromAbsoluteNormalizesTrailingSlash(): void
	{
		$factory = new PathFactory('/home/user/project/');
		$path = $factory->createFileFromAbsolute('/home/user/project/src/File.php');

		$this->assertSame('src/File.php', $path->getRelativePathToRootDir());
	}

	public function testCreateFileFromAbsoluteNormalizesDotsInPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createFileFromAbsolute('/home/user/project/src/../src/File.php');

		$this->assertSame('src/File.php', $path->getRelativePathToRootDir());
	}

	public function testCreateDirectoryFromAbsoluteWithRelativePathThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path must be absolute.');

		$factory->createDirectoryFromAbsolute('relative/path');
	}

	public function testCreateDirectoryFromAbsoluteWithPathOutsideRootThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is not within the root directory');

		$factory->createDirectoryFromAbsolute('/home/user/other');
	}

	public function testCreateFileFromRelativeWithValidPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createFileFromRelative('src/File.php');

		$this->assertSame('src/File.php', $path->getRelativePathToRootDir());
		$this->assertSame('/home/user/project/src/File.php', $path->getAbsolutePath());
		$this->assertTrue($path->isFile());
	}

	public function testCreateDirectoryFromRelativeWithValidPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createDirectoryFromRelative('src/Controllers');

		$this->assertSame('src/Controllers', $path->getRelativePathToRootDir());
		$this->assertSame('/home/user/project/src/Controllers', $path->getAbsolutePath());
		$this->assertTrue($path->isDirectory());
	}

	public function testCreateFileFromRelativeWithEmptyPathThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path must not be empty.');

		$factory->createFileFromRelative('');
	}

	public function testCreateFileFromRelativeWithAbsolutePathThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path must be relative.');

		$factory->createFileFromRelative('/absolute/path');
	}

	public function testCreateFileFromRelativeWithPathOutsideRootThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is not within the root directory');

		$factory->createFileFromRelative('../../etc/passwd');
	}

	public function testCreateFileFromRelativeNormalizesDotsInPath(): void
	{
		$factory = new PathFactory('/home/user/project');
		$path = $factory->createFileFromRelative('src/../src/File.php');

		$this->assertSame('src/File.php', $path->getRelativePathToRootDir());
		$this->assertSame('/home/user/project/src/File.php', $path->getAbsolutePath());
	}

	public function testCreateDirectoryFromRelativeWithEmptyPathThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('Path must not be empty.');

		$factory->createDirectoryFromRelative('');
	}

	public function testCreateDirectoryFromRelativeWithPathOutsideRootThrowsException(): void
	{
		$factory = new PathFactory('/home/user/project');

		$this->expectException(InvalidArgumentException::class);
		$this->expectExceptionMessage('is not within the root directory');

		$factory->createDirectoryFromRelative('../other');
	}

}
