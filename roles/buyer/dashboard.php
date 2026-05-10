<?php
include_once '../../includes/auth_guard.php';
include_once '../../includes/config.php'; 
requireRole(['Buyer']);

$conn = getConnection();
$query = "SELECT p.product_id, p.product_Name , p.short_description, p.price, Round(AVG(r.rating_value),2) As Rating, pi.image_path 
    FROM nps_products p
    Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1 
    WHERE publish_status = 'published'
    GROUP By p.product_id
    ORDER BY P.created_at DESC LIMIT 4";
 $new = mysqli_query($conn, $query);
$sql = "SELECT p.product_id, p.product_Name , p.short_description, p.price, Round(AVG(r.rating_value),2) As Rating, pi.image_path 
    FROM nps_products p
    Left Join nps_Ratings r on p.product_id = r.product_id
    Left Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1 WHERE 1=1
    GROUP By p.product_id";
$result = mysqli_query($conn, $sql);
$sqlquery = "SELECT c.category_id, c.category_name, MIN(pi.image_path) as img FROM nps_categories c
    LEFT JOIN nps_products p
   ON c.category_id =  p.category_id 
   LEFT Join nps_product_images pi on p.product_id = pi.product_id AND pi.is_primary = 1
  GROUP BY c.category_id , C.category_name
  ORDER BY c.category_name ASC";
$categoryResult = mysqli_query($conn, $sqlquery);
   $categoriess = [];
      if($categoryResult) {
    while ($rows = mysqli_fetch_assoc($categoryResult)) {
        $categoriess[] = $rows;
    } 
    }
?>
<!DOCTYPE html language="en">
     <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
