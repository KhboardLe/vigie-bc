<?php

namespace App\Command;

use Doctrine\DBAL\Connection;
use DOMDocument;
use DOMXPath;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:scrape-bc',
    description: 'Scrape bons de commande from marchespublics.gov.ma'
)]
class ScrapeBcCommand extends Command
{
   private Connection $connection;
private MailerInterface $mailer;

    public function __construct(
    Connection $connection,
    MailerInterface $mailer
)
{
    parent::__construct();

    $this->connection = $connection;
    $this->mailer = $mailer;
}

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        set_time_limit(0);
        ini_set('memory_limit', '-1');

        $totalScraped = 0;
        $totalInserted = 0;
        $totalUpdated = 0;

        for ($page = 1; $page <= 1; $page++) {
            sleep(1);

            $output->writeln("Scraping page $page");

            $html = $this->fetchPage($page);

      
            // file_put_contents("page{$page}.html", $html);


            if (!$html || trim($html) === '') {
                $output->writeln("Failed to load page $page");
                continue;
            }

            $dom = new DOMDocument();

            libxml_use_internal_errors(true);
            $dom->loadHTML($html);
            libxml_clear_errors();

            $xpath = new DOMXPath($dom);

            $cards = $xpath->query("//div[contains(@class, 'entreprise__card')]");

            $output->writeln("Cards found: " . $cards->length);

            foreach ($cards as $card) {

            
                $bc = $this->extractCard($card, $xpath);
                $bc['category'] = $this->extractCategory($bc['detail_url']);
                $bc['nature'] = $this->extractNature($bc['detail_url']);
                
                $this->saveCategoryOption($bc['category']);
$this->saveNatureOption($bc['nature']);
$this->saveCityOption($bc['lieu']);

                if (!$bc['reference'] || !$bc['objet']) {
                    continue;
                }

                if (!$bc['detail_url']) {
    continue;
}

                $sourceKey = md5(
                    $bc['reference'] . '|' .
                    $bc['objet'] . '|' .
                    $bc['date_limite']
                );

// $output->writeln(
//     $bc['reference'] . " -> " . ($bc['category'] ?? 'NULL')
// );

$output->writeln(
    sprintf(
        "%s | %s | %s",
        $bc['reference'],
        $bc['category'] ?? 'NULL',
        $bc['nature'] ?? 'NULL'
    )
);


               $inserted = $this->connection->executeStatement("
    INSERT INTO bons_commandes
    (
        reference,
        statut,
        objet,
        acheteur,
        date_limite,
        heure,
        lieu,
        detail_url,
        source_key,
        scraped_at,
        created_at,
        category,
        nature
    )
    VALUES
    (
        :reference,
        :statut,
        :objet,
        :acheteur,
        :date_limite,
        :heure,
        :lieu,
        :detail_url,
        :source_key,
        NOW(),
        NOW(),
        :category,
        :nature
    )
    ON DUPLICATE KEY UPDATE
        statut = VALUES(statut),
        objet = VALUES(objet),
        acheteur = VALUES(acheteur),
        date_limite = VALUES(date_limite),
        heure = VALUES(heure),
        lieu = VALUES(lieu),
        detail_url = VALUES(detail_url),
        scraped_at = NOW(),
        category = VALUES(category),
        nature = VALUES(nature)

", [
    'reference' => $bc['reference'],
    'statut' => $bc['statut'],
    'objet' => $bc['objet'],
    'acheteur' => $bc['acheteur'],
    'date_limite' => $bc['date_limite'],
    'heure' => $bc['heure'],
    'lieu' => $bc['lieu'],
    'detail_url' => $bc['detail_url'],
    'source_key' => $sourceKey,
    'category' => $bc['category'],
    'nature'   => $bc['nature']

]);

                if ($inserted === 1) {
    $totalInserted++;
    $this->createAlerts($bc);
} elseif ($inserted === 2) {
    $totalUpdated++;
}

                $totalScraped++;
            }
        }

        $output->writeln("Total scraped: $totalScraped");
