<?php
// PHP code for login processing - MUST BE AT THE VERY TOP
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit();
}

// Database connection
require_once 'config.php';

// Initialize variables
$error = "";
$success = "";
$show_loading = false;

// Process login form
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_input = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate input
    if (empty($user_input) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        try {
            // Check if user exists
            $sql = "SELECT u.id, u.username, u.email, u.password, u.level, u.exp, u.max_hp, u.current_hp, u.gold,
                           COALESCE(us.total_goals_completed, 0) as total_goals_completed,
                           COALESCE(us.total_exp_earned, 0) as total_exp_earned
                    FROM users u 
                    LEFT JOIN user_stats us ON u.id = us.user_id 
                    WHERE u.username = ? OR u.email = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_input, $user_input]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($user) {
                // Verify password
                if (password_verify($password, $user['password']) || $password === $user['password']) {
                    // Login successful - store user data in session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['user_level'] = $user['level'];
                    $_SESSION['user_exp'] = $user['exp'];
                    $_SESSION['current_hp'] = $user['current_hp'];
                    $_SESSION['max_hp'] = $user['max_hp'];
                    $_SESSION['gold'] = $user['gold'];
                    $_SESSION['total_goals_completed'] = $user['total_goals_completed'];
                    $_SESSION['total_exp_earned'] = $user['total_exp_earned'];
                    
                    // Store user data for loading screen
                    $login_user_data = [
                        'level' => $user['level'],
                        'exp' => $user['exp'],
                        'current_hp' => $user['current_hp'],
                        'max_hp' => $user['max_hp'],
                        'gold' => $user['gold'],
                        'total_goals_completed' => $user['total_goals_completed'],
                        'total_exp_earned' => $user['total_exp_earned'],
                        'username' => $user['username']
                    ];
                    
                    $success = "Login successful! Preparing your journey...";
                    $show_loading = true;
                } else {
                    $error = "Invalid password. Please try again.";
                }
            } else {
                $error = "No user found with that username or email.";
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Adventurer's Login</title>
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

        /* Custom Cursor - Same as index.html */
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

        /* Click Animation */
        .click-effect {
            position: absolute;
            width: 20px;
            height: 20px;
            pointer-events: none;
            z-index: 9997;
            background: radial-gradient(circle, var(--accent-gold) 0%, transparent 70%);
            border-radius: 50%;
            animation: clickRipple 0.6s ease-out forwards;
        }

        @keyframes clickRipple {
            0% {
                transform: scale(0);
                opacity: 0.8;
            }
            100% {
                transform: scale(3);
                opacity: 0;
            }
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

        .welcome-message {
            font-size: 1.4rem;
            color: var(--accent-gold-light);
            margin-bottom: 10px;
            text-align: center;
            font-family: 'Cinzel', serif;
        }

        /* Auth Pages Container */
        .auth-container {
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Background Elements - BRIGHTENED */
        .auth-background {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -3;
            background: 
                linear-gradient(135deg, 
                    rgba(255, 255, 255, 0.1) 0%,
                    rgba(255, 255, 255, 0.05) 50%,
                    rgba(255, 255, 255, 0.1) 100%),
                url('images/login.png');
            background-size: cover;
            background-position: center;
            opacity: 0.9;
            filter: brightness(1.3) contrast(1.1);
        }

        /* Moving Bats */
        .moving-elements {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -2;
            pointer-events: none;
        }

        .bat {
            position: absolute;
            background-size: contain;
            background-repeat: no-repeat;
            opacity: 0.7;
            filter: drop-shadow(0 0 15px rgba(180, 160, 220, 0.8)) brightness(1.2);
        }

        /* Create bats using CSS instead of images */
        .bat::before {
            content: "🦇";
            font-size: 2rem;
            filter: brightness(0) invert(0.8) sepia(1) hue-rotate(10deg) saturate(5);
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

        .bat-4 {
            width: 55px;
            height: 40px;
            top: 80%;
            left: 15%;
            animation: batFly4 18s ease-in-out infinite;
            animation-delay: 5s;
        }

        .bat-5 {
            width: 45px;
            height: 30px;
            top: 25%;
            left: 75%;
            animation: batFly5 20s ease-in-out infinite;
            animation-delay: 7s;
        }

        @keyframes batFly1 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.5; }
            25% { transform: translate(100px, 50px) rotate(10deg); opacity: 0.8; }
            50% { transform: translate(200px, -30px) rotate(-5deg); opacity: 0.6; }
            75% { transform: translate(150px, 70px) rotate(15deg); opacity: 0.9; }
        }

        @keyframes batFly2 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.6; }
            25% { transform: translate(-80px, 60px) rotate(-8deg); opacity: 0.9; }
            50% { transform: translate(-150px, -20px) rotate(5deg); opacity: 0.7; }
            75% { transform: translate(-100px, 80px) rotate(-12deg); opacity: 0.8; }
        }

        @keyframes batFly3 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.7; }
            25% { transform: translate(120px, -40px) rotate(12deg); opacity: 0.9; }
            50% { transform: translate(200px, 30px) rotate(-7deg); opacity: 0.6; }
            75% { transform: translate(160px, -60px) rotate(10deg); opacity: 0.8; }
        }

        @keyframes batFly4 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.5; }
            25% { transform: translate(-60px, -50px) rotate(-10deg); opacity: 0.8; }
            50% { transform: translate(-120px, 20px) rotate(8deg); opacity: 0.6; }
            75% { transform: translate(-80px, -70px) rotate(-15deg); opacity: 0.7; }
        }

        @keyframes batFly5 {
            0%, 100% { transform: translate(0, 0) rotate(0deg); opacity: 0.6; }
            25% { transform: translate(70px, 70px) rotate(15deg); opacity: 0.9; }
            50% { transform: translate(140px, -10px) rotate(-10deg); opacity: 0.7; }
            75% { transform: translate(100px, 90px) rotate(12deg); opacity: 0.8; }
        }

        /* Auth Form Styling */
        .auth-form-container {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.85) 0%, rgba(25, 10, 25, 0.9) 100%);
            border: 2px solid var(--accent-gold-dark);
            border-radius: 10px;
            padding: 40px;
            width: 100%;
            max-width: 450px;
            backdrop-filter: blur(15px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.5);
            position: relative;
            z-index: 10;
        }

        .auth-form-container::before {
            content: "";
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, transparent, var(--accent-gold), var(--accent-gold-dark), var(--accent-gold), transparent);
            animation: borderGlow 3s ease-in-out infinite;
        }

        @keyframes borderGlow {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
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
            animation: iconFloat 4s ease-in-out infinite;
        }

        @keyframes iconFloat {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
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

        .form-control::placeholder {
            color: rgba(230, 214, 179, 0.6);
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

        .btn::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 5px;
            height: 5px;
            background: rgba(255, 255, 255, 0.5);
            opacity: 0;
            border-radius: 100%;
            transform: scale(1, 1) translate(-50%);
            transform-origin: 50% 50%;
        }

        .btn:focus:not(:active)::after {
            animation: ripple 1s ease-out;
        }

        @keyframes ripple {
            0% {
                transform: scale(0, 0);
                opacity: 0.5;
            }
            100% {
                transform: scale(20, 20);
                opacity: 0;
            }
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

        /* Navigation */
        .auth-navigation {
            position: absolute;
            top: 20px;
            left: 20px;
            z-index: 100;
        }

        .btn-back {
            background: linear-gradient(135deg, rgba(42, 26, 42, 0.9), rgba(26, 15, 26, 0.95));
            color: var(--accent-gold);
            border: 1px solid var(--accent-gold-dark);
            padding: 10px 20px;
            font-size: 0.8rem;
            border-radius: 2px;
            font-family: 'Cinzel', serif;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            cursor: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 8px;
            backdrop-filter: blur(5px);
        }

        .btn-back:hover {
            background: linear-gradient(135deg, rgba(50, 30, 50, 0.95), rgba(35, 20, 35, 0.95));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .auth-form-container {
                padding: 30px 20px;
            }
            
            .auth-header h2 {
                font-size: 1.7rem;
            }
            
            .bat {
                transform: scale(0.7);
            }
            
            .loading-portal {
                width: 150px;
                height: 150px;
            }
            
            .loading-text {
                font-size: 1.5rem;
            }
            
            .loading-stats {
                gap: 20px;
            }
        }

        @media (max-width: 480px) {
            .auth-form-container {
                padding: 25px 15px;
            }
            
            .auth-header h2 {
                font-size: 1.5rem;
            }
            
            .bat {
                transform: scale(0.5);
            }
            
            .loading-portal {
                width: 120px;
                height: 120px;
            }
            
            .loading-text {
                font-size: 1.2rem;
            }
            
            .loading-stats {
                flex-direction: column;
                gap: 15px;
            }
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

        .demo-info {
            background: rgba(212, 175, 55, 0.25);
            border: 1px solid var(--accent-gold);
            border-radius: 5px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
            backdrop-filter: blur(5px);
        }

        .demo-info h4 {
            color: var(--accent-gold);
            margin-bottom: 8px;
            font-family: 'Cinzel', serif;
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
        }

        .demo-info p {
            color: var(--text-light);
            text-shadow: 0 1px 2px rgba(0, 0, 0, 0.5);
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
        <div class="welcome-message" id="welcomeMessage">Welcome back, Adventurer!</div>
        <div class="loading-text">ENTERING THE REALM</div>
        <div class="loading-subtext">Gathering your quests, preparing your inventory, and summoning ancient magics...</div>
        <div class="loading-progress">
            <div class="loading-progress-bar"></div>
        </div>
        <div class="loading-stats">
            <div class="stat-item">
                <div class="stat-value" id="statLevel">1</div>
                <div class="stat-label">Adventurer Level</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="statHP">100/100</div>
                <div class="stat-label">Health Points</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="statQuests">0</div>
                <div class="stat-label">Quests Completed</div>
            </div>
            <div class="stat-item">
                <div class="stat-value" id="statGold">0</div>
                <div class="stat-label">Gold</div>
            </div>
        </div>
        <div class="loading-quote">
            "The journey of a thousand miles begins with a single step, but the legend begins with a single quest."
        </div>
    </div>

    <!-- Login Page -->
    <div class="auth-container">
        <!-- Background - BRIGHTENED -->
        <div class="auth-background"></div>
        
        <!-- Moving Elements -->
        <div class="moving-elements">
            <div class="bat bat-1"></div>
            <div class="bat bat-2"></div>
            <div class="bat bat-3"></div>
            <div class="bat bat-4"></div>
            <div class="bat bat-5"></div>
        </div>
        
        <!-- Navigation -->
        <div class="auth-navigation">
            <button class="btn-back" onclick="goBack()">
                <i class="fas fa-arrow-left"></i> BACK TO QUEST
            </button>
        </div>
        
        <!-- Login Form -->
        <div class="auth-form-container">
            <div class="auth-header">
                <div class="auth-icon">
                    <i class="fas fa-dragon"></i>
                </div>
                <h2>ADVENTURER'S LOGIN</h2>
                <p>Return to your epic journey</p>
            </div>
            
            <!-- Demo Info -->
            <div class="demo-info">
                <h4><i class="fas fa-info-circle"></i> DEMO MODE</h4>
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
                    // Show RPG loading screen when login is successful
                    document.addEventListener('DOMContentLoaded', function() {
                        setTimeout(function() {
                            document.getElementById('rpgLoading').classList.add('active');
                            
                            // Get user data from PHP session
                            const userData = {
                                level: <?php echo $login_user_data['level'] ?? 1; ?>,
                                current_hp: <?php echo $login_user_data['current_hp'] ?? 100; ?>,
                                max_hp: <?php echo $login_user_data['max_hp'] ?? 100; ?>,
                                gold: <?php echo $login_user_data['gold'] ?? 0; ?>,
                                total_goals_completed: <?php echo $login_user_data['total_goals_completed'] ?? 0; ?>,
                                username: '<?php echo $login_user_data['username'] ?? 'Adventurer'; ?>'
                            };
                            
                            // Update welcome message with actual username
                            document.getElementById('welcomeMessage').textContent = `Welcome back, ${userData.username}!`;
                            
                            // Animate stats counting up with ACTUAL user data
                            animateCounter('statLevel', userData.level, 0, 1500);
                            animateCounter('statQuests', userData.total_goals_completed, 0, 2000);
                            animateCounter('statGold', userData.gold, 0, 1800);
                            
                            // Update HP display
                            const hpElement = document.getElementById('statHP');
                            hpElement.textContent = `${userData.current_hp}/${userData.max_hp}`;
                            
                            // Redirect after loading animation completes
                            setTimeout(function() {
                                window.location.href = 'dashboard.php';
                            }, 4000);
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
            
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
                <div class="form-group">
                    <label for="username"><i class="fas fa-user"></i> USERNAME OR EMAIL</label>
                    <input type="text" id="username" name="username" class="form-control" 
                           placeholder="Enter your username or email" 
                           value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" 
                           required>
                </div>
                
                <div class="form-group">
                    <label for="password"><i class="fas fa-key"></i> PASSWORD</label>
                    <input type="password" id="password" name="password" class="form-control" 
                           placeholder="Enter your password" required>
                </div>
                
                <div class="form-group">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt"></i> ENTER REALM
                    </button>
                </div>
            </form>
            
            <div class="auth-footer">
                <p>New adventurer? <a href="register.php" class="auth-link">Begin your quest here</a></p>
                <p><a href="#" class="auth-link">Forgotten your magical password?</a></p>
            </div>
        </div>
    </div>

    <script>
        // Custom Cursor Logic - Same as index.html
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
            // Add click animation to cursor
            cursor.classList.add('clicked');
            setTimeout(() => {
                cursor.classList.remove('clicked');
            }, 300);
            
            // Create click effect
            const clickEffect = document.createElement('div');
            clickEffect.className = 'click-effect';
            clickEffect.style.left = e.clientX + 'px';
            clickEffect.style.top = e.clientY + 'px';
            document.body.appendChild(clickEffect);
            
            // Remove effect after animation
            setTimeout(() => {
                clickEffect.remove();
            }, 600);
        });

        // Navigation function
        function goBack() {
            window.location.href = 'index.html';
        }

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value.trim();
            
            if (!username || !password) {
                e.preventDefault();
                alert('Please fill in all required fields, adventurer!');
                return false;
            }
            
            return true;
        });

        // Add some interactive effects
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'translateY(-2px)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'translateY(0)';
            });
        });

        // Make buttons work with custom cursor
        document.querySelectorAll('button, .auth-link').forEach(element => {
            element.addEventListener('mouseenter', function() {
                cursor.style.transform = 'scale(1.2)';
            });
            
            element.addEventListener('mouseleave', function() {
                cursor.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>