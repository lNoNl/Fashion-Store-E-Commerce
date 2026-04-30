<?php
require 'header.php';

// --- 1. ตรวจสอบสิทธิ์ Admin ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit();
}

// --- 2. ตั้งค่าสำหรับ KPIs ด้านบน (เดือนปัจจุบัน) และสำหรับ Dropdowns
$start_date_kpi = date('Y-m-01');
$end_date_kpi = date('Y-m-t');
$status_cancelled = 'ยกเลิก';

$current_month = date('n');
$current_year = date('Y');
$thai_months = [
    1 => 'มกราคม', 2 => 'กุมภาพันธ์', 3 => 'มีนาคม', 4 => 'เมษายน',
    5 => 'พฤษภาคม', 6 => 'มิถุนายน', 7 => 'กรกฎาคม', 8 => 'สิงหาคม',
    9 => 'กันยายน', 10 => 'ตุลาคม', 11 => 'พฤศจิกายน', 12 => 'ธันวาคม'
];

// 3. ดึงข้อมูลสรุป KPIs ตามช่วงวันที่ที่เลือก
$kpi_sql = "
    SELECT 
        COALESCE(SUM(total_amount), 0) as total_revenue,
        COUNT(id) as total_orders,
        COALESCE(AVG(total_amount), 0) as average_order_value,
        (SELECT COALESCE(SUM(oi.quantity), 0) FROM order_items oi JOIN orders o2 ON oi.order_id = o2.id WHERE o2.order_status != ? AND DATE(o2.order_date) BETWEEN ? AND ?) as total_items_sold
    FROM orders 
    WHERE order_status != ? AND DATE(order_date) BETWEEN ? AND ?
";
$stmt_kpi = $conn->prepare($kpi_sql);
$stmt_kpi->bind_param("ssssss", $status_cancelled, $start_date_kpi, $end_date_kpi, $status_cancelled, $start_date_kpi, $end_date_kpi);
$stmt_kpi->execute();
$kpi_result = $stmt_kpi->get_result()->fetch_assoc();
$stmt_kpi->close();
?>

<main class="main-content">
<style>
    /* เพิ่มขอบสีดำให้กรอบด้านนอก (detail-card และ summary-card) ตามคำขอ */
    .detail-card,
    .summary-card {
        border: 1px solid #000;
    }
