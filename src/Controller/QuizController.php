<?php

namespace App\Controller;

use App\Entity\Attempt;
use App\Repository\AttemptRepository;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/quiz')]
class QuizController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuizRepository $quizRepository,
        private AttemptRepository $attemptRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('/today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);

        if (!$quiz) {
            return $this->json(['error' => 'Kvíz neexistuje.'], 404);
        }

        return $this->json([
            'id' => $quiz->getId(),
            'topic' => $quiz->getTopic(),
            'questions_count' => count($quiz->getQuestions())
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/start', methods: ['POST'])]
    public function start(): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);

        if (!$user) {
            return $this->json(['error' => 'Nejsi přihlášen.'], 401);
        }

        if (!$quiz) {
            return $this->json(['error' => 'Kvíz není.'], 404);
        }

        $attempt = $this->attemptRepository->findOneBy([
            'user' => $user,
            'quiz' => $quiz,
            'is_completed' => false
        ]);

        if ($attempt) {
            if ($attempt->getIsCompleted()) {
                return $this->json(['error' => 'Kvíz už dokončen.'], 400);
            }

            return $this->resumeAttempt($attempt, $quiz);
        }

    
        $attempt = new Attempt();
        $attempt->setUser($user);
        $attempt->setQuiz($quiz);
        $attempt->setDifficulty(1);
        $attempt->setStep(0);
        $attempt->setAnsweredQuestions([]);
        $attempt->setPoints(0);
        $attempt->setIsCompleted(false);
        $attempt->setLastInteraction(new \DateTimeImmutable());

        $this->entityManager->persist($attempt);
        $this->entityManager->flush();

        return $this->resumeAttempt($attempt, $quiz);
    }

    private function getQuestions($quiz, $difficulty): array
    {
        return array_values(
            $quiz->getQuestions()
                ->filter(fn($q) => (int)$q->getDifficulty() === $difficulty)
                ->toArray()
        );
    }

    private function resumeAttempt(Attempt $attempt, $quiz): JsonResponse
    {
        $questions = $this->getQuestions($quiz, $attempt->getDifficulty());
        $step = $attempt->getStep() ?? 0;

        if (!isset($questions[$step])) {
            return $this->json(['error' => 'Neplatný stav.'], 500);
        }

        $q = $questions[$step];

        return $this->json([
            'question' => [
                'text' => $q->getText(),
                'options' => $q->getOptions(),
                'step' => $step + 1
            ]
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/submit-answer', methods: ['POST'])]
    public function submitAnswer(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true) ?? [];

        if (!isset($data['answer_index'])) {
            return $this->json(['error' => 'Chybí odpověď.'], 400);
        }

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);

        $attempt = $this->attemptRepository->findOneBy([
            'user' => $user,
            'quiz' => $quiz,
            'is_completed' => false
        ]);

        if (!$attempt) {
            return $this->json(['error' => 'Pokus nenalezen.'], 404);
        }

        $questions = $this->getQuestions($quiz, $attempt->getDifficulty());
        $index = $attempt->getStep() ?? 0;

        if (!isset($questions[$index])) {
            return $this->json(['error' => 'Neplatný stav.'], 400);
        }

       
        $answered = $attempt->getAnsweredQuestions();

        if (in_array($index, $answered, true)) {
            return $this->json(['error' => 'Na tuto otázku už bylo odpovězeno.'], 400);
        }

        $question = $questions[$index];

        $now = new \DateTimeImmutable();
        $last = $attempt->getLastInteraction() ?? $now;
        $duration = $now->getTimestamp() - $last->getTimestamp();

        $isCorrect = ((int)$data['answer_index'] === $question->getCorrectIndex());

        $earnedPoints = $isCorrect
            ? max(10, (100 * $attempt->getDifficulty()) - ($duration * 2))
            : 0;

      
        $answered[] = $index;
        $attempt->setAnsweredQuestions($answered);

       
        $attempt->setStep($index + 1);
        $attempt->setPoints($attempt->getPoints() + $earnedPoints);
        $attempt->setLastInteraction($now);

        $this->entityManager->flush();

        return $this->json([
            'correct' => $isCorrect,
            'earned_points' => $earnedPoints
        ]);
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/fetch-question', methods: ['GET'])]
    public function fetchQuestion(): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);

        $attempt = $this->attemptRepository->findOneBy([
            'user' => $user,
            'quiz' => $quiz,
            'is_completed' => false
        ]);

        if (!$attempt) {
            return $this->json(['error' => 'Pokus nenalezen.'], 404);
        }

        $questions = $this->getQuestions($quiz, $attempt->getDifficulty());
        $step = $attempt->getStep() ?? 0;

        if ($step >= count($questions)) {
            $attempt->setIsCompleted(true);

            $user = $attempt->getUser();
            $user->setTotalScore(($user->getTotalScore() ?? 0) + $attempt->getPoints());

            $this->entityManager->flush();

            return $this->json([
                'status' => 'finished',
                'total_points' => $attempt->getPoints()
            ]);
        }

        $q = $questions[$step];

        return $this->json([
            'text' => $q->getText(),
            'options' => $q->getOptions(),
            'step' => $step + 1
        ]);
    }
}