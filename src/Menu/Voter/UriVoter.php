<?php

namespace App\Menu\Voter;

use Knp\Menu\ItemInterface;
use Knp\Menu\Matcher\Voter\VoterInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Избиратель пункта меню для текущего запроса.
 */
class UriVoter implements VoterInterface
{
	private const SEPARATOR = '/';

	/**
	 * @var RequestStack
	 */
	private RequestStack $requestStack;

	/**
	 * @param RequestStack $requestStack
	 */
	public function __construct(RequestStack $requestStack)
	{
		$this->requestStack = $requestStack;
	}

	/**
	 * @param ItemInterface $item
	 * @return bool|null
	 */
	public function matchItem(ItemInterface $item): ?bool
    {
		$request = $this->requestStack->getMainRequest();
		if (is_null($request)) return null;

		return !empty($item->getUri()) && str_starts_with(
                $this->prepareUri($request->getRequestUri()),
                $this->prepareUri($item->getUri())
            );
	}

	/**
	 * @param string $uri
	 * @return string
	 */
	private function prepareUri(string $uri): string
	{
		return $uri . (str_ends_with($uri, self::SEPARATOR) ? '' : self::SEPARATOR);
	}
}