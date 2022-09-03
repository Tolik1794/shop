<?php

namespace App\Tools;

use Knp\Component\Pager\Pagination\PaginationInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\RouterInterface;

class UrlTool
{
	public function __construct(private readonly RouterInterface $router)
	{
	}

	/**
	 * @param Request             $request
	 * @param PaginationInterface $pagination
	 * @return string
	 */
	public function generateLastPageUri(Request $request, PaginationInterface $pagination): string
	{
		$perpage = $request->query->getInt('perpage', $pagination->getItemNumberPerPage());
		$page = ceil($pagination->getTotalItemCount() / $perpage);

		return $this->router->generate(
			$request->attributes->get('_route'),
			array_merge($request->attributes->get('_route_params'), ['page' => $page])
		);
	}
}