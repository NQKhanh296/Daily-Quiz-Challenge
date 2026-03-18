<?php

namespace App\Controller;

use App\Service\AuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(private readonly LoggerInterface $logger) {}

    #[Route('/registration-code', name: 'api_auth_get_code', methods: ['GET'])]
    public function getCode(AuthService $authService): JsonResponse
    {
        try {
            return $this->json(['words' => $authService->getAvailableWords()]);
        } catch (\Throwable $e) {
            return $this->json(['error' => 'Služba nedostupná.'], 500);
        }
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $request->getSession()->invalidate();
        return $this->json(['message' => 'Odhlášeno']);
    }

    #[Route('/authenticate', name: 'api_auth_authenticate', methods: ['POST'])]
    public function authenticate(): void
    {

        throw new \LogicException('Tento endpoint je spravován Symfony Security.');
    }
}