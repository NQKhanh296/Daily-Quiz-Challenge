<?php

namespace App\Security;

use App\Service\AuthService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;

class SessionAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private AuthService $authService
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/authenticate'
            && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['words']) || !is_array($data['words']) || count($data['words']) !== 3) {
            throw new AuthenticationException('Neplatný formát kódu.');
        }

        $user = $this->authService->authenticate($data['words']);

        return new SelfValidatingPassport(
            new UserBadge($user->getUserIdentifier(), fn() => $user)
        );
    }

    public function onAuthenticationSuccess(
        Request $request,
        TokenInterface $token,
        string $firewallName
    ): ?Response {
        $user = $token->getUser();

        return new JsonResponse([
            'status' => 'success',
            'username' => $user->getUsername()
        ]);
    }

    public function onAuthenticationFailure(
        Request $request,
        AuthenticationException $exception
    ): ?Response {
        return new JsonResponse([
            'error' => 'Neplatný kód nebo uživatel nenalezen.'
        ], Response::HTTP_UNAUTHORIZED);
    }
}