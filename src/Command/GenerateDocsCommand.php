<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Command;

use Nette\Utils\FileSystem;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Generator\DocTemplateGenerator;
use Shredio\DocsGenerator\Path\PathFactory;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('docs:generate')]
final readonly class GenerateDocsCommand
{

	/**
	 * @param non-empty-string $rootDir
	 * @param non-empty-string $sourceDir
	 */
	public function __construct(
		private string $rootDir,
		private string $sourceDir,
		private ?string $docsDir,
	)
	{
	}

	public function __invoke(SymfonyStyle $io, Application $application): int
	{
		$pathFactory = new PathFactory($this->rootDir);
		$generator = new DocTemplateGenerator(
			$pathFactory,
			$this->docsDir === null ? null : $pathFactory->createDirectoryFromRelative($this->docsDir),
		);

		try {
			$generator->generate(FileSystem::resolvePath($this->rootDir, $this->sourceDir));
		} catch (GeneratingFailedException $e) {
			$io->error($e->getMessage());

			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}



}
