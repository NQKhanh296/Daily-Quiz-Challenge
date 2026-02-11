<?php

namespace App\Controller;

use App\Entity\Attempt;
use App\Entity\Quiz;
use App\Entity\User;
use App\Repository\AttemptRepository;
use App\Repository\QuizRepository;
use App\Repository\UserRepository;
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
        private AttemptRepository $attemptRepository,
        private UserRepository $userRepository
    ) {}

    #[Route('/today', methods: ['GET'])]
    public function today(): JsonResponse
    {
        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        if (!$quiz) return $this->json(['error' => 'Kvíz nenalezen.'], 404);

        return $this->json([
            'id' => $quiz->getId(),
            'topic' => $quiz->getTopic(),
            'questions_count' => 3 
        ]);
    }

    #[Route('/start', methods: ['POST'])]
    public function start(Request $request): JsonResponse
    {
        try {
            $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
            if (!$quiz) return $this->json(['error' => 'Kvíz pro dnešek neexistuje.'], 404);

            $user = new User();
            $user->setUsername('guest_' . uniqid());
            $user->setTotalScore(0);
            $user->setRole('ROLE_USER');
            $user->setVerificationCode('GUEST');

            $this->entityManager->persist($user);
            
            $this->entityManager->flush();

            $session = $request->getSession();
            $session->set('temp_user_id', $user->getId());

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
        
            return $this->json(['error' => $e->getMessage()], 500);
        }
    }

    #[Route('/submit-answer', methods: ['POST'])]
    public function submitAnswer(Request $request): JsonResponse
    {
        $userId = $request->getSession()->get('temp_user_id');
        $user = $this->userRepository->find($userId);
        
        if (!$user) return $this->json(['error' => 'Uživatel nenalezen v session.'], 403);

        $data = json_decode($request->getContent(), true);
        $answerIndex = $data['answer_index'];

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        $attempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz, 'is_completed' => false]);

        if (!$attempt) return $this->json(['error' => 'Aktivní pokus nenalezen.'], 404);

        $questions = $quiz->getQuestions()->filter(fn($q) => (int)$q->getDifficulty() === $attempt->getDifficulty());
        $questions = array_values($questions->toArray());
        
        $currentQuestion = $questions[$attempt->getStep()];
        $now = new \DateTimeImmutable();
        $duration = $now->getTimestamp() - $attempt->getLastInteraction()->getTimestamp();
        
        $isCorrect = ($answerIndex === $currentQuestion->getCorrectIndex());
        $earnedPoints = 0;

        if ($isCorrect) {
            $earnedPoints = max(10, (100 * $attempt->getDifficulty()) - ($duration * 2));
            $attempt->setPoints($attempt->getPoints() + $earnedPoints);
        }

        $this->entityManager->flush();

        return $this->json([
            'correct' => $isCorrect,
            'correct_index' => $currentQuestion->getCorrectIndex(),
            'earned_points' => $earnedPoints
        ]);
    }

    #[Route('/fetch-question', methods: ['GET'])]
    public function fetchQuestion(Request $request): JsonResponse
    {
        $userId = $request->getSession()->get('temp_user_id');
        $user = $this->userRepository->find($userId);
        
        if (!$user) return $this->json(['error' => 'Uživatel nenalezen.'], 403);

        $quiz = $this->quizRepository->findOneBy(['date' => new \DateTimeImmutable('today')]);
        $attempt = $this->attemptRepository->findOneBy(['user' => $user, 'quiz' => $quiz, 'is_completed' => false]);

        if (!$attempt) return $this->json(['error' => 'Pokus nenalezen.'], 404);

        $nextStep = $attempt->getStep() + 1;
        
        if ($nextStep >= 3) {
            $attempt->setIsCompleted(true);
            if (method_exists($user, 'setTotalScore')) {
                $user->setTotalScore(($user->getTotalScore() ?? 0) + $attempt->getPoints());
            }
            $this->entityManager->flush();
            return $this->json(['status' => 'finished', 'total_points' => $attempt->getPoints()]);
        }

        $attempt->setStep($nextStep);
        $attempt->setLastInteraction(new \DateTimeImmutable());
        $this->entityManager->flush();

        $questions = $quiz->getQuestions()->filter(fn($q) => (int)$q->getDifficulty() === $attempt->getDifficulty());
        $questions = array_values($questions->toArray());
        
        $nextQuestion = $questions[$nextStep];

        return $this->json([
            'text' => $nextQuestion->getText(),
            'options' => $nextQuestion->getOptions(),
            'step' => $nextStep + 1
        ]);
    }
}