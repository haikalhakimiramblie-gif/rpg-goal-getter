<?php
// PHP code for registration - MUST BE AT THE VERY TOP
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Database connection
$host = "localhost";
$username = "root";
$password = "";
$database = "rpg_goal_getter";

// Initialize variables
$error = "";
$success = "";
$show_loading = false;

// Try to connect to database
$conn = @mysqli_connect($host, $username, $password, $database);

// Process registration form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $gender = trim($_POST['gender']);
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate input
    if (empty($username) || empty($email) || empty($password) || empty($confirm_password) || empty($gender)) {
        $error = "All fields are required, brave adventurer!";
    } elseif ($password !== $confirm_password) {
        $error = "Your magical passwords do not match!";
    } elseif (strlen($password) < 6) {
        $error = "Your password must be at least 6 characters long!";
    } elseif (strlen($username) < 3) {
        $error = "Your hero name must be at least 3 characters long!";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid scroll address (email)!";
    } else {
        if (!$conn) {
            // Demo registration (no database)
            $success = "Registration successful! Welcome to the realm, $username!";
            $show_loading = true;
            
            // Create demo session
            $_SESSION['user_id'] = rand(1000, 9999);
            $_SESSION['username'] = $username;
            $_SESSION['email'] = $email;
            $_SESSION['gender'] = $gender;
            $_SESSION['level'] = 1;
            $_SESSION['exp'] = 0;
            $_SESSION['exp_needed'] = 100;
            
            // Redirect to dashboard after loading animation
            echo "<script>
                setTimeout(function() {
                    window.location.href = 'dashboard.php';
                }, 4000);
            </script>";
        } else {
            // Check if username or email already exists
            $check_sql = "SELECT id FROM users WHERE username = ? OR email = ?";
            $check_stmt = mysqli_prepare($conn, $check_sql);
            mysqli_stmt_bind_param($check_stmt, "ss", $username, $email);
            mysqli_stmt_execute($check_stmt);
            $check_result = mysqli_stmt_get_result($check_stmt);
            
            if (mysqli_num_rows($check_result) > 0) {
                // Check which one exists
                $existing_user = mysqli_fetch_assoc($check_result);
                $check_sql2 = "SELECT username, email FROM users WHERE username = ? OR email = ?";
                $check_stmt2 = mysqli_prepare($conn, $check_sql2);
                mysqli_stmt_bind_param($check_stmt2, "ss", $username, $email);
                mysqli_stmt_execute($check_stmt2);
                $check_result2 = mysqli_stmt_get_result($check_stmt2);
                
                $username_exists = false;
                $email_exists = false;
                
                while ($row = mysqli_fetch_assoc($check_result2)) {
                    if ($row['username'] === $username) {
                        $username_exists = true;
                    }
                    if ($row['email'] === $email) {
                        $email_exists = true;
                    }
                }
                
                if ($username_exists && $email_exists) {
                    $error = "Both hero name and scroll address are already taken!";
                } elseif ($username_exists) {
                    $error = "A hero with that name already exists in the realm!";
                } else {
                    $error = "A hero with that scroll address already exists in the realm!";
                }
                
                mysqli_stmt_close($check_stmt2);
            } else {
                // Hash password for security
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert new user with gender
                $insert_sql = "INSERT INTO users (username, email, password, gender, level, exp, exp_needed, created_at) VALUES (?, ?, ?, ?, 1, 0, 100, NOW())";
                $insert_stmt = mysqli_prepare($conn, $insert_sql);
                mysqli_stmt_bind_param($insert_stmt, "ssss", $username, $email, $hashed_password, $gender);
                
                if (mysqli_stmt_execute($insert_stmt)) {
                    $success = "Registration successful! Welcome to the realm, $username!";
                    $show_loading = true;
                    
                    // Get the new user ID and create session
                    $user_id = mysqli_insert_id($conn);
                    $_SESSION['user_id'] = $user_id;
                    $_SESSION['username'] = $username;
                    $_SESSION['email'] = $email;
                    $_SESSION['gender'] = $gender;
                    $_SESSION['level'] = 1;
                    $_SESSION['exp'] = 0;
                    $_SESSION['exp_needed'] = 100;
                    
                    // Also initialize user stats
                    $stats_sql = "INSERT INTO user_stats (user_id) VALUES (?)";
                    $stats_stmt = mysqli_prepare($conn, $stats_sql);
                    mysqli_stmt_bind_param($stats_stmt, "i", $user_id);
                    mysqli_stmt_execute($stats_stmt);
                    mysqli_stmt_close($stats_stmt);
                    
                    // Get initial stats for loading screen
                    $initial_stats = [
                        'level' => 1,
                        'exp' => 0,
                        'exp_needed' => 100,
                        'total_goals' => 0,
                        'total_exp_earned' => 0
                    ];
                    
                    // Store stats for JavaScript
                    echo "<script>const userStats = " . json_encode($initial_stats) . ";</script>";
                    
                    // Redirect to dashboard after loading animation
                    echo "<script>
                        setTimeout(function() {
                            window.location.href = 'dashboard.php';
                        }, 4000);
                    </script>";
                } else {
                    $error = "A mysterious error occurred while creating your character!";
                }
                
                mysqli_stmt_close($insert_stmt);
            }
            
            mysqli_stmt_close($check_stmt);
        }
    }
}

