<?php
declare(strict_types=1);

/**
 * Shared pagination helpers for list pages.
 *
 * Usage:
 *   require_once __DIR__ . '/.../includes/pagination.php';
 *   $totalRows = (int)$pdo->query("SELECT COUNT(*) ...")->fetchColumn();
 *   [$page, $perPage, $offset, $totalPages] = pagination_init($totalRows, 20);
 *   // ... main query with LIMIT ? OFFSET ? ...
 *   render_pagination($page, $perPage, $totalRows, $totalPages);
 */

/**
 * Resolve the current page from the query string and compute bounds.
 * Returns [$page, $perPage, $offset, $totalPages].
 */
function pagination_init(int $totalRows, int $defaultPerPage = 20): array {
    $perPage = max(1, $defaultPerPage);

    $page = filter_var(
        $_GET['page'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );

    if ($page === false) {
        $page = 1;
    }

    $totalPages = max(1, (int)ceil($totalRows / $perPage));

    if ($page > $totalPages) {
        $page = $totalPages;
    }

    $offset = ($page - 1) * $perPage;

    return [$page, $perPage, $offset, $totalPages];
}

/**
 * Build a link to a given page, preserving all other query parameters.
 */
function pagination_link(int $page): string {
    $params = $_GET;
    $params['page'] = $page;

    return '?' . http_build_query($params);
}

/**
 * Render the "Showing X–Y of Z" summary and page controls.
 * Uses the .pagination styles from the shared stylesheet.
 */
function render_pagination(int $page, int $perPage, int $totalRows, int $totalPages): void {
    if ($totalPages <= 1) {
        return; // Nothing to page through.
    }

    $from = $totalRows === 0 ? 0 : ($page - 1) * $perPage + 1;
    $to = min($page * $perPage, $totalRows);

    // Decide which page numbers to show.
    if ($totalPages <= 9) {
        $pagesToShow = range(1, $totalPages);
    } else {
        $pagesToShow = array_unique(array_merge(
            [1, 2],
            range(max(1, $page - 2), min($totalPages, $page + 2)),
            [$totalPages - 1, $totalPages]
        ));
        sort($pagesToShow);
    }

    echo '<div class="pagination">';
    echo '<p class="pagination-summary">Showing ' . $from . '&ndash;' . $to
        . ' of ' . $totalRows . '</p>';
    echo '<div class="pagination-controls">';

    if ($page > 1) {
        echo '<a class="page-link" href="' . htmlspecialchars(pagination_link($page - 1))
            . '">&laquo; Prev</a>';
    }

    $previous = 0;

    foreach ($pagesToShow as $p) {
        // Insert an ellipsis wherever numbers were skipped.
        if ($previous !== 0 && $p - $previous > 1) {
            echo '<span class="pagination-ellipsis">&hellip;</span>';
        }

        if ($p === $page) {
            echo '<button type="button" class="is-current">' . $p . '</button>';
        } else {
            echo '<a class="page-link" href="' . htmlspecialchars(pagination_link($p))
                . '">' . $p . '</a>';
        }

        $previous = $p;
    }

    if ($page < $totalPages) {
        echo '<a class="page-link" href="' . htmlspecialchars(pagination_link($page + 1))
            . '">Next &raquo;</a>';
    }

    echo '</div>';
    echo '</div>';
}
