<?php
/**
 * Application dashboard — main entry point for the application area.
 *
 * Variables:
 *   $taskOpen     (int) — Open + in-progress tasks assigned to current user
 *   $taskOverdue   (int) — Overdue tasks assigned to current user
 *   $taskDueToday  (int) — Tasks due today assigned to current user
 *   $chatRooms     (int) — Total chat rooms
 *   $chatUnread    (int) — Unread messages for current user
 *   $fileRoots     (int) — Total file roots
 *   $fileTotal     (int) — Total files
 *   $notifUnread   (int) — Unread notifications for current user
 *   $notifTotal    (int) — Total notifications
 */
?>

<div class="container-fluid">
    <!-- ── Version bar ──────────────────────────────── -->
    <div class="card mb-4">
        <div class="card-body py-2 px-3">
            <div class="d-flex flex-wrap align-items-center gap-3">
                <small class="text-muted">
                    <i class="bi bi-app-indicator"></i> Application Dashboard
                </small>
                <span class="badge bg-success">All systems operational</span>
            </div>
        </div>
    </div>

    <!-- ── Module summary cards ─────────────────────── -->
    <div class="row g-4 mb-4">

        <!-- Tasks -->
        <div class="col-sm-6 col-xl-3">
            <a href="/tasks" class="text-decoration-none">
                <div class="card h-100 <?= $taskOpen > 0 ? 'border-primary border-top-0' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded p-3 bg-primary bg-opacity-10 text-primary flex-shrink-0">
                            <i class="bi bi-check2-square fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">Tasks</div>
                            <div class="fw-semibold fs-5">
                                <?= $taskOpen ?>
                                <?php if ($taskOverdue > 0): ?>
                                    <span class="text-danger ms-1" style="font-size:0.75em;">
                                        (<?= $taskOverdue ?> overdue)
                                    </span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= $taskDueToday > 0 ? $taskDueToday . ' due today' : 'No tasks due today' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Chat -->
        <div class="col-sm-6 col-xl-3">
            <a href="/chat" class="text-decoration-none">
                <div class="card h-100 <?= $chatRooms > 0 ? 'border-info border-top-0' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded p-3 bg-info bg-opacity-10 text-info flex-shrink-0">
                            <i class="bi bi-chat-dots fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">Chat</div>
                            <div class="fw-semibold fs-5"><?= $chatRooms ?> <span class="text-muted fw-normal" style="font-size:0.75em;">rooms</span></div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= $chatUnread > 0 ? $chatUnread . ' unread messages' : 'No unread messages' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- File Manager -->
        <div class="col-sm-6 col-xl-3">
            <a href="/files" class="text-decoration-none">
                <div class="card h-100 <?= $fileRoots > 0 ? 'border-success border-top-0' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded p-3 bg-success bg-opacity-10 text-success flex-shrink-0">
                            <i class="bi bi-folder2-open fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">File Manager</div>
                            <div class="fw-semibold fs-5"><?= $fileRoots ?> <span class="text-muted fw-normal" style="font-size:0.75em;">roots</span></div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= $fileTotal > 0 ? $fileTotal . ' files' : 'No files yet' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Notifications -->
        <div class="col-sm-6 col-xl-3">
            <a href="/notifications" class="text-decoration-none">
                <div class="card h-100 <?= $notifUnread > 0 ? 'border-warning border-top-0' : '' ?>">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="rounded p-3 bg-warning bg-opacity-10 text-warning flex-shrink-0">
                            <i class="bi bi-bell fs-4"></i>
                        </div>
                        <div class="min-w-0">
                            <div class="text-muted small">Notifications</div>
                            <div class="fw-semibold fs-5">
                                <?= $notifUnread ?>
                                <?php if ($notifTotal > 0): ?>
                                    <span class="text-muted fw-normal" style="font-size:0.75em;">/<?= $notifTotal ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="text-muted" style="font-size:.75rem;">
                                <?= $notifUnread > 0 ? 'unread' : 'All caught up' ?>
                            </div>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    <!-- ── Quick navigation ─────────────────────────── -->
    <div class="row g-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <span class="fw-semibold small">Module Quick Access</span>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">

                        <!-- Tasks -->
                        <a href="/tasks" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-4 py-3">
                            <div class="rounded p-2 bg-primary bg-opacity-10 text-primary flex-shrink-0">
                                <i class="bi bi-check2-square"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold small">Tasks</div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    Create, assign, track, and link follow-up tasks to any domain entity
                                </div>
                            </div>
                            <div class="text-muted" style="font-size:.75rem; white-space:nowrap;">
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </a>

                        <!-- Chat -->
                        <a href="/chat" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-4 py-3">
                            <div class="rounded p-2 bg-info bg-opacity-10 text-info flex-shrink-0">
                                <i class="bi bi-chat-dots"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold small">Chat</div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    Create rooms and send real-time messages
                                </div>
                            </div>
                            <div class="text-muted" style="font-size:.75rem; white-space:nowrap;">
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </a>

                        <!-- File Manager -->
                        <a href="/files" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-4 py-3">
                            <div class="rounded p-2 bg-success bg-opacity-10 text-success flex-shrink-0">
                                <i class="bi bi-folder2-open"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold small">File Manager</div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    Browse, upload, preview, and manage files
                                </div>
                            </div>
                            <div class="text-muted" style="font-size:.75rem; white-space:nowrap;">
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </a>

                        <!-- Notifications -->
                        <a href="/notifications" class="list-group-item list-group-item-action d-flex align-items-center gap-3 px-4 py-3">
                            <div class="rounded p-2 bg-warning bg-opacity-10 text-warning flex-shrink-0">
                                <i class="bi bi-bell"></i>
                            </div>
                            <div class="min-w-0 flex-grow-1">
                                <div class="fw-semibold small">Notifications</div>
                                <div class="text-muted" style="font-size:.75rem;">
                                    View your notification inbox and manage preferences
                                </div>
                            </div>
                            <div class="text-muted" style="font-size:.75rem; white-space:nowrap;">
                                <i class="bi bi-chevron-right"></i>
                            </div>
                        </a>

                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
