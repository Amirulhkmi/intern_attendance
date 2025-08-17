<?php
session_start();
require 'db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'intern') {
    header("Location: index.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$date    = date('Y-m-d');

// Check if record exists for today
$sql = "SELECT * FROM attendance WHERE user_id='$user_id' AND date='$date'";
$result = $conn->query($sql);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['clock_in'])) {
        if ($result->num_rows == 0) {
            $conn->query("INSERT INTO attendance (user_id, clock_in, date) VALUES ('$user_id', NOW(), '$date')");
        }
    }
    if (isset($_POST['clock_out'])) {
        if ($result->num_rows > 0) {
            $conn->query("UPDATE attendance SET clock_out=NOW() WHERE user_id='$user_id' AND date='$date'");
        }
    }
    header("Location: clock.php");
    exit();
}

$row_today = ($result->num_rows > 0) ? $result->fetch_assoc() : null;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Clock In/Out</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/styles.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        body {
            background-color: #f3f4f6;
            color: #1f2937;
            line-height: 1.5;
            padding: 10px;
        }
        .header {
            background-color: #3b82f6;
            color: white;
            padding: 15px;
            text-align: center;
            border-radius: 10px 10px 0 0;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 1.5rem;
            margin: 0;
        }
        .content {
            max-width: 400px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .welcome {
            text-align: center;
            font-size: 1.2rem;
            margin-bottom: 20px;
            color: #2563eb;
        }
        .status {
            text-align: center;
            margin-bottom: 20px;
            font-size: 1rem;
        }
        .status p {
            margin: 5px 0;
        }
        
a.logout {
    display: block;
    text-align: center;
    padding: 10px;
    color: #dc2626; /* Red color for distinction */
    text-decoration: none;
    font-size: 1rem;
    transition: color 0.3s ease;
}

a.logout:hover {
    color: #b91c1c; /* Darker red on hover */
    text-decoration: underline;
}

@media (min-width: 600px) {
    a.logout {
        font-size: 1.1rem;
    }

}

    </style>
</head>
<body>
    <header class="header">
        <h1>Clock In/Out</h1>
    </header>
    <div class="content clock-container">
    <div class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['name']); ?>!</div>
    <div class="status">
        <?php if (!$row_today) { ?>
            <p>No clock-in record for today.</p>
        <?php } else { ?>
            <p>Clock In: <?php echo htmlspecialchars($row_today['clock_in']); ?></p>
            <?php if (!$row_today['clock_out']) { ?>
                <p>Clock Out: Not yet clocked out</p>
            <?php } else { ?>
                <p>Clock Out: <?php echo htmlspecialchars($row_today['clock_out']); ?></p>
            <?php } ?>
        <?php } ?>
    </div>
    <form method="post">
        <?php if (!$row_today) { ?>
            <button name="clock_in">Clock In</button>
        <?php } else { ?>
            <?php if (!$row_today['clock_out']) { ?>
                <button name="clock_out" onclick="return confirm('Are you sure you want to clock out?');">Clock Out</button>
            <?php } ?>
        <?php } ?>
    </form>
    <a href="logout.php" class="logout">Logout</a>
</div>
</body>
</html>