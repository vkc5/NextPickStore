<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';
requireRole(['Buyer']);

$conn = getConnection();
$id =  isset($_GET['id']) ? (int) $_GET['id'] : 0;
$query = "SELECT p.product_id, p.product_Name , p.short_description, p.price, Round(AVG(r.rating_value),2) As Rating, pi.image_path 
    FROM nps_products p
    Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1 
    WHERE p.category_id = $id
    GROUP By p.product_id
    ORDER BY p.created_at DESC";        
        
$result = mysqli_query($conn, $query);
?>

<!DOCTYPE html language="en">
     <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
<head>
    <head>
        <title>Categories</title>
        
        <style>
            body {
                background: #e9e9e9;
                color: #222;
            }
             a {
                text-decoration: none;
                color: inherit;
            }
            .page-wrapper {
                margin: 25px;
                background: #f6f6f6;
                border-radius: 14px;
                padding: 25px 30px 0;
                min-height: calc(100vh - 50px);
            }
           /* Wrapper (section around everything) */
.product-wrapper {
  position: relative;
  
}

/* Row */
.product-row {
  display: flex;
  flex-direction: row;
  flex-wrap: nowrap;
  overflow-x: auto;
  gap: 20px;
  padding: 20px;
}

/* Hide scrollbar (optional) */
.product-row::-webkit-scrollbar {
  height: 8px;
}

/* Product Card */
.product-card {
  min-width: 230px; /* important for horizontal scroll */
  max-width: 230px;
  height: 300px;
  background: #fff;
  border-radius: 12px;
  padding: 12px;
  box-shadow: 0 4px 10px rgba(0,0,0,0.08);
  display: flex;
  flex-direction: column;
  flex-shrink: 0; /* prevents shrinking */
  transition: 0.3s;
}

.product-card:hover {
  transform: translateY(-5px);
}

/* Product Image */
.product_images {
  width: 100%;
  height: 150px;
  object-fit: cover;
  border-radius: 8px;
}

/* Product Name */
.product-name {
  font-size: 15px;
  font-weight: 600;
  margin: 10px 0 5px;
}

/* Description */
.product-description {
  font-size: 13px;
  color: #777;
  height: 35px;
  overflow: hidden;
}

.product-info {
    display: flex;
    justify-content:  space-between;
    align-items: center;
    margin-top: auto;
}
/* Price */
.product-price {
  font-weight: bold;
  color: #27ae60;
  margin: 8px 0;
}

/* Rating */
.rating {
  color: #f1c40f;
  font-size: 14px;
   
}



              /* Footer*/
     .footer {
                margin-top: 35px;
                border-top: 1px solid #ddd;
                padding: 28px 0 18px;
            }

            .footer-top {
                display: grid;
                grid-template-columns: repeat(4, 1fr);
                gap: 25px;
                margin-bottom: 22px;
                font-size: 14px;
                color: #666;
            }

            .footer-top h4 {
                color: #222;
                margin-bottom: 10px;
                font-size: 15px;
            }
     .footer-bottom {
                border-top: 1px solid #ddd;
                padding-top: 16px;
                font-size: 13px;
                color: #666;
                display: flex;
                justify-content: space-between;
                flex-wrap: wrap;
                gap: 12px;
            }
            </style>
    </head>
    
    <body>
       
        <div class="page-wrapper">
        <div class="product-wrapper">
      <div class="product-row" id="productRow">

      
   <?php
                 
       if($result){
           while ($row = mysqli_fetch_assoc($result)){
     ?>
          <a href="productDetails.php?id=<?php echo  $row['product_id'];?>">

            <div class="product-card">
                <img class="product_images" src="../../<?php echo $row["image_path"];?> "alt="product image">
          <h3 class="product-name"><?php echo $row['product_Name'];?></h3>
            <p class="product-description"><?php echo $row['short_description'];?></p>
     
   <div class="product-info">
          <span class="price"><?php  echo $row['price'];?> </span>
           <span class="rating">⭐<?php echo $row['Rating'];?></span>
           </div>
           </div>
               <?php
          }
       }else{
           echo '<p>No</p>';
       }
       ?>
             
</div>
</a> 
      </div>
            

         <footer class="footer">
                <div class="footer-top">
                    <div>
                        <h4>E-commerce support</h4>
                        <div>NEXTPICK</div>
                        <div>Damstraat 123</div>
                        <div>1012 AB Amsterdam</div>
                        <div>The Netherlands</div>
                        <br>
                        <div>Phone: +31 20 123 4567</div>
                        <div>Email: support@nextpick.com</div>
                    </div>
                    <div>
                        <h4>About us</h4>
                        <div>Career</div>
                    </div>
                    <div>
                        <h4>Help & Support</h4>
                        <div>Help center</div>
                        <div>FAQ</div>
                    </div>
                    <div>
                        <h4>Find Us</h4>
                        <div>Facebook | Instagram | Twitter</div>
                    </div>
                </div>

                <div class="footer-bottom">
                    <div>© 2026 NEXTPICK. All Rights Reserved.</div>
                    <div>Privacy policy &nbsp;&nbsp; Cookie settings &nbsp;&nbsp; Terms and conditions</div>
                </div>
            </footer>
        </div>
    </body>
</html>


