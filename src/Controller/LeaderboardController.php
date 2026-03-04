<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class LeaderboardController extends AbstractController
{
    #[Route('/api/leaderboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function index(UserRepository $userRepository, LoggerInterface $logger): JsonResponse
    {
        try {
            /** @var \App\Entity\User $currentUser */
            $currentUser = $this->getUser();

            if (!$currentUser) {
                return $this->json(['error' => 'Unauthorized'], 401);
            }

            $topUsers = $userRepository->findBy([], ['totalScore' => 'DESC'], 10);

            $topData = [];
            foreach ($topUsers as $user) {
                $topData[] = [
                    'username' => $user->getUsername(),
                    'score' => $user->getTotalScore()
                ];
            }

            $currentUserRank = $userRepository->getUserRank(
                $currentUser->getTotalScore()
            );

            $currentUserData = [
                'username' => $currentUser->getUsername(),
                'score' => $currentUser->getTotalScore(),
                'rank' => $currentUserRank
            ];

            return $this->json([
                'top10' => $topData,
                'currentUser' => $currentUserData
            ]);

        } catch (\Exception $e) {
            $logger->error('Leaderboard fetch failed', [
                'error' => $e->getMessage()
            ]);

            return $this->json(['error' => 'Could not load leaderboard'], 500);
        }
    }
}