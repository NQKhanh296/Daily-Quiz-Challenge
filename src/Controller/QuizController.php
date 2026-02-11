<?php

namespace App\Controller;

use App\Entity\Attempt;
use App\Entity\Quiz;
use App\Repository\AttemptRepository;
use App\Repository\QuizRepository;
use Doctrine\ORM\EntityManagerInterface;
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
        private AttemptRepository $attemptRepository
    ) {}

    #[Route('/today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);

        if (!$quiz) {
            return $this->json(['error' => 'Žádný kvíz pro dnešek nebyl nalezen.'], 404);
        }

        return $this->json([
            'id' => $quiz->getId(),
            'topic' => $quiz->getTopic(),
            'questions_count' => 3 
        ]);
    }

    #[Route('/start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        
        /*
        $existingAttempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz]);
        if ($existingAttempt) {
            return $this->json(['error' => 'Dnešní kvíz už jsi zahájil.'], 400);
        }*/

        $data = json_decode($request->getContent(), true);
        $difficulty = (int)($data['difficulty'] ?? 1);

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

        $questions = $quiz->getQuestions()->filter(fn($q) => $q->getDifficulty() === $difficulty);
        $questions = array_values($questions->toArray());

        if (empty($questions)) {
            return $this->json(['error' => 'Žádné otázky pro tuto obtížnost.'], 404);
        }

        $firstQuestion = $questions[0];

        return $this->json([
            'attempt_id' => $attempt->getId(),
            'question' => [
                'text' => $firstQuestion->getText(),
                'options' => $firstQuestion->getOptions(),
                'step' => 1
            ]
        ]);
    }

    #[Route('/submit-answer', methods: ['POST'])]
    public function submitAnswer(Request $request): JsonResponse
    {
        $user = $this->getUser();
        $data = json_decode($request->getContent(), true);
        $answerIndex = $data['answer_index'];

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        $attempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz, 'is_completed' => false]);

        if (!$attempt) return $this->json(['error' => 'Aktivní pokus nenalezen.'], 404);

        $questions = $quiz->getQuestions()->filter(fn($q) => $q->getDifficulty() === $attempt->getDifficulty());
        $questions = array_values($questions->toArray());
        
        $currentQuestion = $questions[$attempt->getStep()];

        $now = new \DateTimeImmutable();
        $duration = $now->getTimestamp() - $attempt->getLastInteraction()->getTimestamp();
        
        $isCorrect = ($answerIndex === $currentQuestion->getCorrectIndex());
        $earnedPoints = 0;

        if ($isCorrect) {
            $earnedPoints = (100 * $attempt->getDifficulty()) - ($duration * 2);
            $earnedPoints = max(10, $earnedPoints);
            $attempt->setPoints($attempt->getPoints() + $earnedPoints);
        }

        $this->entityManager->flush();

        return $this->json([
            'correct' => $isCorrect,
            'correct_index' => $currentQuestion->getCorrectIndex(),
            'earned_points' => $earnedPoints,
            'time_taken' => $duration
        ]);
    }

    #[Route('/fetch-question', methods: ['GET'])]
    public function fetchQuestion(): JsonResponse
    {
        $user = $this->getUser();
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        $attempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz, 'is_completed' => false]);

        if (!$attempt) return $this->json(['error' => 'Pokus nenalezen.'], 404);

        $nextStep = $attempt->getStep() + 1;
        
        if ($nextStep >= 3) {
            $attempt->setIsCompleted(true);
            $user->setTotalScore($user->getTotalScore() + $attempt->getPoints());
            $this->entityManager->flush();
            
            return $this->json([
                'status' => 'finished', 
                'total_points' => $attempt->getPoints()
            ]);
        }

        $attempt->setStep($nextStep);
        $attempt->setLastInteraction(new \DateTimeImmutable());
        $this->entityManager->flush();

        $questions = $quiz->getQuestions()->filter(fn($q) => $q->getDifficulty() === $attempt->getDifficulty());
        $questions = array_values($questions->toArray());
        
        $nextQuestion = $questions[$nextStep];

        return $this->json([
            'text' => $nextQuestion->getText(),
            'options' => $nextQuestion->getOptions(),
            'step' => $nextStep + 1
        ]);
    }
}