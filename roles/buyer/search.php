<?php
include_once '../../includes/config.php'; 

class Search
{
    public function showProduct($search, $rank = false,$searchType = null)
    {
        $search = trim($search);
        if (in_array($searchType, ['all', 'none', 'first', 'part','exact'])){
          $q = "SELECT p.*, MATCH(p.product_name,p.short_description,p.full_description)
              AGAINST('$search') AS relevance, Round(AVG(r.rating_value),2) As Rating, pi.image_path 
                  FROM nps_products p
                    Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1
             WHERE MATCH(p.product_name, p.short_description,p.full_description)
              AGAINST('$search')
            GROUP By p.product_id
              ORDER BY relevance DESC";


        }else
        {
            if ($rank) {
                $q = "SELECT p.*, MATCH(p.product_name,p.short_description,p.full_description)
              AGAINST('$search' IN BOOLEAN MODE) AS relevance, Round(AVG(r.rating_value),2) As Rating, pi.image_path 
                  FROM nps_products p
                    Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1
             WHERE MATCH(p.product_name, p.short_description,p.full_description)
              AGAINST('$search' IN BOOLEAN MODE)
            GROUP By p.product_id
              ORDER BY relevance DESC";
            } else {
            $q = "SELECT p.*, Round(AVG(r.rating_value),2) As Rating, pi.image_path  FROM nps_products p
                   Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1
                 WHERE MATCH(product_name, short_description,full_description)
              AGAINST('$search' IN BOOLEAN MODE)
                              GROUP By p.product_id";

        }
        }
        $this->showResults($q, $rank);
    }
        private function showResults($q, $rank)
        {
            $conn = getConnection();
             
            $result = mysqli_query($conn, $q);

            if (!$result) {
                die("Query failed: " . mysqli_error($conn));
            }
          //
            $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
           $output = "";
            if (empty($data)) {
                echo '<div class="product-result"><p>No results found.</p></div>';
            } else {
                foreach ($data as $row) {
                   
                    $output .=  "<div class='product-row' id='productRow'>";

         $output .=  '<a href="productDetails.php?id='. $row['product_id'].'">';
         $output .=   "<div class='product-card'>";
              $output .=   '<img class="product_images" src="../../'. $row['image_path'].'" alt="product image">';
          $output .=   '<h3 class="product-name">'. $row['product_name'].'</h3>';
            $output .=   '<p class="product-description">'. $row['short_description'].'</p>';
     
    $output .=  '<div class="product-info">';
          $output .=  '<span class="price">'.  $row['price'].' </span>';
          $output .=  '<span class="rating">⭐'. $row['Rating'].'</span>';
           $output .=  ' </div>
           </div>               
</a>
</div>';
      
                    if ($rank) {
                        echo "<p>Relevance: " . round($row['relevance'], 2) . "</p>";
                    }

                }
                echo '<div class="product-result">'. $output . '</div>';
            }
        }

    

public function handleAny($search) { 
$terms = explode(" ", $search);
    $query = "";
    foreach ($terms as $term) {
        if($term !== "")
        $query .= $term . "";
    }
    return trim($query);
}
public function handleAll($search) {
    $terms = explode(" ", $search);
    $query = "";
    foreach ($terms as $term) {
        if($term !== "")
        $query .= "+" . $term . " ";
    }
    return trim($query);
}
public function handleNone($search) {
    $terms = explode(" ", $search);
    $query = "";
    foreach ($terms as $term) {
        if($term !== "")
        $query .= "-" . $term . " ";
    }
    return trim($query);
}
public function handleFirst($search) {
    $terms = explode(" ", $search);
    if (count($terms) >= 2) {
        return "+" . $terms[0] . " -" . $terms[1]; 
}
    return $search;
}
public function handlePart($search) {
    return $search . "*";
}

public function handleExact($search) {
    return '"' . trim($search) . '"';
}
public function handleRank($search) {
    return $search;
}
}
?>



