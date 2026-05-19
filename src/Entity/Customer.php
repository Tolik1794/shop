<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Repository\CustomerRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_customer_store_email', columns: ['store_id', 'email'], options: ['where' => '((email IS NOT NULL) AND (deleted_at IS NULL))'])]
#[ORM\UniqueConstraint(name: 'uniq_customer_store_phone', columns: ['store_id', 'phone'], options: ['where' => '((phone IS NOT NULL) AND (deleted_at IS NULL))'])]
class Customer
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $lastName = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $phone = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $email = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

	#[ORM\ManyToOne(inversedBy: 'customers')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\OneToMany(mappedBy: 'customer', targetEntity: Order::class)]
	private Collection $orders;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->orders = new ArrayCollection();
	}

	public function __toString(): string
	{
		return $this->getFullName();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getName(): ?string
	{
		return $this->name;
	}

	public function setName(string $name): self
	{
		$this->name = $name;

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

	public function getFullName(): string
	{
		return trim(implode(' ', array_filter([
			$this->name,
			$this->lastName,
		])));
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setPhone(?string $phone): self
	{
		$this->phone = self::normalizePhone($phone);

		return $this;
	}

	public static function normalizePhone(?string $phone): ?string
	{
		$phone = trim((string) $phone);

		if ($phone === '') {
			return null;
		}

		$digits = preg_replace('/\D+/', '', $phone);

		if ($digits === null) {
			return $phone;
		}

		if (preg_match('/^380\d{9}$/', $digits) === 1) {
			return '+' . $digits;
		}

		if (preg_match('/^0\d{9}$/', $digits) === 1) {
			return '+38' . $digits;
		}

		return $phone;
	}

	public function getEmail(): ?string
	{
		return $this->email;
	}

	public function setEmail(?string $email): self
	{
		$this->email = $email;

		return $this;
	}

	public function getComment(): ?string
	{
		return $this->comment;
	}

	public function setComment(?string $comment): self
	{
		$this->comment = $comment;

		return $this;
	}

	public function getStatus(): ActiveStatusEnum
	{
		return $this->status;
	}

	public function setStatus(ActiveStatusEnum $status): self
	{
		$this->status = $status;

		return $this;
	}

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
	}

	public function setCreatedAt(DateTimeImmutable $createdAt): self
	{
		$this->createdAt = $createdAt;

		return $this;
	}

	public function getUpdatedAt(): DateTimeImmutable
	{
		return $this->updatedAt;
	}

	public function setUpdatedAt(DateTimeImmutable $updatedAt): self
	{
		$this->updatedAt = $updatedAt;

		return $this;
	}

	public function getDeletedAt(): ?DateTimeImmutable
	{
		return $this->deletedAt;
	}

	public function setDeletedAt(?DateTimeImmutable $deletedAt): self
	{
		$this->deletedAt = $deletedAt;

		return $this;
	}

	public function getStore(): ?Store
	{
		return $this->store;
	}

	public function setStore(?Store $store): self
	{
		$this->store = $store;

		return $this;
	}

	public function getCreatedBy(): ?User
	{
		return $this->createdBy;
	}

	public function setCreatedBy(?User $createdBy): self
	{
		$this->createdBy = $createdBy;

		return $this;
	}

	public function getUpdatedBy(): ?User
	{
		return $this->updatedBy;
	}

	public function setUpdatedBy(?User $updatedBy): self
	{
		$this->updatedBy = $updatedBy;

		return $this;
	}

	/**
	 * @return Collection<int, Order>
	 */
	public function getOrders(): Collection
	{
		return $this->orders;
	}

	public function addOrder(Order $order): self
	{
		if (!$this->orders->contains($order)) {
			$this->orders->add($order);
			$order->setCustomer($this);
		}

		return $this;
	}

	public function removeOrder(Order $order): self
	{
		if ($this->orders->removeElement($order) && $order->getCustomer() === $this) {
			$order->setCustomer(null);
		}

		return $this;
	}
}
