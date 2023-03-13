<?php

namespace App\Entity\User;

use App\Entity\Permission;
use App\Entity\Role;
use App\Entity\Store;
use App\Manager\Avatar\AvatarEntityInterface;
use App\Repository\UserRepository;
use DateTimeInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation\Blameable;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
#[UniqueEntity(fields: ['nickname'], message: 'There is already an account with this nickname')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, AvatarEntityInterface
{
	#[ORM\Id]
	#[ORM\Column(length: 255, unique: true, nullable: true)]
	private ?string $nickname = null;

	#[ORM\Column(length: 180, unique: true)]
	private ?string $email = null;

	/**
	 * @var string The hashed password
	 */
	#[ORM\Column]
	private string $password;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $avatar = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $firstName = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $lastName = null;

	#[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
	private ?DateTimeInterface $dateOfBirth = null;

	#[ORM\Column(type: 'boolean')]
	private bool $isVerified = false;

	#[ORM\ManyToMany(targetEntity: Store::class, inversedBy: 'managers')]
	#[ORM\JoinColumn(name: 'nickname', referencedColumnName: 'nickname', onDelete: 'CASCADE')]
	private Collection $managerStores;

	#[Blameable(on: 'create')]
	#[ORM\ManyToOne(targetEntity: self::class, inversedBy: 'children')]
	#[ORM\JoinColumn(name: 'parent', referencedColumnName: 'nickname', onDelete: 'CASCADE')]
	private ?self $parent = null;

	#[ORM\OneToMany(mappedBy: 'parent', targetEntity: self::class)]
	private Collection $children;

	#[ORM\Column]
	private array $roles = [];

	public function __construct()
	{
		$this->managerStores = new ArrayCollection();
		$this->children = new ArrayCollection();
	}

	public function __toString(): string
	{
		return $this->email;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(string $email): self
	{
		$this->email = $email;

		return $this;
	}

	public function getUsername(): string
	{
		return (string)$this->email;
	}

	/**
	 * @see PasswordAuthenticatedUserInterface
	 */
	public function getPassword(): string
	{
		return $this->password;
	}

	public function setPassword(string $password): self
	{
		$this->password = $password;

		return $this;
	}

	/**
	 * Returning a salt is only needed, if you are not using a modern
	 * hashing algorithm (e.g. bcrypt or sodium) in your security.yaml.
	 *
	 * @see UserInterface
	 */
	public function getSalt(): ?string
	{
		return null;
	}

	/**
	 * @see UserInterface
	 */
	public function eraseCredentials()
	{
		// If you store any temporary, sensitive data on the user, clear it here
		// $this->plainPassword = null;
	}

	public function isVerified(): bool
	{
		return $this->isVerified;
	}

	public function setIsVerified(bool $isVerified): self
	{
		$this->isVerified = $isVerified;

		return $this;
	}

	/**
	 * @return Collection<int, Store>
	 */
	public function getManagerStores(): Collection
	{
		return $this->managerStores;
	}

	public function addManagerStore(Store $managerStore): self
	{
		if (!$this->managerStores->contains($managerStore)) {
			$this->managerStores->add($managerStore);
		}

		return $this;
	}

	public function removeManagerStore(Store $managerStore): self
	{
		$this->managerStores->removeElement($managerStore);

		return $this;
	}

	public function getAvatar(): ?string
	{
		return $this->avatar;
	}

	public function setAvatar(?string $avatar): self
	{
		$this->avatar = $avatar;

		return $this;
	}

	public function getParent(): ?self
	{
		return $this->parent;
	}

	public function setParent(?self $parent): self
	{
		$this->parent = $parent;

		return $this;
	}

	/**
	 * @return Collection<int, self>
	 */
	public function getChildren(): Collection
	{
		return $this->children;
	}

	public function addChild(self $child): self
	{
		if (!$this->children->contains($child)) {
			$this->children->add($child);
			$child->setParent($this);
		}

		return $this;
	}

	public function removeChild(self $child): self
	{
		if ($this->children->removeElement($child)) {
			// set the owning side to null (unless already changed)
			if ($child->getParent() === $this) {
				$child->setParent(null);
			}
		}

		return $this;
	}

	public function getFirstName(): ?string
	{
		return $this->firstName;
	}

	public function setFirstName(?string $firstName): self
	{
		$this->firstName = $firstName;

		return $this;
	}

	public function getLastName(): ?string
	{
		return $this->lastName;
	}

	public function setLastName(?string $lastName): self
	{
		$this->lastName = $lastName;

		return $this;
	}

	public function getDateOfBirth(): ?DateTimeInterface
	{
		return $this->dateOfBirth;
	}

	public function setDateOfBirth(?DateTimeInterface $dateOfBirth): self
	{
		$this->dateOfBirth = $dateOfBirth;

		return $this;
	}

	public function getNickname(): ?string
	{
		return $this->nickname;
	}

	public function setNickname(?string $nickname): self
	{
		$this->nickname = $nickname;

		return $this;
	}

	public function getRoles(): array
	{
		return $this->roles;
	}

	public function setRoles(array $roles): self
	{
		$this->roles = $roles;

		return $this;
	}

	public function getUserIdentifier(): string
	{
		return $this->nickname;
	}
}
