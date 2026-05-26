<?php

namespace App\Security\Voter;

use App\Entity\Store;
use App\Entity\User\User;
use App\Security\PermissionChecker;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;
use Symfony\Component\Security\Core\User\UserInterface;

class StoreVoter extends Voter
{
    public const EDIT = 'store.edit';
    public const VIEW = 'store.view';

	public function __construct(private readonly PermissionChecker $permissionChecker)
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
        if (!$user instanceof User) {
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
		return $this->permissionChecker->isGranted($user, self::EDIT)
			&& ($this->permissionChecker->isGranted($user, 'store.view_all') || $this->managesStore($user, $store));
	}

	private function canView(User|UserInterface $user, Store $store): bool
	{
		$parent = $user->getParent();

		return $this->permissionChecker->isGranted($user, 'store.view_all')
			|| (
				$this->permissionChecker->isGranted($user, self::VIEW)
				&& ($this->managesStore($user, $store) || ($parent && $this->managesStore($parent, $store)))
			);
	}

	private function managesStore(User|UserInterface $user, Store $store): bool
	{
		return $user->getManagerStores()->exists(fn (int $key, Store $value) => $value->getId() === $store->getId());
	}
}
