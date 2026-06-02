<?php

namespace App\Entity;

use App\Entity\User\User;
use App\Repository\OrderCommentReadStateRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderCommentReadStateRepository::class)]
#[ORM\Table(name: 'order_comment_read_state')]
#[ORM\UniqueConstraint(name: 'uniq_order_comment_read_state_order_user', columns: ['order_id', 'user_id'])]
#[ORM\Index(name: 'idx_order_comment_read_state_user', columns: ['user_id'])]
class OrderCommentReadState
{
	#[ORM\Id]
	#[ORM\GeneratedValue]
	#[ORM\Column]
	private ?int $id = null;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private Order $order;

	#[ORM\ManyToOne]
	#[ORM\JoinColumn(nullable: false)]
	private User $user;

	#[ORM\Column]
	private DateTimeImmutable $lastReadAt;

	public function __construct()
	{
		$this->lastReadAt = new DateTimeImmutable();
	}

	public function getId(): ?int { return $this->id; }
	public function getOrder(): Order { return $this->order; }
	public function setOrder(Order $order): self { $this->order = $order; return $this; }
	public function getUser(): User { return $this->user; }
	public function setUser(User $user): self { $this->user = $user; return $this; }
	public function getLastReadAt(): DateTimeImmutable { return $this->lastReadAt; }
	public function setLastReadAt(DateTimeImmutable $lastReadAt): self { $this->lastReadAt = $lastReadAt; return $this; }
}