</style>
<div class="container">

    <div class="page-header">
        <h1>สรุปยอดขาย</h1>
        <div class="button-group">
            <a href="dashboard.php" class="btn btn--primary">📋 จัดการออเดอร์</a>
            <a href="manage_products.php" class="btn btn--primary">📦 จัดการสินค้า</a>
            <a href="manage_categories.php" class="btn btn--primary">🗂️ จัดการหมวดหมู่</a>
            <a href="add_product.php" class="btn btn--primary">＋ เพิ่มสินค้าใหม่</a>
        </div>
    </div>

    <div class="summary-grid">
        <div class="summary-card">
            <h4>ยอดขายรวม (เดือนนี้)</h4>
            <p>฿<?= number_format($kpi_result['total_revenue'], 0) ?></p>
        </div>
        <div class="summary-card">
            <h4>จำนวนออเดอร์ (เดือนนี้)</h4>
            <p><?= number_format($kpi_result['total_orders']) ?> รายการ</p>
        </div>
        <div class="summary-card">
            <h4>ยอดขายเฉลี่ย (เดือนนี้)</h4>
            <p>฿<?= number_format($kpi_result['average_order_value'], 0) ?></p>
        </div>
        <div class="summary-card">
            <h4>สินค้าที่ขายได้ (เดือนนี้)</h4>
            <p><?= number_format($kpi_result['total_items_sold']) ?> ชิ้น</p>
        </div>
    </div>

    <div class="detail-card mb-20" style="clear: both; margin-top: 20px;">
        <div class="card-header-flex">
            <h3>แนวโน้มยอดขาย</h3>
            <div class="button-group">
                <select class="form-control chart-filter-day" data-chart-id="salesTrend">
                    <option value="0">-- ทั้งเดือน --</option>
                    <?php for ($d = 1; $d <= 31; $d++): ?>
                        <option value="<?= $d ?>"><?= $d ?></option>
                    <?php endfor; ?>
                </select>
                <select class="form-control chart-filter-month" data-chart-id="salesTrend">
                    <?php foreach ($thai_months as $month_num => $month_name): ?>
                        <option value="<?= $month_num ?>" <?= ($month_num == $current_month) ? 'selected' : '' ?>><?= $month_name ?></option>
                    <?php endforeach; ?>
                </select>
                <select class="form-control chart-filter-year" data-chart-id="salesTrend">
                    <?php for ($year = $current_year; $year >= $current_year - 4; $year--): ?>
                        <option value="<?= $year ?>" <?= ($year == $current_year) ? 'selected' : '' ?>><?= $year ?></option>
                    <?php endfor; ?>
                </select>
            </div>
        </div>
        <div class="chart-container" style="height: 350px;">
            <canvas id="salesTrendChart"></canvas>
        </div>
    </div>

    <div class="dashboard-grid">
        <div class="detail-card">
            <div class="card-header-flex">
                <h3>10 อันดับสินค้าขายดี</h3>
                <div class="button-group">
                    <select class="form-control chart-filter-day" data-chart-id="topProducts">
                        <option value="0">-- ทั้งเดือน --</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                    <select class="form-control chart-filter-month" data-chart-id="topProducts">
                        <?php foreach ($thai_months as $month_num => $month_name): ?>
                            <option value="<?= $month_num ?>" <?= ($month_num == $current_month) ? 'selected' : '' ?>><?= $month_name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-control chart-filter-year" data-chart-id="topProducts">
                        <?php for ($year = $current_year; $year >= $current_year - 4; $year--): ?>
                            <option value="<?= $year ?>" <?= ($year == $current_year) ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="chart-container" style="height: 350px;">
                <canvas id="topProductsChart"></canvas>
            </div>
        </div>
        <div class="detail-card">
            <div class="card-header-flex">
                <h3>สัดส่วนยอดขายตามหมวดหมู่</h3>
                 <div class="button-group">
                    <select class="form-control chart-filter-day" data-chart-id="categorySales">
                        <option value="0">-- ทั้งเดือน --</option>
                        <?php for ($d = 1; $d <= 31; $d++): ?>
                            <option value="<?= $d ?>"><?= $d ?></option>
                        <?php endfor; ?>
                    </select>
                    <select class="form-control chart-filter-month" data-chart-id="categorySales">
                        <?php foreach ($thai_months as $month_num => $month_name): ?>
                            <option value="<?= $month_num ?>" <?= ($month_num == $current_month) ? 'selected' : '' ?>><?= $month_name ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select class="form-control chart-filter-year" data-chart-id="categorySales">
                        <?php for ($year = $current_year; $year >= $current_year - 4; $year--): ?>
                            <option value="<?= $year ?>" <?= ($year == $current_year) ? 'selected' : '' ?>><?= $year ?></option>
                        <?php endfor; ?>
                    </select>
                </div>
            </div>
            <div class="chart-container" style="height: 350px;">
                <canvas id="categorySalesChart"></canvas>
            </div>
        </div>
    </div>

</div> <!-- Close container -->
</main> <!-- Close main-content -->


