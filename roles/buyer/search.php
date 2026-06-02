<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';

requireRole(['Buyer']);

class Search
{
    public function showProduct($search, $rank = false, $searchType = null, $page = 1)
    {
        $conn = getConnection();

        $search = trim((string)$search);
        $page = max(1, (int)$page);
        $limit = 10;
        $offset = ($page - 1) * $limit;

        if ($search === '') {
            $this->showEmptyState('Please enter a product name, category, seller, or keyword to search.');
            return;
        }

        $searchQuery = $this->buildSearchQuery($search, $searchType, $rank);

        /*
            FULLTEXT search:
            Make sure this exists in your database:

            ALTER TABLE nps_products 
            ADD FULLTEXT(product_name, short_description, full_description);
        */

        $sql = "
            SELECT 
                p.product_id,
                p.product_name,
                p.short_description,
                p.full_description,
                p.price,
                p.brand,
                p.created_at,
                ROUND(AVG(r.rating_value), 2) AS Rating,
                pi.image_path,
                u.full_name AS seller_name,
                c.category_name,
                MATCH(p.product_name, p.short_description, p.full_description)
                    AGAINST (? IN BOOLEAN MODE) AS relevance
            FROM nps_products p
            LEFT JOIN nps_ratings r
                ON p.product_id = r.product_id
            LEFT JOIN nps_product_images pi 
                ON p.product_id = pi.product_id AND pi.is_primary = 1
            LEFT JOIN nps_users u
                ON p.seller_id = u.user_id
            LEFT JOIN nps_categories c
                ON p.category_id = c.category_id
            WHERE p.publish_status = 'published'
              AND (
                    MATCH(p.product_name, p.short_description, p.full_description)
                        AGAINST (? IN BOOLEAN MODE)
                    OR c.category_name LIKE ?
                    OR u.full_name LIKE ?
              )
            GROUP BY p.product_id
            ORDER BY relevance DESC, p.created_at DESC
            LIMIT ? OFFSET ?
        ";

        $likeSearch = '%' . $search . '%';

        $stmt = mysqli_prepare($conn, $sql);

        if (!$stmt) {
            $this->showError(mysqli_error($conn));
            return;
        }

        mysqli_stmt_bind_param(
            $stmt,
            "ssssii",
            $searchQuery,
            $searchQuery,
            $likeSearch,
            $likeSearch,
            $limit,
            $offset
        );

        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);

        $data = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $data[] = $row;
        }

        mysqli_stmt_close($stmt);

        $this->showResults($data, $search, $rank, $page);
    }

    private function buildSearchQuery($search, $searchType, $rank)
    {
        switch ($searchType) {
            case 'all':
                return $this->handleAll($search);

            case 'none':
                return $this->handleNone($search);

            case 'first':
                return $this->handleFirst($search);

            case 'part':
                return $this->handlePart($search);

            case 'exact':
                return $this->handleExact($search);

            default:
                if ($rank) {
                    return $this->handleRank($search);
                }

                return $this->handleAny($search);
        }
    }

    private function showResults($data, $search, $rank, $page)
    {
        echo '<section class="search-results-section">';

        echo '
            <div class="search-results-header">
                <div>
                    <h2>Search results</h2>
                    <p>Results for <strong>"' . htmlspecialchars($search) . '"</strong></p>
                </div>

                <a href="dashboard.php" class="clear-search-link">Clear search</a>
            </div>
        ';

        if (empty($data)) {
            $this->showEmptyState(
                'We could not find any products matching "' . htmlspecialchars($search) . '". Try another keyword.'
            );
            echo '</section>';
            return;
        }

        echo '<div class="product-row">';

        foreach ($data as $row) {
            $productId = (int)$row['product_id'];
            $productName = htmlspecialchars($row['product_name'] ?? '');
            $description = htmlspecialchars($row['short_description'] ?? '');
            $price = number_format((float)($row['price'] ?? 0), 2);
            $rating = !empty($row['Rating']) ? htmlspecialchars($row['Rating']) : '0.00';
            $imagePath = !empty($row['image_path'])
                ? '../../' . htmlspecialchars($row['image_path'])
                : '../../uploads/products/view.png';

            echo '
                <a href="productDetails.php?id=' . $productId . '">
                    <div class="product-card">
                        <img class="product_images" src="' . $imagePath . '" alt="product image">

                        <h3 class="product-name">' . $productName . '</h3>

                        <p class="product-description">' . $description . '</p>

                        <div class="product-info">
                            <span class="price">€' . $price . '</span>
                            <span class="rating">★ ' . $rating . '</span>
                        </div>
                    </div>
                </a>
            ';

            if ($rank && isset($row['relevance'])) {
                echo '
                    <div class="relevance-note">
                        Relevance: ' . round((float)$row['relevance'], 2) . '
                    </div>
                ';
            }
        }

        echo '</div>';

        echo '
            <div class="search-pagination">
                <a class="pagination-btn" href="?q=' . urlencode($search) . '&page=' . max(1, $page - 1) . '">Previous</a>
                <span>Page ' . (int)$page . '</span>
                <a class="pagination-btn" href="?q=' . urlencode($search) . '&page=' . ($page + 1) . '">Next</a>
            </div>
        ';

        echo '</section>';
    }

    private function showEmptyState($message)
    {
        echo '
            <div class="empty-search-card">
                <div class="empty-icon">🔎</div>
                <h3>No products found</h3>
                <p>' . $message . '</p>
                <a href="dashboard.php">
                    <button class="btn-primary">Back to Home</button>
                </a>
            </div>
        ';
    }

    private function showError($error)
    {
        echo '
            <div class="empty-search-card">
                <div class="empty-icon">⚠️</div>
                <h3>Search error</h3>
                <p>' . htmlspecialchars($error) . '</p>
            </div>
        ';
    }

    public function handleAny($search)
    {
        $terms = preg_split('/\s+/', trim($search));
        $query = "";

        foreach ($terms as $term) {
            if ($term !== "") {
                $query .= $term . " ";
            }
        }

        return trim($query);
    }

    public function handleAll($search)
    {
        $terms = preg_split('/\s+/', trim($search));
        $query = "";

        foreach ($terms as $term) {
            if ($term !== "") {
                $query .= "+" . $term . " ";
            }
        }

        return trim($query);
    }

    public function handleNone($search)
    {
        $terms = preg_split('/\s+/', trim($search));
        $query = "";

        foreach ($terms as $term) {
            if ($term !== "") {
                $query .= "-" . $term . " ";
            }
        }

        return trim($query);
    }

    public function handleFirst($search)
    {
        $terms = preg_split('/\s+/', trim($search));

        if (count($terms) >= 2) {
            return "+" . $terms[0] . " -" . $terms[1];
        }

        return $search;
    }

    public function handlePart($search)
    {
        $terms = preg_split('/\s+/', trim($search));
        $query = "";

        foreach ($terms as $term) {
            if ($term !== "") {
                $query .= $term . "* ";
            }
        }

        return trim($query);
    }

    public function handleExact($search)
    {
        return '"' . trim($search) . '"';
    }

    public function handleRank($search)
    {
        return trim($search);
    }
}
?>
