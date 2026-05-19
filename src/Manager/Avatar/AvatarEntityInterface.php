<?php

namespace App\Manager\Avatar;

interface AvatarEntityInterface
{
	public function getAvatar(): ?string;

	public function setAvatar(?string $avatar): AvatarEntityInterface;
}