$output->writeln("New BCs inserted: $totalInserted");
$output->writeln("Existing BCs updated: $totalUpdated");

        return Command::SUCCESS;
    }

    private function fetchPage(int $page): string|false
    {
      $url = "https://www.marchespublics.gov.ma/bdc/entreprise/consultation/"
    . "?search_consultation_entreprise%5Bkeyword%5D="
    . "&search_consultation_entreprise%5Breference%5D="
    . "&search_consultation_entreprise%5Bobjet%5D="
    . "&search_consultation_entreprise%5BdateLimiteStart%5D="
    . "&search_consultation_entreprise%5BdateLimiteEnd%5D="
    . "&search_consultation_entreprise%5BdateMiseEnLigneStart%5D="
    . "&search_consultation_entreprise%5BdateMiseEnLigneEnd%5D="
    . "&search_consultation_entreprise%5Bcategorie%5D="
    . "&search_consultation_entreprise%5BnaturePrestation%5D="
    . "&search_consultation_entreprise%5Bacheteur%5D="
    . "&search_consultation_entreprise%5Bservice%5D="
    . "&search_consultation_entreprise%5BlieuExecution%5D="
    . "&search_consultation_entreprise%5BpageSize%5D=50"
    . "&page=" . $page;
        $ch = curl_init($url);

        $output = $url;
echo $output . PHP_EOL;

        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);

        $html = curl_exec($ch);

        curl_close($ch);

        return $html;
    }

   private function extractCard($card, DOMXPath $xpath): array
{
    $text = $this->cleanText($card->textContent);

    $bc = [
        'reference' => null,
        'statut' => null,
        'objet' => null,
        'acheteur' => null,
        'date_limite' => null,
        'heure' => null,
        'lieu' => null,
        'detail_url' => null,
        'category' => null,
        'nature' => null
    ];

    if (preg_match('/Référence\s*:\s*(.+?)(Annulé|Publié|En cours|Objet\s*:)/u', $text, $m)) {
        $bc['reference'] = trim($m[1]);
    }

    if (preg_match('/(Annulé|Publié|En cours)/u', $text, $m)) {
        $bc['statut'] = trim($m[1]);
    }

    if (preg_match('/Objet\s*:\s*(.+?)\s+Acheteur\s*:/u', $text, $m)) {
        $bc['objet'] = trim($m[1]);
    }

    if (preg_match('/Acheteur\s*:\s*(.+?)\s+Date limite de remise des devis/u', $text, $m)) {
        $bc['acheteur'] = trim($m[1]);
    }

    if (preg_match('/Date limite de remise des devis\s+(\d{2}\/\d{2}\/\d{4})\s+(\d{2}:\d{2})/u', $text, $m)) {
        $bc['date_limite'] = $m[1];
        $bc['heure'] = $m[2];
    }

    if (preg_match('/Lieu d\'exécution\s+(.+)$/u', $text, $m)) {
        $bc['lieu'] = trim($m[1]);
    }

  $links = $xpath->query(".//a", $card);

//  foreach ($links as $link) {
//     echo "LINK FOUND: " .
//           $link->getAttribute('href') .
//           PHP_EOL;}
 

    if ($links->length > 0) {
        $href = $links->item(0)->getAttribute('href');

        $bc['detail_url'] = str_starts_with($href, 'http')
            ? $href
            : 'https://www.marchespublics.gov.ma' . $href;
    }

    return $bc;
}

private function extractCategory(string $url): ?string
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        return null;
    }

    $dom = new DOMDocument();

    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $node = $xpath->query(
        "//div[@class='d-flex flex-column'][span[contains(., 'Catégorie principale')]]/span[2]"
    );

    if ($node->length > 0) {
        return trim($node->item(0)->textContent);
    }

    return null;
}

private function extractNature(string $url): ?string
{
    $ch = curl_init($url);

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $html = curl_exec($ch);
    curl_close($ch);

    if (!$html) {
        return null;
    }

    $dom = new DOMDocument();

    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();

    $xpath = new DOMXPath($dom);

    $node = $xpath->query(
        "//div[@class='d-flex flex-column'][span[contains(., 'Nature de prestation')]]/span[2]"
    );

    if ($node->length > 0) {
        return trim($node->item(0)->textContent);
    }

    return null;
}

// private function extractCategory(string $url): ?string
// {
//     $ch = curl_init($url);

// curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
// curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
// curl_setopt($ch, CURLOPT_TIMEOUT, 30);

// $html = curl_exec($ch);
// file_put_contents("detail.html", $html);
// curl_close($ch);

//     if (!$html) {
//         return null;
//     }

//     $dom = new DOMDocument();

//     libxml_use_internal_errors(true);
//     $dom->loadHTML($html);
//     libxml_clear_errors();

//     $xpath = new DOMXPath($dom);

// // $category = $xpath->query(
// //     "//span[contains(text(),'Catégorie principale')]/following-sibling::span[1]"
// // );
// $category = $xpath->query(
//     "//span[contains(normalize-space(.), 'Catégorie principale')]/following-sibling::span[1]"
// );

// if ($category->length > 0) {

// $output = trim($category->item(0)->textContent);
// echo "CATEGORY FOUND: " . $output . PHP_EOL;
//     return trim($category->item(0)->textContent);
// }

// return null;


// /* 
//     $labels = $xpath->query("//span[contains(text(),'Catégorie principale')]");

//     if ($labels->length > 0) {

//         $parent = $labels->item(0)->parentNode;

//         foreach ($parent->childNodes as $child) {

