<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class BcController extends AbstractController
{
    #[Route('/bc', name: 'app_bc_list')]
    public function index(
    Request $request,
    SessionInterface $session,
    Connection $connection
): Response {
        if (!$session->has('user_id')) {
            return $this->redirectToRoute('app_login');
        }

        $userId = $session->get('user_id');
   


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

$where = [];
$params = [];

// Category
if (!empty($categories)) {

    $placeholders = implode(',', array_fill(0, count($categories), '?'));

    $where[] = "category IN ($placeholders)";

    $params = array_merge($params, $categories);

}

// City
if (!empty($cities)) {

    $placeholders = implode(',', array_fill(0, count($cities), '?'));

    $where[] = "lieu IN ($placeholders)";

    $params = array_merge($params, $cities);


}
// Nature
if (!empty($natures)) {

    $placeholders = implode(',', array_fill(0, count($natures), '?'));

    $where[] = "nature IN ($placeholders)";

    $params = array_merge($params, $natures);

}

// Keywords
if (!empty($keywords)) {

    $likes = [];

    foreach ($keywords as $keyword) {

        $likes[] = "LOWER(objet) LIKE ?";

        $params[] = "%" . strtolower($keyword) . "%";

    }

    $where[] = "(" . implode(" OR ", $likes) . ")";

}


// dd(
//     $categories,
//     $natures,
//     $cities,
//     $keywords
// );

$page = max(1, (int) $request->query->get('page', 1));

$perPage = 10;

$offset = ($page - 1) * $perPage;

$countSql = "
    SELECT COUNT(*)
    FROM bons_commandes
";

if (!empty($where)) {
    $countSql .= " WHERE " . implode(" AND ", $where);
}

$total = $connection->fetchOne(
    $countSql,
    $params
);

$sql = "
    SELECT *
    FROM bons_commandes
";
if (!empty($where)) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= "
    ORDER BY scraped_at DESC
    LIMIT $perPage
    OFFSET $offset
";


$bcs = $connection->fetchAllAssociative(
    $sql,
    $params
);


$totalPages = max(1, ceil($total / $perPage));

return $this->render('bc/index.html.twig', [
    'bcs' => $bcs,

    'page' => $page,

    'totalPages' => $totalPages,

    'perPage' => $perPage,

    'total' => $total

]);

    }}