<head>
  <title>Home</title>

  <style>
    :root{
      --PrimaryBlue :#1A4DE1;
    }
    * {
     box-sizing: border-box;
        margin: 0;
         padding: 0;

    }

    body {
      font-family: Arial, sans-serif;
      background: #e9e9e9;

    }
    a {
                text-decoration: none;
                color: inherit;
            }
    .banner {
      width: 100%;
      padding: 0;
    }

    .slider {
      width: 100%;
      height: 756px;
      overflow: hidden;
      position: relative;
      cursor: grab;
      user-select: none;
    }

    .slider:active {
      cursor: grabbing;
    }

    .slides {
      display: flex;
      height: 100%;
      transition: transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1);
    }

    .slide {
      min-width: 100%;
      height: 100%;
      position: relative;
      flex-shrink: 0;
      overflow: hidden;
    }

    .slide img {
      width: 100%;
      height: 100%;
      pointer-events: none;
      object-fit: cover;
      user-select: none;
      -webkit-user-drag: none;
      transform: scale(1.08);
      opacity: 0.75;
      transition: transform 0.8s ease, opacity 0.8s ease;
    }

    .slide.active img {
      transform: scale(1);
      opacity: 1;
    }

    .overlay {
    position: absolute;
    top:50%;
     font-family: "Roboto";
      width: 100%;
      height: 211px;
      Gap: 32px;
      transform: translateY(-50%) translateX(-30px);
      color: #424242;
      opacity: 0;
      transition: all 0.6s ease;
    }
     .content{
     position: absolute;
     left:4%;
     width: 350px;
     }
    .content2{
     position: absolute;
     right: 4%;
     width:350px;
    }
    .slide.active .overlay {
      opacity: 1;
      transform: translateY(-50%) translateX(0);
    }

    .overlay h1 {
      font-size: 44px;
      margin-bottom: 10px;
    }

    .overlay p {
      font-size: 20px;
      width: 100%;
      line-height: 1.5;
    }
    
     .page-wrapper {
                margin: 25px auto;
                background: #f6f6f6;
                border-radius: 14px;
                padding: 25px 30px 0;
                min-height: calc(100vh - 50px);
                margin-left: 25px;
                margin-right: 25px;
            }
    

    .dots {
      position: absolute;
      bottom: 20px;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 10px;
      z-index: 10;
    }

    .dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      background: white;
      transition: 0.3s;
    }

    .dot.active {
      background: black;
      transform: scale(1.2);
    }

    @media (max-width: 768px) {
      .slider {
        height: 300px;
      }

      .overlay h1 {
        font-size: 24px;
        color: #424242;
      }

      .overlay p {
        font-size: 14px;
      }
    }
     .top-bar {
      height: 64px;
      background: white;
      border-radius: 8px;
      margin: 40px auto;
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 0 20px;
      box-sizing: border-box;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
     position : absolute;
     top : 0.5%;
     left: 4%;
     right: 4%;
    }
    .dropdown-btn{
        border: none;
        cursor: pointer;
    }
   
    .dropdawn {
        position: relative;
        display: inline-block;
    }
    
    .product-menu {
       position: absolute;
         top: 100%;
         left:0%;
         transform:translateY(10px);
       background: white;
       color: #444;
       font-size: 14px;
       padding: 10px 0;
        list-style: none;
        visibility: hidden;
        transition: 0.3s ease;
       z-index: 1000;

        
    }
   
   .dropdawn:hover .product-menu{
    opacity: 1;
       visibility: visible;
    transform: translateY(0);
   } 
  
    .search-box {
      width: 300px;
      height: 32px;
      border: 1px solid #ddd;
      border-radius: 6px;
      padding: 0 10px;
      outline: none;
    }

    .icons {
      display: flex;
      gap: 15px;
      font-size: 8px;
      color: black;
    }
    .icons img{
        width: 10px;
        height:10px;
    }
    .btn-Discover1 {
      background-color: var(--PrimaryBlue);
        display: inline-block;
      height: 48px;
      width : 180px;
        color: white;
      text-decoration: none;
      font-size: 20px;
      font-weight: bold;
      border-radius: 8px;
      box-shadow: 0 8px 20px rgba(47, 91, 255, 0.35);
      margin-top:  100px;
      border: none;
      cursor: pointer;

    }
    
    .btn-Discover1:hover{
     background: linear-gradient(135deg, #fff8ee, #f3ede2);
     color:black;
     transform: translateY(-3px);
     box-shadow: 0 10px 25px rgba(200, 255,255,0.22);
    }
    
    /* Category */
    .category-row {
        display: flex;    
        gap: 24px;
    }
    .category-card{
        
        display: flex;
        flex-direction: column;
        align-items: center;
        gap: 12px;
    }
    .image-wrapper{
         width: 184px;
        height: 122px;
        border-radius: 8px;
        overflow: hidden;
    }  
    .category_images{
        width: 100%;
        height: 100%;
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


.scroll-btn:hover {
  background: #eee;
}
  </style>
</head>
<body>

  <section class="banner">
    <div class="slider" id="slider">
      <div class="slides" id="slides">

        <div class="slide active">
          <img src="../../assets/images/products/Banner-Image1.png" alt="Banner 1">
          <div class="overlay">
              <div class="content">
            <h1>Introducing the Next Generation of Sound</h1>
            <p>Experience pure,Immersive sound never before    </p>
             <button class="btn-Discover1" >Discover More</button>
              </div>
          </div>
        </div>

        <div class="slide">
          <img src="../../assets/images/products/Banner-Image2.png" alt="Banner 2">
          <div class="overlay">
              <div class="content2">
                  <h1>Your Ultimate Destination for Next-Gen Tech</h1>
             <p>Explore a premium collection of smartphones,laptops and professional gear curated for your lifestyle</p>
           <button class="btn-Discover1" >Discover More</button>
              </div>
          </div>
        </div>
      </div>
       <div class="top-bar">
    <div class="logo"><img src="../../assets/images/Logos/nextpickstore-logo.png" alt="Logo"/></div>
    <div class="dropdawn">
    <button class="dropdown-btn"> All Products</button>
    <ul class="product-menu"> 
        
          <?php 
          foreach ($categoriess as $cat):
    ?>
        <li> <a href="productCategory.php?id=<?php echo $cat['category_id'];?>"><?php echo $cat['category_name'];?> </a></li>
        <?php    endforeach; ?>
    </ul>
    </div>
    <input type="text" class="search-box" placeholder="Iam Searching for..." />
    
    <div class="icons">
        <span></span>
      <span></span>
      <span><a href="/NextPickStore/auth/logout.php" class="logout-btn">Logout</a>
</span>
    </div>
  </div>

      <div class="dots" id="dots"></div>
    </div>
  </section>
    <div class="page-wrapper">
        <section>
            <h2>Latest Arrived</h2>
      <div class="product-row" id="productRow">

      
   <?php
                 
       if($new){
           while ($row = mysqli_fetch_assoc($new)){
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
      
      </section>
        
     <section  class="Producta">
        <h3>Products</h3>
  
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
      </section>
    <section class="category">
        <h3>Shop by Category</h3>
        <div class="category-row">
       <?php
              foreach($categoriess as $cat):
              ?>
            <a href="productCategory.php?id=<?php echo  $cat['category_id'];?>">
            <div class="category-card">
                <div class="image-wrapper">
            <img class="category_images" src="../../<?php echo $cat["img"];?> "alt="product image"> 
                </div>
                <?php
           
           echo $cat['category_name'];?>
             
        </div>
            </a>
        <?php       endforeach;   ?>
        </div>
                 

    </section>
    
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
  <script>
    const slider = document.getElementById("slider");
    const slidesContainer = document.getElementById("slides");
    const allSlides = document.querySelectorAll(".slide");
    const dotsContainer = document.getElementById("dots");
    const totalSlides = allSlides.length;

    let currentIndex = 0;
    let startX = 0;
    let isDragging = false;
    let currentTranslate = 0;
    let prevTranslate = 0;
    let animationID = 0;

    for (let i = 0; i < totalSlides; i++) {
      const dot = document.createElement("div");
      dot.classList.add("dot");
      if (i === 0) dot.classList.add("active");
      dotsContainer.appendChild(dot);
    }

    const dots = document.querySelectorAll(".dot");

    function setSliderPosition() {
      slidesContainer.style.transform = `translateX(${currentTranslate}px)`;
    }

    function updateActiveSlide() {
      allSlides.forEach((slide, index) => {
        slide.classList.toggle("active", index === currentIndex);
      });

      dots.forEach((dot, index) => {
        dot.classList.toggle("active", index === currentIndex);
      });
    }

    function goToSlide(index) {
      if (index < 0) index = 0;
      if (index >= totalSlides) index = totalSlides - 1;

      currentIndex = index;
      currentTranslate = -currentIndex * slider.offsetWidth;
      prevTranslate = currentTranslate;

      slidesContainer.style.transition = "transform 0.6s cubic-bezier(0.25, 0.8, 0.25, 1)";
      setSliderPosition();
      updateActiveSlide();
    }

    function getPositionX(event) {
      return event.type.includes("mouse")
        ? event.pageX
        : event.touches[0].clientX;
    }

    function startDrag(event) {
      isDragging = true;
      startX = getPositionX(event);
      slidesContainer.style.transition = "none";
      animationID = requestAnimationFrame(animation);
    }

    function drag(event) {
      if (!isDragging) return;

      const currentPosition = getPositionX(event);
      const movedBy = currentPosition - startX;
      currentTranslate = prevTranslate + movedBy;
    }

    function endDrag() {
      if (!isDragging) return;

      isDragging = false;
      cancelAnimationFrame(animationID);

      const movedBy = currentTranslate - prevTranslate;
      const threshold = 100;

      if (movedBy < -threshold && currentIndex < totalSlides - 1) {
        currentIndex++;
      } else if (movedBy > threshold && currentIndex > 0) {
        currentIndex--;
      }

      goToSlide(currentIndex);
    }

    function animation() {
      setSliderPosition();
      if (isDragging) requestAnimationFrame(animation);
    }

    slider.addEventListener("mousedown", startDrag);
    slider.addEventListener("mousemove", drag);
    slider.addEventListener("mouseup", endDrag);
    slider.addEventListener("mouseleave", endDrag);

    slider.addEventListener("touchstart", startDrag);
    slider.addEventListener("touchmove", drag);
    slider.addEventListener("touchend", endDrag);

    window.addEventListener("resize", () => {
      goToSlide(currentIndex);
    });

    updateActiveSlide();
    goToSlide(0);
     
//
    function scrollProducts(amount) {

      const row = document.getElementById("productRow");

      row.scrollBy({

        left: amount,

        behavior: "smooth"

      });

    }

  </script>


</body>
</html>