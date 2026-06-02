<?php

namespace App\Service\Order;

use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use App\Security\PermissionChecker;

/**
 * Resolves which comment types are relevant (addressed) to a given user, based on permissions.
 * Universal types (no audience permission) are relevant to everyone; departmental types only to
 * users holding the matching permission. Important comments are always counted regardless of type.
 */
final readonly class CommentAudienceResolver
{
	public function __construct(private PermissionChecker $permissionChecker)
	{
	}

	/**
	 * @return list<CommentTypeEnum>
	 */
	public function relevantTypesFor(User $user): array
	{
		$types = [];

		foreach (CommentTypeEnum::cases() as $type) {
			$audiencePermission = $type->audiencePermission();

			if ($audiencePermission === null || $this->permissionChecker->isGranted($user, $audiencePermission)) {
				$types[] = $type;
			}
		}

		return $types;
	}
}
