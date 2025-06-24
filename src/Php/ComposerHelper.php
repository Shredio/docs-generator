<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Php;

use LogicException;
use Nette\Utils\FileSystem;
use Nette\Utils\Json;
use RuntimeException;

final class ComposerHelper
{

	/**
	 * @var array<string, mixed>|null
	 */
	private static ?array $composerJson = null;

	/**
	 * @var array<string, mixed>|null
	 */
	private static ?array $composerLockJson = null;

	/**
	 * @return array<string, string>
	 */
	public static function getPsr4Autoloading(): array
	{
		$composerJson = self::getComposerJson();

		if (!isset($composerJson['autoload']) || !is_array($composerJson['autoload']) || 
		    !isset($composerJson['autoload']['psr-4']) || !is_array($composerJson['autoload']['psr-4'])) {
			throw new LogicException('PSR-4 autoloading is not defined in composer.json.');
		}

		$psr4 = $composerJson['autoload']['psr-4'];
		$result = [];
		foreach ($psr4 as $namespace => $paths) {
			if (is_array($paths) && isset($paths[0]) && is_string($paths[0])) {
				$result[$namespace] = $paths[0];
			} elseif (is_string($paths)) {
				$result[$namespace] = $paths;
			}
		}
		return $result;
	}

	/**
	 * @return array<string, array{version: string}>
	 */
	public static function getInstalledPackages(): array
	{
		$composerData = self::getComposerLockJson();

		if (!isset($composerData['packages']) || !is_array($composerData['packages'])) {
			return [];
		}

		$packages = [];

		foreach ($composerData['packages'] as $package) {
			if (!is_array($package) || !isset($package['name']) || !is_string($package['name'])) {
				continue;
			}
			
			$name = $package['name'];
			$version = isset($package['version']) && is_string($package['version']) ? $package['version'] : 'unknown';

			$packages[$name] = [
				'version' => $version,
			];
		}

		return $packages;
	}

	public static function getPackageVersion(string $package): string
	{
		$installedPackages = self::getInstalledPackages();

		if (isset($installedPackages[$package])) {
			return $installedPackages[$package]['version'];
		}

		throw new RuntimeException("Package '$package' is not installed or version is unknown.");
	}

	/**
	 * @throws RuntimeException
	 */
	public static function getDirectoryByNamespace(string $namespace): string
	{
		$autoload = self::getPsr4Autoloading();

		foreach ($autoload as $prefix => $path) {
			if (str_starts_with($namespace, $prefix)) {
				$relativeNamespace = substr($namespace, strlen($prefix));

				return rtrim($path, '/') . '/' . str_replace('\\', '/', $relativeNamespace);
			}
		}

		throw new RuntimeException("Namespace '$namespace' not found in autoload.");
	}

	/**
	 * @throws RuntimeException
	 */
	public static function getFileLocationByFullName(string $namespace, string $className): string
	{
		$location = self::getDirectoryByNamespace($namespace);

		return rtrim($location, '/') . '/' . $className . '.php';
	}

	/**
	 * @return array{string, string}
	 */
	public static function extractNamespaceAndClassName(string $fullName): array
	{
		$lastBackslash = strrpos($fullName, '\\');

		if ($lastBackslash === false) {
			return ['', $fullName];
		}

		$namespace = substr($fullName, 0, $lastBackslash);
		$className = substr($fullName, $lastBackslash + 1);

		return [$namespace, $className];
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getComposerJson(): array
	{
		if (self::$composerJson === null) {
			/** @var array<string, mixed> $decoded */
			$decoded = Json::decode(FileSystem::read(self::getCurrentDir() . '/composer.json'), true);
			self::$composerJson = $decoded;
		}

		return self::$composerJson;
	}

	/**
	 * @return array<string, mixed>
	 */
	private static function getComposerLockJson(): array
	{
		if (self::$composerLockJson === null) {
			/** @var array<string, mixed> $decoded */
			$decoded = Json::decode(FileSystem::read(self::getCurrentDir() . '/composer.lock'), true);
			self::$composerLockJson = $decoded;
		}

		return self::$composerLockJson;
	}

	private static function getCurrentDir(): string
	{
		$currentDir = getcwd();

		if ($currentDir === false) {
			throw new LogicException('Failed to get current working directory.');
		}

		return $currentDir;
	}

}
