<?php

// http://132.145.247.234/?utm_source=letak_jidelna&utm_medium=qr&utm_campaign=jaro2026

namespace App\EventSubscriber;

use App\Entity\Visit;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
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
            KernelEvents::REQUEST => [['onKernelRequest', 10]],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        $source = $request->query->get('utm_source');
        $medium = $request->query->get('utm_medium');
        $campaign = $request->query->get('utm_campaign');

        if ($source) {

            $session->set('utm_source', $source);
            $session->set('utm_medium', $medium);
            $session->set('utm_campaign', $campaign);

            $visit = new Visit();
            $visit->setUtmSource($source);
            $visit->setUtmMedium($medium);
            $visit->setUtmCampaign($campaign);
            $visit->setCreatedAt(new \DateTimeImmutable());
            
            $visit->setIpHash(hash('sha256', $request->getClientIp() . $this->appSecret));

            $this->em->persist($visit);
            $this->em->flush();
        }
    }
}