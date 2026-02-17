<?php
namespace App\Controller;

use App\Service\AuthService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\DependencyInjection\Attribute\Autowire;



#[Route('/api')]
class AuthController extends AbstractController {

    #[Route('/registration-code', methods: ['GET'])]
    public function getCode(AuthService $authService): Response {
      try {
          return $this->json([
              'words' => $authService->getAvailableWords()
          ]);
      } catch (\Exception $e) {
          return $this->json([
              'error' => $e->getMessage()
          ], 500);
      }
    }

    #[Route('/logout', methods: ['POST'])]
    public function logout(Request $request): Response {
      $request->getSession()->remove('temp_user_id');
      return $this->json(['message' => 'Odhlášeno']);
    }


    #[Route('/authenticate', methods: ['POST'])]
    public function authenticate(
    Request $request, 
    AuthService $authService,
    #[Autowire(service: 'limiter.auth')]
    RateLimiterFactory $authLimiter
    ): Response
    {
        $limiter = $authLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            return $this->json(['error' => 'Příliš mnoho pokusů!'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $words = $data['words'] ?? [];

        if (count($words) !== 3) {
            return $this->json(['error' => 'Kód musí obsahovat 3 slova.'], 400);
        }

        try {
            $user = $authService->authenticate($words);
            $request->getSession()->set('temp_user_id', $user->getId());

            return $this->json([
                'message' => 'Success',
                'user_id' => $user->getId()
            ]);
        } catch (\Exception $e) {
            return $this->json(['error' => $e->getMessage()], 400);
        }
    }
}