<?php
session_start();
require 'db_connect.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = md5($_POST['password']); // Hashing for simple security

    $sql = "SELECT * FROM users WHERE username='$username' AND password='$password'";
    $result = $conn->query($sql);

    if ($result->num_rows == 1) {
        $row = $result->fetch_assoc();
        $_SESSION['user_id'] = $row['user_id'];
        $_SESSION['role']    = $row['role'];
        $_SESSION['name']    = $row['full_name'];

        if ($row['role'] == 'supervisor') {
            header("Location: dashboard.php");
        } else {
            header("Location: clock.php");
        }
        exit();
    } else {
        $error = "Invalid username or password.";
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Login - Intern Attendance</title>
    <link rel="stylesheet" href="css/style.css">
    <style>
        /* Inline styles for login-specific adjustments */
        .login-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #e0e7ff, #ffffff);
            padding: 20px;
        }
        .login-box {
            background: #ffffff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            width: 100%;
            max-width: 400px;
            text-align: center;
        }
        .login-box h2 {
            margin-bottom: 20px;
            color: #2d3748;
            font-size: 1.8rem;
            font-weight: 700;
        }
        .login-box .error {
            color: #dc2626;
            margin-bottom: 15px;
            font-size: 0.9rem;
        }
        .login-box label {
            display: block;
            text-align: left;
            margin-bottom: 5px;
            color: #4a5568;
            font-weight: 500;
        }
        .login-box input[type="text"],
        .login-box input[type="password"] {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            box-sizing: border-box;
            transition: border-color 0.3s ease;
        }
        .login-box input[type="text"]:focus,
        .login-box input[type="password"]:focus {
            border-color: #3b82f6;
            outline: none;
            box-shadow: 0 0 5px rgba(59, 130, 246, 0.3);
        }
        .login-box button {
            width: 100%;
            padding: 12px;
            background: #3b82f6;
            color: white;
            font-size: 1.1rem;
            font-weight: 500;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .login-box button:hover {
            background-color: #2563eb;
        }
        .login-box button:disabled {
            background-color: #9ca3af;
            cursor: not-allowed;
        }
        @media (max-width: 480px) {
            .login-box {
                padding: 20px;
            }
            .login-box h2 {
                font-size: 1.5rem;
            }
            .login-box button {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>

    <div class="login-container">
        <div class="login-box">
            <h2>Login</h2>
            <?php if (!empty($error)) echo "<p class='error'>$error</p>"; ?>
            <form method="post">
                <label>Username</label><br>
                <input type="text" name="username" required><br>
                <label>Password</label><br>
                <input type="password" name="password" required><br><br>
                <button type="submit">Login</button>
            </form>
        </div>
    </div>
</body>
</html>