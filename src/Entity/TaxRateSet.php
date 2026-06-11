<?php

namespace App\Entity;

use App\Repository\TaxRateSetRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TaxRateSetRepository::class)]
#[ORM\UniqueConstraint(name: 'uniq_tax_rate_set_year', columns: ['year'])]
class TaxRateSet
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column]
	private ?int $year = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $minimumWage = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $subsistenceMinimum = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $group1IncomeLimit = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $group2IncomeLimit = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $group3IncomeLimit = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $group1EpMonthly = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $group2EpMonthly = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
	private ?string $group3EpRatePct = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
	private ?string $group3EpRateVatPct = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
	private ?string $esvRatePct = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $esvMonthlyMin = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 14, scale: 4)]
	private ?string $vzGroup12Monthly = null;

	#[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
	private ?string $vzGroup3RatePct = null;

	#[ORM\Column(type: Types::TEXT, nullable: true)]
	private ?string $comment = null;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column]
	private DateTimeImmutable $updatedAt;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
		$this->updatedAt = new DateTimeImmutable();
	}

	public function getId(): ?int
	{
		return $this->id;
	}

	public function getYear(): ?int
	{
		return $this->year;
	}

	public function setYear(int $year): self
	{
		$this->year = $year;

		return $this;
	}

	public function getMinimumWage(): ?string
	{
		return $this->minimumWage;
	}

	public function setMinimumWage(string $minimumWage): self
	{
		$this->minimumWage = $minimumWage;

		return $this;
	}

	public function getSubsistenceMinimum(): ?string
	{
		return $this->subsistenceMinimum;
	}

	public function setSubsistenceMinimum(string $subsistenceMinimum): self
	{
		$this->subsistenceMinimum = $subsistenceMinimum;

		return $this;
	}

	public function getGroup1IncomeLimit(): ?string
	{
		return $this->group1IncomeLimit;
	}

	public function setGroup1IncomeLimit(string $group1IncomeLimit): self
	{
		$this->group1IncomeLimit = $group1IncomeLimit;

		return $this;
	}

	public function getGroup2IncomeLimit(): ?string
	{
		return $this->group2IncomeLimit;
	}

	public function setGroup2IncomeLimit(string $group2IncomeLimit): self
	{
		$this->group2IncomeLimit = $group2IncomeLimit;

		return $this;
	}

	public function getGroup3IncomeLimit(): ?string
	{
		return $this->group3IncomeLimit;
	}

	public function setGroup3IncomeLimit(string $group3IncomeLimit): self
	{
		$this->group3IncomeLimit = $group3IncomeLimit;

		return $this;
	}

	public function getGroup1EpMonthly(): ?string
	{
		return $this->group1EpMonthly;
	}

	public function setGroup1EpMonthly(string $group1EpMonthly): self
	{
		$this->group1EpMonthly = $group1EpMonthly;

		return $this;
	}

	public function getGroup2EpMonthly(): ?string
	{
		return $this->group2EpMonthly;
	}

	public function setGroup2EpMonthly(string $group2EpMonthly): self
	{
		$this->group2EpMonthly = $group2EpMonthly;

		return $this;
	}

	public function getGroup3EpRatePct(): ?string
	{
		return $this->group3EpRatePct;
	}

	public function setGroup3EpRatePct(string $group3EpRatePct): self
	{
		$this->group3EpRatePct = $group3EpRatePct;

		return $this;
	}

	public function getGroup3EpRateVatPct(): ?string
	{
		return $this->group3EpRateVatPct;
	}

	public function setGroup3EpRateVatPct(string $group3EpRateVatPct): self
	{
		$this->group3EpRateVatPct = $group3EpRateVatPct;

		return $this;
	}

	public function getEsvRatePct(): ?string
	{
		return $this->esvRatePct;
	}

	public function setEsvRatePct(string $esvRatePct): self
	{
		$this->esvRatePct = $esvRatePct;

		return $this;
	}

	public function getEsvMonthlyMin(): ?string
	{
		return $this->esvMonthlyMin;
	}

	public function setEsvMonthlyMin(string $esvMonthlyMin): self
	{
		$this->esvMonthlyMin = $esvMonthlyMin;

		return $this;
	}

	public function getVzGroup12Monthly(): ?string
	{
		return $this->vzGroup12Monthly;
	}

	public function setVzGroup12Monthly(string $vzGroup12Monthly): self
	{
		$this->vzGroup12Monthly = $vzGroup12Monthly;

		return $this;
	}

	public function getVzGroup3RatePct(): ?string
	{
		return $this->vzGroup3RatePct;
	}

	public function setVzGroup3RatePct(string $vzGroup3RatePct): self
	{
		$this->vzGroup3RatePct = $vzGroup3RatePct;

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

	public function getCreatedAt(): DateTimeImmutable
	{
		return $this->createdAt;
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
}
