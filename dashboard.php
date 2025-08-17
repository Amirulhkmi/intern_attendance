<?php
session_start();
require 'db_connect.php';

// Only supervisor can view
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');

// Get today's date
$today = date('Y-m-d');

// Function to generate color from name
function nameToColor($name) {
    $hash = 0;
    for ($i = 0; $i < strlen($name); $i++) {
        $hash = ord($name[$i]) + (($hash << 5) - $hash);
    }
    $color = '#';
    for ($i = 0; $i < 3; $i++) {
        $value = ($hash >> ($i * 8)) & 0xFF;
        $color .= str_pad(dechex($value), 2, '0', STR_PAD_LEFT);
    }
    return $color;
}

// Fake departments for similarity
$depts = ['Internship'];

// Pagination
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$per_page = 10;
$offset = ($page - 1) * $per_page;

// Total students
$sql_total = "SELECT COUNT(*) FROM users WHERE role='intern'";
$total_students = $conn->query($sql_total)->fetch_row()[0];
$total_pages = ceil($total_students / $per_page);

// Stats
$stmt_present = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE DATE(clock_in) = ? AND TIME(clock_in) <= '09:00:00' AND clock_in IS NOT NULL");
$stmt_present->bind_param("s", $today);
$stmt_present->execute();
$present_today = $stmt_present->get_result()->fetch_row()[0];
$stmt_present->close();

$stmt_late = $conn->prepare("SELECT COUNT(*) FROM attendance WHERE DATE(clock_in) = ? AND TIME(clock_in) > '09:00:00' AND clock_in IS NOT NULL");
$stmt_late->bind_param("s", $today);
$stmt_late->execute();
$late_today = $stmt_late->get_result()->fetch_row()[0];
$stmt_late->close();

$checked_in = $present_today + $late_today;
$absent_today = $total_students - $checked_in;

