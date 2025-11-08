<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Command;

use Shredio\DocsGenerator\Exception\GeneratingFailedException;
use Shredio\DocsGenerator\Generator\DocTemplateGenerator;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('docs:generate')]
final class GenerateDocsCommand
{

	public function __construct(
		private readonly string $rootDir,
		private readonly string $sourceDir,
	)
	{
	}

	public function __invoke(SymfonyStyle $io, Application $application): int
	{
		$generator = new DocTemplateGenerator($this->rootDir);

		try {
			$generator->generate($this->sourceDir);
		} catch (GeneratingFailedException $e) {
			$io->error($e->getMessage());

			return Command::FAILURE;
		}

		return Command::SUCCESS;
	}



}
