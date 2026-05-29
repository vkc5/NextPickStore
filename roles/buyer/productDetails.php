<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php';
include_once '../../includes/session.php';
include 'Search.php';
requireRole(['Buyer']);

$conn = getConnection();
$id =  isset($_GET['id']) ? (int) $_GET['id'] : 0;
$query = "SELECT p.product_id, p.product_Name ,p.brand , p.short_description,p.full_description, p.price, Round(AVG(r.rating_value),2) As Rating,
    pi.image_path,u.full_name AS seller_name
    FROM nps_products p
     INNER JOIN nps_users u ON p.seller_id = u.user_id
    Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1 WHERE p.product_id = $id
    GROUP By p.product_id";
$results = mysqli_query($conn, $query);
$product = mysqli_fetch_assoc($results);
$q = "SELECT
         p.product_id,
         c.comment_text,
         c.created_at,
         u.full_name,
         COUNT(c.comment_id) AS total_comments 
         FROM nps_products p 
         INNER JOIN nps_comments c on p.product_id = c.product_id 
         INNER JOIN nps_users u on c.user_id = u.user_id 
         WHERE p.product_id = $id
         GROUP BY p.product_id 
         ORDER BY c.created_at DESC";
$comments = mysqli_query($conn, $q);
        ?>

<!DOCTYPE html language="en">
     <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
<head>
    
        <title>Product Details</title>
        
        <style>
            body {
                background: #e9e9e9;
                color: #222;
            }
           
            .page-wrapper {
                margin: 25px;
                background: #f6f6f6;
                border-radius: 14px;
                padding: 25px 30px 0;
                min-height: calc(100vh - 50px);
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
             .container {
            max-width: 1200px;
            margin: 40px auto;
            padding: 20px;
        }

        .product-cards {
            background: white;
            border-radius: 20px;
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 40px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
        }

        .product-image-box {
            display: flex;
            justify-content: center;
            align-items: center;
            background: #f1f5f9;
            border-radius: 18px;
            padding: 25px;
        }

        .product-image-box img {
            width: 100%;
            max-height: 450px;
            object-fit: contain;
        }

        _product-info {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .brand,
        .seller {
            font-size: 15px;
            color: #555;
        }

        .brand span,
        .seller span {
            color: #2563eb;
            font-weight: bold;
        }

        .product-title {
            font-size: 34px;
            line-height: 1.2;
        }

        .short-desc {
            color: #666;
            line-height: 1.7;
        }

        _rating {
            color: #f59e0b;
            font-size: 18px;
        }

        _price {
            font-size: 32px;
            font-weight: bold;
            color: #2563eb;
        }

        .quantity {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }

        .quantity input {
            width: 120px;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 10px;
        }

        .buttons {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn {
            border: none;
            padding: 14px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: bold;
            cursor: pointer;
        }
         a {
        text-decoration: none;
        color: inherit;
    }

        .add-cart {
            background: #2563eb;
            color: white;
        }

        .buy-now {
            background: #f59e0b;
            color: white;
        }

        .section {
            margin-top: 30px;
            background: white;
            padding: 30px;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.06);
        }
        
    /* Row */
    .product-row {
        display: flex;
        flex-direction: row;
        flex-wrap: wrap;
        overflow-x: auto;
        gap: 20px;
        padding: 20px;
    }
    /* Hide scrollbar (optional) */
    .product-row::-webkit-scrollbar {
        height: 8px;
    }
    .product-result {
        display: flex;
        position: absolute;
        top:20%;
            left:4%;
      width: 92%;
    z-index: 1000;
    }
    /* Product Card */
    .product-card {
        min-width: 200px; 
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
    .topBar{
        margin-left: 4%; 
        margin-right: 4%;
    }
   

        .section h2 {
            margin-bottom: 15px;
        }

        .section p {
            line-height: 1.8;
            color: #555;
        }

        .review {
            border-top: 1px solid #eee;
            padding-top: 15px;
            margin-top: 15px;
        }

        @media (max-width: 900px) {
            .product-card {
                grid-template-columns: 1fr;
            }

            .product-title {
                font-size: 28px;
            }
        }

        @media (max-width: 500px) {
            .container {
                margin: 15px auto;
                padding: 12px;
            }

            .product-card,
            .section {
                padding: 18px;
                border-radius: 15px;
            }

            .product-title {
                font-size: 24px;
            }

            .price {
                font-size: 26px;
            }

            .buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
            }

            .quantity input {
                width: 100%;
            }
        }
    

            </style>
    </head>
    
    <body>
         <div class="topBar">
        <?php include '../../includes/buyer_topBar.php'; ?>
        </div>
      
        <div class="page-wrapper">
    <?php
            if (!$isSearching){
       ?>
<div class="container">
    <div class="product-cards">

        <div class="product-image-box">
            <img src="../../<?php echo $product["image_path"];?>" alt="Product Image">
        </div>

        <div class="_product-info">
            <div class="brand">Brand: <span><?php echo $product['brand'];?></span></div>

            <h1 class="product-title"><?php echo $product['product_Name'];?></h1>

            <p class="short-desc">
            <?php echo $product["short_description"];?>
            </p>

            <div class="_rating">
                <?php
                $s =" ";
                $stars = floor($product['Rating']);
                for ($i = 0; $i < $stars; $i++)
                {
                    echo "★";
                }
                 echo " " .  $product["Rating"];?></div>

            <div class="_price">$<?php echo $product["price"];?></div>

            <div class="seller">Sold by: <span><?php echo $product['seller_name'];?></span></div>

            <div class="quantity">
                <label>Quantity</label>
                <input type="number" value="1" min="1">
            </div>

            <div class="buttons">
                <button class="btn add-cart">Add To Cart</button>
                <button class="btn buy-now">Buy Now</button>
            </div>
        </div>

    </div>

    <div class="section">
        <h2>Description</h2>
        <p>
            <?php echo $product["full_description"];?>
        </p>
    </div>

    <div class="section">
        <h2>Customer Reviews</h2>
      <?php
        if(mysqli_num_rows($comments)){
             while ($ro = mysqli_fetch_assoc($comments)){
            
            ?>
        
        <div class="review">
            <strong><?php echo $ro['full_name'];?></strong>
            <div class="rating"></div>
            <p><?php  echo $ro['comment_text'];?></p>
        </div>
    <?php
        } 
        }
        else
        {
            echo "No Reviews found";
        }
        ?>
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
                     <?php } ?>
        
</div>
    </body>
</html>


