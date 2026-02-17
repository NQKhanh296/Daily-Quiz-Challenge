<?php
namespace App\Service;

use App\Entity\Code;
use App\Entity\User;
use App\Entity\Word;
use App\Repository\CodeRepository;
use App\Repository\WordRepository;
use Doctrine\ORM\EntityManagerInterface;

class AuthService {
    public function __construct(
        private EntityManagerInterface $em,
        private CodeRepository $codeRepo,
        private WordRepository $wordRepo
    ) {}

    public function getAvailableWords(): array {
        $allWords = $this->wordRepo->findAll();
        if (count($allWords) < 3) throw new \Exception("Nedostatek slov v databázi.");

        while (true) {
            shuffle($allWords);
            $pick = array_slice($allWords, 0, 3);
            if (!$this->codeRepo->findOneByWords($pick[0], $pick[1], $pick[2])) {
                return array_map(fn($w) => $w->getText(), $pick);
            }
        }
    }

    public function authenticate(array $wordTexts): User {
        $words = [];
        foreach ($wordTexts as $text) {
            $word = $this->wordRepo->findOneBy(['text' => $text]);
            if (!$word) throw new \Exception("Slovo '$text' neexistuje.");
            $words[] = $word;
        }

        $existingCode = $this->codeRepo->findOneByWords($words[0], $words[1], $words[2]);
        if ($existingCode) return $existingCode->getUser();

        return $this->em->wrapInTransaction(function() use ($words) {
            $user = new User();
            $user->setUsername('user_' . bin2hex(random_bytes(4)));
            $user->setRole('ROLE_USER');
            $user->setTotalScore(0);
            $user->setVerificationCode(implode('-', array_map(fn($w) => $w->getText(), $words)));

            $code = new Code();
            $code->setWord1Id($words[0]);
            $code->setWord2Id($words[1]);
            $code->setWord3Id($words[2]);
            $code->setUser($user);

            $this->em->persist($user);
            $this->em->persist($code);
            return $user;
        });
    }
}