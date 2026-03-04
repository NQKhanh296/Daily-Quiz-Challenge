<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;
/*use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;*/

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
        //CsrfTokenManagerInterface $csrfTokenManager,
        #[Autowire(service: 'limiter.username_change')] 
        RateLimiterFactory $usernameLimiter
    ): JsonResponse {
        /** @var User|null $user */
        $user = $security->getUser();

        if (!$user) {
            $logger->warning('Unauthorized attempt to change username.');
            return $this->json(['error' => 'Pro změnu jména se musíš přihlásit.'], 401);
        }
        /*
        $submittedToken = $request->headers->get('X-CSRF-TOKEN');


        if (!$csrfTokenManager->isTokenValid(new CsrfToken('change-username', $submittedToken))) {
            $logger->warning('CSRF attack detected or token expired for user {id}', ['id' => $user->getId()]);
            return $this->json(['error' => 'Neplatný bezpečnostní token.'], 403);
        }*/

        $limiter = $usernameLimiter->create((string)$user->getId());
        if (false === $limiter->consume(1)->isAccepted()) {
            $logger->info("User {id} exceeded username change rate limit.", ['id' => $user->getId()]);
            return $this->json(['error' => 'Jméno můžete měnit jen jednou za 15 minut.'], 429);
        }

        $data = json_decode($request->getContent(), true);
        $newUsername = trim($data['username'] ?? '');

        if (preg_match('/[<>]/', $newUsername)) {
            return $this->json(['error' => 'Jméno obsahuje nepovolené znaky.'], 400);
        }
        if (empty($newUsername)) {
            return $this->json(['error' => 'Uživatelské jméno nesmí být prázdné.'], 400);
        }

        $user->setUsername($newUsername);
        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            $messages = [];
            foreach ($errors as $error) {
                $messages[] = $error->getMessage();
            }
            return $this->json(['errors' => $messages], 422);
        }

        try {
            $entityManager->flush();
            $logger->info("User ID {id} changed username to {new_name}", [
                'id' => $user->getId(), 
                'new_name' => $newUsername
            ]);
        } catch (\Exception $e) {
            $logger->error('Database failure during username update', ['exception' => $e->getMessage()]);
            return $this->json(['error' => 'Chyba databáze. Zkuste to později.'], 500);
        }

        return $this->json([
            'message' => 'Uživatelské jméno bylo úspěšně změněno.',
            'username' => $newUsername
        ]);
    }
}