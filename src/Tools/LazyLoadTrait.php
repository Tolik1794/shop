<?php

namespace App\Tools;

trait LazyLoadTrait
{
	private array $lazyLoad = [];

	public function lazyLoad(callable $callable, string $key): mixed
	{
		$this->lazyLoad[$key] = $callable();

		return $this->lazyLoad[$key];
	}
}