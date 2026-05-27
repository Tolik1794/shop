<?php

namespace App\Tests\EventSubscriber;

use App\EventSubscriber\AdminTurboResponseSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class AdminTurboResponseSubscriberTest extends TestCase
{
	public function testAdminPostRedirectUsesSeeOther(): void
	{
		$response = new RedirectResponse('/admin/store/1/order/', Response::HTTP_FOUND);

		$this->subscriber()->onKernelResponse($this->event(Request::create('/admin/store/1/order/new', 'POST'), $response));

		self::assertSame(Response::HTTP_SEE_OTHER, $response->getStatusCode());
	}

	public function testAdminPostHtmlResponseUsesUnprocessableEntity(): void
	{
		$response = new Response('<form></form>', Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);

		$this->subscriber()->onKernelResponse($this->event(Request::create('/uk/admin/store/1/order/new', 'POST'), $response));

		self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $response->getStatusCode());
	}

	public function testXmlHttpRequestIsNotChanged(): void
	{
		$response = new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']);
		$request = Request::create('/admin/store/1/order/1/confirm', 'POST', server: [
			'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest',
		]);

		$this->subscriber()->onKernelResponse($this->event($request, $response));

		self::assertSame(Response::HTTP_OK, $response->getStatusCode());
	}

	private function subscriber(): AdminTurboResponseSubscriber
	{
		return new AdminTurboResponseSubscriber();
	}

	private function event(Request $request, Response $response): ResponseEvent
	{
		return new ResponseEvent(
			$this->createMock(HttpKernelInterface::class),
			$request,
			HttpKernelInterface::MAIN_REQUEST,
			$response,
		);
	}
}
