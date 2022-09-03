<?php

namespace App\Tools\ControllerTools;

use App\Tools\LazyLoadTrait;
use ReflectionClass;
use Symfony\Component\Routing\Annotation\Route;

class ControllerReflection
{
	use LazyLoadTrait;

	private static $instances = [];

	protected function __construct(private readonly ReflectionClass $reflection)
	{
	}

	protected function __clone()
	{
	}

	public function __wakeup()
	{
		throw new \Exception("Cannot unserialize a singleton.");
	}

	public static function getInstance(string $class): self
	{
		if (!isset(self::$instances[$class]) && class_exists($class)) {
			self::$instances[$class] = new static(new ReflectionClass($class));
		}

		return self::$instances[$class];
	}

	public function getReflection(): ReflectionClass
	{
		return $this->reflection;
	}

	public function getRouteName(): ?string
	{
		return $this->lazyLoad(function () {
			$attributes = $this->reflection->getAttributes(Route::class);

			if (!count($attributes)) return null;

			$routeAttribute = current($attributes);
			$routeArguments = $routeAttribute->getArguments();

			return $routeArguments['name'] ?? null;
		}, __CLASS__ . __FUNCTION__);
	}
}