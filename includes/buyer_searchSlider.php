<?php

?>
<!-- FILTER BUTTON -->

<button class="filter-btn" onclick="openFilter()">

    ☰ Filters

</button>



<!-- OVERLAY -->

<div class="filter-overlay" id="filterOverlay"
     onclick="closeFilter()">
</div>



<!-- SIDEBAR -->

<div class="filter-sidebar" id="filterSidebar">

    <div class="sidebar-top">

        <h2>Filters</h2>

        <button class="close-btn"
                onclick="closeFilter()">

            ✕

        </button>

    </div>



    <!-- PRICE -->

    <div class="filter-box">

        <div class="filter-header"
             onclick="toggleFilter(this)">

            <h3>Price Range</h3>

            <span>⌄</span>

        </div>

        <div class="filter-content">

            <div class="price-inputs">

                <input type="number"
                       placeholder="Min">

                <input type="number"
                       placeholder="Max">

            </div>

        </div>

    </div>



    <!-- BRAND -->

    <div class="filter-box">

        <div class="filter-header"
             onclick="toggleFilter(this)">

            <h3>Brand</h3>

            <span>⌄</span>

        </div>

        <div class="filter-content">

            <input type="text"
                   class="search-input"
                   placeholder="Search brand">

            <label><input type="checkbox"> Apple</label>
            <label><input type="checkbox"> Dell</label>
            <label><input type="checkbox"> HP</label>
            <label><input type="checkbox"> Lenovo</label>

        </div>

    </div>



    <!-- SELLER -->

    <div class="filter-box">

        <div class="filter-header"
             onclick="toggleFilter(this)">

            <h3>Seller Name</h3>

            <span>⌄</span>

        </div>

        <div class="filter-content">

            <input type="text"
                   class="search-input"
                   placeholder="Search seller">

            <label><input type="checkbox"> NextPick</label>
            <label><input type="checkbox"> Tech Store</label>
            <label><input type="checkbox"> Laptop World</label>

        </div>

    </div>



    <!-- RATING -->

    <div class="filter-box">

        <div class="filter-header"
             onclick="toggleFilter(this)">

            <h3>Rating</h3>

            <span>⌄</span>

        </div>

        <div class="filter-content">

            <label><input type="radio" name="rating"> ★★★★★</label>
            <label><input type="radio" name="rating"> ★★★★☆ & Up</label>
            <label><input type="radio" name="rating"> ★★★☆☆ & Up</label>

        </div>

    </div>



    <!-- DATE -->

    <div class="filter-box">

        <div class="filter-header"
             onclick="toggleFilter(this)">

            <h3>Date Range</h3>

            <span>⌄</span>

        </div>

        <div class="filter-content">

            <div class="date-inputs">

                <input type="date">

                <input type="date">

            </div>

        </div>

    </div>



   

</div>

<style>
/* FILTER BUTTON */

.filter-btn
{
    height: 48px;
    padding: 0 22px;
    border: none;
    border-radius: 14px;
    color: black;
    font-size: 16px;
    font-weight: bold;

    cursor: pointer;
    box-shadow: 0 4px 14px rgba(26,77,225,0.25);
    transition: 0.3s;
}

.filter-btn:hover
{
        background: #153bb8;
        color: #fff;
    transform: translateY(-2px);
}

/* OVERLAY */

.filter-overlay
{
    position: fixed;

    inset: 0;

    background: rgba(0,0,0,0.35);

    backdrop-filter: blur(3px);

    opacity: 0;

    visibility: hidden;

    transition: 0.3s;

    z-index: 999;
}

.filter-overlay.active
{
    opacity: 1;

    visibility: visible;
}



/* SIDEBAR */

.filter-sidebar
{
    position: fixed;

    top: 0;

    left: -340px;

    width: 320px;

    height: 100vh;

    background: white;

    padding: 24px;

    overflow-y: auto;

    transition: 0.35s ease;

    z-index: 1000;

    box-shadow: 4px 0 24px rgba(0,0,0,0.12);
}

.filter-sidebar.active
{
    left: 0;
}



/* TOP */

.sidebar-top
{
    display: flex;

    justify-content: space-between;

    align-items: center;

    margin-bottom: 25px;
}

.sidebar-top h2
{
    font-size: 28px;
}



/* CLOSE */

.close-btn
{
    border: none;

    background: #f2f2f2;

    width: 38px;

    height: 38px;

    border-radius: 10px;

    cursor: pointer;

    font-size: 18px;
}



/* FILTER BOX */

.filter-box
{
    border-bottom: 1px solid #eee;

    padding: 18px 0;
}



/* HEADER */

.filter-header
{
    display: flex;

    justify-content: space-between;

    align-items: center;

    cursor: pointer;
}

.filter-header h3
{
    font-size: 17px;
}



/* CONTENT */

.filter-content
{
    display: none;

    margin-top: 16px;
}

.filter-box.active .filter-content
{
    display: block;
}



/* INPUTS */

.search-input
{
    width: 100%;

    height: 44px;

    border: 1px solid #ddd;

    border-radius: 12px;

    padding: 0 14px;

    margin-bottom: 15px;

    outline: none;
}

.price-inputs,
.date-inputs
{
    display: flex;

    gap: 10px;
}

.price-inputs input,
.date-inputs input
{
    width: 100%;

    height: 42px;

    border: 1px solid #ddd;

    border-radius: 10px;

    padding: 0 12px;

    outline: none;
}



/* LABELS */

.filter-content label
{
    display: block;

    margin-bottom: 12px;

    color: #555;

    cursor: pointer;
}



/* APPLY */

.apply-btn
{
    width: 100%;

    height: 50px;

    border: none;

    border-radius: 14px;

    background: #1A4DE1;

    color: white;

    font-size: 16px;

    font-weight: bold;

    margin-top: 24px;

    cursor: pointer;
}
</style>
<script>

function openFilter()
{
    document.getElementById("filterSidebar").classList.add("active");

    document.getElementById("filterOverlay").classList.add("active");
}



function closeFilter()
{
    document.getElementById("filterSidebar") .classList.remove("active");

    document.getElementById("filterOverlay").classList.remove("active");
}



function toggleFilter(element)
{
    const parent = element.parentElement;

    parent.classList.toggle("active");
}
</script>