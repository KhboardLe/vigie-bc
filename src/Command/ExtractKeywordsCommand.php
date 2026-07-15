<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:extract-keywords',
    description: 'Extract keyword options from existing BCs'
)]
class ExtractKeywordsCommand extends Command
{
    public function __construct(private Connection $connection)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $rows = $this->connection->fetchAllAssociative("
            SELECT objet, acheteur, lieu
            FROM bons_commandes_complete
        ");

        $stopWords = [
            'de', 'des', 'du', 'la', 'le', 'les', 'un', 'une', 'et', 'a', 'au', 'aux',
            'pour', 'dans', 'en', 'sur', 'par', 'avec', 'ou', 'd', 'l', 'à',
            'achat', 'acquisition', 'prestation', 'fourniture', 'fournitures',
            'travaux', 'service', 'services', 'commune', 'province', 'ministere',
            'delegue', 'regional', 'provincial'
        ];

        $counts = [];

        foreach ($rows as $row) {
            $text = mb_strtolower(
                ($row['objet'] ?? '') . ' ' .
                ($row['acheteur'] ?? '') . ' ' .
                ($row['lieu'] ?? ''),
                'UTF-8'
            );

            $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
            $words = preg_split('/\s+/u', $text);

            foreach ($words as $word) {
                $word = trim($word);

                if (mb_strlen($word, 'UTF-8') < 4) {
                    continue;
                }

                if (in_array($word, $stopWords)) {
                    continue;
                }

                $counts[$word] = ($counts[$word] ?? 0) + 1;
            }
        }

        arsort($counts);

        $inserted = 0;

        foreach ($counts as $keyword => $count) {
            if ($count < 3) {
                continue;
            }

            

            $result = $this->connection->executeStatement("
                INSERT IGNORE INTO keyword_options (keyword)
                VALUES (:keyword)
            ", [
                'keyword' => $keyword,
            ]);

            if ($result > 0) {
                $inserted++;
            }
        }

        $output->writeln("Keywords extracted: " . count($counts));
        $output->writeln("New keyword options inserted: " . $inserted);

        return Command::SUCCESS;
    }
}