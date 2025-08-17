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

// Handle submitted data from report.php
$period_type = isset($_POST['period_type']) ? $_POST['period_type'] : 'weekly';
$start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-d', strtotime('sunday this week', strtotime($today)));
$end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-d', strtotime('saturday this week', strtotime($today)));
if ($period_type === 'monthly') {
    $start_date = isset($_POST['start_date']) ? $_POST['start_date'] : date('Y-m-01');
    $end_date = isset($_POST['end_date']) ? $_POST['end_date'] : date('Y-m-t', strtotime($today));
}

?>

<!DOCTYPE html>
<html>
<head>
    <title>Attendance Export</title>
    <link rel="stylesheet" href="https://cdn.datatables.net/1.10.22/css/jquery.dataTables.min.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/buttons/1.6.5/css/buttons.dataTables.min.css">
    <style>
        .modern-table th, .modern-table td {
            padding: 8px 12px;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        .modern-table th {
            background-color: #3b82f6;
            color: white;
            font-weight: 600;
        }
        .modern-table .status-present {
            background: #d1fae5;
            color: #065f46;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .modern-table .status-late {
            background: #fef3c7;
            color: #92400e;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .modern-table .status-absent {
            background: #fee2e2;
            color: #991b1b;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .modern-table .status-off {
            background: #e5e7eb;
            color: #6b7280;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .modern-table th .day-date {
            display: block;
            font-size: 0.7rem;
            text-transform: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2>Attendance Export</h2>
        <p><?php echo ucfirst($period_type) . ' of ' . date('F d, Y', strtotime($start_date)) . ' - ' . date('F d, Y', strtotime($end_date)); ?></p>
        <?php if ($period_type === 'weekly'): ?>
        <table class="modern-table" id="attendanceTable">
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
                        $date = date('M d', strtotime($week_dates[$day]));
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
        <table class="modern-table" id="attendanceTable">
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
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.js"></script>
    <script src="https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/dataTables.buttons.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/3.1.3/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/pdfmake.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdfmake/0.1.53/vfs_fonts.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.html5.min.js"></script>
    <script src="https://cdn.datatables.net/buttons/1.6.5/js/buttons.print.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#attendanceTable').DataTable({
                dom: 'lBfitp',
                buttons: ['copy', 'csv', 'excel', 'pdf', 'print'],
                pageLength: 10,
                ordering: false,
                paging: true,
                lengthMenu: [10, 25, 50],
                language: {
                    paginate: {
                        previous: '',
                        next: ''
                    }
                }
            });
        });
    </script>
</body>
</html>