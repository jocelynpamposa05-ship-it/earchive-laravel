<?php
?>

<aside class="sidebar">
    <nav>
        <ul>
            <li class="<?php echo ($active_page == 'dashboard') ? 'active' : ''; ?>"><a href="dashboardadmin.php"><i class="fa-solid fa-home"></i> <span>DASHBOARD</span></a></li>
            <li class="<?php echo ($active_page == 'upload') ? 'active' : ''; ?>"><a href="uploadpaper.php"><i class="fa-solid fa-upload"></i> <span>UPLOAD PAPERS</span></a></li>
            <li class="<?php echo ($active_page == 'upload_student_id') ? 'active' : ''; ?>"><a href="upload_student_id.php"><i class="fa-solid fa-id-card"></i> <span>UPLOAD STUDENT ID</span></a></li>
            <li class="<?php echo ($active_page == 'collection') ? 'active' : ''; ?>"><a href="collectionadmin.php"><i class="fa-solid fa-book"></i> <span>COLLECTION</span></a></li>
            <li class="<?php echo ($active_page == 'users') ? 'active' : ''; ?>"><a href="usersadmin.php"><i class="fa-solid fa-users"></i> <span>USER ACCOUNTS</span></a></li>
            <li class="<?php echo ($active_page == 'reports') ? 'active' : ''; ?>"><a href="reportsadmin.php"><i class="fa-solid fa-chart-line"></i> <span>REPORTS</span></a></li>
            <li><a href="logout.php"><i class="fa-solid fa-sign-out-alt"></i> <span>LOG OUT</span></a></li>
        </ul>
    </nav>
</aside>