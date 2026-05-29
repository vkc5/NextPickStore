<?php
$finalOutput = "";
    $isSearching = false;

  if(isset($_POST['submitted'])){
      $result = new Search();
  
  $showRank = (isset($_POST['showRank']) && $_POST['showRank']== 'show');
  $search = trim($_POST['Search']);
  $searchType = $_POST['searchType'];

if ($searchType == 'any')
$search = $result->handleAny($search);

if($searchType == 'all')
$search = $result->handleAll($search);

if($searchType == 'none')
$search = $result->handleNone($search);

if($searchType == 'first')
$search = $result->handleFirst($search);

if ($searchType == 'part')
$search = $result->handlePart($search);

if ($searchType == 'exact')
$search= $result->handleExact($search);

  if ($searchType == 'rank')
 $search = $result->handleRank($search);
     
 $finalOutput = $result->showProduct($search,$showRank,$searchType);
 $isSearching = false;
 if(isset($_POST['submitted']) && !empty(trim($_POST['Search'])))
  {
    $isSearching = true;
  } 
 
 
  }
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
<style>
     :root {
        --PrimaryBlue: #1A4DE1;
        --TopBarHeight: 72px;
        --TopBarShadow: 0 4px 18px rgba(26, 77, 225, 0.08);
        --TopBarRadius: 16px;
    }
     .top-bar {
        height: var(--TopBarHeight);
        background: #fff;
        border-radius: var(--TopBarRadius);
        margin: 40px auto 0 auto;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 36px;
        box-sizing: border-box;
        box-shadow: var(--TopBarShadow);
        position: relative;
        
        
       
        z-index: 100;
        transition: box-shadow 0.2s;
    }
    .top-bar:hover {
        box-shadow: 0 8px 32px rgba(26, 77, 225, 0.13);
    }
    .logo img {
        height: 20px;
        width: auto;
        display: block;
    }
    .dropdawn {
        position: relative;
        display: inline-block;
    }
    .dropdown-btn {
        background: none;
        color: #4B4B4B;
        border: none;
        border-radius: 8px;
        font-size: 17px;
        font-weight: 600;
        padding: 10px 28px 10px 18px;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(26, 77, 225, 0.08);
        transition: background 0.2s, color 0.2s;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .dropdown-btn::after {
        content: "▼";
        font-size: 12px;
        margin-left: 8px;
        color: #fff;
    }
    .dropdown-btn:hover, .dropdown-btn:focus {
        background: #153bb8;
        color: #fff;
    }
    .product-menu {
        position: absolute;
        top: 110%;
        left: 0;
        min-width: 180px;
        background: #fff;
        color: #444;
        font-size: 15px;
        padding: 10px 0;
        list-style: none;
        box-shadow: 0 10px 25px rgba(0,0,0,0.12);
        border: 1px solid #eee;
        border-radius: 8px;
        opacity: 0;
        visibility: hidden;
        transform: translateY(10px);
        transition: opacity 0.25s, transform 0.25s, visibility 0.25s;
        z-index: 999;
    }
    .dropdawn:hover .product-menu,
    .dropdawn:focus-within .product-menu {
        opacity: 1;
        visibility: visible;
        transform: translateY(0);
    }
    .product-menu li {
        width: 100%;
    }
    .product-menu li a {
        display: block;
        padding: 12px 22px;
        transition: 0.25s;
        border-radius: 4px;
    }
    .product-menu li a:hover {
        background-color: var(--PrimaryBlue);
        color: #fff;
        padding-left: 30px;
    }
    .search-box {
        width: 320px;
        height: 38px;
        border: 1.5px solid #dbe6fd;
        border-radius: 8px;
        background: #f7faff;
        display: flex;
        align-items: center;
        padding: 0 18px;
        gap: 10px;
        box-shadow: 0 1px 4px rgba(26, 77, 225, 0.04);
        transition: border 0.2s;
    }
    .search-box:focus-within {
        border: 1.5px solid var(--PrimaryBlue);
        background: #fff;
    }
    .search {
        border: none;
        outline: none;
        background: transparent;
        font-size: 16px;
        width: 100%;
        color: #222;
    }
    .0B4B0tton {
        width: 22px;
        height: 22px;

    }
    .icons {
        display: flex;
        gap: 18px;
        align-items: center;
        font-size: 8px;
        color: #222;
    }
    .icons img {
        width: 32px;
        height: 32px;
        background: #f3f6ff;
        padding: 5px;
        transition: background 0.2s;
       cursor: pointer;
    }
    .icons img:hover {
        background: #e3eaff;
    }
    .logout-btn {
        background: var(--PrimaryBlue);
        color: #fff;
        border: none;
        border-radius: 8px;
        padding: 8px 18px;
        font-size: 15px;
        font-weight: 500;
        margin-left: 8px;
        transition: background 0.2s, color 0.2s;
        box-shadow: 0 2px 8px rgba(26, 77, 225, 0.08);
    }
    .logout-btn:hover {
        background: #153bb8;
        color: #fff;
    }
    @media (max-width: 1100px) {
        .top-bar {
            flex-direction: column;
            height: auto;
            padding: 18px 10px;
            gap: 12px;
        }
        .search-box {
            width: 100%;
            margin: 8px 0;
        }
    }
    @media (max-width: 768px) {
        .top-bar {
            flex-direction: column;
            height: auto;
            padding: 12px 4px;
            gap: 10px;
            position: static;
            margin: 18px 0 0 0;
        }
        .logo img {
            height: 36px;
        }
        .search-box {
            width: 100%;
            min-width: 0;
        }
        .dropdown-btn {
            font-size: 15px;
            padding: 8px 16px 8px 12px;
        }
        .icons img {
            width: 28px;
            height: 28px;
        }
        .logout-btn {
            font-size: 13px;
            padding: 7px 12px;
        }
    }
    
    </style>

  <div class="top-bar">
      <div class="logo"><a href="../../roles/buyer/dashboard.php"><img src="../../assets/images/Logos/nextpickstore-logo.png" alt="Logo"/></a></div>
    <div class="dropdawn">
    <button class="dropdown-btn">☰ All Products</button>
    <ul class="product-menu">  
          <?php 
          foreach ($categoriess as $cat):
    ?>
        <li> <a href="productCategory.php?id=<?php echo $cat['category_id'];?>"><?php echo $cat['category_name'];?> </a></li>
        <?php    endforeach; ?>
    </ul>
    </div>
    <form action="" method="post" class="search-box">
        <input type="text" name="Search" class="Search" placeholder="I am Searching for..." value="<?php echo isset($_POST['Search'])? htmlspecialchars($_POST['Search']) : ''; ?>">
        <input type="hidden" name="submitted" value="1">
        <input type="hidden" name="searchType" value="any">
<!--        value="<?php // if(isset($_POST['Search'])) echo htmlSpecialChars($_POST['Search']); ?>"/>-->
<!--    <input type="hidden" name="submitted" value="1"/>-->
        <input type="submit" class="button" value="Search"/>
    </form>
    <div class="icons">
        <span><img src="../../assets/images/icons/buyer/shopcart.png" alt="shopCart"/></span>
        <span><a href="profile/profile.php"><img src="../../assets/images/icons/buyer/profile.png" alt="Profile"/></a></span>
      <span><a href="/NextPickStore/auth/logout.php" class="logout-btn">Logout</a>
</span>
    </div>
  </div>

