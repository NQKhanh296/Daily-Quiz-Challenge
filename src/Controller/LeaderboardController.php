<?php

namespace App\Controller;

use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;

class LeaderboardController extends AbstractController
{
    #[Route('/api/leaderboard', methods: ['GET'])]
    public function index(UserRepository $userRepository): JsonResponse
    {
        $topUsers = $userRepository->findBy([], ['totalScore' => 'DESC'], 10);
        
        $data = [];
        foreach ($topUsers as $user) {
            $data[] = [
                'username' => $user->getUsername(),
                'score' => $user->getTotalScore()
            ];
        }

        return $this->json($data);
    }
}