<?php

namespace App\Command;

use App\Entity\Question;
use Doctrine\ORM\EntityManagerInterface;
use League\Csv\Reader;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(name: 'app:import-questions')]
class ImportQuestionsCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ParameterBagInterface $params
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $filePath = $this->params->get('kernel.project_dir') . '/public/otazky.csv';

        if (!file_exists($filePath)) {
            $output->writeln("<error>Soubor nebyl nalezen na cestě: $filePath</error>");
            return Command::FAILURE;
        }

        $csv = Reader::createFromPath($filePath, 'r');
        $csv->setHeaderOffset(0);

        $records = $csv->getRecords();
        $totalRows = count($csv);

        $progressBar = new ProgressBar($output, $totalRows);
        $progressBar->start();

        $batchSize = 20;
        $i = 0;

        foreach ($records as $row) {
            try {
                $q = new Question();
                $q->setTopic($row['topic'] ?? 'Neznámé');
                $q->setDifficulty((int)($row['difficulty'] ?? 1));
                $q->setText($row['text'] ?? '');

                $optionsRaw = $row['options'] ?? '[]';
                $options = json_decode($optionsRaw, true);

                if (json_last_error() !== JSON_ERROR_NONE || !is_array($options)) {
                    $output->writeln("\n<error>Chyba JSONu na řádku s otázkou: " . ($row['text'] ?? 'neznámý text') . "</error>");
                    $options = [];
                }

                $q->setOptions($options);
                $q->setCorrectIndex((int)($row['correct_index'] ?? 0));
                $q->setUsed(filter_var($row['used'] ?? false, FILTER_VALIDATE_BOOLEAN));

                $this->em->persist($q);

                if (($i % $batchSize) === 0) {
                    $this->em->flush();
                    $this->em->clear();
                }

                $i++;
                $progressBar->advance();

            } catch (\Exception $e) {
                $output->writeln("\n<error>Kritická chyba na řádku: " . $e->getMessage() . "</error>");
                continue;
            }
        }

        $this->em->flush();
        $progressBar->finish();

        $output->writeln("\n\n<info>Hotovo! Importováno $i otázek.</info>");
        return Command::SUCCESS;
    }
}