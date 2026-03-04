<?php

namespace App\Repository;

use App\Entity\Visit;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class VisitRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Visit::class);
    }

    public function getUtmStatsBySource(): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.utmSource as source, COUNT(v.id) as visitCount')
            ->groupBy('v.utmSource')
            ->orderBy('visitCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function getCampaignStats(): array
    {
        return $this->createQueryBuilder('v')
            ->select('v.utmCampaign as campaign, COUNT(v.id) as visitCount')
            ->where('v.utmCampaign IS NOT NULL')
            ->groupBy('v.utmCampaign')
            ->getQuery()
            ->getResult();
    }
}