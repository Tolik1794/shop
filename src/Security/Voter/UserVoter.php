<?php

namespace App\Security\Voter;

use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Manager\UserManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class UserVoter extends Voter
{
    public const EDIT = 'POST_EDIT';
    public const VIEW = 'POST_VIEW';

	public function __construct(private readonly UserManager $userManager)
	{
	}

	protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW]) && $subject instanceof User;
    }

	protected function voteOnAttribute(string $attribute, $subject, TokenInterface $token): bool
	{
		$user = $token->getUser();
		if (!$user instanceof UserInterface) {
			return false;
		}

		return match ($attribute) {
			self::EDIT => $this->canEdit($user, $subject),
			self::VIEW => $this->canView($user, $subject),
			default => false,
		};

	}

	private function canEdit(User|UserInterface $user, User $manager): bool
	{
		return $user->getChildren()->exists(fn (int $key, User $value) => $value->getId() === $manager->getId())
			&& $this->userManager->hasRole(RoleEnum::ROLE_STORE_ADMIN, $user)
			|| $this->userManager->hasRole(RoleEnum::ROLE_ADMIN);
	}

	private function canView(User|UserInterface $user, User $manager): bool
	{
		return $this->canEdit($user, $manager) || $this->canEdit($user->getParent(), $manager);
	}
}
