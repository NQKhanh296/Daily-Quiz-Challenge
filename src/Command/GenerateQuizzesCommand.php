<?php
namespace App\Command;

use App\Entity\Quiz;
use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:generate-quizzes')]
class GenerateQuizzesCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) {
        parent::__construct();
    }

   protected function execute(InputInterface $input, OutputInterface $output): int
  {
      $io = new SymfonyStyle($input, $output);
      $questionRepo = $this->em->getRepository(Question::class);

      $topics = $this->em->createQuery('SELECT DISTINCT q.topic FROM App\Entity\Question q')->getResult();
      
      $currentDate = new \DateTimeImmutable('today'); 
      $totalQuizzesCreated = 0;

      foreach ($topics as $topicRow) {
          $topic = $topicRow['topic'];
          
          while (true) {
              $this->em->beginTransaction();
              try {
                  $qEasy = $questionRepo->findBy(['topic' => $topic, 'difficulty' => 1, 'used' => false], null, 3);
                  $qMed  = $questionRepo->findBy(['topic' => $topic, 'difficulty' => 2, 'used' => false], null, 3);
                  $qHard = $questionRepo->findBy(['topic' => $topic, 'difficulty' => 3, 'used' => false], null, 3);

                  if (count($qEasy) < 3 || count($qMed) < 3 || count($qHard) < 3) {
                      $this->em->rollback();
                      break;
                  }

                  $quiz = new Quiz();
                  $quiz->setTopic($topic);
                  
                  $quiz->setDate($currentDate); 

                  foreach (array_merge($qEasy, $qMed, $qHard) as $question) {
                      $quiz->addQuestion($question);
                      $question->setUsed(true);
                  }

                  $this->em->persist($quiz);
                  $this->em->flush();
                  $this->em->commit();

                  $totalQuizzesCreated++;
                  $currentDate = $currentDate->modify('+1 day');

              } catch (\Exception $e) {
                  $this->em->rollback();
                  $io->error("Chyba: " . $e->getMessage());
                  break;
              }
          }
      }

      $io->success("Vytvořeno $totalQuizzesCreated kvízů.");
      return Command::SUCCESS;
  }
}