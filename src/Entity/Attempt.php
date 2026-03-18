<?php

namespace App\Entity;

use ApiPlatform\Metadata\ApiResource;
use App\Repository\AttemptRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AttemptRepository::class)]
#[ApiResource]
class Attempt
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'attempts')]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'attempts')]
    private ?Quiz $quiz = null;

    #[ORM\Column(nullable: false)]
    private int $difficulty = 1;

    #[ORM\Column(type: 'json', nullable: false)]
    private array $answeredQuestions = [];

    #[ORM\Column(nullable: false)]
    private int $step = 0;

    #[ORM\Column(nullable: false)]
    private int $points = 0;

    #[ORM\Column(type: 'datetime_immutable', nullable: false)]
    private \DateTimeImmutable $last_interaction;

    #[ORM\Column(nullable: false)]
    private bool $is_completed = false;

    public function __construct()
    {
        $this->last_interaction = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getQuiz(): ?Quiz
    {
        return $this->quiz;
    }

    public function setQuiz(?Quiz $quiz): static
    {
        $this->quiz = $quiz;
        return $this;
    }

    public function getDifficulty(): int
    {
        return $this->difficulty;
    }

    public function setDifficulty(int $difficulty): static
    {
        $this->difficulty = $difficulty;
        return $this;
    }

    public function getStep(): int
    {
        return $this->step;
    }

    public function setStep(int $step): static
    {
        $this->step = $step;
        return $this;
    }

    public function getPoints(): int
    {
        return $this->points;
    }

    public function setPoints(int $points): static
    {
        $this->points = $points;
        return $this;
    }

    public function getLastInteraction(): \DateTimeImmutable
    {
        return $this->last_interaction;
    }

    public function setLastInteraction(\DateTimeImmutable $last_interaction): static
    {
        $this->last_interaction = $last_interaction;
        return $this;
    }

    public function getIsCompleted(): bool
    {
        return $this->is_completed;
    }

    public function setIsCompleted(bool $is_completed): self
    {
        $this->is_completed = $is_completed;
        return $this;
    }

    public function getAnsweredQuestions(): array
    {
        return $this->answeredQuestions;
    }

    public function setAnsweredQuestions(array $answeredQuestions): self
    {
        $this->answeredQuestions = $answeredQuestions;
        return $this;
    }
}