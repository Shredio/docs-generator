<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Command;

use Shredio\DocsGenerator\Command\ConsoleDocTemplateCommand;
use Shredio\DocsGenerator\Exception\LogicException;
use Shredio\DocsGenerator\Processor\Command\InlineDocTemplateCommand;
use Shredio\DocsGenerator\Processor\DocTemplateProcessor;
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
		private readonly ?string $claudeCommandsDir = null,
	)
	{
	}

	public function __invoke(SymfonyStyle $io, Application $application): int
	{
		try {
			$processor = new DocTemplateProcessor($this->rootDir, $this->claudeCommandsDir);
			$processor->addCommand(new InlineDocTemplateCommand('symfony-command', static function (DocTemplateContext $context, array $args) use ($application): string {
				$name = $args[0];
				$command = $application->get($name);
				$description = trim($command->getDescription());

				if ($description === '') {
					throw new LogicException(sprintf('Command "%s" does not have a description.', $name));
				}

				return sprintf('**%s**: `bin/console %s`', $description, $name);
			}));

			$directory = $this->rootDir . '/' . ltrim($this->sourceDir, '/');
			
			foreach ($processor->processTemplates($directory) as $createdFile) {
				$io->writeln($createdFile);
			}

			return Command::SUCCESS;
		} catch (LogicException $e) {
			$io->error($e->getMessage());
			return Command::FAILURE;
		}
	}



}
