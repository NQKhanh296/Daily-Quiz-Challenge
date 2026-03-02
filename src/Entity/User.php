<?php
namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255)]
    private ?string $verificationCode = null;

    #[ORM\Column(length: 255)]
    private ?string $role = null;

    #[ORM\Column]
    private int $totalScore = 0;

    #[ORM\OneToMany(targetEntity: Attempt::class, mappedBy: 'user')]
    private Collection $attempts;

    public function __construct() {
        $this->attempts = new ArrayCollection();
    }

    public function getTotalScore(): int { return $this->totalScore; }
    public function setTotalScore(int $score): self { $this->totalScore = $score; return $this; }
    public function getVerificationCode(): ?string { return $this->verificationCode; }
    public function setVerificationCode(string $code): self { $this->verificationCode = $code; return $this; }
    
    public function getId(): ?int { return $this->id; }
    public function getUsername(): ?string { return $this->username; }
    public function setUsername(string $username): self { $this->username = $username; return $this; }
    public function getRole(): ?string { return $this->role; }
    public function setRole(string $role): self { $this->role = $role; return $this; }

    public function getRoles(): array {
        return [$this->role ?: 'ROLE_USER'];
    }

    public function eraseCredentials(): void {

    }

    public function getUserIdentifier(): string {

        return (string) $this->id;
    }
}