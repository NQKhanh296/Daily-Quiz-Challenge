<?php

namespace App\Entity;

use App\Repository\CodeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CodeRepository::class)]
class Code
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(targetEntity: User::class, cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'codes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Word $word1_id = null;

    #[ORM\ManyToOne(inversedBy: 'codes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Word $word2_id = null;

    #[ORM\ManyToOne(inversedBy: 'codes')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Word $word3_id = null;

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

    public function getWord1Id(): ?Word
    {
        return $this->word1_id;
    }

    public function setWord1Id(?Word $word1_id): static
    {
        $this->word1_id = $word1_id;

        return $this;
    }

    public function getWord2Id(): ?Word
    {
        return $this->word2_id;
    }

    public function setWord2Id(?Word $word2_id): static
    {
        $this->word2_id = $word2_id;

        return $this;
    }

    public function getWord3Id(): ?Word
    {
        return $this->word3_id;
    }

    public function setWord3Id(?Word $word3_id): static
    {
        $this->word3_id = $word3_id;

        return $this;
    }
}
