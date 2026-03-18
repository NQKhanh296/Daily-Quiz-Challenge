<?php

namespace App\Security;

use App\Service\AuthService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;

class SessionAuthenticator extends AbstractAuthenticator
{
    public function __construct(
        private readonly AuthService $authService,
        private readonly RateLimiterFactory $authLimiter 
    ) {}

    public function supports(Request $request): ?bool
    {
        return $request->getPathInfo() === '/api/authenticate' && $request->isMethod('POST');
    }

    public function authenticate(Request $request): Passport
    {
     
        $limiter = $this->authLimiter->create($request->getClientIp());
        if (false === $limiter->consume(1)->isAccepted()) {
            throw new CustomUserMessageAuthenticationException('Příliš mnoho pokusů. Zkuste to později.');
        }


        $data = json_decode($request->getContent(), true);
        if (!$data || !isset($data['words']) || !is_array($data['words']) || count($data['words']) !== 3) {
            throw new CustomUserMessageAuthenticationException('Neplatný formát registračního kódu.');
        }

        try {

            $user = $this->authService->authenticate($data['words']);

            if (null === $user) {
                throw new CustomUserMessageAuthenticationException('Neplatný kód nebo uživatel nenalezen.');
            }

            return new SelfValidatingPassport(
                new UserBadge($user->getUserIdentifier(), fn() => $user)
            );

        } catch (\Throwable $e) {

            throw new CustomUserMessageAuthenticationException('Chyba při ověřování kódu.');
        }
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        return new JsonResponse([
            'status' => 'success',
            'username' => $user->getUserIdentifier()
        ]);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        return new JsonResponse([
            'error' => $exception->getMessageKey() === 'An authentication exception occurred.' 
                       ? $exception->getMessage() 
                       : $exception->getMessageKey()
        ], Response::HTTP_UNAUTHORIZED);
    }
}