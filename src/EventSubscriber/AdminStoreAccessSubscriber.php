<?php

namespace App\EventSubscriber;

use App\Entity\Store;
use App\Repository\StoreRepository;
use App\Security\Voter\StoreVoter;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ControllerEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AdminStoreAccessSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private readonly StoreRepository $storeRepository,
		private readonly AuthorizationCheckerInterface $authorizationChecker,
	)
	{
	}

	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::CONTROLLER => 'denyInaccessibleStore',
		];
	}

	public function denyInaccessibleStore(ControllerEvent $event): void
	{
		$request = $event->getRequest();
		$route = (string) $request->attributes->get('_route', '');
		$storeId = $request->attributes->get('store_id');

		if ($storeId === null || !str_starts_with($route, 'app_admin_') && !str_starts_with($route, 'admin_') && !str_starts_with($route, 'app_api_admin_')) {
			return;
		}

		$store = $this->storeRepository->find($storeId);

		if (!$store instanceof Store) {
			throw new NotFoundHttpException();
		}

		if (!$this->authorizationChecker->isGranted(StoreVoter::VIEW, $store)) {
			throw new AccessDeniedException();
		}
	}
}
