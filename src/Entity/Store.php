<?php

namespace App\Entity;

use App\Enum\ActiveStatusEnum;
use App\Manager\Avatar\AvatarEntityInterface;
use App\Repository\StoreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreRepository::class)]
class Store implements AvatarEntityInterface
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(length: 255)]
	private ?string $name = null;

	#[ORM\Column(length: 255)]
	private ?string $slug = null;

	#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'managerStores')]
	private Collection $managers;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Warehouse::class)]
	private Collection $warehouses;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Order::class)]
	private Collection $orders;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Purchase::class)]
	private Collection $purchases;

	#[ORM\Column(length: 255)]
	private ?string $phone = null;

	#[ORM\Column(length: 255)]
	private ?string $email = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $description = null;

	#[ORM\Column(length: 255, nullable: true)]
	private ?string $avatar = null;

	#[ORM\Column(length: 255, enumType: ActiveStatusEnum::class)]
	private ActiveStatusEnum $status;

	public function __construct()
	{
		$this->managers = new ArrayCollection();
		$this->warehouses = new ArrayCollection();
		$this->orders = new ArrayCollection();
		$this->purchases = new ArrayCollection();
		$this->status = ActiveStatusEnum::INACTIVE;
	}

	public function __toString(): string
	{
		return $this->name;
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

	public function getSlug(): ?string
	{
		return $this->slug;
	}

	public function setSlug(string $slug): self
	{
		$this->slug = $slug;

		return $this;
	}

	/**
	 * @return Collection<int, User>
	 */
	public function getManagers(): Collection
	{
		return $this->managers;
	}

	public function addManager(User $manager): self
	{
		if (!$this->managers->contains($manager)) {
			$this->managers->add($manager);
			$manager->addManagerStore($this);
		}

		return $this;
	}

	public function removeManager(User $manager): self
	{
		if ($this->managers->removeElement($manager)) {
			$manager->removeManagerStore($this);
		}

		return $this;
	}

	/**
	 * @return Collection<int, Warehouse>
	 */
	public function getWarehouses(): Collection
	{
		return $this->warehouses;
	}

	public function addWarehouse(Warehouse $warehouse): self
	{
		if (!$this->warehouses->contains($warehouse)) {
			$this->warehouses->add($warehouse);
			$warehouse->setStore($this);
		}

		return $this;
	}

	public function removeWarehouse(Warehouse $warehouse): self
	{
		if ($this->warehouses->removeElement($warehouse)) {
			// set the owning side to null (unless already changed)
			if ($warehouse->getStore() === $this) {
				$warehouse->setStore(null);
			}
		}

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
			$order->setStore($this);
		}

		return $this;
	}

	public function removeOrder(Order $order): self
	{
		if ($this->orders->removeElement($order)) {
			// set the owning side to null (unless already changed)
			if ($order->getStore() === $this) {
				$order->setStore(null);
			}
		}

		return $this;
	}

	/**
	 * @return Collection<int, Purchase>
	 */
	public function getPurchases(): Collection
	{
		return $this->purchases;
	}

	public function addPurchase(Purchase $purchase): self
	{
		if (!$this->purchases->contains($purchase)) {
			$this->purchases->add($purchase);
			$purchase->setStore($this);
		}

		return $this;
	}

	public function removePurchase(Purchase $purchase): self
	{
		if ($this->purchases->removeElement($purchase)) {
			// set the owning side to null (unless already changed)
			if ($purchase->getStore() === $this) {
				$purchase->setStore(null);
			}
		}

		return $this;
	}

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setPhone(string $phone): self
	{
		$this->phone = $phone;

		return $this;
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

	public function getDescription(): ?string
	{
		return $this->description;
	}

	public function setDescription(?string $description): self
	{
		$this->description = $description;

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

	public function getStatus(): ActiveStatusEnum
	{
		return $this->status;
	}

	public function setStatus(ActiveStatusEnum $status): self
	{
		$this->status = $status;

		return $this;
	}
}