//             if (
//                 $child->nodeName === 'span' &&
//                 trim($child->textContent) !== 'Catégorie principale'
//             ) {
//                 return trim($child->textContent);
//             }
//         }
//     } */

   
// }



private function saveCategoryOption(?string $category): void
{
    if (!$category) {
        return;
    }

    $exists = $this->connection->fetchOne("
        SELECT id
        FROM category_options
        WHERE name = ?
    ", [$category]);

    if (!$exists) {
        $this->connection->insert('category_options', [
            'name' => $category
        ]);
    }
}

private function saveNatureOption(?string $nature): void
{
    if (!$nature) {
        return;
    }

    $exists = $this->connection->fetchOne("
        SELECT id
        FROM nature_options
        WHERE name = ?
    ", [$nature]);

    if (!$exists) {
        $this->connection->insert('nature_options', [
            'name' => $nature
        ]);
    }
}

private function saveCityOption(?string $city): void
{
    if (!$city) {
        return;
    }

    $exists = $this->connection->fetchOne("
        SELECT id
        FROM city_options
        WHERE name = ?
    ", [$city]);

    if (!$exists) {
        $this->connection->insert('city_options', [
            'name' => $city
        ]);
    }
}

private function createAlerts(array $bc): void
{

$bcId = $this->connection->fetchOne("
    SELECT id
    FROM bons_commandes
    WHERE source_key = ?
", [
    md5(
        $bc['reference'] . '|' .
        $bc['objet'] . '|' .
        $bc['date_limite']
    )
]);

if (!$bcId) {
    return;
}

$users = $this->connection->fetchAllAssociative("
    SELECT id, email
    FROM users
");

foreach ($users as $user) {

    $userId = $user['id'];


$categories = $this->connection->fetchFirstColumn("
    SELECT category
    FROM user_categories
    WHERE user_id = ?
", [$userId]);

$natures = $this->connection->fetchFirstColumn("
    SELECT nature
    FROM user_natures
    WHERE user_id = ?
", [$userId]);

$cities = $this->connection->fetchFirstColumn("
    SELECT city
    FROM user_cities
    WHERE user_id = ?
", [$userId]);

$keywords = $this->connection->fetchFirstColumn("
    SELECT keyword
    FROM user_keywords
    WHERE user_id = ?
", [$userId]);

if (
    empty($categories) &&
    empty($natures) &&
    empty($cities) &&
    empty($keywords)
) {
    continue;
}

$matches = true;

if (!empty($categories) && !in_array($bc['category'], $categories)) {
    $matches = false;
}

if (!empty($natures) && !in_array($bc['nature'], $natures)) {
    $matches = false;
}

if (!empty($cities) && !in_array($bc['lieu'], $cities)) {
    $matches = false;
}

if (!empty($keywords)) {

   $text = strtolower(
    ($bc['objet'] ?? '') . ' ' .
    ($bc['nature'] ?? '') . ' ' .
    ($bc['category'] ?? '') . ' ' .
    ($bc['lieu'] ?? '') . ' ' .
    ($bc['acheteur'] ?? '')
);

foreach ($keywords as $keyword) {

    if (strpos($text, strtolower($keyword)) === false) {
        $matches = false;
        break;
    }

}

}
    if ($matches) {

    $exists = $this->connection->fetchOne("
    SELECT id
    FROM alerts
    WHERE user_id = ?
      AND bc_id = ?
", [
    $userId,
    $bcId
]);

if ($exists) {
    continue;
}

   $this->connection->insert('alerts', [

   

    'user_id' => $userId,

    'bc_id' => $bcId,

    'keyword' => !empty($keywords)
        ? implode(', ', $keywords)
        : null,

    'is_read' => 0,

    'created_at' => date('Y-m-d H:i:s')

]);

$link = $bc['detail_url'] ?? '#';
 $email = (new Email())
    ->from('alerts@vigiebc.test')
    ->to($user['email'])
    ->subject('🔔 Nouveau Bon de Commande détecté')

    ->html("
        <h2>Nouveau Bon de Commande détecté</h2>

        <p><strong>Référence :</strong> {$bc['reference']}</p>

        <p><strong>Objet :</strong><br>{$bc['objet']}</p>

        <p><strong>Catégorie :</strong> {$bc['category']}</p>

        <p><strong>Nature :</strong> {$bc['nature']}</p>

        <p><strong>Ville :</strong> {$bc['lieu']}</p>

        <p><strong>Date limite :</strong> {$bc['date_limite']}</p>

       <p>
            <a href='{$link}'>Voir le Bon de Commande</a>
        </p>
    ");

try {

    $this->mailer->send($email);

} catch (\Throwable $e) {

    // Ignore email errors.
    // The alert is already saved in the database.

}

// $alertId = $this->connection->lastInsertId();

}
}

}



private function cleanText(string $text): string
{
    return trim(preg_replace('/\s+/', ' ', $text));
}
}

