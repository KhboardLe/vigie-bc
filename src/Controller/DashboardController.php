<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractController
{
 /**
 * @Route("/dashboard", name="dashboard")
 */
public function index(SessionInterface $session, Connection $connection): Response
{
    if (!$session->has('user_id')) {
        return $this->redirectToRoute('app_login');
    }

$userId = $session->get('user_id');
    $alertsSent = 0;
    $totalBC = $connection->fetchOne("
    SELECT COUNT(*)
    FROM bons_commandes_complete");
    $activeKeywords = $connection->fetchOne("
    SELECT COUNT(*)
    FROM user_keywords
    WHERE user_id = :user
", [
    'user' => $session->get('user_id')
]);


// User filters
$categories = $connection->fetchFirstColumn("
    SELECT category
    FROM user_categories
    WHERE user_id = ?
", [$userId]);

$natures = $connection->fetchFirstColumn("
    SELECT nature
    FROM user_natures
    WHERE user_id = ?
", [$userId]);

$cities = $connection->fetchFirstColumn("
    SELECT city
    FROM user_cities
    WHERE user_id = ?
", [$userId]);

$keywords = $connection->fetchFirstColumn("
    SELECT keyword
    FROM user_keywords
    WHERE user_id = ?
", [$userId]);

$lastScan = $connection->fetchOne("
    SELECT MAX(scraped_at)
    FROM bons_commandes
");

$where = [];
$params = [];



if (!empty($categories)) {

    $placeholders = implode(',', array_fill(0, count($categories), '?'));

    $where[] = "category IN ($placeholders)";

    foreach ($categories as $category) {
        $params[] = $category;
    }

}

if (!empty($natures)) {

    $placeholders = implode(',', array_fill(0, count($natures), '?'));

    $where[] = "nature IN ($placeholders)";

    foreach ($natures as $nature) {
        $params[] = $nature;
    }

}

if (!empty($cities)) {

    $placeholders = implode(',', array_fill(0, count($cities), '?'));

    $where[] = "lieu IN ($placeholders)";

    foreach ($cities as $city) {
        $params[] = $city;
    }

}

if (!empty($keywords)) {

    $likes = [];


    foreach ($keywords as $keyword) {

       $likes[] = "(objet LIKE ? OR acheteur LIKE ? OR reference LIKE ?)";

$params[] = "%".$keyword."%";
$params[] = "%".$keyword."%";
$params[] = "%".$keyword."%";

    }

    $where[] = "(" . implode(" OR ", $likes) . ")";

}

$sql = "
    SELECT *
    FROM bons_commandes
";

if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
    ORDER BY scraped_at DESC
    LIMIT 5
";

$latestBCs = $connection->fetchAllAssociative(
    $sql,
    $params
);


$unreadAlerts = $connection->fetchOne("
    SELECT COUNT(*)
    FROM alerts
    WHERE user_id = ?
      AND is_read = 0
", [$userId]);

$connection->executeStatement("
    UPDATE alerts
    SET is_read = 1
    WHERE user_id = ?
", [$userId]);
// dd($categories, $natures, $cities, $keywords);


return $this->render('dashboard/index.html.twig', [
    'totalBC' => $totalBC,
    'alertsSent' => $alertsSent,
    'activeKeywords' => $activeKeywords,
    'lastScan' => $lastScan,
    'latestBCs' => $latestBCs,
    'unreadAlerts' => $unreadAlerts,
]);
}

    
}