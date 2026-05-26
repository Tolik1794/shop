<?php

namespace App\Entity\User;

use App\Entity\Permission;
use App\Enum\PermissionOverrideEffect;
use App\Repository\UserPermissionOverrideRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserPermissionOverrideRepository::class)]
#[ORM\Table(name: 'user_permission_override')]
#[ORM\UniqueConstraint(name: 'uniq_user_permission_override', columns: ['user_id', 'permission_id'])]
class UserPermissionOverride
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'permissionOverrides')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private User $user;

	#[ORM\ManyToOne(targetEntity: Permission::class, inversedBy: 'userOverrides')]
	#[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
	private Permission $permission;

	#[ORM\Column(length: 20, enumType: PermissionOverrideEffect::class)]
	private PermissionOverrideEffect $effect;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	public function __construct()
	{
		$this->effect = PermissionOverrideEffect::ALLOW;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getUser(): User
	{
		return $this->user;
	}

	public function setUser(User $user): self
	{
		$this->user = $user;

		return $this;
	}

	public function getPermission(): Permission
	{
		return $this->permission;
	}

	public function setPermission(Permission $permission): self
	{
		$this->permission = $permission;

		return $this;
	}

	public function getEffect(): PermissionOverrideEffect
	{
		return $this->effect;
	}

	public function setEffect(PermissionOverrideEffect $effect): self
	{
		$this->effect = $effect;

		return $this;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function touch(): self
	{
		$this->updatedAt = new DateTimeImmutable();

		return $this;
	}
}
