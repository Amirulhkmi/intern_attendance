<?php
session_start();
require 'db_connect.php';

// Only supervisor can view
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

// Get current date: Wednesday, August 13, 2025, 11:38 AM +08
$today = date('Y-m-d');
$current_week_start = date('Y-m-d', strtotime('sunday this week', strtotime($today)));
$current_week_end = date('Y-m-d', strtotime('saturday this week', strtotime($today)));
$current_month_start = date('Y-m-01');
$current_month_end = date('Y-m-t', strtotime($today));

// Handle form submission
$period_type = isset($_POST['period_type']) ? $_POST['period_type'] : 'weekly'; // Default to weekly
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : ($period_type === 'monthly' ? $current_month_start : $current_week_start);
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : ($period_type === 'monthly' ? $current_month_end : $current_week_end);

// Handle period navigation
if (isset($_POST['previous_period'])) {
    if ($period_type === 'weekly') {
        $start_date = date('Y-m-d', strtotime('-1 week', strtotime($start_date)));
        $end_date = date('Y-m-d', strtotime('-1 week +6 days', strtotime($end_date)));
    } elseif ($period_type === 'monthly') {
        $start_date = date('Y-m-01', strtotime('-1 month', strtotime($start_date)));
        $end_date = date('Y-m-t', strtotime($start_date));
    }
} elseif (isset($_POST['next_period'])) {
    if ($period_type === 'weekly') {
        $start_date = date('Y-m-d', strtotime('+1 week', strtotime($start_date)));
        $end_date = date('Y-m-d', strtotime('+1 week +6 days', strtotime($end_date)));
    } elseif ($period_type === 'monthly') {
        $start_date = date('Y-m-01', strtotime('+1 month', strtotime($start_date)));
        $end_date = date('Y-m-t', strtotime($start_date));
    }
    // Prevent future dates
    if (strtotime($end_date) > strtotime($today)) {
        $start_date = $current_week_start;
        $end_date = $current_week_end;
        if ($period_type === 'monthly') {
            $start_date = $current_month_start;
            $end_date = $current_month_end;
        }
    }
}

// Generate week days for weekly report and count workdays
$week_days = [];
$workday_count = 0;
$current_day = strtotime($start_date);
$end_day = strtotime($end_date);
while ($current_day <= $end_day) {
    $date = date('Y-m-d', $current_day);
    $day_name = date('l', $current_day);
    $day_of_week = date('w', $current_day); // 0 (Sunday) to 6 (Saturday)
    if ($day_of_week >= 0 && $day_of_week <= 4) { // Sunday (0) to Thursday (4) are workdays
        $week_days[$date] = $day_name;
        $workday_count++;
    }
    $current_day = strtotime('+1 day', $current_day);
}

// Total interns
$sql_total = "SELECT COUNT(*) FROM users WHERE role='intern'";
$total_interns = $conn->query($sql_total)->fetch_row()[0];

// Weekly/Monthly stats (exclude Friday and Saturday)
$sql_present = "SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(clock_in) BETWEEN ? AND ? AND TIME(clock_in) <= '09:00:00' AND clock_in IS NOT NULL AND DAYOFWEEK(clock_in) NOT IN (6, 7)";
$stmt_present = $conn->prepare($sql_present);
$stmt_present->bind_param("ss", $start_date, $end_date);
$stmt_present->execute();
$present_days = $stmt_present->get_result()->fetch_row()[0];
$stmt_present->close();

$sql_late = "SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(clock_in) BETWEEN ? AND ? AND TIME(clock_in) > '09:00:00' AND clock_in IS NOT NULL AND DAYOFWEEK(clock_in) NOT IN (6, 7)";
$stmt_late = $conn->prepare($sql_late);
$stmt_late->bind_param("ss", $start_date, $end_date);
$stmt_late->execute();
$late_days = $stmt_late->get_result()->fetch_row()[0];
$stmt_late->close();

$sql_attended = "SELECT COUNT(DISTINCT user_id) FROM attendance WHERE DATE(clock_in) BETWEEN ? AND ? AND DAYOFWEEK(clock_in) NOT IN (6, 7)";
$stmt_attended = $conn->prepare($sql_attended);
$stmt_attended->bind_param("ss", $start_date, $end_date);
$stmt_attended->execute();
$attended_interns = $stmt_attended->get_result()->fetch_row()[0];
$stmt_attended->close();

