<?php
require_once 'connection.php'; 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>R-tel Admin Dashboard</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/bootstrap.css">

    <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="assets/css/app.css">
    <link rel="stylesheet" href="assets/css/logo.css">
    <link rel="shortcut icon" href="../web/images/logo.jpg" type="image/x-icon">
</head>

<body>
    <?php
    require_once __DIR__ . '/includes/sidebar-nav.php';
    rtel_render_sidebar_nav('customer_info.php');
    ?>
    <div id="app">
        <?php if (false): ?>
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="header-container">
                    <div class="brand-logo"> 
                        <a href="../web/index.php" class="avatar avatar-xl"><img src="../web/images/logo.jpg" alt="Logo" srcset=""></a>
                    </div>
                    <div class="user-name">
                        <a href="../web/index.php" class="user-name">R-tel Admin Dashboard</a>
                    </div>
                </div>

                <div class="d-flex "> 
                    <div class="toggler">
                        <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle-l"></i></a>
                    </div>
                </div>
                <div class="sidebar-menu">
                    <ul class="menu">

                        <li class="sidebar-item  ">
                            <a href="index.php" class='sidebar-link'>
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-stack"></i>
                                <span>Products</span>
                            </a>
                            <ul class="submenu ">
                                <li class="submenu-item ">
                                    <a href="brand.php">Brand</a>
                                </li>
                                <li class="submenu-item ">
                                    <a href="category.php">Category</a>
                                </li>
                                <li class="submenu-item ">
                                    <a href="product.php">Add Products</a>
                                </li>
                                <li class="submenu-item ">
                                    <a href="allproducts.php">All Products</a>
                                </li>
                            </ul>

                        <li class="sidebar-item  ">
                            <a href="order.php" class='sidebar-link'>
                                <i class="bi bi-basket-fill"></i>
                                <span>Order</span>
                            </a>
                        </li>

                        <li class="sidebar-item active">
                            <a href="customer.php" class='sidebar-link'>
                                <i class="bi bi-people-fill"></i>
                                <span>Customer</span>
                            </a>
                        </li>
                        <li class="sidebar-item  ">
                            <a href="reports.php" class='sidebar-link'>
                                <i class="bi bi-file-earmark-fill"></i>
                                <span>Reports</span>
                            </a>
                        </li>

                        <li class="sidebar-item">
                            <a href="delivery_fee.php" class='sidebar-link'>
                                <i class="bi bi-truck"></i>
                                <span>Delivery Fee</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="coupon.php" class='sidebar-link'>
                               <i class="bi bi-percent"></i>
                                <span>Coupons & Discounts</span>
                            </a>
                        </li>
                        <li class="sidebar-item">
                            <a href="feedback.php" class='sidebar-link'>
                                <i class="bi bi-chat-left-quote-fill"></i>
                                <span>Feed Backs</span>
                            </a>
                        </li>
                        <li class="sidebar-item   has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-gear-fill"></i>
                                <span>Settings</span>
                            </a>
                            <ul class="submenu ">
                                <li class="submenu-item ">
                                    <a href="banner.php">Banner</a>
                                </li>
                                <li class="submenu-item ">
                                    <a href="contactinfo.php">Contact Info</a>
                                </li>
                                
                            </ul>
                        </li>
                        <li class="sidebar-item  has-sub">
                            <a href="#" class='sidebar-link'>
                                <i class="bi bi-person-circle"></i>
                                <span>Profile</span>
                            </a>
                            <ul class="submenu ">
                                <li class="submenu-item ">
                                    <a href="profile.php">My Profile</a>
                                </li>
                                <li class="submenu-item ">
                                    <a href="profile.php">My Profile</a>
                                </li>
                                
                            </ul>
                        </li>
                    </ul>
                </div>
                <button class="sidebar-toggler btn x"><i data-feather="x"></i></button>
            </div>
        </div>
        <?php endif; ?>
        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Customers</h3>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="index.php">Dashboard</a></li>
                                    <li class="breadcrumb-item"><a href="customer.php">Customer</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Customer Information</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
               
                <section class="section">

                    <div class="row">
                        <div class="col-md-12">
                            <div class="card">
                                <div class="card-body">
                                    <div class="table-responsive ">
                                            <?php 
                                            mysqli_close($conn);
                                            include ('connection.php');
                                            if (isset($_GET['id'])){
                                                $run=mysqli_query($conn,"SELECT c.cus_id, c.name, c.email, c.dob, c.gender, a.address_1, a.address_2, p.phone_1, p.phone_2 FROM tblcustomer AS c JOIN tbladdress AS a ON c.cus_id = a.cus_id 
                                                                    JOIN tblphone AS p ON p.cus_id = c.cus_id WHERE c.cus_id='$_GET[id]'");
                                                $query = mysqli_fetch_array($run);       
                                            
                                            ?>
                                        <!-- for active customer -->
                                        <table class="table" id="table1"> 
                                            <tr>
                                                <th>ID</th>
                                                <td><?php echo $query['cus_id'];?></td>
                                            </tr>
                                            <tr>
                                                <th>Customer Name</th>
                                                <td><?php echo $query['name'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td><?php echo $query['dob'];?></td> 
                                            </tr>
                                                <tr>
                                                <th>Gender</th>
                                                <td><?php echo $query['gender'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?php echo $query['email'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Address 1</th>
                                                <td><?php echo $query['address_1'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Address 2</th>
                                                <td><?php echo $query['address_2'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Phone Number 1</th>
                                                <td><?php echo $query['phone_1'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Phone Number 2</th>
                                                <td><?php echo $query['phone_2'];?></td> 
                                            </tr>
                                            <tr>
                                                <div class="col-12 d-flex justify-content-end">
                                                    <td></td>
                                                    <td>
                                                        <button type="submit" class="btn btn-success me-1 mb-1"><a href="customer.php" style="color: white;">Back</a></button>
                                                        <button class="btn btn-danger me-1 mb-1"><?php echo "<a href = 'customer.php?block=$query[cus_id]' style='color:white;'>Block</a>"; ?></button>
                                                    </td>
                                                </div>
                                            </tr>
                                        </table>

                                        <!-- for block customer -->
                                            <?php }
                                        
                                            mysqli_close($conn);
                                            include ('connection.php');
                                            if (isset($_GET['block_id'])){
                                                $run=mysqli_query($conn,"SELECT c.cus_id, c.name, c.email, c.dob, c.gender, a.address_1, a.address_2, p.phone_1, p.phone_2 FROM tblcustomer AS c JOIN tbladdress AS a ON c.cus_id = a.cus_id 
                                                                    JOIN tblphone AS p ON p.cus_id = c.cus_id WHERE c.cus_id='$_GET[block_id]'");
                                                $query = mysqli_fetch_array($run);       
                                            
                                            ?>
                                        <table class="table" id="table2">
                                            <tr>
                                                <th>ID</th>
                                                <td><?php echo $query['cus_id'];?></td>
                                            </tr>
                                            <tr>
                                                <th>Customer Name</th>
                                                <td><?php echo $query['name'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Date of Birth</th>
                                                <td><?php echo $query['dob'];?></td> 
                                            </tr>
                                                <tr>
                                                <th>Gender</th>
                                                <td><?php echo $query['gender'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Email</th>
                                                <td><?php echo $query['email'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Address 1</th>
                                                <td><?php echo $query['address_1'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Address 2</th>
                                                <td><?php echo $query['address_2'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Phone Number 1</th>
                                                <td><?php echo $query['phone_1'];?></td> 
                                            </tr>
                                            <tr>
                                                <th>Phone Number 2</th>
                                                <td><?php echo $query['phone_2'];?></td> 
                                            </tr>
                                            <tr>
                                                <div class="col-12 d-flex justify-content-end">
                                                    <td></td>
                                                    <td>
                                                        <button type="submit" class="btn btn-success me-1 mb-1" ><a href="customer.php" style="color: white;">Back</a></button>
                                                        <button class="btn btn-danger me-1 mb-1"><?php echo "<a href='customer.php?unblock=$query[cus_id]' style='color:white;'>Unblock</a>"?></button>
                                                    </td>
                                                </div>
                                            </tr>
                                        </table>
                                        <?php } ?>
                                    </div>
                                </div>
                            </div> 
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </div>
    <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="assets/js/bootstrap.bundle.min.js"></script>

    <script src="assets/js/main.js"></script>
</body>

</html>