<?php

namespace App\Entity\User;

use App\Entity\Permission;
use App\Repository\UserGroupRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UserGroupRepository::class)]
#[ORM\Table(name: 'user_group')]
#[ORM\UniqueConstraint(name: 'uniq_user_group_code', columns: ['code'])]
class UserGroup
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 80)]
	private string $code;

	#[ORM\Column(length: 120)]
	private string $name;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $description = null;

	#[ORM\Column]
	private bool $system = false;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToMany(targetEntity: Permission::class, inversedBy: 'groups')]
	#[ORM\JoinTable(name: 'user_group_permission')]
	#[ORM\JoinColumn(name: 'user_group_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	#[ORM\InverseJoinColumn(name: 'permission_id', referencedColumnName: 'id', onDelete: 'CASCADE')]
	private Collection $permissions;

	#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'groups')]
	private Collection $users;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->permissions = new ArrayCollection();
		$this->users = new ArrayCollection();
	}

	public function __toString(): string
	{
		return $this->name;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getCode(): string
	{
		return $this->code;
	}

	public function setCode(string $code): self
	{
		$this->code = $code;

		return $this;
	}

	public function getName(): string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;

		return $this;
	}

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;

		return $this;
	}

	public function isSystem(): bool
	{
		return $this->system;
	}

	public function setSystem(bool $system): self
	{
		$this->system = $system;

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

	/**
	 * @return Collection<int, Permission>
	 */
	public function getPermissions(): Collection
	{
		return $this->permissions;
	}

	public function addPermission(Permission $permission): self
	{
		if (!$this->permissions->contains($permission)) {
			$this->permissions->add($permission);
		}

		return $this;
	}

	public function removePermission(Permission $permission): self
	{
		$this->permissions->removeElement($permission);

		return $this;
	}

	public function setPermissions(iterable $permissions): self
	{
		$this->permissions->clear();

		foreach ($permissions as $permission) {
			$this->addPermission($permission);
		}

		return $this;
	}

	/**
	 * @return Collection<int, User>
	 */
	public function getUsers(): Collection
	{
		return $this->users;
	}
}