// Calculate absent days based on workdays
$expected_attendance = $workday_count * $total_interns; // Total possible attendance days for all interns
$actual_attendance = $attended_interns; // Total interns who attended on any workday
$absent_days = max(0, $expected_attendance - $actual_attendance); // Ensure no negative values
?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Report</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .report-filter {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            align-items: center;
        }
        .report-filter select, .report-filter input {
            padding: 8px 12px;
            border: 1px solid #3b82f6;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        .modern-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .modern-table th, .modern-table td {
            padding: 12px 15px;
            text-align: center;
            border-bottom: 1px solid #e5e7eb;
        }
        .modern-table th {
            background: #3b82f6;
            color: white;
            text-transform: uppercase;
            font-size: 0.9rem;
            font-weight: 600;
            position: sticky;
            top: 0;
            z-index: 1;
        }
        .modern-table td {
            font-size: 0.95rem;
            color: #333;
        }
        .modern-table th .day-date {
            display: block;
            font-size: 0.7rem;
            text-transform: none;
        }
        .modern-table tr:hover td {
            background: #f3f4f6;
        }
        .status-present {
            background: #d1fae5;
            color: #065f46;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .status-late {
            background: #fef3c7;
            color: #92400e;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .status-absent {
            background: #fee2e2;
            color: #991b1b;
            padding: 4px 8px;
            border-radius: 12px;
        }
        .status-off {
            background: #e5e7eb;
            color: #6b7280;
            padding: 4px 8px;
            border-radius: 12px;
        }
        /* Period navigation buttons styling */
        .period-nav-btn {
            padding: 8px 15px;
            background: white;
            color: #3b82f6;
            border: 2px solid #3b82f6;
            border-radius: 20px;
            cursor: pointer;
            font-size: 0.9rem;
            box-shadow: 0 0 10px rgba(59, 130, 246, 0.5); /* Glowing blue circumference */
            transition: box-shadow 0.3s ease;
        }
        .period-nav-btn:hover {
            box-shadow: 0 0 15px rgba(59, 130, 246, 0.7); /* Enhanced glow on hover */
        }
        @media (max-width: 600px) {
            .report-filter {
                flex-direction: column;
                gap: 15px;
            }
            .modern-table th, .modern-table td {
                padding: 10px;
                font-size: 0.85rem;
            }
            .modern-table th .day-date {
                font-size: 0.6rem;
            }
            .period-nav-btn {
                padding: 6px 12px;
                font-size: 0.8rem;
            }
        }
    </style>
</head>
<body>

<header class="top-header">
    <div class="logo">CDC Intern Attendence</div>
    <?php
    $current_page = basename($_SERVER['PHP_SELF']); // e.g., 'report.php'
    ?>
    <nav>
        <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
        <a href="#" class="<?php echo $current_page === 'interns.php' ? 'active' : ''; ?>">Interns</a>
        <a href="report.php" class="<?php echo $current_page === 'report.php' ? 'active' : ''; ?>">Reports</a>
    </nav>
    <div class="header-right">
        <span class="date-time"><?php echo date('l, F j, Y at h:i A'); ?></span>
        <span class="profile-circle">&#x1F464;</span>
    </div>
</header>

