<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Repository\CustomerLabelRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CustomerLabelRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_customer_label_store_code', columns: ['store_id', 'code'], options: ['where' => '(deleted_at IS NULL)'])]
class CustomerLabel
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 50)]
	private ?string $code = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(length: 32, nullable: true)]
	private ?string $color = null;

	#[ORM\Column(options: ['default' => 0])]
	private int $sortOrder = 0;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

	#[ORM\ManyToOne(inversedBy: 'customerLabels')]
	#[ORM\JoinColumn(nullable: false)]
	private ?Store $store = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	/**
	 * @var Collection<int, Customer>
	 */
	#[ORM\ManyToMany(targetEntity: Customer::class, mappedBy: 'labels')]
	private Collection $customers;

	public function __construct()
	{
		$this->status = ActiveStatusEnum::ACTIVE;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->customers = new ArrayCollection();
	}

	public function __toString(): string
	{
		return (string) $this->name;
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getCode(): ?string
	{
		return $this->code;
	}

	public function setCode(string $code): self
	{
		$this->code = $code;

		return $this;
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

	public function getColor(): ?string
	{
		return $this->color;
	}

	public function setColor(?string $color): self
	{
		$this->color = $color;

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
	 * @return Collection<int, Customer>
	 */
	public function getCustomers(): Collection
	{
		return $this->customers;
	}

	public function addCustomer(Customer $customer): self
	{
		if (!$this->customers->contains($customer)) {
			$this->customers->add($customer);
			$customer->addLabel($this);
		}

		return $this;
	}

	public function removeCustomer(Customer $customer): self
	{
		if ($this->customers->removeElement($customer)) {
			$customer->removeLabel($this);
		}

		return $this;
	}
}
