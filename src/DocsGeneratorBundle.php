<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator;

use Shredio\DocsGenerator\Command\GenerateDocsCommand;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;

final class DocsGeneratorBundle extends AbstractBundle
{
	/**
	 * @param array<string, mixed> $config
	 */
	public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
	{
		$container->services()
			->set(GenerateDocsCommand::class)
			->args([
				$config['root_dir'],
				$config['source_dir'],
				$config['docs_dir'],
			])
			->tag('console.command');
	}

	public function configure(DefinitionConfigurator $definition): void
	{
		$definition->rootNode()
			->children() // @phpstan-ignore method.notFound
				->scalarNode('root_dir')
					->defaultValue('%kernel.project_dir%')
					->info('Root directory for the documentation generation.')
					->cannotBeEmpty()
					->end()
				->scalarNode('source_dir')->cannotBeEmpty()->end()
				->scalarNode('docs_dir')->defaultNull()->end()
			->end();
	}
}
