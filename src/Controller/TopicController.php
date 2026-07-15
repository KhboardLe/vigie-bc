<?php

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;

class TopicController extends AbstractController
{
    #[Route('/choose-topics', name: 'app_choose_topics')]
    public function chooseTopics(
        Request $request,
        Connection $connection,
        SessionInterface $session
    ): Response {

        $userId = $session->get('user_id');

        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        if ($request->isMethod('POST')) {

            // User clicked "Skip"
            if ($request->request->has('skip')) {

                // CHANGED: topics is step 1 of 5 now, so the next step is Catégories, not Villes.
                return $this->redirectToRoute('app_choose_categories');
            }

            $topics = $request->request->all('topics');

if (!is_array($topics)) {
    $topics = [];
}

            // Remove previous choices
            $connection->executeStatement(
                "DELETE FROM user_topics WHERE user_id = ?",
                [$userId]
            );

            // Save new choices
            foreach ($topics as $topicId) {

                $connection->insert('user_topics', [
                    'user_id' => $userId,
                    'topic_id' => $topicId,
                ]);
            }

            // CHANGED: same fix as the skip branch above.
            if ($request->query->get('profile')) {
    return $this->redirectToRoute('app_profile');
}

return $this->redirectToRoute('app_choose_categories');
        }

        // Load available topics
    $topics = $connection->fetchAllAssociative("
    SELECT *
    FROM topic_options
    WHERE id NOT IN (
        SELECT topic_id
        FROM user_topics
        WHERE user_id = ?
    )
    ORDER BY name ASC
", [$userId]);


        // Load already selected topics (Profile page support)
        $selectedTopics = $connection->fetchFirstColumn("
            SELECT topic_id
            FROM user_topics
            WHERE user_id = ?
        ", [$userId]);

        return $this->render('topics/choose.html.twig', [
            'topics' => $topics,
            'selectedTopics' => $selectedTopics,
        ]);
    }
}
