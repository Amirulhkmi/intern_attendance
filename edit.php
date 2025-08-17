<?php
session_start();
require 'db_connect.php';

// Only supervisor can view
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'supervisor') {
    header("Location: index.php");
    exit();
}

date_default_timezone_set('Asia/Kuala_Lumpur');
$today = date('Y-m-d');
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$user_id = isset($_GET['user_id']) ? (int)$_GET['user_id'] : 0;

if ($user_id <= 0) {
    header("Location: dashboard.php?page=$page");
    exit();
}

// Fetch existing attendance data
$sql_att = "SELECT clock_in, clock_out FROM attendance WHERE user_id = ? AND date = ?";
$stmt = $conn->prepare($sql_att);
$stmt->bind_param("is", $user_id, $today);
$stmt->execute();
$result_att = $stmt->get_result();
$attendance = $result_att->fetch_assoc();
$clock_in = $attendance ? $attendance['clock_in'] : null;
$clock_out = $attendance ? $attendance['clock_out'] : null;
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $time_in = $_POST['clock_in'] ? $_POST['clock_in'] : null;
    $time_out = $_POST['clock_out'] ? $_POST['clock_out'] : null;

    // Convert time to DATETIME with today's date
    $new_clock_in = $time_in ? date('Y-m-d H:i:s', strtotime("$today $time_in")) : null;
    $new_clock_out = $time_out ? date('Y-m-d H:i:s', strtotime("$today $time_out")) : null;

    if ($clock_in || $clock_out) {
        // Update existing record
        $sql_update = "UPDATE attendance SET clock_in = ?, clock_out = ? WHERE user_id = ? AND date = ?";
        $stmt = $conn->prepare($sql_update);
        $stmt->bind_param("ssis", $new_clock_in, $new_clock_out, $user_id, $today);
    } else {
        // Insert new record
        $sql_insert = "INSERT INTO attendance (user_id, date, clock_in, clock_out) VALUES (?, ?, ?, ?)";
        $stmt = $conn->prepare($sql_insert);
        $stmt->bind_param("isss", $user_id, $today, $new_clock_in, $new_clock_out);
    }
    if ($stmt->execute()) {
        header("Location: dashboard.php?page=$page");
    } else {
        echo "Error updating record: " . $conn->error;
    }
    $stmt->close();
    exit();
}

// Format existing times for display in input type="time"
$display_clock_in = $clock_in ? date('H:i', strtotime($clock_in)) : '';
$display_clock_out = $clock_out ? date('H:i', strtotime($clock_out)) : '';
?>

<!DOCTYPE html>
<html>
<head>
    <title>Edit Attendance</title>
    <link rel="stylesheet" href="css/styles.css">
    <style>
        .edit-form {
            max-width: 400px;
            margin: 20px auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .edit-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
        }
        .edit-form input[type="time"] {
            width: 100%;
            padding: 8px;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 4px;
        }
        .edit-form button {
            padding: 8px 15px;
            background: #3b82f6;
            color: white;
            border: none;
            border-radius: 20px;
            cursor: pointer;
        }
        .edit-form button:hover {
            background: #2563eb;
        }
        .error {
            color: #ef4444;
            margin-top: 10px;
        }
    </style>
</head>
<body>

<header class="top-header">
    <div class="logo">CDC Intern Attendence</div>
    <nav>
        <a href="dashboard.php?page=<?php echo $page; ?>">Dashboard</a>
        <a href="#">Interns</a>
        <a href="#">Reports</a>
    </nav>
    <div class="header-right">
        <span class="date-time"><?php echo date('l, F j, Y at h:i A'); ?></span>
        <span class="profile-circle">O</span>
    </div>
</header>

<section class="dashboard-content">
    <h1>Edit Attendance</h1>

    <div class="edit-form">
        <form method="POST">
            <label for="clock_in">Clock In Time:</label>
            <input type="time" id="clock_in" name="clock_in" value="<?php echo $display_clock_in; ?>">

            <label for="clock_out">Clock Out Time:</label>
            <input type="time" id="clock_out" name="clock_out" value="<?php echo $display_clock_out; ?>">

            <button type="submit">Save Changes</button>
            <?php if (isset($error)): ?>
                <div class="error"><?php echo $error; ?></div>
            <?php endif; ?>
        </form>
    </div>
</section>

</body>
</html>