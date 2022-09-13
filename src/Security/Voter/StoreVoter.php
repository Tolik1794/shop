<?php

namespace App\Security\Voter;

use App\Entity\Store;
use App\Entity\User;
use App\Enum\RoleEnum;
use App\Manager\UserManager;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class StoreVoter extends Voter
{
    public const EDIT = 'POST_EDIT';
    public const VIEW = 'POST_VIEW';

	public function __construct(private readonly UserManager $userManager)
	{
	}

	protected function supports(string $attribute, $subject): bool
    {
        return in_array($attribute, [self::EDIT, self::VIEW])
            && $subject instanceof Store;
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

	private function canEdit(User|UserInterface $user, Store $store): bool
	{
		return $user->getManagerStores()->exists(fn (int $key, Store $value) => $value->getId() === $store->getId())
			&& $this->userManager->hasRole(RoleEnum::ROLE_STORE_ADMIN);
	}

	private function canView(User|UserInterface $user, Store $store): bool
	{
		$parent = $user->getParent();

		return $this->canEdit($user, $store) || ($parent && $this->canEdit($parent, $store));
	}
}
