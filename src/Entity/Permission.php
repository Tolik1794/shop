<?php

namespace App\Entity;

use App\Entity\User\UserPermissionOverride;
use App\Entity\User\UserGroup;
use App\Repository\PermissionRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PermissionRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_permission_code', columns: ['code'])]
class Permission
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 120)]
	private string $code;

	#[ORM\Column(length: 120)]
	private string $name;

	#[ORM\Column(length: 80)]
	private string $category;

	#[ORM\Column(type: 'text', nullable: true)]
	private ?string $description = null;

	#[ORM\Column]
	private int $sortOrder = 0;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToMany(targetEntity: UserGroup::class, mappedBy: 'permissions')]
	private Collection $groups;

	#[ORM\OneToMany(mappedBy: 'permission', targetEntity: UserPermissionOverride::class, cascade: ['persist'], orphanRemoval: true)]
	private Collection $userOverrides;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->groups = new ArrayCollection();
		$this->userOverrides = new ArrayCollection();
	}

	public function __toString(): string
	{
		return $this->code;
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

	public function getCategory(): string
	{
		return $this->category;
	}

	public function setCategory(string $category): self
	{
		$this->category = $category;

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

	public function getSortOrder(): int
	{
		return $this->sortOrder;
	}

	public function setSortOrder(int $sortOrder): self
	{
		$this->sortOrder = $sortOrder;

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
	 * @return Collection<int, UserGroup>
	 */
	public function getGroups(): Collection
	{
		return $this->groups;
	}

	/**
	 * @return Collection<int, UserPermissionOverride>
	 */
	public function getUserOverrides(): Collection
	{
		return $this->userOverrides;
	}
}
