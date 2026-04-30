<?php
// ========== Database Configuration ==========

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "la_maison_db"; // <-- ตรวจสอบให้แน่ใจว่าชื่อฐานข้อมูลถูกต้อง

// 1. ตั้งค่าให้ mysqli รายงานข้อผิดพลาดออกมาเป็น Exception
//    ซึ่งเป็นวิธีที่ทันสมัยและจัดการข้อผิดพลาดได้ดีกว่า
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    // 2. สร้างการเชื่อมต่อฐานข้อมูล
    $conn = new mysqli($servername, $username, $password, $dbname);

    // 3. ตั้งค่า Character Set เป็น utf8mb4 เพื่อรองรับภาษาไทยอย่างสมบูรณ์
    $conn->set_charset("utf8mb4");

} catch (mysqli_sql_exception $e) {
    // 4. หากการเชื่อมต่อล้มเหลว จะหยุดการทำงานและแสดงข้อความ Error
    //    (ในระบบจริง ควรซ่อนรายละเอียด Error และบันทึกเป็น Log แทน)
    // For development:
     die("<h3>Database Connection Failed</h3><p>Error: " . $e->getMessage() . "</p><p>Please check your database credentials in 'config.php' and ensure the database server is running.</p>");
    
    // For production (Recommended):
    // error_log("Database Connection Failed: " . $e->getMessage());
    // die("An unexpected error occurred. Please try again later.");
}

?>
