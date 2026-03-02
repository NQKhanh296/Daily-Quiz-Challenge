<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/user')]
class UserController extends AbstractController
{
    #[Route('/change-username', name: 'app_user_update_username', methods: ['PATCH'])]
    public function updateUsername(
        Request $request, 
        Security $security, 
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator,
        LoggerInterface $logger,
        #[Autowire(service: 'limiter.username_change')] RateLimiterFactory $usernameLimiter
    ): JsonResponse {
        /** @var User|null $user */
        $user = $security->getUser();

        if (!$user) {
            $logger->warning('Unauthorized attempt to change username.');
            return $this->json(['error' => 'Authentication required'], 401);
        }
        $limiter = $usernameLimiter->create((string)$user->getId());
        if (false === $limiter->consume(1)->isAccepted()) {
            $logger->info("User {id} exceeded username change rate limit.", ['id' => $user->getId()]);
            return $this->json(['error' => 'Jméno můžete měnit jen jednou za 15 minut.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $newUsername = trim($data['username'] ?? '');

        $user->setUsername($newUsername);

        $errors = $validator->validate($user);
        if (count($errors) > 0) {
            return $this->json(['errors' => (string) $errors], 422);
        }

        try {
            $entityManager->flush();
            $logger->info("User ID {id} changed username to {new_name}", [
                'id' => $user->getId(), 
                'new_name' => $newUsername
            ]);
        } catch (\Exception $e) {
            $logger->error('Database failure during username update', ['exception' => $e->getMessage()]);
            return $this->json(['error' => 'Could not update username at this time.'], 500);
        }

        return $this->json(['message' => 'Username updated successfully']);
    }
}