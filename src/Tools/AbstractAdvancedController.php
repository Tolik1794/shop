<?php

namespace App\Tools;

use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

abstract class AbstractAdvancedController extends AbstractController
{
	public function stayOrRedirect(
		string $route,
		array  $parameters = [],
		?string $stayRoute = null,
		array  $stayParameters = []
	): RedirectResponse
	{
		$request = $this->getCurrentRequest();
		$listContextParameters = $this->getListContextParameters($request);

		if ($request->request->get('save') && $request->request->get('save') === 'stay') {
			if ($stayRoute) return $this->redirectToRoute(
				route: $stayRoute,
				parameters: $listContextParameters + $stayParameters
			);

			return $this->redirectToRoute(
				route: $request->attributes->get('_route'),
				parameters: $listContextParameters + $request->attributes->get('_route_params', [])
			);
		}

		return $this->redirectToRoute(
			route: $route,
			parameters: $listContextParameters + $request->attributes->get('_route_params', []) + $parameters
		);
	}

	public function redirectToFirstPage(): RedirectResponse
	{
		$request = $this->getCurrentRequest();
		$params = $request->query->all() ?: [];
		$params['page'] = 1;

		return $this->redirectToRoute(
			route: $request->attributes->get('_route'),
			parameters: $params + $request->attributes->get('_route_params', [])
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
			route: $request->attributes->get('_route'),
			parameters: $params + $request->attributes->get('_route_params', [])
		);
	}

	public function getCurrentRequest(): ?Request
	{
		return $this->container->get('request_stack')?->getCurrentRequest();
	}

	public function getRefererRequest(Request $request): Request
	{
		return Request::create($request->headers->get('referer'));
	}

	private function getListContextParameters(Request $request): array
	{
		$queryParameters = $request->query->all();

		if ($queryParameters !== []) {
			return $queryParameters;
		}

		return $this->getRefererRequest($request)->query->all();
	}
}
