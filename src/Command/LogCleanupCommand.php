<?php
namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:log-cleanup', description: 'Maže logy starší než 30 dní')]
class LogCleanupCommand extends Command
{
    public function __construct(private EntityManagerInterface $em) { parent::__construct(); }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $date = (new \DateTime('-30 days'))->format('Y-m-d H:i:s');
        $conn = $this->em->getConnection();
        
        $sql = 'DELETE FROM system_logs WHERE created_at < :date';
        $conn->executeStatement($sql, ['date' => $date]);

        $output->writeln('Staré logy byly promazány.');
        return Command::SUCCESS;
    }
}