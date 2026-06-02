<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Enum\CommentTypeEnum;
use App\Repository\OrderCommentRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderCommentRepository::class)]
class OrderComment
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\Column(type: Types::TEXT)]
	private string $body;

	#[ORM\Column(length: 32, enumType: CommentTypeEnum::class)]
	private CommentTypeEnum $type = CommentTypeEnum::GENERAL;

	#[ORM\Column]
	private bool $isImportant = false;

	#[ORM\Column]
	private DateTimeImmutable $createdAt;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $updatedAt = null;

	#[ORM\Column(nullable: true)]
	private ?DateTimeImmutable $deletedAt = null;

	#[ORM\ManyToOne(inversedBy: 'comments')]
	#[ORM\JoinColumn(nullable: false)]
	private Order $order;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private User $author;

	public function __construct()
	{
		$this->createdAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getBody(): string { return $this->body; }
	public function setBody(string $body): self { $this->body = $body; return $this; }
	public function getType(): CommentTypeEnum { return $this->type; }
	public function setType(CommentTypeEnum $type): self { $this->type = $type; return $this; }
	public function isImportant(): bool { return $this->isImportant; }
	public function setIsImportant(bool $isImportant): self { $this->isImportant = $isImportant; return $this; }
	public function getCreatedAt(): DateTimeImmutable { return $this->createdAt; }
	public function setCreatedAt(DateTimeImmutable $createdAt): self { $this->createdAt = $createdAt; return $this; }
	public function getUpdatedAt(): ?DateTimeImmutable { return $this->updatedAt; }
	public function setUpdatedAt(?DateTimeImmutable $updatedAt): self { $this->updatedAt = $updatedAt; return $this; }
	public function getDeletedAt(): ?DateTimeImmutable { return $this->deletedAt; }
	public function setDeletedAt(?DateTimeImmutable $deletedAt): self { $this->deletedAt = $deletedAt; return $this; }
	public function getOrder(): Order { return $this->order; }
	public function setOrder(Order $order): self { $this->order = $order; return $this; }
	public function getAuthor(): User { return $this->author; }
	public function setAuthor(User $author): self { $this->author = $author; return $this; }
}
