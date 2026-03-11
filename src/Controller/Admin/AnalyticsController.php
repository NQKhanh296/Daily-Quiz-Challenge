<?php
namespace App\Controller\Admin;

use App\Repository\VisitRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/admin/analytics', name: 'api_admin_analytics')]
// #[IsGranted('ROLE_ADMIN')] 
class AnalyticsController extends AbstractController
{
    #[Route('', name: '_index', methods: ['GET'])]
    public function index(VisitRepository $visitRepository): JsonResponse
    {
        $data = [
            'status' => 'success',
            'timestamp' => new \DateTimeImmutable(),
            'summary' => [
                'total_visits' => $visitRepository->count([]),
            ],
            'stats' => [
                'by_source'   => $visitRepository->getUtmStatsBySource(),
                'by_campaign' => $visitRepository->getCampaignStats(),
            ],
        ];

        return $this->json($data);
    }
}