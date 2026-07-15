<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class OnboardingKeywordController extends AbstractController
{
    #[Route('/choose-keywords', name: 'app_choose_keywords')]
    public function chooseKeywords(
        Request $request,
        Connection $connection,
        SessionInterface $session
    ): Response {

        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

if ($request->isMethod('POST')) {

    $keywords = $request->request->all('keywords');

if (!is_array($keywords)) {
    $keywords = [];
}

$keywords = array_unique(
    array_map('trim', $keywords)
);

    // Remove previous keywords
    $connection->executeStatement(
        "DELETE FROM user_keywords WHERE user_id = ?",
        [$userId]
    );

    foreach (array_unique($keywords) as $keyword) {

        $keyword = trim($keyword);

        if ($keyword === '') {
            continue;
        }

        // Save keyword for this user
        $connection->insert('user_keywords', [
            'user_id' => $userId,
            'keyword' => $keyword,
        ]);

        // Add to suggestions if new
        $exists = $connection->fetchOne("
            SELECT COUNT(*)
            FROM keyword_options
            WHERE LOWER(keyword)=LOWER(?)
        ", [$keyword]);

        if (!$exists) {

            $connection->insert('keyword_options', [
                'keyword' => $keyword,
            ]);

        }

    }

    return $this->redirectToRoute('dashboard');
}

        // A handful of suggested keywords — swap for whatever selection logic you prefer
        // (e.g. the ones linked to the topics/categories/nature already chosen).
        $keywords = $connection->fetchAllAssociative("
            SELECT *
            FROM keyword_options
            ORDER BY keyword ASC
        ");

        $selectedKeywords = $connection->fetchFirstColumn("
    SELECT keyword
    FROM user_keywords
    WHERE user_id = ?
", [$userId]);

        return $this->render('keywords_onboarding/choose.html.twig', [
            'keywords' => $keywords,
            'selectedKeywords' => $selectedKeywords,
        ]);
    }
}
