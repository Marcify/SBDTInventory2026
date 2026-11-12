<?php
// Expected (optional) variables: $page_title, $active_nav.
// $user comes from current_user(); role determines the sidebar.
$user = current_user();
$page_title = $page_title ?? 'SBDT Inventory';
$active_nav = $active_nav ?? '';
$displayName = $user['name'] ?? $user['username'] ?? '';
$initial = strtoupper(mb_substr($displayName, 0, 1));
$homeHref = ($user['role'] ?? '') === 'admin'
    ? '/SBDTInventory/admin/dashboard.php'
    : '/SBDTInventory/dancer/dashboard.php';
$hasNotice = (($_GET['pw'] ?? '') === 'changed') || isset($_GET['pw_error']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#841818">
    <title><?= htmlspecialchars($page_title) ?> - SBDT Inventory</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <link rel="stylesheet" href="/SBDTInventory/assets/css/styles.css">
</head>
<body>
    <nav class="navbar app-navbar sticky-top">
        <div class="container-fluid px-3 px-lg-4">
            <div class="d-flex align-items-center gap-2 gap-sm-3">
                <button class="btn btn-maroon d-lg-none" type="button"
                        data-bs-toggle="offcanvas" data-bs-target="#appSidebar"
                        aria-controls="appSidebar" aria-label="Open menu">
                    <i class="bi bi-list" aria-hidden="true"></i>
                </button>
                <a class="navbar-brand d-flex align-items-center gap-2 mb-0" href="<?= htmlspecialchars($homeHref) ?>">
                    <img class="logo" src="/SBDTInventory/assets/images/sbdt-logo.png" alt="SBDT">
                    <span class="brand d-none d-md-inline">Inventory Management System</span>
                    <span class="brand d-md-none">SBDT</span>
                </a>
            </div>

            <div class="header-actions d-flex align-items-center gap-2">
                <div class="dropdown">
                    <button class="btn user-chip dropdown-toggle" type="button"
                            data-bs-toggle="dropdown" aria-expanded="false"
                            title="Logged in as <?= htmlspecialchars($displayName) ?>">
                        <span class="user-avatar"><?= htmlspecialchars($initial) ?></span>
                        <span class="user-name d-none d-md-inline"><?= htmlspecialchars($displayName) ?></span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                        <li class="px-3 py-2">
                            <div class="fw-bold text-maroon"><?= htmlspecialchars($displayName) ?></div>
                            <div class="small text-muted"><?= htmlspecialchars($user['username'] ?? '') ?></div>
                            <span class="badge bg-gold text-dark mt-2"><?= htmlspecialchars(ucfirst((string)($user['role'] ?? ''))) ?></span>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item" data-open-pw>
                                <i class="bi bi-key me-2 text-gold" aria-hidden="true"></i>Change Password
                            </button>
                        </li>
                        <li>
                            <form action="/SBDTInventory/logout.php" method="POST">
                                <?= csrf_field() ?>
                                <button type="submit" class="dropdown-item text-maroon">
                                    <i class="bi bi-box-arrow-right me-2" aria-hidden="true"></i>Log out
                                </button>
                            </form>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </nav>

    <div class="modal fade" id="pw-modal" tabindex="-1" aria-labelledby="pw-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h2 class="modal-title h5" id="pw-modal-title">Change Password</h2>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-close-pw aria-label="Close"></button>
                </div>
                <form action="/SBDTInventory/change_password.php" method="POST">
                    <div class="modal-body">
                        <?= csrf_field() ?>
                        <div class="mb-3 form-group">
                            <label class="form-label" for="pw-current">Current password</label>
                            <input type="password" class="form-control" id="pw-current" name="current_password" autocomplete="current-password" required>
                        </div>
                        <div class="mb-3 form-group">
                            <label class="form-label" for="pw-new">New password</label>
                            <input type="password" class="form-control" id="pw-new" name="new_password" minlength="8" maxlength="72" autocomplete="new-password" required>
                        </div>
                        <div class="mb-0 form-group">
                            <label class="form-label" for="pw-confirm">Confirm new password</label>
                            <input type="password" class="form-control" id="pw-confirm" name="confirm_password" autocomplete="new-password" required>
                        </div>
                    </div>
                    <div class="modal-footer password-modal-actions">
                        <button type="button" class="btn btn-outline-maroon cancel-btn" data-bs-dismiss="modal" data-close-pw>Cancel</button>
                        <button type="submit" class="btn btn-maroon save-btn">Save</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="app-shell">
        <aside class="app-sidebar app-nav d-none d-lg-flex flex-column">
            <?php require __DIR__ . '/sidebar_nav.php'; ?>
        </aside>

        <div class="offcanvas offcanvas-start app-offcanvas app-nav" tabindex="-1" id="appSidebar" aria-labelledby="appSidebarLabel">
            <div class="offcanvas-header">
                <h2 class="offcanvas-title h5" id="appSidebarLabel">Menu</h2>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="offcanvas" aria-label="Close"></button>
            </div>
            <div class="offcanvas-body p-0">
                <?php require __DIR__ . '/sidebar_nav.php'; ?>
            </div>
        </div>

        <main class="app-main main">
        <?php if (($_GET['pw'] ?? '') === 'changed'): ?>
            <div class="alert alert-success alert-dismissible fade show message success" role="alert">
                Password updated successfully.
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php elseif (isset($_GET['pw_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show message error" role="alert">
                <?= htmlspecialchars($_GET['pw_error']) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
