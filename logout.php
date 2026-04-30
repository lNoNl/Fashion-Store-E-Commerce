<?php
// 1. เริ่ม Session เป็นอย่างแรกเสมอ
session_start();

// 2. ล้างข้อมูลและทำลาย Session
session_unset();
session_destroy();

// 3. ส่งผู้ใช้กลับไปหน้าล็อกอินพร้อมข้อความแจ้งเตือน
header("Location: login.php?status=loggedout");
exit();
?>