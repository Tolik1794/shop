<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne(inversedBy: 'orders')]
	#[ORM\JoinColumn(nullable: false)]
	private Store $store;

	#[ORM\Column(length: 128, enumType: OrderStatus::class)]
	private OrderStatus $status;

	#[ORM\OneToMany(mappedBy: 'order', targetEntity: OrderEntry::class)]
	private Collection $orderEntries;

	public function __construct()
	{
		$this->status = OrderStatus::FORM;
		$this->orderEntries = new ArrayCollection();
	}

	public function getId(): ?int
	{
		return $this->id;
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

	public function getStatus(): ?OrderStatus
	{
		return $this->status;
	}

	public function setStatus(OrderStatus $status): self
	{
		$this->status = $status;

		return $this;
	}

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
}
