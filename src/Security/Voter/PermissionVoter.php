<?php

namespace App\Security\Voter;

use App\Entity\User\User;
use App\Security\PermissionCatalog;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class PermissionVoter extends Voter
{
	public function __construct(
		private readonly PermissionCatalog $permissionCatalog,
		private readonly PermissionChecker $permissionChecker,
	)
	{
	}

	protected function supports(string $attribute, mixed $subject): bool
	{
		if ($subject !== null && in_array($attribute, ['store.view', 'store.edit', 'user.view', 'user.edit'], true)) {
			return false;
		}

		return $this->permissionCatalog->has($attribute);
	}

	protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
	{
		$user = $token->getUser();

		return $user instanceof User && $this->permissionChecker->isGranted($user, $attribute);
	}
}
