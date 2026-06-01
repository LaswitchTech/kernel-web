<?php

namespace App\Controllers;

use App\Core\Controller;

/**
 * Application dashboard — the main entry point for the application area.
 *
 * Shows summary cards for all app modules (Tasks, Chat, File Manager, Notifications)
 * with quick action links.
 */
class AppDashboardController extends Controller
{
    /**
     * GET /app — Application dashboard.
     */
    public function index(array $params = []): void
    {
        // Gather summary counts from each module.
        $taskCounts   = $this->getTaskCounts();
        $chatSummary  = $this->getChatSummary();
        $fileSummary  = $this->getFileSummary();
        $notifSummary = $this->getNotifSummary();

        // Pass data to the view.
        $taskOpen       = $taskCounts['open'] ?? 0;
        $taskOverdue    = $taskCounts['overdue'] ?? 0;
        $taskDueToday   = $taskCounts['dueToday'] ?? 0;
        $chatRooms      = $chatSummary['rooms'] ?? 0;
        $chatUnread     = $chatSummary['unread'] ?? 0;
        $fileRoots      = $fileSummary['roots'] ?? 0;
        $fileTotal      = $fileSummary['files'] ?? 0;
        $notifUnread    = $notifSummary['unread'] ?? 0;
        $notifTotal     = $notifSummary['total'] ?? 0;

        $pageTitle      = 'Application Dashboard';

        // Provide the global view context (needed by the layout's ViewGlobals call).
        $principal      = $this->container->get('principal');
        $config         = $this->container->get('config');
        $appName        = $config['name'] ?? 'Kernel-Web';

        ob_start();
        require __DIR__ . '/../Views/app/dashboard.php';
        $content        = ob_get_clean();

        require __DIR__ . '/../Views/layouts/app.php';
    }

    /**
     * Get task summary counts from TaskService.
     */
    private function getTaskCounts(): array
    {
        try {
            $service = $this->container->get('tasks.service');
            $user    = $this->container->get('auth')->user();

            if ($user === null) {
                return ['open' => 0, 'overdue' => 0, 'dueToday' => 0];
            }

            // Use TaskService methods to count tasks.
            $open = 0;
            $overdue = 0;
            $dueToday = 0;

            // Get tasks from the service.
            if (method_exists($service, 'getSummary')) {
                $summary = $service->getSummary();
                return [
                    'open'   => $summary['countOpen'] ?? 0,
                    'overdue' => $summary['countOverdue'] ?? 0,
                    'dueToday' => $summary['countDueToday'] ?? 0,
                ];
            }

            // Fallback: query directly.
            $db = $this->container->get('db');
            $userId = $user['id'] ?? 0;

            // Count my open tasks
            $open = (int) $db->fetchOne(
                "SELECT COUNT(*) FROM tasks WHERE assigned_type = 'user' AND assigned_id = ? AND status IN ('open', 'in_progress')",
                [$userId]
            )['COUNT(*)'] ?? 0;

            // Count my overdue tasks
            $overdue = (int) $db->fetchOne(
                "SELECT COUNT(*) FROM tasks WHERE assigned_type = 'user' AND assigned_id = ? AND due_at < NOW() AND status IN ('open', 'in_progress')",
                [$userId]
            )['COUNT(*)'] ?? 0;

            // Count my due-today tasks
            $dueToday = (int) $db->fetchOne(
                "SELECT COUNT(*) FROM tasks WHERE assigned_type = 'user' AND assigned_id = ? AND DATE(due_at) = CURDATE() AND status IN ('open', 'in_progress')",
                [$userId]
            )['COUNT(*)'] ?? 0;

            return ['open' => $open, 'overdue' => $overdue, 'dueToday' => $dueToday];

        } catch (\Throwable $e) {
            return ['open' => 0, 'overdue' => 0, 'dueToday' => 0];
        }
    }

    /**
     * Get chat summary.
     */
    private function getChatSummary(): array
    {
        try {
            // Get room count
            $db = $this->container->get('db');
            $roomCount = (int) ($db->fetchOne("SELECT COUNT(*) FROM chat_rooms")['COUNT(*)'] ?? 0);
            return ['rooms' => $roomCount, 'unread' => 0];
        } catch (\Throwable) {
            return ['rooms' => 0, 'unread' => 0];
        }
    }

    /**
     * Get file manager summary.
     */
    private function getFileSummary(): array
    {
        try {
            $db = $this->container->get('db');
            $rootCount = (int) ($db->fetchOne("SELECT COUNT(*) FROM files_roots")['COUNT(*)'] ?? 0);
            return ['roots' => $rootCount, 'files' => 0];
        } catch (\Throwable) {
            return ['roots' => 0, 'files' => 0];
        }
    }

    /**
     * Get notifications summary.
     */
    private function getNotifSummary(): array
    {
        try {
            $db = $this->container->get('db');
            $user = $this->container->get('auth')->user();
            $unread = 0;

            if ($user !== null) {
                $unread = (int) ($db->fetchOne(
                    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0",
                    [$user['id']]
                )['COUNT(*)'] ?? 0);
            }

            $total = (int) ($db->fetchOne("SELECT COUNT(*) FROM notifications")['COUNT(*)'] ?? 0);
            return ['unread' => $unread, 'total' => $total];
        } catch (\Throwable) {
            return ['unread' => 0, 'total' => 0];
        }
    }
}
