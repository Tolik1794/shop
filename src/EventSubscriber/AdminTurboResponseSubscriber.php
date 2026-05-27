<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class AdminTurboResponseSubscriber implements EventSubscriberInterface
{
	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::RESPONSE => 'onKernelResponse',
		];
	}

	public function onKernelResponse(ResponseEvent $event): void
	{
		if (!$event->isMainRequest()) {
			return;
		}

		$request = $event->getRequest();

		if (!$this->isAdminRequest($request) || $request->isMethodSafe() || $request->isXmlHttpRequest()) {
			return;
		}

		$response = $event->getResponse();

		if ($response instanceof RedirectResponse && in_array($response->getStatusCode(), [Response::HTTP_MOVED_PERMANENTLY, Response::HTTP_FOUND], true)) {
			$response->setStatusCode(Response::HTTP_SEE_OTHER);

			return;
		}

		if ($response->getStatusCode() === Response::HTTP_OK && str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
			$response->setStatusCode(Response::HTTP_UNPROCESSABLE_ENTITY);
		}
	}

	private function isAdminRequest(Request $request): bool
	{
		return preg_match('#^/(?:uk/)?admin(?:/|$)#', $request->getPathInfo()) === 1;
	}
}
