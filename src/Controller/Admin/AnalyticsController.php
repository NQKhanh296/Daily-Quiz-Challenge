<?php

namespace App\Controller\Admin;

use App\Repository\VisitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/analytics', name: 'admin_analytics')]
class AnalyticsController extends AbstractController
{
    public function index(VisitRepository $visitRepository): Response
    {
        return $this->render('admin/analytics.html.twig', [
            'sourceStats'   => $visitRepository->getUtmStatsBySource(),
            'campaignStats' => $visitRepository->getCampaignStats(),
            'totalVisits'   => $visitRepository->count([]),
        ]);
    }
}