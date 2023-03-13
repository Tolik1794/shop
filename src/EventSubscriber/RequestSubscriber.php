<?php

namespace App\EventSubscriber;

use App\Entity\User\User;
use Gedmo\Blameable\BlameableListener;
use JetBrains\PhpStorm\ArrayShape;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class RequestSubscriber implements EventSubscriberInterface
{
	public function __construct(
		private readonly BlameableListener     $blameableListener,
		private readonly TokenStorageInterface $tokenStorage,
		private readonly RouterInterface $router,
	)
	{
	}

	public function onKernelRequest(RequestEvent $requestEvent): void
	{
		if ($this->tokenStorage?->getToken()?->getUser() !== null) {
			/** @var User|UserInterface $user */
			$user = $this->tokenStorage->getToken()->getUser();
			$this->blameableListener->setUserValue($user);

			if (
				!$this->isUserHasFullData($user)
				&& $this->isRoute($requestEvent->getRequest(), 'admin_user_profile')
			) {
				$requestEvent->setResponse(new RedirectResponse($this->router->generate('admin_user_profile')));
			}
		}
	}

	#[ArrayShape([KernelEvents::RESPONSE => "string[]"])]
	public static function getSubscribedEvents(): array
	{
		return [
			KernelEvents::REQUEST => ['onKernelRequest'],
		];
	}

	private function isRoute(Request $request, string $routeName): bool
	{
		return $request->get('_route') !== $routeName && $request->getPathInfo() !== '/_fragment';
	}

	private function isUserHasFullData(User $user): bool
	{
		return $user->getNickname() && $user->getFirstName() && $user->getLastName() && $user->getDateOfBirth();
	}
}