if ($conn) {
    mysqli_close($conn);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Begin Your Quest</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=EB+Garamond:wght@400;500;600&family=MedievalSharp&family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --bg-primary: #0a050a;
            --bg-secondary: #1a0f1a;
            --accent-gold: #d4af37;
            --accent-gold-light: #f0e68c;
            --accent-gold-dark: #8b6914;
            --text-primary: #e6d6b3;
            --text-secondary: #b8860b;
            --text-dark: #2c1810;
            --text-light: #fff8dc;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Custom Cursor */
        .cursor {
            position: fixed;
            width: 32px;
            height: 32px;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.1s ease;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><path fill="%23d4af37" d="M16 2L2 16l14 14 14-14L16 2zm0 4l10 10-10 10L6 16 16 6z"/></svg>') center/contain no-repeat;
        }

        .cursor-follower {
            position: fixed;
            width: 16px;
            height: 16px;
            background: rgba(212, 175, 55, 0.3);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            transition: transform 0.15s ease;
        }

        .cursor.clicked {
            animation: cursorClick 0.3s ease;
        }

        @keyframes cursorClick {
            0% { transform: scale(1); }
            50% { transform: scale(0.8); }
            100% { transform: scale(1); }
        }

        body {
            background: radial-gradient(ellipse at top, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: 'EB Garamond', serif;
            transition: background 2s ease;
            cursor: none;
        }

        /* RPG Loading Screen */
        .rpg-loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: radial-gradient(ellipse at center, #1a0f1a 0%, #0a050a 100%);
            z-index: 10000;
            display: none;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .rpg-loading-screen.active {
            display: flex;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .loading-portal {
            width: 200px;
            height: 200px;
            border: 3px solid var(--accent-gold);
            border-radius: 50%;
            position: relative;
            margin-bottom: 40px;
            animation: portalSpin 4s linear infinite;
            box-shadow: 
                0 0 30px var(--accent-gold),
                inset 0 0 30px var(--accent-gold);
        }

        @keyframes portalSpin {
            0% { transform: rotate(0deg) scale(1); }
            50% { transform: rotate(180deg) scale(1.1); }
            100% { transform: rotate(360deg) scale(1); }
        }

        .portal-inner {
            position: absolute;
            top: 20px;
            left: 20px;
            right: 20px;
            bottom: 20px;
            border: 2px solid var(--accent-gold-light);
            border-radius: 50%;
            animation: portalInner 3s linear infinite reverse;
        }

        @keyframes portalInner {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(-360deg); }
        }

        .loading-dragon {
            font-size: 4rem;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            animation: dragonFloat 2s ease-in-out infinite;
            filter: drop-shadow(0 0 10px var(--accent-gold));
        }

        @keyframes dragonFloat {
            0%, 100% { transform: translate(-50%, -50%) scale(1); }
            50% { transform: translate(-50%, -60%) scale(1.1); }
        }

        .loading-text {
            font-family: 'Cinzel', serif;
            font-size: 2rem;
            color: var(--accent-gold);
            text-align: center;
            margin-bottom: 20px;
            text-shadow: 0 0 10px var(--accent-gold);
            animation: textGlow 2s ease-in-out infinite;
        }

        @keyframes textGlow {
            0%, 100% { text-shadow: 0 0 10px var(--accent-gold); }
            50% { text-shadow: 0 0 20px var(--accent-gold), 0 0 30px var(--accent-gold-light); }
        }

        .loading-subtext {
            font-size: 1.2rem;
            color: var(--text-primary);
            text-align: center;
            margin-bottom: 30px;
            max-width: 500px;
            line-height: 1.6;
        }

        .loading-progress {
            width: 300px;
            height: 8px;
            background: rgba(212, 175, 55, 0.2);
            border-radius: 4px;
            overflow: hidden;
            position: relative;
        }

        .loading-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--accent-gold-dark), var(--accent-gold), var(--accent-gold-light));
            border-radius: 4px;
            width: 0%;
            animation: loadingProgress 3s ease-in-out forwards;
            box-shadow: 0 0 10px var(--accent-gold);
        }

        @keyframes loadingProgress {
            0% { width: 0%; }
            20% { width: 25%; }
            40% { width: 50%; }
            60% { width: 75%; }
            80% { width: 90%; }
            100% { width: 100%; }
        }

        .loading-stats {
            display: flex;
            gap: 30px;
            margin-top: 30px;
            opacity: 0;
            animation: statsFadeIn 0.5s ease 1s forwards;
        }

        @keyframes statsFadeIn {
            to { opacity: 1; }
        }

        .stat-item {
            text-align: center;
        }

        .stat-value {
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            color: var(--accent-gold);
            text-shadow: 0 0 5px var(--accent-gold);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-top: 5px;
        }

        .loading-quote {
            position: absolute;
            bottom: 50px;
            font-style: italic;
            color: var(--text-secondary);
            text-align: center;
            max-width: 600px;
            opacity: 0;
            animation: quoteFadeIn 0.5s ease 2s forwards;
        }

        @keyframes quoteFadeIn {
            to { opacity: 1; }
        }

        /* Main Container */
        .main-container {
            position: relative;
            height: 1000vh;
        }

        /* Fixed Background */
        .fixed-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: 
                linear-gradient(135deg, 
                    rgba(255, 255, 255, 0.1) 0%,
                    rgba(255, 255, 255, 0.05) 50%,
                    rgba(255, 255, 255, 0.1) 100%),
                url('images/register.png');
            background-size: cover;
            background-position: center;
            opacity: 0.9;
            filter: brightness(1.3) contrast(1.1);
            z-index: 1;
        }

        /* Moving Bats */
        .moving-elements {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 2;
            pointer-events: none;
        }

        .bat {
            position: absolute;
            background: url('images/bat.png') center/contain no-repeat;
            opacity: 0.7;
            filter: drop-shadow(0 0 15px rgba(180, 160, 220, 0.8)) brightness(1.2);
        }

        /* Bat Animations */
        .bat-1 {
            width: 60px;
            height: 40px;
            top: 15%;
            left: 5%;
            animation: batFly1 12s ease-in-out infinite;
        }

        .bat-2 {
            width: 50px;
            height: 35px;
            top: 75%;
            left: 85%;
            animation: batFly2 14s ease-in-out infinite;
            animation-delay: 1s;
        }

        .bat-3 {
            width: 70px;
            height: 45px;
            top: 50%;
            left: 90%;
            animation: batFly3 16s ease-in-out infinite;
            animation-delay: 3s;
        }

        @keyframes batFly1 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.5; }
            25% { transform: translate(100px, 50px) rotate(10deg); opacity: 0.8; }
            50% { transform: translate(200px, -30px) rotate(-5deg); opacity: 0.6; }
            75% { transform: translate(150px, 70px) rotate(15deg); opacity: 0.9; }
        }

        @keyframes batFly2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.6; }
            25% { transform: translate(-80px, 40px) rotate(-10deg); opacity: 0.9; }
            50% { transform: translate(-150px, -20px) rotate(5deg); opacity: 0.5; }
            75% { transform: translate(-100px, 60px) rotate(-15deg); opacity: 0.8; }
        }

        @keyframes batFly3 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.4; }
            25% { transform: translate(120px, -20px) rotate(5deg); opacity: 0.7; }
            50% { transform: translate(80px, 50px) rotate(-10deg); opacity: 0.8; }
            75% { transform: translate(140px, 30px) rotate(8deg); opacity: 0.6; }
        }

        /* Story Images Container */
        .story-container {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 3;
            pointer-events: none;
        }

        .story-image {
            position: absolute;
            top: 50%;
            left: 70%; /* Positioned on the right */
            transform: translate(-50%, -50%);
            opacity: 0;
            transition: opacity 0.5s ease;
            object-fit: contain;
            pointer-events: none;
        }

        .story-image.active {
            opacity: 1;
        }

        /* Progress Indicator */
        .scroll-progress {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 10;
            background: rgba(40, 20, 40, 0.8);
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            padding: 15px;
            backdrop-filter: blur(10px);
        }

        .progress-text {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 0.9rem;
            text-align: center;
            margin-bottom: 8px;
        }

        .progress-dots {
            display: flex;
            gap: 5px;
            justify-content: center;
        }

        .progress-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--accent-gold-dark);
            transition: all 0.3s ease;
        }

        .progress-dot.active {
            background: var(--accent-gold);
            transform: scale(1.2);
            box-shadow: 0 0 10px var(--accent-gold);
        }

        /* Register Form - Positioned on the left */
        .register-form-container {
            position: fixed;
            top: 50%;
            left: 25%; /* Positioned on the left */
            transform: translate(-50%, -50%) scale(0.8);
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.95) 0%, rgba(25, 10, 25, 0.98) 100%);
            border: 3px solid var(--accent-gold);
            border-radius: 15px;
            padding: 40px;
            width: 90%;
            max-width: 500px;
            backdrop-filter: blur(20px);
            box-shadow: 0 0 50px rgba(212, 175, 55, 0.3);
            z-index: 4;
            opacity: 0;
            transition: all 1s ease-in-out;
            pointer-events: none;
        }

        .register-form-container.active {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1);
            pointer-events: all;
        }

        .register-form-container.hidden {
            opacity: 0;
            transform: translate(-50%, -50%) scale(0.8);
            pointer-events: none;
        }

        .auth-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .auth-header h2 {
            font-size: 2rem;
            color: var(--accent-gold);
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light), var(--accent-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Cinzel', serif;
            letter-spacing: 2px;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
        }

        .auth-header p {
            color: var(--text-primary);
            font-size: 1rem;
            text-shadow: 0 1px 3px rgba(0, 0, 0, 0.5);
        }

        .auth-icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, rgba(212, 175, 55, 0.3), rgba(184, 134, 11, 0.4));
            border: 2px solid var(--accent-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.2rem;
            backdrop-filter: blur(5px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            letter-spacing: 1px;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .form-control {
            width: 100%;
            padding: 12px 15px;
            background: linear-gradient(135deg, rgba(50, 25, 50, 0.8) 0%, rgba(30, 15, 30, 0.9) 100%);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 5px;
            color: var(--text-light);
            font-size: 1rem;
            font-family: 'EB Garamond', serif;
            transition: all 0.3s ease;
            box-shadow: inset 0 2px 5px rgba(0, 0, 0, 0.3);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5), inset 0 2px 5px rgba(0, 0, 0, 0.3);
            transform: translateY(-2px);
        }

        .btn {
            padding: 12px 20px;
            border: none;
            border-radius: 2px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-family: 'Cinzel', serif;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
            cursor: none;
            width: 100%;
            margin-top: 10px;
        }

        .btn-primary {
            background: linear-gradient(135deg, rgba(139, 105, 20, 0.9), rgba(101, 67, 33, 0.95));
            color: #fff8dc;
            border: 1px solid var(--accent-gold);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, rgba(184, 134, 11, 0.95), rgba(139, 105, 20, 0.95));
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.5);
        }

        .auth-footer {
            text-align: center;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 1px solid rgba(212, 175, 55, 0.3);
        }

        .auth-footer p {
            color: var(--text-secondary);
            font-size: 0.9rem;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .auth-link {
            color: var(--accent-gold-light);
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .auth-link:hover {
            color: var(--accent-gold);
            text-decoration: underline;
            text-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }

        /* Message Styles */
        .message {
            padding: 10px;
            margin: 15px 0;
            border-radius: 5px;
            text-align: center;
            font-weight: 500;
            backdrop-filter: blur(5px);
        }

        .message.error {
            background: rgba(139, 0, 0, 0.4);
            border: 1px solid #8b0000;
            color: #ff6b6b;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .message.success {
            background: rgba(0, 100, 0, 0.4);
            border: 1px solid #006400;
            color: #90ee90;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        /* Scroll Instruction */
        .scroll-instruction {
            position: fixed;
            bottom: 30px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(40, 20, 40, 0.8);
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            padding: 15px 25px;
            backdrop-filter: blur(10px);
            z-index: 10;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }

        .scroll-instruction p {
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            font-size: 0.9rem;
            text-align: center;
        }

        /* Gender Selection Styles */
        .gender-options {
            display: flex;
            gap: 10px;
            margin-top: 5px;
        }

        .gender-option {
            flex: 1;
            text-align: center;
        }

        .gender-option input[type="radio"] {
            display: none;
        }

        .gender-option label {
            display: block;
            padding: 10px;
            background: linear-gradient(135deg, rgba(50, 25, 50, 0.8) 0%, rgba(30, 15, 30, 0.9) 100%);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 5px;
            color: var(--text-light);
            cursor: none;
            transition: all 0.3s ease;
            font-family: 'EB Garamond', serif;
        }

        .gender-option input[type="radio"]:checked + label {
            background: linear-gradient(135deg, rgba(139, 105, 20, 0.9), rgba(101, 67, 33, 0.95));
            border-color: var(--accent-gold);
            color: var(--accent-gold-light);
            box-shadow: 0 0 15px rgba(212, 175, 55, 0.5);
            transform: translateY(-2px);
        }

        .gender-option label:hover {
            border-color: var(--accent-gold);
            transform: translateY(-2px);
        }
    </style>
</head>
<body>
    <!-- Custom Cursor -->
    <div class="cursor"></div>
    <div class="cursor-follower"></div>

    <!-- RPG Loading Screen -->
    <div class="rpg-loading-screen" id="rpgLoading">
        <div class="loading-portal">
            <div class="portal-inner"></div>
            <div class="loading-dragon">🐉</div>
        </div>
        <div class="loading-text">FORGING YOUR DESTINY</div>
        <div class="loading-subtext">Creating your character, preparing your journey, and awakening ancient powers...</div>
        <div class="loading-progress">
            <div class="loading-progress-bar"></div>
        </div>
        <div class="loading-stats">
            <div class="stat-item">
                <div class="stat-value" id="statLevel">1</div>
                <div class="stat-label">Starting Level</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="statExp">0</div>
                <div class="stat-label">Experience</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="statGoals">0</div>
                <div class="stat-label">Quests Ready</div>
            </div>
        </div>
        <div class="loading-quote">
            "Every great adventurer begins with a single step into the unknown. Your legend starts now."
        </div>
    </div>

    <!-- Fixed Background -->
    <div class="fixed-background"></div>

    <!-- Moving Bats -->
    <div class="moving-elements">
        <div class="bat bat-1"></div>
        <div class="bat bat-2"></div>
        <div class="bat bat-3"></div>
    </div>

    <!-- Story Images -->
    <div class="story-container" id="storyContainer">
        <!-- Images will be dynamically loaded here -->
    </div>

    <!-- Progress Indicator -->
    <div class="scroll-progress">
        <div class="progress-text">YOUR JOURNEY</div>
        <div class="progress-dots" id="progressDots">
            <!-- Dots will be dynamically created -->
        </div>
    </div>

    <!-- Scroll Instruction -->
    <div class="scroll-instruction" id="scrollInstruction">
        <p><i class="fas fa-arrow-down"></i> Scroll to begin your adventure <i class="fas fa-arrow-down"></i></p>
    </div>

    <!-- Register Form -->
    <div class="register-form-container" id="registerFormContainer">
        <div class="auth-header">
            <div class="auth-icon">
                <i class="fas fa-crown"></i>
            </div>
            <h2>BEGIN YOUR QUEST</h2>
            <p>Forge your legend today</p>
        </div>
        
        <!-- Display messages -->
        <?php if (!empty($error)): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($success)): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
            <script>
                // Show RPG loading screen when registration is successful
                document.addEventListener('DOMContentLoaded', function() {
                    setTimeout(function() {
                        document.getElementById('rpgLoading').classList.add('active');
                        
                        // Animate stats counting up
                        animateCounter('statLevel', <?php echo isset($initial_stats['level']) ? $initial_stats['level'] : 1; ?>, 0, 1500);
                        animateCounter('statExp', <?php echo isset($initial_stats['exp']) ? $initial_stats['exp'] : 0; ?>, 0, 2000);
                        animateCounter('statGoals', <?php echo isset($initial_stats['total_goals']) ? $initial_stats['total_goals'] : 0; ?>, 0, 2500);
                    }, 1000);
                });
                
                function animateCounter(elementId, finalValue, startValue, duration) {
                    const element = document.getElementById(elementId);
                    const startTime = performance.now();
                    
                    function updateCounter(currentTime) {
                        const elapsed = currentTime - startTime;
                        const progress = Math.min(elapsed / duration, 1);
                        
                        const currentValue = Math.floor(startValue + (finalValue - startValue) * progress);
                        element.textContent = currentValue.toLocaleString();
                        
                        if (progress < 1) {
                            requestAnimationFrame(updateCounter);
                        } else {
                            element.textContent = finalValue.toLocaleString();
                        }
                    }
                    
                    requestAnimationFrame(updateCounter);
                }
            </script>
        <?php endif; ?>
        
        <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" id="registerForm">
            <div class="form-group">
                <label for="username"><i class="fas fa-user"></i> CHOOSE YOUR HERO NAME</label>
                <input type="text" id="username" name="username" class="form-control" 
                       placeholder="Enter your hero name (min. 3 characters)" 
                       value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                       required minlength="3">
            </div>
            
            <div class="form-group">
                <label for="email"><i class="fas fa-envelope"></i> SCROLL ADDRESS</label>
                <input type="email" id="email" name="email" class="form-control" 
                       placeholder="Enter your email address" 
                       value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>" 
                       required>
            </div>
            
            <div class="form-group">
                <label for="password"><i class="fas fa-key"></i> FORGE YOUR PASSWORD</label>
                <input type="password" id="password" name="password" class="form-control" 
                       placeholder="Create a strong password (min. 6 characters)" 
                       required minlength="6">
            </div>
            
            <div class="form-group">
                <label for="confirm_password"><i class="fas fa-shield-alt"></i> CONFIRM YOUR PASSWORD</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                       placeholder="Repeat your password" required>
            </div>

            <div class="form-group">
                <label><i class="fas fa-venus-mars"></i> CHOOSE YOUR IDENTITY</label>
                <div class="gender-options">
                    <div class="gender-option">
                        <input type="radio" id="gender_male" name="gender" value="male" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'male') ? 'checked' : ''; ?> required>
                        <label for="gender_male">Male</label>
                    </div>
                    <div class="gender-option">
                        <input type="radio" id="gender_female" name="gender" value="female" <?php echo (isset($_POST['gender']) && $_POST['gender'] == 'female') ? 'checked' : ''; ?>>
                        <label for="gender_female">Female</label>
                    </div>
                </div>
            </div>
            
            <div class="form-group">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-gem"></i> FORGE YOUR DESTINY
                </button>
            </div>
        </form>
        
        <div class="auth-footer">
            <p>Already have an account? <a href="login.php" class="auth-link">Enter the realm here</a></p>
        </div>
    </div>

    <!-- Main Scroll Container -->
    <div class="main-container" id="mainContainer"></div>

    <script>
        // Custom Cursor Logic
        const cursor = document.querySelector('.cursor');
        const cursorFollower = document.querySelector('.cursor-follower');
        
        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
            
            setTimeout(() => {
                cursorFollower.style.left = e.clientX + 'px';
                cursorFollower.style.top = e.clientY + 'px';
            }, 100);
        });
        
        document.addEventListener('click', (e) => {
            cursor.classList.add('clicked');
            setTimeout(() => {
                cursor.classList.remove('clicked');
            }, 300);
        });

        // Fixed image sizes for each PNG
        const imageSizes = {
            '100.png': 500,
            '101.png': 600,
            '102.png': 700,
            '103.png': 800,
            '104.png': 900,
            '105.png': 1000,
            '106.png': 1100
        };

        const imageFiles = ['100.png', '101.png', '102.png', '103.png', '104.png', '105.png', '106.png'];
        const totalImages = imageFiles.length;
        let currentImageIndex = -1;
        const storyContainer = document.getElementById('storyContainer');
        const progressDots = document.getElementById('progressDots');
        const registerForm = document.getElementById('registerFormContainer');
        const scrollInstruction = document.getElementById('scrollInstruction');
        const rpgLoading = document.getElementById('rpgLoading');

        // Create progress dots
        for (let i = 0; i < totalImages; i++) {
            const dot = document.createElement('div');
            dot.className = 'progress-dot';
            progressDots.appendChild(dot);
        }

        // Load story images with their fixed sizes
        imageFiles.forEach((filename, index) => {
            const img = document.createElement('img');
            img.className = 'story-image';
            img.src = `images/${filename}`;
            img.alt = `Story image ${index + 1}`;
            img.style.display = 'block';
            img.style.zIndex = index + 1;
            
            // Set fixed size for this image
            const size = imageSizes[filename];
            img.style.width = `${size}px`;
            img.style.height = `${size}px`;
            
            storyContainer.appendChild(img);
        });

        const storyImages = document.querySelectorAll('.story-image');
        const progressDotsAll = document.querySelectorAll('.progress-dot');

        // Scroll event handler
        let scrollTimeout;
        window.addEventListener('scroll', () => {
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                const scrollPosition = window.scrollY;
                const windowHeight = window.innerHeight;
                const documentHeight = document.documentElement.scrollHeight;
                const maxScroll = documentHeight - windowHeight;
                
                // Calculate which image to show based on scroll position
                const scrollPercentage = Math.min(scrollPosition / maxScroll, 1);
                const imageIndex = Math.min(Math.floor(scrollPercentage * totalImages), totalImages - 1);
                
                if (imageIndex !== currentImageIndex) {
                    // Hide all images first
                    storyImages.forEach(img => {
                        img.classList.remove('active');
                    });
                    
                    // Show current image
                    if (imageIndex >= 0) {
                        const currentImage = storyImages[imageIndex];
                        currentImage.classList.add('active');
                    }
                    
                    // Update progress dots
                    progressDotsAll.forEach((dot, index) => {
                        dot.classList.remove('active');
                        if (index === imageIndex) {
                            dot.classList.add('active');
                        }
                    });
                    
                    currentImageIndex = imageIndex;
                    
                    // Hide scroll instruction after first scroll
                    if (currentImageIndex >= 0) {
                        scrollInstruction.style.opacity = '0';
                        setTimeout(() => {
                            scrollInstruction.style.display = 'none';
                        }, 500);
                    }
                    
                    // Show/hide register form based on scroll position
                    // Show form when we're at the last image (90% scroll) or beyond
                    if (scrollPercentage >= 0.9) {
                        registerForm.classList.add('active');
                        registerForm.classList.remove('hidden');
                    } else {
                        registerForm.classList.remove('active');
                        registerForm.classList.add('hidden');
                    }
                }
            }, 50);
        });

        // Make interactive elements work with custom cursor
        document.querySelectorAll('button, .auth-link, input, select, .gender-option label').forEach(element => {
            element.addEventListener('mouseenter', function() {
                cursor.style.transform = 'scale(1.2)';
            });
            
            element.addEventListener('mouseleave', function() {
                cursor.style.transform = 'scale(1)';
            });
        });

        // Show loading animation on form submission
        document.getElementById('registerForm').addEventListener('submit', function(e) {
            // Only show loading if there are no validation errors
            const username = document.getElementById('username').value;
            const email = document.getElementById('email').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const gender = document.querySelector('input[name="gender"]:checked');
            
            if (username && email && password && confirmPassword && gender) {
                // Show RPG loading screen immediately
                rpgLoading.classList.add('active');
                
                // Animate stats with default values (will be updated by PHP if successful)
                animateCounter('statLevel', 1, 0, 1500);
                animateCounter('statExp', 0, 0, 2000);
                animateCounter('statGoals', 0, 0, 2500);
            }
        });

        // Counter animation function
        function animateCounter(elementId, finalValue, startValue, duration) {
            const element = document.getElementById(elementId);
            const startTime = performance.now();
            
            function updateCounter(currentTime) {
                const elapsed = currentTime - startTime;
                const progress = Math.min(elapsed / duration, 1);
                
                const currentValue = Math.floor(startValue + (finalValue - startValue) * progress);
                element.textContent = currentValue.toLocaleString();
                
                if (progress < 1) {
                    requestAnimationFrame(updateCounter);
                } else {
                    element.textContent = finalValue.toLocaleString();
                }
            }
            
            requestAnimationFrame(updateCounter);
        }

        // Initialize first image on load
        window.addEventListener('load', () => {
            window.dispatchEvent(new Event('scroll'));
        });

        // Force show form when user reaches the bottom
        window.addEventListener('scroll', function() {
            const scrollPosition = window.scrollY;
            const windowHeight = window.innerHeight;
            const documentHeight = document.documentElement.scrollHeight;
            
            // If user is at the very bottom of the page, show the form
            if (scrollPosition + windowHeight >= documentHeight - 10) {
                registerForm.classList.add('active');
                registerForm.classList.remove('hidden');
            }
        });
    </script>
</body>
</html>