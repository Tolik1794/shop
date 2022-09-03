<?php

namespace App\Tools\ControllerTools;

use Knp\Component\Pager\Pagination\PaginationInterface;
use Psr\Container\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

trait ControllerHelperTrait
{
	abstract function redirectToRoute(string $route, array $parameters = [], int $status = 302): RedirectResponse;

	abstract function setContainer(ContainerInterface $container): ?ContainerInterface;

	public function stayOrRedirect(
		string $route,
		array  $parameters = [],
		string $stayRoute = null,
		array  $stayParameters = []
	): RedirectResponse
	{
		$request = $this->getCurrentRequest();
		$referer = $this->getRefererRequest($request);

		if ($request->get('save') && $request->get('save') === 'stay') {
			if ($stayRoute) return $this->redirectToRoute(
				route: $stayRoute,
				parameters: $referer->query->all() + $stayParameters
			);

			return $this->redirectToRoute(
				route: $request->get('_route'),
				parameters: $referer->query->all() + $request->get('_route_params')
			);
		}

		return $this->redirectToRoute($route, $referer->query->all() + $request->get('_route_params') + $parameters);
	}

	public function redirectToFirstPage(): RedirectResponse
	{
		$request = $this->getCurrentRequest();
		$params = $request->query->all() ?: [];
		$params['page'] = 1;

		return $this->redirectToRoute(
			route: $request->get('_route'),
			parameters: $params + $request->get('_route_params')
		);
	}

	public function redirectToLastPage(PaginationInterface $pagination): RedirectResponse
	{
		$request = $this->getCurrentRequest();
		$perpage = $request->query->getInt('perpage', $pagination->getItemNumberPerPage());
		$page = ceil($pagination->getTotalItemCount() / $perpage);
		$params = $request->query->all() ?: [];
		$params['page'] = $page;

		return $this->redirectToRoute(
			route: $request->get('_route'),
			parameters: $params + $request->get('_route_params')
		);
	}

	public function getRouteName(): ?string
	{
		return ControllerReflection::getInstance(static::class)->getRouteName();
	}

	public function getCurrentRequest(): ?Request
	{
		return $this->container->get('request_stack')?->getCurrentRequest();
	}

	public function getRefererRequest(Request $request): Request
	{
		return Request::create($request->headers->get('referer'));
	}
}