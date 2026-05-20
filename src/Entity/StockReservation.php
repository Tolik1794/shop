<?php

namespace App\Entity;

use App\Repository\StockReservationRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StockReservationRepository::class)]
#[ORM\Table(name: 'stock_reservation')]
#[ORM\Index(name: 'idx_stock_reservation_order_entry', columns: ['order_entry_id'])]
#[ORM\Index(name: 'idx_stock_reservation_stock_status', columns: ['warehouse_stock_id', 'status'])]
class StockReservation
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $quantity = null;

	#[ORM\Column(length: 32, enumType: StockReservationStatus::class)]
	private StockReservationStatus $status;

	#[ORM\Column]
	private DateTimeImmutable $reservedAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $expiresAt = null;

	#[ORM\ManyToOne(inversedBy: 'stockReservations')]
	#[ORM\JoinColumn(nullable: false)]
	private ?OrderEntry $orderEntry = null;

	#[ORM\ManyToOne(inversedBy: 'stockReservations')]
	#[ORM\JoinColumn(nullable: false)]
	private ?WarehouseStock $warehouseStock = null;

	public function __construct()
	{
		$this->quantity = '0.0000';
		$this->status = StockReservationStatus::ACTIVE;
		$this->reservedAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getQuantity(): ?string
	{
		return $this->quantity;
	}

	public function setQuantity(string $quantity): self
	{
		$this->quantity = $quantity;

		return $this;
	}

	public function getStatus(): StockReservationStatus
	{
		return $this->status;
	}

	public function setStatus(StockReservationStatus $status): self
	{
		$this->status = $status;

		return $this;
	}

	public function getReservedAt(): DateTimeImmutable
	{
		return $this->reservedAt;
	}

	public function setReservedAt(DateTimeImmutable $reservedAt): self
	{
		$this->reservedAt = $reservedAt;

		return $this;
	}

	public function getExpiresAt(): ?DateTimeImmutable
	{
		return $this->expiresAt;
	}

	public function setExpiresAt(?DateTimeImmutable $expiresAt): self
	{
		$this->expiresAt = $expiresAt;

		return $this;
	}

	public function getOrderEntry(): ?OrderEntry
	{
		return $this->orderEntry;
	}

	public function setOrderEntry(?OrderEntry $orderEntry): self
	{
		$this->orderEntry = $orderEntry;

		return $this;
	}

	public function getWarehouseStock(): ?WarehouseStock
	{
		return $this->warehouseStock;
	}

	public function setWarehouseStock(?WarehouseStock $warehouseStock): self
	{
		$this->warehouseStock = $warehouseStock;

		return $this;
	}
}
