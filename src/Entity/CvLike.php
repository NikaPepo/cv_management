<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\CvLikeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CvLikeRepository::class)]
#[ORM\Table(name: 'cv_like')]
#[ORM\UniqueConstraint(
    name: 'UNIQ_CV_LIKE_RECRUITER',
    columns: ['cv_id', 'recruiter_id']
)]
class CvLike
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Cv::class, inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Cv $cv;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'recruiter_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $recruiter;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCv(): Cv
    {
        return $this->cv;
    }

    public function setCv(Cv $cv): static
    {
        $this->cv = $cv;
        return $this;
    }

    public function getRecruiter(): User
    {
        return $this->recruiter;
    }

    public function setRecruiter(User $recruiter): static
    {
        $this->recruiter = $recruiter;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}