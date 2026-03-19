<?php declare(strict_types = 1);

namespace Tests\Path;

use PHPUnit\Framework\TestCase;
use Shredio\DocsGenerator\Path\PathType;
use Shredio\DocsGenerator\Path\ResolvedPath;

final class ResolvedPathTest extends TestCase
{

	public function testRelativeToSiblingFile(): void
	{
		$fileA = new ResolvedPath('src/Controller/UserController.php', '/project/src/Controller/UserController.php', PathType::File);
		$fileB = new ResolvedPath('src/Controller/PostController.php', '/project/src/Controller/PostController.php', PathType::File);

		$this->assertSame('UserController.php', $fileA->relativeTo($fileB));
		$this->assertSame('PostController.php', $fileB->relativeTo($fileA));
	}

	public function testRelativeToFileInDifferentDirectory(): void
	{
		$fileA = new ResolvedPath('src/Service/UserService.php', '/project/src/Service/UserService.php', PathType::File);
		$fileB = new ResolvedPath('src/Controller/PostController.php', '/project/src/Controller/PostController.php', PathType::File);

		$this->assertSame('../Service/UserService.php', $fileA->relativeTo($fileB));
	}

	public function testRelativeToFileInParentDirectory(): void
	{
		$fileA = new ResolvedPath('src/deep/nested/File.php', '/project/src/deep/nested/File.php', PathType::File);
		$fileB = new ResolvedPath('src/Index.php', '/project/src/Index.php', PathType::File);

		$this->assertSame('deep/nested/File.php', $fileA->relativeTo($fileB));
	}

	public function testRelativeToCompletelyDifferentBranch(): void
	{
		$fileA = new ResolvedPath('src/Service/Auth.php', '/project/src/Service/Auth.php', PathType::File);
		$fileB = new ResolvedPath('tests/Unit/AuthTest.php', '/project/tests/Unit/AuthTest.php', PathType::File);

		$this->assertSame('../../src/Service/Auth.php', $fileA->relativeTo($fileB));
	}

	public function testRelativeToSelf(): void
	{
		$file = new ResolvedPath('src/File.php', '/project/src/File.php', PathType::File);

		$this->assertSame('File.php', $file->relativeTo($file));
	}

	public function testRelativeToDirectory(): void
	{
		$file = new ResolvedPath('src/Service/Auth.php', '/project/src/Service/Auth.php', PathType::File);
		$dir = new ResolvedPath('src', '/project/src', PathType::Directory);

		$this->assertSame('Service/Auth.php', $file->relativeTo($dir));
	}

	public function testRelativeToDirectoryInDifferentBranch(): void
	{
		$file = new ResolvedPath('src/Service/Auth.php', '/project/src/Service/Auth.php', PathType::File);
		$dir = new ResolvedPath('tests/Unit', '/project/tests/Unit', PathType::Directory);

		$this->assertSame('../../src/Service/Auth.php', $file->relativeTo($dir));
	}

	public function testRelativeDirectoryToDirectory(): void
	{
		$dirA = new ResolvedPath('src/Service', '/project/src/Service', PathType::Directory);
		$dirB = new ResolvedPath('src/Controller', '/project/src/Controller', PathType::Directory);

		$this->assertSame('../Service', $dirA->relativeTo($dirB));
	}

}
