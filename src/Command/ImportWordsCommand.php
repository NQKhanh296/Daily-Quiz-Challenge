<?php
namespace App\Command;

use App\Entity\Word;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:import-words')]
class ImportWordsCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $csv = Reader::createFromPath('public/words.csv', 'r');
        $csv->setHeaderOffset(0);

        $i = 0;
        foreach ($csv->getRecords() as $row) {
            $word = new Word();
            $word->setText($row['text']);
            
            $this->em->persist($word);
            
            if (($i % 100) === 0) {
                $this->em->flush();
            }
            $i++;
        }

        $this->em->flush();
        $output->writeln("Importováno $i slov do tabulky Word.");
        
        return Command::SUCCESS;
    }
}