<section class="dashboard-content">
    <h1>Attendance Report</h1>
    <p>Summary of intern attendance for the selected period.</p>

    <form method="POST" class="report-filter">
        <select name="period_type" onchange="this.form.submit()">
            <option value="weekly" <?php echo $period_type === 'weekly' ? 'selected' : ''; ?>>Weekly</option>
            <option value="monthly" <?php echo $period_type === 'monthly' ? 'selected' : ''; ?>>Monthly</option>
        </select>
        <?php if ($period_type === 'monthly'): ?>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" onchange="this.form.submit()">
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" onchange="this.form.submit()">
        <?php endif; ?>
    </form>

    <?php if ($period_type === 'weekly'): ?>
    <div class="table-header">
        <h2>Weekly Attendance Details</h2>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="period_type" value="weekly">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            <input type="hidden" name="previous_period" value="1">
            <button type="submit" class="period-nav-btn">Previous Week</button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="period_type" value="weekly">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            <input type="hidden" name="next_period" value="1">
            <button type="submit" class="period-nav-btn">Next Week</button>
        </form>
        <form method="POST" action="export.php" style="display: inline;">
            <input type="hidden" name="period_type" value="weekly">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            <button type="submit" class="export-report">↓ Export Report</button>
        </form>
    </div>
    <table class="modern-table">
        <thead>
            <tr>
                <th>Intern Name</th>
                <?php
                $days_of_week = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
                $week_dates = [];
                $current_day = strtotime($start_date);
                for ($i = 0; $i < 7; $i++) {
                    $week_dates[$days_of_week[$i]] = date('Y-m-d', $current_day);
                    $current_day = strtotime('+1 day', $current_day);
                }
                foreach ($days_of_week as $day) {
                    $date = date('M d', strtotime($week_dates[$day])); // e.g., "Aug 10"
                ?>
                    <th>
                        <?php echo $day; ?>
                        <span class="day-date"><?php echo $date; ?></span>
                    </th>
                <?php } ?>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql_interns = "SELECT user_id, full_name FROM users WHERE role='intern'";
            $result_interns = $conn->query($sql_interns);

            while ($intern = $result_interns->fetch_assoc()) {
                $user_id = $intern['user_id'];
                $full_name = $intern['full_name'];
            ?>
            <tr>
                <td><?php echo $full_name; ?></td>
                <?php foreach ($days_of_week as $day): 
                    $date = $week_dates[$day];
                    $sql_att = "SELECT clock_in FROM attendance WHERE user_id = ? AND DATE(clock_in) = ?";
                    $stmt_att = $conn->prepare($sql_att);
                    $stmt_att->bind_param("is", $user_id, $date);
                    $stmt_att->execute();
                    $result_att = $stmt_att->get_result();
                    $status = $result_att->num_rows ? 
                        (in_array($day, ['Friday', 'Saturday']) ? 'Off Day' : 
                            (strtotime(date('H:i:s', strtotime($result_att->fetch_assoc()['clock_in']))) <= strtotime('09:00:00') ? 'Present' : 'Late')) : 
                        (in_array($day, ['Friday', 'Saturday']) ? 'Off Day' : 'Absent');
                    $stmt_att->close();
                ?>
                <td><span class="status-<?php echo strtolower(str_replace(' ', '-', $status)); ?>"><?php echo $status; ?></span></td>
                <?php endforeach; ?>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php endif; ?>

    <?php if ($period_type === 'monthly'): ?>
    <div class="table-header">
        <h2>Monthly Attendance Summary</h2>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="period_type" value="monthly">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            <input type="hidden" name="previous_period" value="1">
            <button type="submit" class="period-nav-btn">Previous Month</button>
        </form>
        <form method="POST" style="display: inline;">
            <input type="hidden" name="period_type" value="monthly">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            <input type="hidden" name="next_period" value="1">
            <button type="submit" class="period-nav-btn">Next Month</button>
        </form>
        <form method="POST" action="export.php" style="display: inline;">
            <input type="hidden" name="period_type" value="monthly">
            <input type="hidden" name="start_date" value="<?php echo $start_date; ?>">
            <input type="hidden" name="end_date" value="<?php echo $end_date; ?>">
            <button type="submit" class="export-report">↓ Export Report</button>
        </form>
    </div>
    <table class="modern-table">
        <thead>
            <tr>
                <th>Intern Name</th>
                <th>Present Days</th>
                <th>Late Days</th>
                <th>Absent Days</th>
            </tr>
        </thead>
        <tbody>
            <?php
            $sql_interns = "SELECT user_id, full_name FROM users WHERE role='intern'";
            $result_interns = $conn->query($sql_interns);
            while ($intern = $result_interns->fetch_assoc()) {
                $user_id = $intern['user_id'];
                $full_name = $intern['full_name'];

                $sql_present_count = "SELECT COUNT(DISTINCT DATE(clock_in)) FROM attendance WHERE user_id = ? AND DATE(clock_in) BETWEEN ? AND ? AND TIME(clock_in) <= '09:00:00' AND clock_in IS NOT NULL AND DAYOFWEEK(clock_in) NOT IN (6, 7)";
                $stmt_present_count = $conn->prepare($sql_present_count);
                $stmt_present_count->bind_param("iss", $user_id, $start_date, $end_date);
                $stmt_present_count->execute();
                $present_count = $stmt_present_count->get_result()->fetch_row()[0];
                $stmt_present_count->close();

                $sql_late_count = "SELECT COUNT(DISTINCT DATE(clock_in)) FROM attendance WHERE user_id = ? AND DATE(clock_in) BETWEEN ? AND ? AND TIME(clock_in) > '09:00:00' AND clock_in IS NOT NULL AND DAYOFWEEK(clock_in) NOT IN (6, 7)";
                $stmt_late_count = $conn->prepare($sql_late_count);
                $stmt_late_count->bind_param("iss", $user_id, $start_date, $end_date);
                $stmt_late_count->execute();
                $late_count = $stmt_late_count->get_result()->fetch_row()[0];
                $stmt_late_count->close();

                $sql_attended_count = "SELECT COUNT(DISTINCT DATE(clock_in)) FROM attendance WHERE user_id = ? AND DATE(clock_in) BETWEEN ? AND ? AND DAYOFWEEK(clock_in) NOT IN (6, 7)";
                $stmt_attended_count = $conn->prepare($sql_attended_count);
                $stmt_attended_count->bind_param("iss", $user_id, $start_date, $end_date);
                $stmt_attended_count->execute();
                $attended_count = $stmt_attended_count->get_result()->fetch_row()[0];
                $stmt_attended_count->close();

                // Calculate workdays in the period
                $workday_count = 0;
                $current_day = strtotime($start_date);
                while ($current_day <= strtotime($end_date)) {
                    $day_of_week = date('w', $current_day);
                    if ($day_of_week >= 0 && $day_of_week <= 4) { // Sunday (0) to Thursday (4)
                        $workday_count++;
                    }
                    $current_day = strtotime('+1 day', $current_day);
                }
                $absent_count = max(0, $workday_count - $attended_count); // Exclude off days
            ?>
            <tr>
                <td><?php echo $full_name; ?></td>
                <td><?php echo $present_count; ?></td>
                <td><?php echo $late_count; ?></td>
                <td><?php echo $absent_count; ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php endif; ?>
</section>

</body>
</html>