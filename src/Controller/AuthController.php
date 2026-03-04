<?php

namespace App\Controller;

use App\Service\AuthService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api')]
class AuthController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    #[Route('/registration-code', name: 'api_auth_get_code', methods: ['GET'])]
    public function getCode(AuthService $authService): JsonResponse
    {
        try {
            return $this->json([
                'words' => $authService->getAvailableWords()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Chyba při načítání slov: ' . $e->getMessage());
            return $this->json(['error' => 'Nepodařilo se načíst registrační slova.'], 500);
        }
    }

    #[Route('/logout', name: 'api_auth_logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $request->getSession()->invalidate();
        return $this->json(['message' => 'Odhlášeno']);
    }

    #[Route('/authenticate', name: 'api_auth_authenticate', methods: ['POST'])]
    public function authenticate(
        Request $request,
        AuthService $authService,
        #[Autowire(service: 'limiter.auth')] 
        RateLimiterFactory $authLimiter
    ): JsonResponse {
        $limiter = $authLimiter->create($request->getClientIp());
        $limit = $limiter->consume(1);
        
        if (false === $limit->isAccepted()) {
            $this->logger->warning('Brute force detekován z IP: ' . $request->getClientIp());
            return $this->json([
                'error' => 'Příliš mnoho pokusů! Zkuste to znovu za ' . $limit->getRetryAfter()->format('%i') . ' min.'
            ], 429);
        }

        $data = json_decode($request->getContent(), true);
        if (null === $data || !isset($data['words'])) {
            return $this->json(['error' => 'Neplatný formát dat.'], 400);
        }

        $words = $data['words'];
        if (!is_array($words) || count($words) !== 3) {
            return $this->json(['error' => 'Kód musí obsahovat přesně 3 slova.'], 400);
        }

        try {
            $user = $authService->authenticate($words);
            
            $session = $request->getSession();
            $session->migrate(); 

            $this->logger->info("Uživatel ID {id} se úspěšně přihlásil.", ['id' => $user->getId()]);

            return $this->json([
                'status' => 'success',
                'user_id' => $user->getId(),
                'username' => $user->getUsername()
            ]);

        } catch (\Exception $e) {
            $this->logger->notice('Neúspěšný pokus o přihlášení: ' . $e->getMessage());
            return $this->json(['error' => 'Neplatný kód nebo uživatel nenalezen.'], 401);
        }
    }
}