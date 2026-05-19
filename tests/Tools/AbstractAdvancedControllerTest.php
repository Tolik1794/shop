<?php

namespace App\Tests\Tools;

use App\Tools\AbstractAdvancedController;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class AbstractAdvancedControllerTest extends KernelTestCase
{
	private RequestStack $requestStack;

	protected function setUp(): void
	{
		self::bootKernel();
		$this->requestStack = static::getContainer()->get(RequestStack::class);
	}

	protected function tearDown(): void
	{
		while ($this->requestStack->getCurrentRequest() instanceof Request) {
			$this->requestStack->pop();
		}

		parent::tearDown();
		unset($this->requestStack);
	}

	public function testStayOrRedirectPreservesCurrentRequestQueryBeforeReferer(): void
	{
		$request = Request::create(
			'/admin/store/1/customer/2/edit?customer%5Bname%5D=Alice&page=2',
			'POST',
			server: ['HTTP_REFERER' => '/admin/store/1/customer/2/edit'],
		);
		$request->attributes->set('_route', 'app_admin_customer_edit');
		$request->attributes->set('_route_params', [
			'store_id' => 1,
			'id' => 2,
		]);
		$this->requestStack->push($request);

		$controller = new class extends AbstractAdvancedController {};
		$controller->setContainer(static::getContainer());

		$response = $controller->stayOrRedirect('app_admin_customer_index', ['store_id' => 1]);

		self::assertStringContainsString('/admin/store/1/customer/', $response->getTargetUrl());
		self::assertStringContainsString('customer%5Bname%5D=Alice', $response->getTargetUrl());
		self::assertStringContainsString('page=2', $response->getTargetUrl());
	}

	public function testStayOrRedirectFallsBackToRefererQueryWhenCurrentRequestHasNoQuery(): void
	{
		$request = Request::create(
			'/admin/store/1/customer/2/edit',
			'POST',
			server: ['HTTP_REFERER' => '/admin/store/1/customer/2/edit?customer%5Bname%5D=Bob&page=3'],
		);
		$request->attributes->set('_route', 'app_admin_customer_edit');
		$request->attributes->set('_route_params', [
			'store_id' => 1,
			'id' => 2,
		]);
		$this->requestStack->push($request);

		$controller = new class extends AbstractAdvancedController {};
		$controller->setContainer(static::getContainer());

		$response = $controller->stayOrRedirect('app_admin_customer_index', ['store_id' => 1]);

		self::assertStringContainsString('customer%5Bname%5D=Bob', $response->getTargetUrl());
		self::assertStringContainsString('page=3', $response->getTargetUrl());
	}
}
