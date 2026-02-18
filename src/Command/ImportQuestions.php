<?php
namespace App\Command;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import-questions')]
class ImportCsvCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
       
        $csv = Reader::createFromPath('public\questions.csv', 'r');
        $csv->setHeaderOffset(0);

        foreach ($csv->getRecords() as $row) {
            $q = new Question();
            $q->setTopic($row['topic']);
            $q->setDifficulty((int)$row['difficulty']);
            $q->setText($row['text']);
            
           
            $q->setOptions(json_decode($row['options'], true));
            
            $q->setCorrectIndex((int)$row['correct_index']);
            $q->setUsed(filter_var($row['used'], FILTER_VALIDATE_BOOLEAN));

            $this->em->persist($q);
        }

        $this->em->flush();
        $output->writeln('Hotovo, data jsou v Neonu!');
        return Command::SUCCESS;
    }
}