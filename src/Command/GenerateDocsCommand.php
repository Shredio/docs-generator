<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Command;

use Nette\Utils\FileSystem;
use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Generator\DocTemplateGenerator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('docs:generate')]
final readonly class GenerateDocsCommand
{

	public function __construct(
		private string $rootDir,
		private string $sourceDir,
		private ?string $docsDir,
	)
	{
	}

	public function __invoke(SymfonyStyle $io, Application $application): int
	{
		$generator = new DocTemplateGenerator(
			$this->rootDir,
			$this->docsDir === null ? null : FileSystem::resolvePath($this->rootDir, $this->docsDir),
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
