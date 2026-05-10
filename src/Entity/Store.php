<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\ActiveStatusEnum;
use App\Enum\CostingMethodEnum;
use App\Manager\Avatar\AvatarEntityInterface;
use App\Repository\StoreRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StoreRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_store_slug', columns: ['slug'])]
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

	#[ORM\Column(length: 255, enumType: CostingMethodEnum::class)]
	private CostingMethodEnum $costingMethod;

	#[ORM\Column]
	private bool $allowBackorders;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	#[ORM\ManyToOne(inversedBy: 'stores')]
	#[ORM\JoinColumn(name: 'base_currency_code', referencedColumnName: 'code', nullable: false)]
	private ?Currency $baseCurrency = null;

	#[ORM\ManyToMany(targetEntity: User::class, mappedBy: 'managerStores')]
	private Collection $managers;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Warehouse::class)]
	private Collection $warehouses;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Order::class)]
	private Collection $orders;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Purchase::class)]
	private Collection $purchases;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Customer::class)]
	private Collection $customers;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Supplier::class)]
	private Collection $suppliers;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Category::class)]
	private Collection $categories;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Product::class)]
	private Collection $products;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: ProductPrice::class)]
	private Collection $productPrices;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: Unit::class)]
	private Collection $units;

	#[ORM\OneToMany(mappedBy: 'store', targetEntity: ExchangeRate::class)]
	private Collection $exchangeRates;

	public function __construct()
	{
		$this->managers = new ArrayCollection();
		$this->warehouses = new ArrayCollection();
		$this->orders = new ArrayCollection();
		$this->purchases = new ArrayCollection();
		$this->customers = new ArrayCollection();
		$this->suppliers = new ArrayCollection();
		$this->status = ActiveStatusEnum::INACTIVE;
		$this->costingMethod = CostingMethodEnum::AVERAGE_COST;
		$this->allowBackorders = false;
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
		$this->categories = new ArrayCollection();
		$this->products = new ArrayCollection();
		$this->productPrices = new ArrayCollection();
		$this->units = new ArrayCollection();
		$this->exchangeRates = new ArrayCollection();
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

	public function getPhone(): ?string
	{
		return $this->phone;
	}

	public function setPhone(string $phone): self
	{
		$this->phone = $phone;

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
			$customer->setStore($this);
		}

		return $this;
	}

	public function removeCustomer(Customer $customer): self
	{
		if ($this->customers->removeElement($customer) && $customer->getStore() === $this) {
			$customer->setStore(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, Supplier>
	 */
	public function getSuppliers(): Collection
	{
		return $this->suppliers;
	}

	public function addSupplier(Supplier $supplier): self
	{
		if (!$this->suppliers->contains($supplier)) {
			$this->suppliers->add($supplier);
			$supplier->setStore($this);
		}

		return $this;
	}

	public function removeSupplier(Supplier $supplier): self
	{
		if ($this->suppliers->removeElement($supplier) && $supplier->getStore() === $this) {
			$supplier->setStore(null);
		}

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

	public function getCostingMethod(): CostingMethodEnum
	{
		return $this->costingMethod;
	}

	public function setCostingMethod(CostingMethodEnum $costingMethod): self
	{
		$this->costingMethod = $costingMethod;

		return $this;
	}

	public function isAllowBackorders(): bool
	{
		return $this->allowBackorders;
	}

	public function setAllowBackorders(bool $allowBackorders): self
	{
		$this->allowBackorders = $allowBackorders;

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

	public function getBaseCurrency(): ?Currency
	{
		return $this->baseCurrency;
	}

	public function setBaseCurrency(?Currency $baseCurrency): self
	{
		$this->baseCurrency = $baseCurrency;

		return $this;
	}

	/**
	 * @return Collection<int, Category>
	 */
	public function getCategories(): Collection
	{
		return $this->categories;
	}

	public function addCategory(Category $category): self
	{
		if (!$this->categories->contains($category)) {
			$this->categories->add($category);
			$category->setStore($this);
		}

		return $this;
	}

	public function removeCategory(Category $category): self
	{
		if ($this->categories->removeElement($category)) {
			// set the owning side to null (unless already changed)
			if ($category->getStore() === $this) {
				$category->setStore(null);
			}
		}

		return $this;
	}

	/**
	 * @return Collection<int, Product>
	 */
	public function getProducts(): Collection
	{
		return $this->products;
	}

	public function addProduct(Product $product): self
	{
		if (!$this->products->contains($product)) {
			$this->products->add($product);
			$product->setStore($this);
		}

		return $this;
	}

	public function removeProduct(Product $product): self
	{
		if ($this->products->removeElement($product)) {
			// set the owning side to null (unless already changed)
			if ($product->getStore() === $this) {
				$product->setStore(null);
			}
		}

		return $this;
	}

	/**
	 * @return Collection<int, ProductPrice>
	 */
	public function getProductPrices(): Collection
	{
		return $this->productPrices;
	}

	public function addProductPrice(ProductPrice $productPrice): self
	{
		if (!$this->productPrices->contains($productPrice)) {
			$this->productPrices->add($productPrice);
			$productPrice->setStore($this);
		}

		return $this;
	}

	public function removeProductPrice(ProductPrice $productPrice): self
	{
		if ($this->productPrices->removeElement($productPrice) && $productPrice->getStore() === $this) {
			$productPrice->setStore(null);
		}

		return $this;
	}

	/**
	 * @return Collection<int, Unit>
	 */
	public function getUnits(): Collection
	{
		return $this->units;
	}

	public function addUnit(Unit $unit): self
	{
		if (!$this->units->contains($unit)) {
			$this->units->add($unit);
			$unit->setStore($this);
		}

		return $this;
	}

	public function removeUnit(Unit $unit): self
	{
		if ($this->units->removeElement($unit)) {
			// set the owning side to null (unless already changed)
			if ($unit->getStore() === $this) {
				$unit->setStore(null);
			}
		}

		return $this;
	}

	/**
	 * @return Collection<int, ExchangeRate>
	 */
	public function getExchangeRates(): Collection
	{
		return $this->exchangeRates;
	}

	public function addExchangeRate(ExchangeRate $exchangeRate): self
	{
		if (!$this->exchangeRates->contains($exchangeRate)) {
			$this->exchangeRates->add($exchangeRate);
			$exchangeRate->setStore($this);
		}

		return $this;
	}

	public function removeExchangeRate(ExchangeRate $exchangeRate): self
	{
		if ($this->exchangeRates->removeElement($exchangeRate) && $exchangeRate->getStore() === $this) {
			$exchangeRate->setStore(null);
		}

		return $this;
	}
}
