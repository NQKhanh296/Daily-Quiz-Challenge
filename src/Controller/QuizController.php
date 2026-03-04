<?php

namespace App\Controller;

use App\Entity\Attempt;
use App\Repository\AttemptRepository;
use App\Repository\QuizRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/api/quiz')]
class QuizController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuizRepository $quizRepository,
        private AttemptRepository $attemptRepository,
        private UserRepository $userRepository,
        private LoggerInterface $logger
    ) {}

    #[Route('/today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        try {
            $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
            
            if (!$quiz) {
                return $this->json(['error' => 'Kvíz pro dnešní den nebyl vytvořen.'], 404);
            }

            return $this->json([
                'id' => $quiz->getId(),
                'topic' => $quiz->getTopic(),
                'questions_count' => count($quiz->getQuestions())
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching today\'s quiz: ' . $e->getMessage());
            return $this->json(['error' => 'Nastala chyba při načítání kvízu.'], 500);
        }
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        if (!$quiz) return $this->json(['error' => 'Kvíz není k dispozici.'], 404);

        $existingAttempt = $this->attemptRepository->findOneBy([
            'user' => $user, 
            'quiz' => $quiz, 
            'is_completed' => true
        ]);
        
        if ($existingAttempt) {
            return $this->json(['error' => 'Dnes už jsi kvíz dokončil.'], 400);
        }

        $data = json_decode($request->getContent(), true);
        $difficulty = isset($data['difficulty']) ? max(1, min(3, (int)$data['difficulty'])) : 1;

        try {
            $attempt = new Attempt();
            $attempt->setUser($user);
            $attempt->setQuiz($quiz);
            $attempt->setDifficulty($difficulty);
            $attempt->setStep(0);
            $attempt->setPoints(0);
            $attempt->setIsCompleted(false);
            $attempt->setLastInteraction(new \DateTimeImmutable()); 

            $this->entityManager->persist($attempt);
            $this->entityManager->flush();

            $this->logger->info("User {id} started quiz {quizId}", ['id' => $user->getId(), 'quizId' => $quiz->getId()]);

            $questions = $quiz->getQuestions()->filter(fn($q) => (int)$q->getDifficulty() === $difficulty);
            $questions = array_values($questions->toArray());

            if (empty($questions)) {
                return $this->json(['error' => 'Žádné otázky pro tuto obtížnost.'], 404);
            }

            return $this->json([
                'attempt_id' => $attempt->getId(),
                'question' => [
                    'text' => $questions[0]->getText(),
                    'options' => $questions[0]->getOptions(),
                    'step' => 1
                ]
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to start quiz attempt: ' . $e->getMessage());
            return $this->json(['error' => 'Chyba při startu kvízu.'], 500);
        }
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/submit-answer', methods: ['POST'])]
    public function submitAnswer(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $data = json_decode($request->getContent(), true);
        $answerIndex = $data['answer_index'] ?? null;

        if ($answerIndex === null) {
            return $this->json(['error' => 'Odpověď nebyla vybrána.'], 400);
        }

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        $attempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz, 'is_completed' => false]);

        if (!$attempt) return $this->json(['error' => 'Aktivní pokus nenalezen.'], 404);

        try {
            $questions = $quiz->getQuestions()->filter(fn($q) => (int)$q->getDifficulty() === $attempt->getDifficulty());
            $questions = array_values($questions->toArray());
            
            $currentQuestion = $questions[$attempt->getStep()];
            $now = new \DateTimeImmutable();
            $duration = $now->getTimestamp() - $attempt->getLastInteraction()->getTimestamp();
            
            $isCorrect = ($answerIndex === $currentQuestion->getCorrectIndex());
            
            $earnedPoints = $isCorrect ? max(10, (100 * $attempt->getDifficulty()) - ($duration * 2)) : 0;

            $attempt->setPoints($attempt->getPoints() + $earnedPoints);
            $this->entityManager->flush();

            return $this->json([
                'correct' => $isCorrect,
                'correct_index' => $currentQuestion->getCorrectIndex(),
                'earned_points' => $earnedPoints
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing answer: ' . $e->getMessage());
            return $this->json(['error' => 'Chyba při zpracování odpovědi.'], 500);
        }
    }

    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    #[Route('/fetch-question', methods: ['GET'])]
    public function fetchQuestion(Request $request): JsonResponse
    {
        $user = $this->getUser();

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        $attempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz, 'is_completed' => false]);

        if (!$attempt) return $this->json(['error' => 'Pokus nenalezen.'], 404);

        try {
            $nextStep = $attempt->getStep() + 1;
            $questions = $quiz->getQuestions()->filter(fn($q) => (int)$q->getDifficulty() === $attempt->getDifficulty());
            $questions = array_values($questions->toArray());

            if ($nextStep >= 3 || $nextStep >= count($questions)) {
                $attempt->setIsCompleted(true);
                $user->setTotalScore(($user->getTotalScore() ?? 0) + $attempt->getPoints());
                
                $this->entityManager->flush();
                $this->logger->info("User {id} finished quiz with {points} pts", ['id' => $user->getId(), 'points' => $attempt->getPoints()]);

                return $this->json([
                    'status' => 'finished', 
                    'total_points' => $attempt->getPoints(),
                    'user_total_score' => $user->getTotalScore()
                ]);
            }

            $attempt->setStep($nextStep);
            $attempt->setLastInteraction(new \DateTimeImmutable());
            $this->entityManager->flush();

            $nextQuestion = $questions[$nextStep];

            return $this->json([
                'text' => $nextQuestion->getText(),
                'options' => $nextQuestion->getOptions(),
                'step' => $nextStep + 1
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error fetching next question: ' . $e->getMessage());
            return $this->json(['error' => 'Chyba při načítání další otázky.'], 500);
        }
    }
}