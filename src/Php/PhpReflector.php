<?php declare(strict_types = 1);

namespace Shredio\DocsGenerator\Php;

use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Factory;
use Nette\PhpGenerator\Helpers;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\Printer;
use ReflectionClass;
use ReflectionClassConstant;
use ReflectionIntersectionType;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionProperty;
use ReflectionType;
use ReflectionUnionType;

final readonly class PhpReflector
{

	/**
	 * @param class-string $className
	 * @param list<string> $excludeMethods
	 */
	public static function getClassSignature(
		string $className,
		array $excludeMethods = [],
		bool $shortDescription = true,
		bool $nested = false,
		int $methodsToPrint = ReflectionMethod::IS_PUBLIC,
	): string
	{
		$reflectionClass = new ReflectionClass($className);
		$generatorFactory = new Factory();
		$namespace = new PhpNamespace($reflectionClass->getNamespaceName());
		$propertiesToPrint = ReflectionProperty::IS_PUBLIC;
		$classContantsToPrint = ReflectionClassConstant::IS_PUBLIC;

		if ($reflectionClass->isInterface()) {
			$class = $namespace->addClass($reflectionClass->getShortName());
		} else if ($reflectionClass->isTrait()) {
			$class = $namespace->addTrait($reflectionClass->getShortName());
		} else {
			$class = $namespace->addClass($reflectionClass->getShortName());
			$class->setFinal($reflectionClass->isFinal() && $class->isClass());
			$class->setAbstract($reflectionClass->isAbstract() && $class->isClass());
			$class->setReadOnly($reflectionClass->isReadOnly());
		}

		if ($class instanceof ClassType) {
			$parentClass = $reflectionClass->getParentClass();
			if ($parentClass !== false) {
				$class->setExtends($parentClass->getName());
			}
		}

		$docComment = $reflectionClass->getDocComment();

		if ($docComment !== false) {
			if ($shortDescription) {
				$class->setComment(self::getDescriptionFromDocComment($docComment));
			} else {
				$class->setComment(self::unformatDocComment($docComment));
			}
		}

		$getNames = fn (iterable $reflections): array => array_map(
			fn (ReflectionProperty|ReflectionMethod|ReflectionClassConstant $reflection): string => $reflection->getName(),
			is_array($reflections) ? $reflections : iterator_to_array($reflections),
		);

		$excludeProperties = !$nested && $reflectionClass->isTrait() ? $getNames(self::getTraitProperties($reflectionClass, $propertiesToPrint)) : [];
		$excludeClassConstants = !$nested && $reflectionClass->isTrait() ? $getNames(self::getTraitConstants($reflectionClass, $classContantsToPrint)) : [];
		$excludeMethods = array_merge(
			$excludeMethods,
			!$nested && $reflectionClass->isTrait() ? $getNames(self::getTraitMethods($reflectionClass, $methodsToPrint)) : [],
		);

		foreach ($reflectionClass->getProperties($propertiesToPrint) as $reflectionProperty) {
			if (!$nested && $reflectionProperty->getDeclaringClass()->name !== $reflectionClass->name) {
				continue;
			}

			if (in_array($reflectionProperty->getName(), $excludeProperties, true)) {
				continue;
			}

			if (self::shouldIgnoreDocComment($reflectionProperty->getDocComment())) {
				continue;
			}

			$property = $generatorFactory->fromPropertyReflection($reflectionProperty);
			$property->setAttributes([]);

			$comment = null;
			$propertyComment = $property->getComment();

			if ($propertyComment !== null) {
				if ($shortDescription && self::typeNeedComment($reflectionProperty->getType())) {
					if (preg_match('#@var\s+(.+?)$#m', $propertyComment, $matches)) {
						$comment = trim($matches[0]) . "\n";
					}
				} else if (!$shortDescription) {
					$comment = $propertyComment;
				}
			}

			$property->setComment($comment === null ? null : trim($comment));
			$class->addMember($property);
		}

		foreach ($reflectionClass->getReflectionConstants($classContantsToPrint) as $reflectionConstant) {
			if (!$nested && $reflectionConstant->getDeclaringClass()->name !== $reflectionClass->name) {
				continue;
			}

			if (in_array($reflectionConstant->getName(), $excludeClassConstants, true)) {
				continue;
			}

			if (self::shouldIgnoreDocComment($reflectionConstant->getDocComment())) {
				continue;
			}

			$constant = $generatorFactory->fromConstantReflection($reflectionConstant);
			$constant->setAttributes([]);

			if ($shortDescription) {
				$constant->setComment(null);
			}

			$class->addMember($constant);
		}

		foreach ($reflectionClass->getMethods($methodsToPrint) as $reflectionMethod) {
			if (!$nested && $reflectionMethod->getDeclaringClass()->name !== $reflectionClass->name) {
				continue;
			}

			if (in_array($reflectionMethod->getName(), $excludeMethods, true)) {
				continue;
			}

			if (self::shouldIgnoreDocComment($reflectionMethod->getDocComment())) {
				continue;
			}

			$method = $generatorFactory->fromMethodReflection($reflectionMethod);
			$method->setAttributes([]);

			$comment = $method->getComment();

			if ($shortDescription && $comment !== null) {
				$comment = (self::getDescriptionFromDocComment($comment) ?? '') . "\n";

				foreach ($reflectionMethod->getParameters() as $parameter) {
					if (self::typeNeedComment($parameter->getType())) {
						$hint = self::getParameterCommentHint($comment, $parameter->getName());

						if ($hint !== null) {
							$comment .= $hint . "\n";
						}
					}
				}

				if (self::typeNeedComment($reflectionMethod->getReturnType())) {
					if (preg_match('#@return\s+(.+?)$#m', $comment, $matches)) {
						$comment .= trim($matches[0]) . "\n";
					}
				}

				$comment = trim($comment);
			}

			$method->setComment($comment === '' ? null : $comment);
			$class->addMember($method);
		}

		$printer = new Printer();
		$printer->linesBetweenMethods = 1;
		$printer->bracesOnNextLine = false;

		return self::normalize($printer->printNamespace($namespace));
	}

	/**
	 * @param ReflectionClass<object> $reflectionClass
	 * @return iterable<ReflectionMethod>
	 */
	private static function getTraitMethods(ReflectionClass $reflectionClass, ?int $filter = null): iterable
	{
		foreach ($reflectionClass->getTraits() as $trait) {
			foreach ($trait->getMethods($filter) as $method) {
				yield $method;
			}

			foreach (self::getTraitMethods($trait, $filter) as $method) {
				yield $method;
			}
		}
	}

	/**
	 * @param ReflectionClass<object> $reflection
	 * @return iterable<ReflectionProperty>
	 */
	private static function getTraitProperties(ReflectionClass $reflection, ?int $filter = null): iterable
	{
		foreach ($reflection->getTraits() as $trait) {
			foreach ($trait->getProperties($filter) as $property) {
				yield $property;
			}

			yield from self::getTraitProperties($trait);
		}
	}

	/**
	 * @param ReflectionClass<object> $reflection
	 * @return iterable<ReflectionClassConstant>
	 */
	private static function getTraitConstants(ReflectionClass $reflection, ?int $filter = null): iterable
	{
		foreach ($reflection->getTraits() as $trait) {
			foreach ($trait->getReflectionConstants($filter) as $constant) {
				yield $constant;
			}

			yield from self::getTraitConstants($trait, $filter);
		}
	}

	private static function shouldIgnoreDocComment(string|false|null $docComment): bool
	{
		if ($docComment === null || $docComment === false) {
			return false;
		}

		return str_contains($docComment, '@docs-ignore') || str_contains($docComment, '@internal');
	}

	private static function getParameterCommentHint(string $comment, string $parameterName): ?string
	{
		if (preg_match(sprintf('#@param\s+.+?\s+\$%s\s+(.+?)$#m', preg_quote($parameterName, '#')), $comment, $matches)) {
			return trim($matches[0]);
		}

		return null;
	}

	private static function normalize(string $code): string
	{
		$code = preg_replace('#\s*\{\s*}#', ';', $code);

		if ($code === null) {
			throw new LogicException('Failed to normalize code.');
		}

		return $code;
	}

	private static function typeNeedComment(?ReflectionType $type): bool
	{
		if ($type === null) {
			return false;
		}

		if ($type instanceof ReflectionIntersectionType) {
			foreach ($type->getTypes() as $subType) {
				if (self::typeNeedComment($subType)) {
					return true;
				}
			}
			return false;
		}

		if ($type instanceof ReflectionUnionType) {
			foreach ($type->getTypes() as $subType) {
				if (self::typeNeedComment($subType)) {
					return true;
				}
			}
			return false;
		}

		if ($type instanceof ReflectionNamedType) {
			return $type->getName() === 'array';
		}

		return false;
	}

	private static function unformatDocComment(string $comment): string
	{
		foreach (['internal', 'see'] as $annotation) {
			$comment = self::removeAnnotationFromDocComment($comment, $annotation);
		}

		return Helpers::unformatDocComment($comment);
	}

	private static function removeAnnotationFromDocComment(string $comment, string $annotation): string
	{
		$pattern = sprintf('#^\s*\*\s*@%s.*$#m', preg_quote($annotation, '#'));

		$replaced = preg_replace($pattern, '', $comment);

		if ($replaced === null) {
			throw new LogicException(sprintf('Failed to remove annotation "%s" from doc comment.', $annotation));
		}

		return $replaced;
	}

	private static function getDescriptionFromDocComment(?string $comment): ?string
	{
		if ($comment === null) {
			return null;
		}

		$comment = self::unformatDocComment($comment);

		$pos = strpos($comment, '@');

		if ($pos === false) {
			return trim($comment);
		}

		$description = trim(substr($comment, 0, $pos));

		if ($description !== '') {
			$pos = strpos($description, '.');
			if ($pos !== false) {
				return substr($description, 0, $pos + 1);
			}

			$pos = strpos($description, ';');
			if ($pos !== false) {
				return substr($description, 0, $pos + 1);
			}

			return $description;
		}

		return null;
	}

	public static function getNamespaceFromContent(string $contents): ?string
	{
		if (preg_match('/^namespace\s+([a-zA-Z0-9_\\\\]+);/m', $contents, $matches)) {
			return $matches[1];
		}

		return null;
	}

	public static function getClassNameFromContent(string $contents): ?string
	{
		if (preg_match('/(?:class|interface|enum|trait)\s+([a-zA-Z0-9_]+)/', $contents, $matches)) {
			return $matches[1];
		}

		return null;
	}

	public static function getClassNameFromFullName(string $fullName): string
	{
		$pos = strrpos($fullName, '\\');

		if ($pos === false) {
			return $fullName;
		}

		return substr($fullName, $pos + 1);
	}

}