// Fetch interns for current page
$sql_interns = "SELECT user_id, full_name FROM users WHERE role='intern' LIMIT $offset, $per_page";
$result_interns = $conn->query($sql_interns);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete']) && isset($_POST['user_id'])) {
    $user_id = $_POST['user_id'];
    $sql_delete = "DELETE FROM attendance WHERE user_id = ? AND DATE(clock_in) = ?";
    $stmt = $conn->prepare($sql_delete);
    $stmt->bind_param("is", $user_id, $today);
    $stmt->execute();
    $stmt->close();
    header("Location: dashboard.php?page=$page");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Supervisor Dashboard</title>
    <link rel="stylesheet" href="css/styles.css">
    <script>
        function confirmDelete(userId, fullName) {
            if (confirm("Are you sure you want to delete the attendance record for " + fullName + "?")) {
                var form = document.createElement('form');
                form.method = 'POST';
                form.action = '';
                var input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'delete';
                input.value = '1';
                form.appendChild(input);
                var inputUserId = document.createElement('input');
                inputUserId.type = 'hidden';
                inputUserId.name = 'user_id';
                inputUserId.value = userId;
                form.appendChild(inputUserId);
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</head>
<body>

<header class="top-header">
    <div class="logo">CDC Intern Attendence</div>
    <?php
$current_page = basename($_SERVER['PHP_SELF']); // e.g., 'report.php'
?>
<nav>
    <a href="dashboard.php" class="<?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>">Dashboard</a>
    <a href="internList.php" class="<?php echo $current_page === 'interns.php' ? 'active' : ''; ?>">Interns</a>
    <a href="report.php" class="<?php echo $current_page === 'report.php' ? 'active' : ''; ?>">Reports</a>
</nav>
    <div class="header-right">
         <span class="date-time"><?php echo date('l, F j, Y at h:i A'); ?></span>
         <span class="profile-circle">&#x1F464;</span>
    </div>
</header>

<section class="dashboard-content">
    <h1>Attendance Dashboard</h1>
    <p>Monitor and manage intern students attendance for today.</p>

    <div class="stats-container">
        <div class="stat-card">
            <span class="stat-icon total">&#x1F465;</span>
            <h3>Total intern</h3>
            <p><?php echo $total_students; ?></p>
        </div>
        <div class="stat-card">
            <span class="stat-icon present">&#x2705;</span>
            <h3>Present Today</h3>
            <p><?php echo $present_today; ?></p>
        </div>
        <div class="stat-card">
            <span class="stat-icon absent">&#x274C;</span>
            <h3>Absent Today</h3>
            <p><?php echo $absent_today; ?></p>
        </div>
        <div class="stat-card">
            <span class="stat-icon late">&#x1F550;</span>
            <h3>Late Arrivals</h3>
            <p><?php echo $late_today; ?></p>
        </div>
    </div>

    <div class="table-header">
        <h2>Attendance</h2>
        <input type="search" placeholder="Search ...">
        <button class="export-report">↓ Export Report</button>
    </div>

    <table>
        <thead>
            <tr>
                <th>STUDENT NAME ▲</th>
                <th>STATUS</th>
                <th>TIME IN</th>
                <th>TIME OUT</th>
                <th>REMARKS</th>
                <th>ACTIONS</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($intern = $result_interns->fetch_assoc()): 
                $user_id = $intern['user_id'];
                $full_name = $intern['full_name'];
                $names = explode(' ', $full_name);
                $initials = strtoupper(substr($names[0], 0, 1) . (isset($names[1]) ? substr($names[1], 0, 1) : ''));
                $color = nameToColor($full_name);
                $department = $depts[0];

                // Fetch attendance for today
                $sql_att = "SELECT clock_in, clock_out FROM attendance WHERE user_id = ? AND DATE(clock_in) = ?";
                $stmt = $conn->prepare($sql_att);
                $stmt->bind_param("is", $user_id, $today);
                $stmt->execute();
                $result_att = $stmt->get_result();
                if ($row = $result_att->fetch_assoc()) {
                    $clock_in = $row['clock_in'];
                    $clock_out = $row['clock_out'];
                    $time_in = $clock_in ? date('h:i A', strtotime($clock_in)) : '-';
                    $time_out = $clock_out ? date('h:i A', strtotime($clock_out)) : '-';
                    if ($clock_in) {
                        $clock_in_time = date('H:i:s', strtotime($clock_in));
                        $status = (strtotime($clock_in_time) > strtotime('09:00:00')) ? 'Late' : 'Present';
                    } else {
                        $status = 'Absent';
                        $time_in = '-';
                        $time_out = '-';
                    }
                } else {
                    $status = 'Absent';
                    $time_in = '-';
                    $time_out = '-';
                }

                // Compute remarks
                $remarks = '';
                if ($status == 'Absent') {
                    $remarks = '-';
                } elseif ($status == 'Late') {
                    $remarks = '-';
                } else {
                    if ($clock_in && strtotime($clock_in) < strtotime('08:30:00')) {
                        $remarks = 'Early arrival';
                    } else {
                        $remarks = 'On time';
                    }
                }
            ?>
            <tr>
                <td class="student-name">
                    <span class="avatar" style="background-color: <?php echo $color; ?>"><?php echo $initials; ?></span>
                    <div class="name-dept">
                        <span class="name"><?php echo $full_name; ?></span>
                        <span class="dept"><?php echo $department; ?></span>
                    </div>
                </td>
                <td><span class="status <?php echo strtolower($status); ?>"><?php echo $status; ?></span></td>
                <td><?php echo $time_in; ?></td>
                <td><?php echo $time_out; ?></td>
                <td><?php echo $remarks; ?></td>
                <td>
                    <a href="#" class="action-edit" onclick="window.location.href='edit.php?user_id=<?php echo $user_id; ?>&page=<?php echo $page; ?>'">Edit</a>
                    <a href="#" class="action-delete" onclick="confirmDelete(<?php echo $user_id; ?>, '<?php echo addslashes($full_name); ?>')">Delete</a>
                </td>
            </tr>
            <?php endwhile; ?>
        </tbody>
    </table>

    <div class="pagination">
        <span>Showing <?php echo $offset + 1; ?> to <?php echo min($offset + $per_page, $total_students); ?> of <?php echo $total_students; ?> results</span>
        <div class="page-links">
            <?php if ($page > 1): ?>
                <a href="?page=<?php echo $page - 1; ?>">Previous</a>
            <?php endif; ?>
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                <?php if ($i == $page): ?>
                    <span class="current"><?php echo $i; ?></span>
                <?php elseif ($i <= 3 || $i >= $total_pages - 2 || ($i >= $page - 1 && $i <= $page + 1)): ?>
                    <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                <?php elseif ($i == 4 && $page > 4): ?>
                    ...
                <?php elseif ($i == $total_pages - 3 && $page < $total_pages - 3): ?>
                    ...
                <?php endif; ?>
            <?php endfor; ?>
            <?php if ($page < $total_pages): ?>
                <a href="?page=<?php echo $page + 1; ?>">Next</a>
            <?php endif; ?>
        </div>
    </div>
</section>

</body>
</html>