<?php
// PHP code for dashboard - MUST BE AT THE VERY TOP
session_start();

// Check if user is logged in, if not redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection - UPDATED PATH
require_once 'config.php';

// Process logout
if (isset($_POST['logout'])) {
    // Destroy all session variables
    $_SESSION = array();
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: login.php");
    exit();
}

// Get user data from session and database
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];
$email = $_SESSION['email'];

// Fetch user data from database
try {
    // Get user details with level and gender
    $stmt = $pdo->prepare("
        SELECT u.*, 
               COALESCE(us.total_goals_completed, 0) as total_goals_completed,
               COALESCE(us.total_exp_earned, 0) as total_exp_earned,
               COALESCE(us.current_streak, 0) as current_streak,
               COALESCE(us.longest_streak, 0) as longest_streak
        FROM users u 
        LEFT JOIN user_stats us ON u.id = us.user_id 
        WHERE u.id = ?
    ");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        throw new Exception("User not found");
    }
    
    // Get user's active goals
    $goals_stmt = $pdo->prepare("
        SELECT * FROM goals 
        WHERE user_id = ? AND completed = 0 
        ORDER BY created_at DESC 
        LIMIT 10
    ");
    $goals_stmt->execute([$user_id]);
    $active_goals = $goals_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's completed goals count - UPDATED TO COUNT FROM goal_logs
    $completed_stmt = $pdo->prepare("
        SELECT COUNT(*) as completed_count FROM goal_logs 
        WHERE user_id = ? AND status = 'completed'
    ");
    $completed_stmt->execute([$user_id]);
    $completed_result = $completed_stmt->fetch(PDO::FETCH_ASSOC);
    $total_completed_goals = $completed_result['completed_count'] ?? 0;
    
    // Calculate stats - WITH PROPER LEVEL UP LOGIC
    $user_level = $user['level'] ?? 1;
    $user_xp = $user['exp'] ?? 0;
    
    // Calculate XP needed for next level based on current level
    $base_xp_needed = 100; // Base XP needed for level 1
    $xp_multiplier = 1.3; // 30% increase per level
    
    // LEVEL UP SYSTEM - FIXED VERSION
    $leveled_up = false;
    $levels_gained = 0;
    $new_level = $user_level;
    $new_xp = $user_xp;
    
    // Calculate required XP for current level
    $required_xp = $base_xp_needed;
    if ($new_level > 1) {
        $required_xp = round($base_xp_needed * pow($xp_multiplier, $new_level - 1));
    }
    
    // Check for level ups (handle multiple levels at once)
    while ($new_xp >= $required_xp) {
        // Level up!
        $new_level++;
        $new_xp -= $required_xp; // Carry over excess XP
        $levels_gained++;
        
        // Recalculate required XP for next level
        $required_xp = round($base_xp_needed * pow($xp_multiplier, $new_level - 1));
        
        $leveled_up = true;
        
        // Safety check to prevent infinite loop
        if ($new_level >= 1000) {
            break;
        }
    }
    
    // If user leveled up, update the database
    if ($leveled_up) {
        try {
            // Update level and XP in database
            $stmt = $pdo->prepare("UPDATE users SET level = ?, exp = ? WHERE id = ?");
            $stmt->execute([$new_level, $new_xp, $user_id]);
            
            // Update local variables
            $user_level = $new_level;
            $user_xp = $new_xp;
            
            // Level up rewards
            $new_max_hp = 100 + ($user_level * 10); // +10 HP per level
            $gold_reward = $levels_gained * 25; // 25 gold per level gained
            
            // Update max HP and give gold reward
            $stmt = $pdo->prepare("UPDATE users SET max_hp = ?, gold = gold + ? WHERE id = ?");
            $stmt->execute([$new_max_hp, $gold_reward, $user_id]);
            
            // Update local max_hp variable
            $max_hp = $new_max_hp;
            
        } catch (Exception $e) {
            error_log("Level up error: " . $e->getMessage());
        }
    }
    
    // Calculate XP for display (after potential level up)
    $next_level_xp = round($base_xp_needed * pow($xp_multiplier, $user_level - 1));
    $xp_percentage = $next_level_xp > 0 ? ($user_xp / $next_level_xp) * 100 : 0;
    
    $total_goals_completed = $user['total_goals_completed'] ?? 0;
    $current_streak = $user['current_streak'] ?? 0;
    $longest_streak = $user['longest_streak'] ?? 0;
    $gender = $user['gender'] ?? 'prefer_not_to_say';
    
    // Get HP from database - Health stays the same regardless of level
    $max_hp = $user['max_hp'] ?? 100;
    $current_hp = $user['current_hp'] ?? 100;
    $health_percentage = $max_hp > 0 ? ($current_hp / $max_hp) * 100 : 0;
    
    // Determine character image based on gender and ACTUAL HP - FIXED
    $character_image = 'images/male.png'; // Default fallback
    if ($gender == 'male') {
        $character_image = $current_hp <= 30 ? 'images/male_injured.png' : 'images/male.png';
    } elseif ($gender == 'female') {
        $character_image = $current_hp <= 30 ? 'images/female_injured.png' : 'images/female.png';
    } else {
        // For 'other' or 'prefer_not_to_say', default to male
        $character_image = $current_hp <= 30 ? 'images/male_injured.png' : 'images/male.png';
    }
    
} catch (Exception $e) {
    die("Error loading dashboard: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Adventurer's Dashboard</title>
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
            --black-box: rgba(10, 5, 10, 0.95);
            --red-dark: #8b0000;
            --red-medium: #ff0000;
            --red-light: #ff6b6b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: #000000;
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
            font-family: 'EB Garamond', serif;
            position: relative;
            min-height: 100vh;
        }

        /* Background image - REPLACED VIDEO WITH IMAGE */
        .bg-image {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('images/background100.jpg');
            background-size: cover;
            background-position: center;
            background-repeat: no-repeat;
            background-attachment: fixed;
            z-index: -1;
        }

        /* Overlay for better text readability */
        .bg-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            z-index: -1;
        }

        /* Main Dashboard Container - NO INTERNAL SCROLL */
        .dashboard-container {
            max-width: 1400px;
            margin: 0 auto;
            background: var(--black-box);
            border: 3px solid var(--accent-gold-dark);
            border-radius: 15px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            min-height: 100vh;
        }

        /* Header */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px 30px;
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.9) 0%, rgba(25, 10, 25, 0.95) 100%);
            border-bottom: 2px solid var(--accent-gold-dark);
        }

        .logo h1 {
            font-size: 2rem;
            color: var(--accent-gold);
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light), var(--accent-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Cinzel', serif;
            letter-spacing: 2px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .user-welcome {
            text-align: right;
        }

        .user-welcome h2 {
            font-size: 1.3rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
        }

        .user-welcome p {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 2px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            font-family: 'Cinzel', serif;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
            cursor: pointer;
        }

        .btn-logout {
            background: linear-gradient(135deg, rgba(139, 0, 0, 0.7), rgba(101, 0, 0, 0.8));
            color: #fff8dc;
            border: 1px solid #8b0000;
        }

        .btn-logout:hover {
            background: linear-gradient(135deg, rgba(184, 0, 0, 0.8), rgba(139, 0, 0, 0.9));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(139, 0, 0, 0.4);
        }

        /* Main Content */
        .dashboard-content {
            padding: 30px;
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 30px;
            align-items: stretch;
        }

        @media (max-width: 1024px) {
            .dashboard-content {
                grid-template-columns: 1fr;
            }
        }

        /* Character Section */
        .character-section {
            background: var(--black-box);
            border: 2px solid var(--accent-gold-dark);
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
        }

        .character-image {
            width: 100%;
            height: 300px;
            margin: 0 auto 20px;
            background: rgba(0, 0, 0, 0.3);
            border: 3px solid var(--accent-gold);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .character-image img {
            width: auto;
            height: 100%;
            object-fit: contain;
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .character-info {
            margin-top: 15px;
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        .character-name {
            font-size: 1.4rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 10px;
        }

        /* Stats Below Character */
        .character-stats {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin-top: 20px;
            flex: 1;
        }

        .stat-row {
            display: flex;
            align-items: center;
            gap: 15px;
            background: rgba(30, 15, 30, 0.8);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 15px;
        }

        .stat-icon {
            font-size: 1.8rem;
            color: var(--accent-gold);
            min-width: 40px;
            text-align: center;
        }

        .stat-info {
            flex: 1;
        }

        .stat-value {
            font-size: 1.2rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 5px;
        }

        .stat-label {
            color: var(--text-light);
            font-size: 0.8rem;
        }

        /* Progress Bars */
        .progress-container {
            margin-top: 8px;
        }

        .progress-bar {
            width: 100%;
            height: 6px;
            background: rgba(212, 175, 55, 0.2);
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 4px;
        }

        .progress-fill {
            height: 100%;
            border-radius: 3px;
            transition: width 1s ease;
        }

        .xp-progress {
            background: linear-gradient(90deg, var(--accent-gold-dark), var(--accent-gold), var(--accent-gold-light));
        }

        .hp-progress {
            background: linear-gradient(90deg, #8b0000, #ff0000, #ff6b6b);
        }

        .progress-text {
            font-size: 0.7rem;
            color: var(--text-secondary);
        }

        /* Health Status */
        .health-status {
            margin-top: 15px;
            padding: 8px 12px;
            border-radius: 5px;
            font-size: 0.8rem;
            text-align: center;
        }

        .health-critical {
            background: rgba(139, 0, 0, 0.3);
            color: #ff6b6b;
            border: 1px solid #8b0000;
            animation: pulse 2s infinite;
        }

        .health-wounded {
            background: rgba(184, 134, 11, 0.3);
            color: var(--accent-gold-light);
            border: 1px solid var(--accent-gold-dark);
        }

        .health-healthy {
            background: rgba(0, 100, 0, 0.3);
            color: #90ee90;
            border: 1px solid #006400;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.7; }
            100% { opacity: 1; }
        }

        /* Experience Points Display - SMALLER VERSION */
        .experience-points {
            background: rgba(30, 15, 30, 0.8);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 12px 15px;
            margin-top: 15px;
            text-align: center;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 80px;
        }

        .exp-row {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .exp-icon {
            font-size: 1.5rem;
            color: var(--accent-gold);
            min-width: 40px;
            text-align: center;
        }

        .exp-info {
            flex: 1;
        }

        .exp-value {
            font-size: 1.2rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 2px;
        }

        .exp-label {
            color: var(--text-light);
            font-size: 0.8rem;
        }

        /* Number Stats Box */
        .number-stat-box {
            background: rgba(30, 15, 30, 0.8);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .number-stat-box:hover {
            transform: translateY(-5px);
            border-color: var(--accent-gold);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.2);
        }

        .number-stat-icon {
            font-size: 3rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
        }

        .number-stat-value {
            font-size: 3.5rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 10px;
            line-height: 1;
        }

        .number-stat-label {
            color: var(--text-light);
            font-size: 1.1rem;
            font-family: 'Cinzel', serif;
        }

        .number-stat-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 10px;
        }

        /* Top Section - Two Boxes Side by Side */
        .top-section {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .top-section {
                grid-template-columns: 1fr;
            }
        }

        /* Right Column Layout */
        .right-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* QUEST BOARD Box - Same design as other number boxes */
        .quest-board-box {
            background: rgba(30, 15, 30, 0.8);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            color: inherit;
        }

        .quest-board-box:hover {
            transform: translateY(-5px);
            border-color: var(--accent-gold);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.2);
        }

        .quest-board-icon {
            font-size: 3rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
        }

        .quest-board-title {
            font-size: 1.8rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 10px;
            line-height: 1.2;
        }

        .quest-board-description {
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 10px;
            line-height: 1.4;
            margin-bottom: 20px;
        }

        /* VIEW QUEST Button - Gold background with white text */
        .view-quest-btn {
            display: inline-block;
            background: linear-gradient(135deg, var(--accent-gold-dark), var(--accent-gold));
            color: white;
            padding: 10px 25px;
            border-radius: 5px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid var(--accent-gold-light);
            cursor: pointer;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .view-quest-btn:hover {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light));
            transform: translateY(-2px);
            box-shadow: 0 6px 15px rgba(212, 175, 55, 0.6);
            border-color: white;
        }

        /* BOSS ARENA Box - Red design (original) */
        .boss-arena-box {
            background: linear-gradient(135deg, rgba(139, 0, 0, 0.3), rgba(101, 0, 0, 0.4));
            border: 2px solid #8b0000;
            border-radius: 10px;
            padding: 25px;
            text-align: center;
            transition: all 0.3s ease;
            cursor: default;
            box-shadow: 0 5px 20px rgba(139, 0, 0, 0.3);
        }

        .boss-arena-box:hover {
            transform: translateY(-5px);
            border-color: #ff0000;
            box-shadow: 0 10px 25px rgba(139, 0, 0, 0.4);
            background: linear-gradient(135deg, rgba(184, 0, 0, 0.4), rgba(139, 0, 0, 0.5));
        }

        .boss-arena-icon {
            font-size: 3rem;
            color: #ff6b6b;
            margin-bottom: 15px;
            text-shadow: 0 0 10px rgba(255, 0, 0, 0.5);
        }

        .boss-arena-title {
            font-size: 1.8rem;
            color: #ff6b6b;
            font-family: 'Cinzel', serif;
            margin-bottom: 10px;
            text-shadow: 0 0 5px rgba(255, 0, 0, 0.5);
        }

        .boss-arena-description {
            color: var(--text-light);
            font-size: 1rem;
            margin-bottom: 20px;
            line-height: 1.5;
        }

        /* ENTER ARENA Button - White background with red design */
        .enter-arena-btn {
            display: inline-block;
            background: white;
            color: var(--red-dark);
            padding: 12px 30px;
            border-radius: 5px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            border: 2px solid var(--red-dark);
            cursor: pointer;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 2px;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
        }

        .enter-arena-btn:hover {
            background: var(--red-dark);
            color: white;
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(139, 0, 0, 0.4);
            border-color: var(--red-medium);
        }

        /* ADVENTURE LOG Box - SIMPLE VERSION with only button */
        .adventure-log-box {
            background: rgba(30, 15, 30, 0.8);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: block;
            color: inherit;
            margin-top: 20px;
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 120px;
        }

        .adventure-log-box:hover {
            transform: translateY(-5px);
            border-color: var(--accent-gold);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.2);
        }

        /* ADVENTURE LOG Button - Simple text button */
        .adventure-log-btn {
            display: inline-block;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            font-weight: bold;
            text-decoration: none;
            transition: all 0.3s ease;
            cursor: pointer;
            font-size: 2rem;
            letter-spacing: 2px;
            position: relative;
            padding-right: 40px;
        }

        .adventure-log-btn::after {
            content: '>';
            position: absolute;
            right: 0;
            top: 50%;
            transform: translateY(-50%);
            font-size: 2.5rem;
            color: var(--accent-gold);
            transition: all 0.3s ease;
        }

        .adventure-log-btn:hover {
            color: white;
            transform: scale(1.05);
        }

        .adventure-log-btn:hover::after {
            right: -10px;
            color: var(--accent-gold-light);
        }

        /* Left Column Layout */
        .left-column {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        /* Footer */
        .dashboard-footer {
            text-align: center;
            padding: 20px;
            background: linear-gradient(135deg, rgba(25, 10, 25, 0.7) 0%, rgba(15, 5, 15, 0.8) 100%);
            border-top: 1px solid var(--accent-gold-dark);
        }

        .dashboard-footer p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: 'Cinzel', serif;
            letter-spacing: 1px;
        }

        /* Logout Confirmation */
        .logout-confirm {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(10, 5, 10, 0.9);
            z-index: 1000;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(10px);
        }

        .logout-confirm.active {
            display: flex;
        }

        .confirm-box {
            background: var(--black-box);
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            padding: 30px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            backdrop-filter: blur(15px);
        }

        .confirm-box h3 {
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 15px;
        }

        .confirm-box p {
            color: var(--text-primary);
            margin-bottom: 25px;
        }

        .confirm-buttons {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .btn-confirm {
            background: linear-gradient(135deg, rgba(139, 0, 0, 0.8), rgba(101, 0, 0, 0.9));
            color: #fff8dc;
            border: 1px solid #8b0000;
        }

        .btn-cancel {
            background: linear-gradient(135deg, rgba(42, 26, 42, 0.8), rgba(26, 15, 26, 0.9));
            color: var(--accent-gold);
            border: 1px solid var(--accent-gold-dark);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .dashboard-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .user-welcome {
                text-align: center;
            }

            .stat-row {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .exp-row {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }

            .character-image {
                width: 150px;
                height: 225px;
            }

            .number-stat-value {
                font-size: 2.5rem;
            }

            .quest-board-title {
                font-size: 1.5rem;
            }
            
            .boss-arena-title {
                font-size: 1.5rem;
            }
            
            .enter-arena-btn,
            .view-quest-btn {
                padding: 12px 25px;
                font-size: 1rem;
            }
            
            .adventure-log-btn {
                font-size: 1.5rem;
            }
            
            .exp-value {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Background Image - REPLACED VIDEO -->
    <div class="bg-image"></div>
    <div class="bg-overlay"></div>

    <!-- Logout Confirmation Modal -->
    <div class="logout-confirm" id="logoutConfirm">
        <div class="confirm-box">
            <h3><i class="fas fa-exclamation-triangle"></i> LEAVE THE REALM?</h3>
            <p>Are you sure you wish to end your current session, brave adventurer? Your progress will be saved, but you must login again to continue your quests.</p>
            <div class="confirm-buttons">
                <form method="POST" style="display: inline;">
                    <button type="submit" name="logout" class="btn btn-confirm">
                        <i class="fas fa-door-open"></i> YES, LOGOUT
                    </button>
                </form>
                <button class="btn btn-cancel" onclick="hideLogoutConfirm()">
                    <i class="fas fa-times"></i> CONTINUE QUEST
                </button>
            </div>
        </div>
    </div>

    <!-- Main Dashboard Container -->
    <div class="dashboard-container">
        <!-- Header -->
        <header class="dashboard-header">
            <div class="logo">
                <h1>RPG Goal Getter</h1>
            </div>
            <div class="user-info">
                <div class="user-welcome">
                    <h2>Welcome, <?php echo htmlspecialchars($username); ?>!</h2>
                    <p>Level <?php echo $user_level; ?> Adventurer | HP: <?php echo $current_hp; ?>/<?php echo $max_hp; ?></p>
                </div>
                <button class="btn btn-logout" onclick="showLogoutConfirm()">
                    <i class="fas fa-sign-out-alt"></i> LOGOUT
                </button>
            </div>
        </header>

        <!-- Main Content -->
        <main class="dashboard-content">
            <!-- Left Column - Character & Stats -->
            <div class="left-column">
                <!-- Character Section -->
                <section class="character-section">
                    <div class="character-image" id="characterImage">
                        <!-- Character display based on gender and ACTUAL HP from database -->
                        <?php if ($gender == 'male'): ?>
                            <?php if ($current_hp <= 30): ?>
                                <img src="images/male_injured.png" alt="Injured Male Adventurer" id="characterImg">
                            <?php else: ?>
                                <img src="images/male.png" alt="Male Adventurer" id="characterImg">
                            <?php endif; ?>
                        <?php elseif ($gender == 'female'): ?>
                            <?php if ($current_hp <= 30): ?>
                                <img src="images/female_injured.png" alt="Injured Female Adventurer" id="characterImg">
                            <?php else: ?>
                                <img src="images/female.png" alt="Female Adventurer" id="characterImg">
                            <?php endif; ?>
                        <?php else: ?>
                            <!-- For 'other' or 'prefer_not_to_say', default to male -->
                            <?php if ($current_hp <= 30): ?>
                                <img src="images/male_injured.png" alt="Injured Adventurer" id="characterImg">
                            <?php else: ?>
                                <img src="images/male.png" alt="Adventurer" id="characterImg">
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>
                    <div class="character-info">
                        <div>
                            <div class="character-name"><?php echo htmlspecialchars($username); ?></div>
                            
                            <!-- Stats Below Character - Only HP, EXP, and Level -->
                            <div class="character-stats">
                                <div class="stat-row">
                                    <div class="stat-icon">⭐</div>
                                    <div class="stat-info">
                                        <div class="stat-value">Level <?php echo $user_level; ?></div>
                                        <div class="stat-label">Adventurer Rank</div>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill xp-progress" style="width: <?php echo $xp_percentage; ?>%"></div>
                                            </div>
                                            <div class="progress-text"><?php echo $user_xp; ?> / <?php echo $next_level_xp; ?> XP</div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="stat-row">
                                    <div class="stat-icon">❤️</div>
                                    <div class="stat-info">
                                        <div class="stat-value"><?php echo $current_hp; ?>/<?php echo $max_hp; ?></div>
                                        <div class="stat-label">Health Points</div>
                                        <div class="progress-container">
                                            <div class="progress-bar">
                                                <div class="progress-fill hp-progress" style="width: <?php echo $health_percentage; ?>%"></div>
                                            </div>
                                            <div class="progress-text"><?php echo round($health_percentage); ?>% Health</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Health Status -->
                        <div class="health-status <?php 
                            if ($current_hp <= 30) echo 'health-critical';
                            elseif ($current_hp <= 60) echo 'health-wounded';
                            else echo 'health-healthy';
                        ?>">
                            <?php 
                            if ($current_hp <= 30) {
                                echo "CRITICAL CONDITION! Rest and complete quests to heal.";
                            } elseif ($current_hp <= 60) {
                                echo "WOUNDED - Keep pushing forward!";
                            } else {
                                echo "HEALTHY - Ready for adventure!";
                            }
                            ?>
                        </div>

                        <!-- Experience Points Display - SMALLER VERSION -->
                        <div class="experience-points">
                            <div class="exp-row">
                                <div class="exp-icon">✨</div>
                                <div class="exp-info">
                                    <div class="exp-value"><?php echo $user_xp; ?> XP</div>
                                    <div class="exp-label">Experience Points</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- ADVENTURE LOG Box - ONLY BUTTON -->
                <a href="logs.php" class="adventure-log-box">
                    <div class="adventure-log-btn">
                        ADVENTURE LOG
                    </div>
                </a>
            </div>

            <!-- Right Column - Quests & Actions -->
            <div class="right-column">
                <!-- Top Section - Two Boxes Side by Side (Active Quests and Quests Conquered) -->
                <div class="top-section">
                    <!-- Active Quests - Number Only -->
                    <a href="goals.php" class="number-stat-box">
                        <div class="number-stat-icon">📜</div>
                        <div class="number-stat-value"><?php echo count($active_goals); ?></div>
                        <div class="number-stat-label">Active Quests</div>
                        <div class="number-stat-description">Click to manage quests</div>
                    </a>

                    <!-- All Completed Quests - Number Only -->
                    <a href="logs.php" class="number-stat-box">
                        <div class="number-stat-icon">🏆</div>
                        <div class="number-stat-value"><?php echo $total_completed_goals; ?></div>
                        <div class="number-stat-label">Quests Conquered</div>
                        <div class="number-stat-description">Click to view quest logs</div>
                    </a>
                </div>

                <!-- BOSS ARENA Box - Red design (original) -->
                <div class="boss-arena-box">
                    <div class="boss-arena-icon">
                        <i class="fas fa-dragon"></i>
                    </div>
                    <div class="boss-arena-title">BOSS ARENA</div>
                    <div class="boss-arena-description">
                        Face epic challenges and defeat mighty<br>
                        bosses for legendary rewards!
                    </div>
                    <a href="boss_arena.php" class="enter-arena-btn">
                        ENTER ARENA
                    </a>
                </div>

                <!-- QUEST BOARD Box - Same design as other boxes, with gold VIEW QUEST button -->
                <a href="goals.php" class="quest-board-box">
                    <div class="quest-board-icon">
                        <i class="fas fa-scroll"></i>
                    </div>
                    <div class="quest-board-title">QUEST BOARD</div>
                    <div class="quest-board-description">
                        Complete daily quests and earn steady progress toward your goals!
                    </div>
                    <div class="view-quest-btn">
                        VIEW QUEST
                    </div>
                </a>
            </div>
        </main>

        <!-- Footer -->
        <footer class="dashboard-footer">
            <p><i class="fas fa-scroll"></i> © 2023 RPG GOAL GETTER - YOUR EPIC JOURNEY CONTINUES <i class="fas fa-dragon"></i></p>
        </footer>
    </div>

    <script>
        // Logout Confirmation Functions
        function showLogoutConfirm() {
            document.getElementById('logoutConfirm').classList.add('active');
        }

        function hideLogoutConfirm() {
            document.getElementById('logoutConfirm').classList.remove('active');
        }

        // Close modal when clicking outside
        document.getElementById('logoutConfirm').addEventListener('click', function(e) {
            if (e.target === this) {
                hideLogoutConfirm();
            }
        });

        // Animate XP and HP bars on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Animate XP bars
            const xpBars = document.querySelectorAll('.xp-progress');
            xpBars.forEach(bar => {
                const finalWidth = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = finalWidth;
                }, 500);
            });
            
            // Animate HP bars
            const hpBars = document.querySelectorAll('.hp-progress');
            hpBars.forEach(bar => {
                const finalWidth = bar.style.width;
                bar.style.width = '0%';
                setTimeout(() => {
                    bar.style.width = finalWidth;
                }, 800);
            });

            // Character Jiggle Animations
            const characterImage = document.getElementById('characterImage');
            const characterImg = document.getElementById('characterImg');
            const currentHP = <?php echo $current_hp; ?>;

            // Click to jiggle
            characterImage.addEventListener('click', function() {
                characterImg.style.transform = 'scale(1.05)';
                setTimeout(() => {
                    characterImg.style.transform = 'scale(1)';
                }, 300);
            });

            // Hover effect
            characterImage.addEventListener('mouseenter', function() {
                characterImg.style.transform = 'scale(1.05)';
            });

            characterImage.addEventListener('mouseleave', function() {
                characterImg.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>