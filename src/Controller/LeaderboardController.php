<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/api/leaderboard', methods: ['GET'])]
    public function index(UserRepository $userRepository, LoggerInterface $logger): JsonResponse
    {
        try {
            $topUsers = $userRepository->findBy([], ['totalScore' => 'DESC'], 10);
            
            $data = [];
            foreach ($topUsers as $user) {
                $data[] = [
                    'username' => $user->getUsername(),
                    'score' => $user->getTotalScore()
                ];
            }

            return $this->json($data);
        } catch (\Exception $e) {
            $logger->error('Leaderboard fetch failed', ['error' => $e->getMessage()]);
            return $this->json(['error' => 'Could not load leaderboard'], 500);
        }
    }
}