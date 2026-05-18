<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\PaymentStatusEnum;
use App\Workflow\WorkflowSubjectInterface;
use DateTimeImmutable;
use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: 'orders')]
class Order implements WorkflowSubjectInterface
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $number = null;

	#[ORM\Column(length: 128, enumType: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(referencedColumnName: 'code', nullable: false)]
	private ?Currency $currency = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 18, scale: 8)]
	private ?string $exchangeRateToBase = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $customerNameSnapshot = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $customerPhoneSnapshot = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $customerEmailSnapshot = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $deliveryAddress = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalAmount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $totalAmountBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $discountAmount = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4, nullable: true)]
	private ?string $discountAmountBase = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $paidAmountBase = null;

	#[ORM\Column(length: 255, enumType: PaymentStatusEnum::class)]
	private PaymentStatusEnum $paymentStatus;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $paidAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $canceledAt = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToOne(inversedBy: 'orders')]
	#[ORM\JoinColumn(nullable: false)]
	private Store $store;

	#[ORM\ManyToOne(inversedBy: 'orders')]
	private ?Customer $customer = null;

	#[ORM\ManyToOne]
	private ?User $createdBy = null;

	#[ORM\ManyToOne]
	private ?User $updatedBy = null;

	#[ORM\ManyToOne]
	private ?User $canceledBy = null;

	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderEntry::class)]
	private Collection $orderEntries;

	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderComment::class)]
	private Collection $comments;

	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderHistory::class)]
	private Collection $historyEntries;

	public function __construct()
	{
		$this->status = OrderStatus::DRAFT;
		$this->exchangeRateToBase = '1.00000000';
		$this->totalAmount = '0.0000';
		$this->totalAmountBase = '0.0000';
		$this->paidAmountBase = '0.0000';
		$this->paymentStatus = PaymentStatusEnum::UNPAID;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->orderEntries = new ArrayCollection();
		$this->comments = new ArrayCollection();
		$this->historyEntries = new ArrayCollection();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getStatus(): ?OrderStatus
	{
		return $this->status;
	}

	public function getNumber(): ?string
	{
		return $this->number;
	}

	public function setNumber(string $number): self
	{
		$this->number = $number;

		return $this;
	}

	public function setStatus(OrderStatus $status): self
	{
		$this->status = $status;

		return $this;
	}

	public function getWorkflowKey(): string
	{
		return 'order';
	}

	public function getStatusValue(): string
	{
		return $this->status->value;
	}

	public function setStatusValue(string $status): void
	{
		$this->status = OrderStatus::from($status);
	}

	public function getCurrency(): ?Currency
	{
		return $this->currency;
	}

	public function setCurrency(?Currency $currency): self
	{
		$this->currency = $currency;

		return $this;
	}

	public function getExchangeRateToBase(): ?string
	{
		return $this->exchangeRateToBase;
	}

	public function setExchangeRateToBase(string $exchangeRateToBase): self
	{
		$this->exchangeRateToBase = $exchangeRateToBase;

		return $this;
	}

	public function getCustomerNameSnapshot(): ?string { return $this->customerNameSnapshot; }
	public function setCustomerNameSnapshot(?string $customerNameSnapshot): self { $this->customerNameSnapshot = $customerNameSnapshot; return $this; }
	public function getCustomerPhoneSnapshot(): ?string { return $this->customerPhoneSnapshot; }
	public function setCustomerPhoneSnapshot(?string $customerPhoneSnapshot): self { $this->customerPhoneSnapshot = $customerPhoneSnapshot; return $this; }
	public function getCustomerEmailSnapshot(): ?string { return $this->customerEmailSnapshot; }
	public function setCustomerEmailSnapshot(?string $customerEmailSnapshot): self { $this->customerEmailSnapshot = $customerEmailSnapshot; return $this; }
	public function getDeliveryAddress(): ?string { return $this->deliveryAddress; }
	public function setDeliveryAddress(?string $deliveryAddress): self { $this->deliveryAddress = $deliveryAddress; return $this; }
	public function getTotalAmount(): ?string { return $this->totalAmount; }
	public function setTotalAmount(string $totalAmount): self { $this->totalAmount = $totalAmount; return $this; }
	public function getTotalAmountBase(): ?string { return $this->totalAmountBase; }
	public function setTotalAmountBase(string $totalAmountBase): self { $this->totalAmountBase = $totalAmountBase; return $this; }
	public function getDiscountAmount(): ?string { return $this->discountAmount; }
	public function setDiscountAmount(?string $discountAmount): self { $this->discountAmount = $discountAmount; return $this; }
	public function getDiscountAmountBase(): ?string { return $this->discountAmountBase; }
	public function setDiscountAmountBase(?string $discountAmountBase): self { $this->discountAmountBase = $discountAmountBase; return $this; }
	public function getPaidAmountBase(): ?string { return $this->paidAmountBase; }
	public function setPaidAmountBase(string $paidAmountBase): self { $this->paidAmountBase = $paidAmountBase; return $this; }
	public function getPaymentStatus(): PaymentStatusEnum { return $this->paymentStatus; }
	public function setPaymentStatus(PaymentStatusEnum $paymentStatus): self { $this->paymentStatus = $paymentStatus; return $this; }
	public function getPaidAt(): ?DateTimeImmutable { return $this->paidAt; }
	public function setPaidAt(?DateTimeImmutable $paidAt): self { $this->paidAt = $paidAt; return $this; }
	public function getCanceledAt(): ?DateTimeImmutable { return $this->canceledAt; }
	public function setCanceledAt(?DateTimeImmutable $canceledAt): self { $this->canceledAt = $canceledAt; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getUpdatedAt(): DateTimeImmutable { return $this->updatedAt; }
	public function setUpdatedAt(DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }

	public function getStore(): ?Store
	{
		return $this->store;
	}

	public function setStore(?Store $store): self
	{
		$this->store = $store;

		return $this;
	}

	public function getCustomer(): ?Customer
	{
		return $this->customer;
	}

	public function setCustomer(?Customer $customer): self
	{
		$this->customer = $customer;

		if ($customer instanceof Customer) {
			$this->customerNameSnapshot = $customer->getFullName();
			$this->customerPhoneSnapshot = $customer->getPhone();
			$this->customerEmailSnapshot = $customer->getEmail();
		} else {
			$this->customerNameSnapshot = null;
			$this->customerPhoneSnapshot = null;
			$this->customerEmailSnapshot = null;
		}

		return $this;
	}

	public function getCreatedBy(): ?User { return $this->createdBy; }
	public function setCreatedBy(?User $createdBy): self { $this->createdBy = $createdBy; return $this; }
	public function getUpdatedBy(): ?User { return $this->updatedBy; }
	public function setUpdatedBy(?User $updatedBy): self { $this->updatedBy = $updatedBy; return $this; }
	public function getCanceledBy(): ?User { return $this->canceledBy; }
	public function setCanceledBy(?User $canceledBy): self { $this->canceledBy = $canceledBy; return $this; }

	/**
	 * @return Collection<int, OrderEntry>
	 */
	public function getOrderEntries(): Collection
	{
		return $this->orderEntries;
	}

	public function addOrderEntry(OrderEntry $orderEntry): self
	{
		if (!$this->orderEntries->contains($orderEntry)) {
			$this->orderEntries->add($orderEntry);
			$orderEntry->setOrder($this);
		}

		return $this;
	}

	public function removeOrderEntry(OrderEntry $orderEntry): self
	{
		if ($this->orderEntries->removeElement($orderEntry)) {
			// set the owning side to null (unless already changed)
			if ($orderEntry->getOrder() === $this) {
				$orderEntry->setOrder(null);
			}
		}

		return $this;
	}

	/**
	 * @return Collection<int, OrderComment>
	 */
	public function getComments(): Collection
	{
		return $this->comments;
	}

	/**
	 * @return Collection<int, OrderHistory>
	 */
	public function getHistoryEntries(): Collection
	{
		return $this->historyEntries;
	}
}
