<?php

// https://dennikvizovavyzva.online/?utm_source=plakat&utm_medium=qr_code&utm_campaign=jaro2026

namespace App\EventSubscriber;

use App\Entity\Visit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class UtmTrackerSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly string $appSecret
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $query = $request->query;

        $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $foundUtm = [];

        foreach ($utmParams as $param) {
            if ($query->has($param)) {
                $foundUtm[$param] = $query->get($param);
            }
        }

        if (empty($foundUtm)) {
            return;
        }

        $session = $request->getSession();
        foreach ($foundUtm as $key => $value) {
            $session->set($key, $value);
        }

        $visit = new Visit();
        $visit->setUtmSource($foundUtm['utm_source'] ?? null);
        $visit->setUtmMedium($foundUtm['utm_medium'] ?? null);
        $visit->setUtmCampaign($foundUtm['utm_campaign'] ?? null);
        $visit->setCreatedAt(new \DateTimeImmutable());
        $visit->setIpHash(hash('sha256', $request->getClientIp() . $this->appSecret));

        $this->em->persist($visit);
        $this->em->flush();

        $params = $query->all();
        foreach ($utmParams as $param) {
            unset($params[$param]);
        }

        $cleanUrl = $request->getPathInfo() . (count($params) ? '?' . http_build_query($params) : '');
        
        $event->setResponse(new RedirectResponse($cleanUrl));
    }
}