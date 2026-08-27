<?php

declare(strict_types=1);

namespace App\Controller;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

final class UserController extends AbstractController
{
    private const ACTIONS = ['block', 'unblock', 'delete', 'delete_unverified'];

    #[Route('/users', name: 'app_users', methods: ['GET'])]
    public function index(Connection $connection): Response
    {
        return $this->render('users/index.html.twig', [
            'users' => $connection->fetchAllAssociative('SELECT id, name, email, status, last_login_at FROM users ORDER BY last_login_at DESC NULLS LAST, id ASC'),
        ]);
    }

    #[Route('/users/actions', name: 'app_users_actions', methods: ['POST'])]
    public function actions(
        Request $request,
        Connection $connection,
        CsrfTokenManagerInterface $csrfTokenManager,
        LoggerInterface $logger,
    ): Response
    {
        $this->validateCsrf($request, $csrfTokenManager);
        $input = $this->readActionInput($request);
        if ($input === null) {
            return $this->invalidSelection();
        }

        [$action, $ids] = $input;
        try {
            if (!$this->usersExist($connection, $ids)) {
                return $this->invalidSelection('One or more selected users do not exist.');
            }

            $count = $this->executeAction($connection, $action, $ids);
        } catch (Exception $exception) {
            $logger->error('Bulk user action failed.', ['exception' => $exception, 'action' => $action]);

            return $this->operationFailed();
        }

        return $this->actionCompleted($count, $action);
    }

    private function validateCsrf(Request $request, CsrfTokenManagerInterface $csrfTokenManager): void
    {
        $token = new CsrfToken('bulk_user_action', (string) $request->request->get('_token'));
        if (!$csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Invalid CSRF token.');
        }
    }

    private function readActionInput(Request $request): ?array
    {
        // Nota bene: action names and IDs must be validated server-side before building SQL placeholders.
        $action = $request->request->get('action');
        $ids = $request->request->all('ids');
        if (!is_string($action) || !in_array($action, self::ACTIONS, true) || !is_array($ids) || $ids === []) {
            return null;
        }

        $validatedIds = $this->validateIds($ids);
        return $validatedIds === null ? null : [$action, $validatedIds];
    }

    private function validateIds(array $ids): ?array
    {
        $validatedIds = array_map($this->validateId(...), $ids);
        if (in_array(null, $validatedIds, true) || count(array_unique($validatedIds)) !== count($ids)) {
            return null;
        }

        return array_values($validatedIds);
    }

    private function validateId(mixed $id): ?int
    {
        if (is_int($id) && $id > 0) {
            return $id;
        }

        return is_string($id) && preg_match('/^[1-9][0-9]*$/', $id) ? (int) $id : null;
    }

    private function usersExist(Connection $connection, array $ids): bool
    {
        $placeholders = $this->placeholders($ids);

        return (int) $connection->fetchOne("SELECT COUNT(*) FROM users WHERE id IN ($placeholders)", $ids) === count($ids);
    }

    private function executeAction(Connection $connection, string $action, array $ids): int
    {
        $placeholders = $this->placeholders($ids);
        $sql = match ($action) {
            'block' => "UPDATE users SET status = 'BLOCKED' WHERE id IN ($placeholders)",
            'unblock' => "UPDATE users SET status = CASE WHEN verification_token IS NOT NULL THEN 'UNVERIFIED' ELSE 'ACTIVE' END WHERE status = 'BLOCKED' AND id IN ($placeholders)",
            'delete' => "DELETE FROM users WHERE id IN ($placeholders)",
            'delete_unverified' => "DELETE FROM users WHERE status = 'UNVERIFIED' AND id IN ($placeholders)",
        };

        return $connection->executeStatement($sql, $ids);
    }

    private function placeholders(array $ids): string
    {
        return implode(', ', array_fill(0, count($ids), '?'));
    }

    private function invalidSelection(string $message = 'Invalid bulk action or selection.'): Response
    {
        $this->addFlash('error', $message);

        return $this->redirectToRoute('app_users');
    }

    private function actionCompleted(int $count, string $action): Response
    {
        $labels = [
            'block' => 'blocked',
            'unblock' => 'unblocked',
            'delete' => 'deleted',
            'delete_unverified' => 'unverified users deleted',
        ];
        $this->addFlash('success', sprintf('%d user(s) %s.', $count, $labels[$action]));

        return $this->redirectToRoute('app_users');
    }

    private function operationFailed(): Response
    {
        $this->addFlash('error', 'The operation failed. Please try again.');

        return $this->redirectToRoute('app_users');
    }
}
