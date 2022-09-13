<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Валидация пользователя.
 */
class UserAccess
{
	/**
	 * @param UserInterface|null $user
	 * @return string|null
	 */
	public function userAccessError(?UserInterface $user = null): ?string
	{
		$user = $user ?? $this->container->get('security.token_storage')->getToken()?->getUser();

		if (!($user instanceof User)) return null;

		return null;
	}
}