<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns/dist/chartjs-adapter-date-fns.bundle.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function() {
    let salesTrendChartInstance, topProductsChartInstance, categorySalesChartInstance;

    const chartColors = [
        '#BFB4A0', '#D6CDBA', '#A69986', '#E6E0D7', '#8C7B64',
        '#C8BCA7', '#E0D8C9', '#B3A693', '#F0ECE3', '#7F6A51'
    ];

    const createChart = (instance, ctx, type, options) => {
        if (instance) instance.destroy();
        return new Chart(ctx, { type, ...options });
    };

    const getDateRange = (day, month, year) => {
        let startDate, endDate;
        if (day === '0' || day === null) { // If "All month" is selected
            startDate = `${year}-${String(month).padStart(2, '0')}-01`;
            const lastDay = new Date(year, month, 0).getDate();
            endDate = `${year}-${String(month).padStart(2, '0')}-${String(lastDay).padStart(2, '0')}`;
        } else { // If a specific day is selected
            startDate = endDate = `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
        }
        return { startDate, endDate };
    };

    const updateSalesTrendChart = (day, month, year) => {
        const { startDate, endDate } = getDateRange(day, month, year);
        const url = `sales_data_api.php?report_type=sales_trend&start_date=${startDate}&end_date=${endDate}&group_by=day`;
        
        fetch(url).then(res => res.json()).then(apiData => {
            const ctx = document.getElementById('salesTrendChart').getContext('2d');
            salesTrendChartInstance = createChart(salesTrendChartInstance, ctx, 'line', {
                data: {
                    labels: apiData.labels,
                    datasets: [{
                        label: 'ยอดขาย', data: apiData.data,
                        borderColor: '#BFB4A0', backgroundColor: 'rgba(191, 178, 160, 0.2)',
                        fill: true, tension: 0.3
                    }]
                },
                options: {
                    responsive: true, maintainAspectRatio: false,
                    scales: { y: { beginAtZero: true, ticks: { callback: v => '฿' + v.toLocaleString() } } }
                }
            });
        });
    };

    const updateTopProductsChart = (day, month, year) => {
        const { startDate, endDate } = getDateRange(day, month, year);
        const url = `sales_data_api.php?report_type=top_products&start_date=${startDate}&end_date=${endDate}`;
        
        fetch(url).then(res => res.json()).then(apiData => {
            const ctx = document.getElementById('topProductsChart').getContext('2d');
            topProductsChartInstance = createChart(topProductsChartInstance, ctx, 'bar', {
                data: {
                    labels: apiData.labels,
                    datasets: [{
                        label: 'ยอดขาย', data: apiData.data,
                        backgroundColor: chartColors,
                    }]
                },
                options: {
                    indexAxis: 'y', responsive: true, maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: { x: { ticks: { callback: v => '฿' + v.toLocaleString() } } }
                }
            });
        });
    };
    
    const updateCategorySalesChart = (day, month, year) => {
        const { startDate, endDate } = getDateRange(day, month, year);
        const url = `sales_data_api.php?report_type=category_sales&start_date=${startDate}&end_date=${endDate}`;
        
        fetch(url).then(res => res.json()).then(apiData => {
            const ctx = document.getElementById('categorySalesChart').getContext('2d');
            categorySalesChartInstance = createChart(categorySalesChartInstance, ctx, 'pie', {
                data: {
                    labels: apiData.labels,
                    datasets: [{
                        label: 'ยอดขาย', data: apiData.data,
                        backgroundColor: chartColors,
                    }]
                },
                options: { responsive: true, maintainAspectRatio: false }
            });
        });
    };

    // --- Initial Load ---
    const initialMonth = new Date().getMonth() + 1;
    const initialYear = new Date().getFullYear();
    updateSalesTrendChart('0', initialMonth, initialYear);
    updateTopProductsChart('0', initialMonth, initialYear);
    updateCategorySalesChart('0', initialMonth, initialYear);

    // --- Event Listeners ---
    const allFilters = document.querySelectorAll('.chart-filter-day, .chart-filter-month, .chart-filter-year');
    allFilters.forEach(filter => {
        filter.addEventListener('change', (e) => {
            const chartId = e.target.dataset.chartId;
            const day = document.querySelector(`.chart-filter-day[data-chart-id="${chartId}"]`).value;
            const month = document.querySelector(`.chart-filter-month[data-chart-id="${chartId}"]`).value;
            const year = document.querySelector(`.chart-filter-year[data-chart-id="${chartId}"]`).value;

            switch(chartId) {
                case 'salesTrend':
                    updateSalesTrendChart(day, month, year);
                    break;
                case 'topProducts':
                    updateTopProductsChart(day, month, year);
                    break;
                case 'categorySales':
                    updateCategorySalesChart(day, month, year);
                    break;
            }
        });
    });
});
</script>

<?php
?>