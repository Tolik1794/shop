<?php

namespace App\Security\Voter;

use App\Entity\User\User;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

class UserVoter extends Voter
{
    public const EDIT = 'user.edit';
    public const VIEW = 'user.view';

	public function __construct(private readonly PermissionChecker $permissionChecker)
	{
	}

	protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW]) && $subject instanceof User;
    }

	protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
	{
		$user = $token->getUser();
		if (!$user instanceof User) {
			return false;
		}

		return match ($attribute) {
			self::EDIT => $this->canEdit($user, $subject),
			self::VIEW => $this->canView($user, $subject),
			default => false,
		};

	}

	private function canEdit(User $user, User $manager): bool
	{
		if (!$this->permissionChecker->isGranted($user, self::EDIT)) {
			return false;
		}

		return $this->permissionChecker->isGranted($user, 'rbac.manage')
			|| $user->getId() === $manager->getId()
			|| $user->getChildren()->exists(fn (int $key, User $value) => $value->getId() === $manager->getId());
	}

	private function canView(User $user, User $manager): bool
	{
		if (!$this->permissionChecker->isGranted($user, self::VIEW)) {
			return false;
		}

		return $this->permissionChecker->isGranted($user, 'rbac.view')
			|| $user->getId() === $manager->getId()
			|| $user->getChildren()->exists(fn (int $key, User $value) => $value->getId() === $manager->getId())
			|| $user->getParent()?->getId() === $manager->getId();
	}
}
