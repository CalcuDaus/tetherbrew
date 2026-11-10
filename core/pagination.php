<?php
/**
 * Pagination helper
 */

/**
 * Paginate a query result
 * 
 * @param PDO $pdo
 * @param string $query Base query WITHOUT LIMIT/OFFSET (must have WHERE if needed)
 * @param array $params Parameters for the query
 * @param int $perPage Items per page
 * @param int $currentPage Current page number
 * @return array ['data' => [...], 'total' => int, 'current_page' => int, 'last_page' => int, 'per_page' => int]
 */
function paginate($pdo, $query, $params = [], $perPage = 15, $currentPage = null) {
    if ($currentPage === null) {
        $currentPage = max(1, (int)($_GET['page'] ?? 1));
    }
    
    // Count total rows
    $queryWithoutOrder = preg_replace('/\s+ORDER\s+BY\s+[^)]+$/i', '', $query);
    $countQuery = "SELECT COUNT(*) as total FROM (" . $queryWithoutOrder . ") as count_table";
    $countStmt = $pdo->prepare($countQuery);
    $countStmt->execute($params);
    $total = (int)$countStmt->fetch()['total'];
    
    $lastPage = max(1, ceil($total / $perPage));
    $currentPage = min($currentPage, $lastPage);
    $offset = ($currentPage - 1) * $perPage;
    
    // Fetch paginated data
    $dataQuery = $query . " LIMIT {$perPage} OFFSET {$offset}";
    $dataStmt = $pdo->prepare($dataQuery);
    $dataStmt->execute($params);
    $data = $dataStmt->fetchAll();
    
    return [
        'data' => $data,
        'total' => $total,
        'current_page' => $currentPage,
        'last_page' => $lastPage,
        'per_page' => $perPage,
        'from' => $total > 0 ? $offset + 1 : 0,
        'to' => min($offset + $perPage, $total),
    ];
}

/**
 * Render pagination links as HTML
 */
function render_pagination($pagination, $baseUrl = '') {
    if ($pagination['last_page'] <= 1) return '';
    
    // Preserve existing query params
    $queryParams = $_GET;
    unset($queryParams['page']);
    $queryString = http_build_query($queryParams);
    $separator = $queryString ? '&' : '';
    $baseUrl = e($baseUrl ?: strtok($_SERVER['REQUEST_URI'], '?'));
    $prefix = $baseUrl . ($queryString ? '?' . $queryString . '&' : '?');
    
    $html = '<div class="pagination">';
    $html .= '<div class="pagination-info">Menampilkan ' . $pagination['from'] . '-' . $pagination['to'] . ' dari ' . $pagination['total'] . '</div>';
    $html .= '<div class="pagination-links">';
    
    $current = $pagination['current_page'];
    $last = $pagination['last_page'];
    
    // Previous
    if ($current > 1) {
        $html .= '<a href="' . $prefix . 'page=' . ($current - 1) . '" class="pagination-link">‹ Prev</a>';
    } else {
        $html .= '<span class="pagination-link disabled">‹ Prev</span>';
    }
    
    // Page numbers
    $start = max(1, $current - 2);
    $end = min($last, $current + 2);
    
    if ($start > 1) {
        $html .= '<a href="' . $prefix . 'page=1" class="pagination-link">1</a>';
        if ($start > 2) $html .= '<span class="pagination-dots">...</span>';
    }
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current) {
            $html .= '<span class="pagination-link active">' . $i . '</span>';
        } else {
            $html .= '<a href="' . $prefix . 'page=' . $i . '" class="pagination-link">' . $i . '</a>';
        }
    }
    
    if ($end < $last) {
        if ($end < $last - 1) $html .= '<span class="pagination-dots">...</span>';
        $html .= '<a href="' . $prefix . 'page=' . $last . '" class="pagination-link">' . $last . '</a>';
    }
    
    // Next
    if ($current < $last) {
        $html .= '<a href="' . $prefix . 'page=' . ($current + 1) . '" class="pagination-link">Next ›</a>';
    } else {
        $html .= '<span class="pagination-link disabled">Next ›</span>';
    }
    
    $html .= '</div></div>';
    
    return $html;
}
