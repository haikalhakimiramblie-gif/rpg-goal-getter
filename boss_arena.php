<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user data for HP display
try {
    $user_sql = "SELECT current_hp, max_hp, level, exp, gold FROM users WHERE id = ?";
    $stmt = $pdo->prepare($user_sql);
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception("User not found");
    }
    
    $current_hp = $user_data['current_hp'];
    $max_hp = $user_data['max_hp'];
    $user_level = $user_data['level'];
    $user_exp = $user_data['exp'];
    $gold = $user_data['gold'];
    
} catch (Exception $e) {
    die("Error loading user data: " . $e->getMessage());
}

// Function to calculate HP deduction parameters for BOSS
function calculateBossHpDeduction($due_date, $todo_count) {
    $now = new DateTime();
    $deadline = new DateTime($due_date);
    $time_remaining = $deadline->getTimestamp() - $now->getTimestamp();
    
    if ($time_remaining <= 0) {
        throw new Exception("Due date must be in the future");
    }
    
    // BOSS difficulty multiplier - more severe than epic
    $boss_multiplier = 4; // Even higher than epic's 3
    
    // Base HP deduction for boss (higher base)
    $base_deduction = 30 * $boss_multiplier * (1 + ($todo_count * 0.15));
    $total_hp_to_deduct = ceil($base_deduction);
    
    // Calculate optimal deduction interval and amount
    if ($time_remaining <= 300) { // 5 minutes or less - use minutely with immediate start
        $interval = 'minutely';
        $intervals = max(1, ceil($time_remaining / 60));
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 minute');
    } 
    elseif ($time_remaining <= 3600) { // 1 hour or less
        $interval = 'minutely';
        $intervals = max(1, ceil($time_remaining / 60));
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 minute');
    } 
    elseif ($time_remaining <= 86400) { // 24 hours or less
        $interval = 'hourly';
        $intervals = max(1, ceil($time_remaining / 3600));
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 hour');
    } 
    else {
        $interval = 'daily';
        $intervals = max(1, ceil($time_remaining / 86400));
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 day')->setTime(0, 0, 0);
    }
    
    // Ensure minimum deduction of 1
    $deduction_per_interval = max(1, $deduction_per_interval);
    
    // Adjust total HP to deduct to account for rounding
    $adjusted_total_deduct = $deduction_per_interval * $intervals;
    if ($adjusted_total_deduct > $total_hp_to_deduct) {
        $total_hp_to_deduct = $adjusted_total_deduct;
    }
    
    return [
        'total_hp_to_deduct' => $total_hp_to_deduct,
        'hp_deduction_per_interval' => $deduction_per_interval,
        'deduction_interval' => $interval,
        'next_deduction' => $next_deduction->format('Y-m-d H:i:s'),
        'time_remaining_hours' => round($time_remaining / 3600, 1)
    ];
}

// Handle form submission for BOSS ARENA
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $due_date = $_POST['due_date'];
    
    // BOSS ARENA rewards - higher than epic
    $exp_reward = 600; // Higher than epic's 400
    $gold_reward = 150; // Higher than epic's 100
    
    // Process todos
    $todos = [];
    foreach ($_POST['todo'] as $todo) {
        $todo = trim($todo);
        if (!empty($todo)) {
            $todos[] = $todo;
        }
    }
    
    $todo_count = count($todos);
    
    // Validate data
    if (empty($title) || empty($description) || $todo_count == 0) {
        $_SESSION['error'] = "Please fill in all required fields and add at least one task.";
        header("Location: boss_arena.php");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Calculate HP deduction parameters for BOSS
        $deduction_data = calculateBossHpDeduction($due_date, $todo_count);
        
        // Insert goal with status 'active' and difficulty 'epic' for boss
        $sql = "INSERT INTO goals (user_id, title, description, category, difficulty, due_date, exp_reward, gold_reward, todo_count, total_hp_to_deduct, hp_deduction_per_interval, deduction_interval, next_deduction, status) 
                VALUES (?, ?, ?, ?, 'epic', ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, $title, $description, $category, $due_date, 
            $exp_reward, $gold_reward, $todo_count,
            $deduction_data['total_hp_to_deduct'],
            $deduction_data['hp_deduction_per_interval'],
            $deduction_data['deduction_interval'],
            $deduction_data['next_deduction']
        ]);
        
        $goal_id = $pdo->lastInsertId();
        
        // Insert todos
        $position = 1;
        foreach ($todos as $todo) {
            $sql = "INSERT INTO goal_todos (goal_id, todo_name, position) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id, $todo, $position]);
            $position++;
        }
        
        // Create BOSS (always create boss in boss arena)
        $boss_hp = $deduction_data['total_hp_to_deduct'] * 3; // Boss has even more HP in arena
        $boss_folder = rand(1, 3); // Random boss appearance
        
        $sql = "INSERT INTO bosses (goal_id, boss_hp, current_hp, boss_folder) VALUES (?, ?, ?, ?)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goal_id, $boss_hp, $boss_hp, $boss_folder]);
        
        // Log goal creation
        $log_sql = "INSERT INTO goal_logs (user_id, goal_title, goal_description, category, difficulty, todo_count, exp_earned, gold_earned, status, feedback) 
                   VALUES (?, ?, ?, ?, 'epic', ?, ?, ?, 'created', 'BOSS QUEST created - face your destiny!')";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$user_id, $title, $description, $category, $todo_count, $exp_reward, $gold_reward]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "BOSS QUEST created! Prepare for battle - your HP will drain rapidly until victory!";
        header("Location: bossgoals.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating boss quest: " . $e->getMessage();
        header("Location: boss_arena.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boss Arena | RPG Goal Getter</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-red: #ff4444;
            --secondary-red: #ff6b6b;
            --dark-bg: #0a0a0a;
            --darker-bg: #050505;
            --card-bg: rgba(40, 10, 10, 0.8);
            --card-border: rgba(255, 68, 68, 0.3);
            --text-primary: #e6e6e6;
            --text-secondary: #b8b8b8;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ff9800;
            --boss-gold: #ffd700;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: 
                radial-gradient(circle at 20% 30%, rgba(80, 10, 10, 0.6) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(60, 5, 5, 0.6) 0%, transparent 50%),
                linear-gradient(135deg, #1a0000 0%, #0a0000 100%);
            color: var(--text-primary);
            min-height: 100vh;
            line-height: 1.6;
            position: relative;
            overflow-x: hidden;
        }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 25px;
            background: 
                linear-gradient(135deg, rgba(60, 10, 10, 0.9) 0%, rgba(40, 5, 5, 0.95) 100%);
            border-radius: 15px;
            border: 3px solid var(--card-border);
            box-shadow: 0 10px 25px rgba(255, 0, 0, 0.3);
            position: relative;
            overflow: hidden;
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            color: var(--primary-red);
            font-size: 3rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(255, 0, 0, 0.7);
            letter-spacing: 2px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
            font-style: italic;
            margin-top: 10px;
        }

        /* Navigation */
        .nav {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin-bottom: 30px;
            flex-wrap: wrap;
        }

        .nav a {
            color: var(--text-primary);
            text-decoration: none;
            padding: 12px 25px;
            background: rgba(60, 20, 20, 0.7);
            border: 1px solid rgba(100, 40, 40, 0.5);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav a:hover {
            background: rgba(80, 30, 30, 0.8);
            border-color: var(--primary-red);
            color: var(--primary-red);
            transform: translateY(-2px);
        }

        .nav a.active {
            background: rgba(100, 30, 30, 0.9);
            border-color: var(--primary-red);
            color: var(--primary-red);
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(60, 15, 15, 0.8) 0%, rgba(40, 10, 10, 0.9) 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(150, 50, 50, 0.3);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-red);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Boss Challenge Section */
        .boss-section {
            min-height: 50vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            margin-bottom: 30px;
        }

        .boss-title {
            font-family: 'Cinzel', serif;
            color: var(--primary-red);
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 0 0 20px rgba(255, 0, 0, 0.5);
        }

        .boss-subtitle {
            color: var(--text-secondary);
            font-size: 1.3rem;
            margin-bottom: 40px;
            font-style: italic;
        }

        .boss-card {
            background: linear-gradient(135deg, rgba(80, 15, 15, 0.8) 0%, rgba(50, 10, 10, 0.9) 100%);
            border: 3px solid var(--primary-red);
            border-radius: 20px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            box-shadow: 0 0 30px rgba(255, 0, 0, 0.4);
        }

        .boss-card:hover {
            transform: translateY(-10px) scale(1.02);
            box-shadow: 0 20px 40px rgba(255, 0, 0, 0.6);
            border-color: #ff0000;
        }

        .boss-card.selected {
            background: linear-gradient(135deg, rgba(100, 20, 20, 0.9) 0%, rgba(70, 15, 15, 0.95) 100%);
            box-shadow: 0 0 40px rgba(255, 0, 0, 0.7);
            transform: translateY(-5px);
        }

        .boss-icon {
            font-size: 5rem;
            margin-bottom: 25px;
            color: var(--primary-red);
            text-shadow: 0 0 20px rgba(255, 0, 0, 0.7);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        .boss-name {
            font-weight: bold;
            margin-bottom: 20px;
            color: var(--boss-gold);
            font-family: 'Cinzel', serif;
            font-size: 2rem;
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .boss-rewards {
            font-size: 1.4rem;
            color: var(--boss-gold);
            margin-bottom: 15px;
            font-weight: bold;
        }

        .boss-desc {
            font-size: 1rem;
            color: #ffa07a;
            font-style: italic;
            margin-bottom: 20px;
        }

        .boss-warning {
            font-size: 1rem;
            color: var(--primary-red);
            margin-top: 15px;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Quest Form */
        .quest-form {
            background: linear-gradient(135deg, rgba(50, 15, 15, 0.9) 0%, rgba(30, 10, 10, 0.95) 100%);
            border-radius: 15px;
            padding: 30px;
            border: 3px solid var(--card-border);
            box-shadow: 0 10px 30px rgba(255, 0, 0, 0.3);
            display: none;
            position: relative;
            overflow: hidden;
        }

        .quest-form.active {
            display: block;
            animation: fadeInUp 0.5s ease;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            display: block;
            margin-bottom: 10px;
            color: var(--primary-red);
            font-weight: 500;
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(30, 10, 10, 0.8);
            border: 1px solid rgba(150, 50, 50, 0.5);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-red);
            background: rgba(40, 15, 15, 0.9);
            box-shadow: 0 0 10px rgba(255, 68, 68, 0.3);
        }

        .form-textarea {
            min-height: 120px;
            resize: vertical;
        }

        /* Todo System */
        .todo-system {
            margin-top: 10px;
        }

        .todo-item {
            display: flex;
            gap: 10px;
            margin-bottom: 10px;
            align-items: center;
        }

        .todo-input {
            flex: 1;
        }

        .btn-remove-todo {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 6px;
            padding: 14px 16px;
            cursor: pointer;
            transition: all 0.3s ease;
            min-width: 50px;
        }

        .btn-remove-todo:hover {
            background: #c82333;
            transform: scale(1.05);
        }

        .btn-remove-todo:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .btn-add-todo {
            background: var(--danger);
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 10px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-add-todo:hover {
            background: #c82333;
            transform: translateY(-2px);
        }

        /* HP Warning */
        .hp-warning {
            background: linear-gradient(135deg, rgba(100, 10, 10, 0.8) 0%, rgba(70, 5, 5, 0.9) 100%);
            border: 2px solid rgba(255, 68, 68, 0.6);
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            text-align: center;
        }

        .hp-warning i {
            color: var(--primary-red);
            font-size: 2rem;
            margin-bottom: 15px;
        }

        .hp-warning h3 {
            color: #ff4444;
            margin-bottom: 15px;
            font-size: 1.4rem;
            font-family: 'Cinzel', serif;
        }

        /* Deduction Preview */
        .deduction-preview {
            background: linear-gradient(135deg, rgba(100, 10, 10, 0.7) 0%, rgba(70, 5, 5, 0.8) 100%);
            border: 2px solid rgba(255, 68, 68, 0.6);
            border-radius: 10px;
            padding: 25px;
            margin: 20px 0;
            display: none;
        }

        .deduction-preview.active {
            display: block;
            animation: fadeIn 0.5s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .deduction-preview h3 {
            color: #ff4444;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.4rem;
            font-family: 'Cinzel', serif;
        }

        .deduction-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .deduction-item {
            text-align: center;
            padding: 15px;
            background: rgba(40, 10, 10, 0.8);
            border-radius: 8px;
            border: 1px solid rgba(255, 68, 68, 0.4);
        }

        .deduction-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff0000;
            margin-bottom: 5px;
        }

        .deduction-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 20px;
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
            color: white;
            border: none;
            border-radius: 10px;
            font-family: 'Cinzel', serif;
            font-size: 1.4rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 68, 68, 0.6);
            background: linear-gradient(135deg, #ff0000, var(--primary-red));
        }

        /* Back Button */
        .btn-back {
            background: rgba(60, 20, 20, 0.7);
            color: var(--text-primary);
            border: 1px solid rgba(100, 40, 40, 0.5);
            border-radius: 8px;
            padding: 12px 20px;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .btn-back:hover {
            background: rgba(80, 30, 30, 0.8);
            border-color: var(--primary-red);
            color: var(--primary-red);
        }

        /* Messages */
        .message {
            padding: 15px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: 500;
        }

        .message.success {
            background: linear-gradient(135deg, rgba(20, 80, 30, 0.7) 0%, rgba(10, 50, 20, 0.8) 100%);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #90ee90;
        }

        .message.error {
            background: linear-gradient(135deg, rgba(100, 10, 10, 0.8) 0%, rgba(70, 5, 5, 0.9) 100%);
            border: 1px solid rgba(255, 68, 68, 0.6);
            color: #ff6b6b;
        }

        /* Level Requirement Warning */
        .level-warning {
            background: linear-gradient(135deg, rgba(100, 80, 10, 0.7) 0%, rgba(70, 50, 5, 0.8) 100%);
            border: 2px solid rgba(255, 215, 0, 0.6);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .level-warning i {
            color: var(--boss-gold);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .level-warning h3 {
            color: var(--boss-gold);
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-family: 'Cinzel', serif;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .boss-card {
                padding: 25px;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
            
            .boss-title {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1><i class="fas fa-dragon"></i> BOSS ARENA</h1>
            <p>Face legendary challenges and prove your worth</p>
        </header>

        <!-- Navigation -->
        <nav class="nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="bossgoals.php"><i class="fas fa-scroll"></i> My Quests</a>
            <a href="boss_arena.php" class="active"><i class="fas fa-dragon"></i> Boss Arena</a>
            <a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a>
        </nav>

        <!-- User Stats -->
        <div class="stats">
            <div class="stat-card">
                <div class="stat-value">Level <?php echo $user_level; ?></div>
                <div class="stat-label">Adventurer</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $current_hp; ?>/<?php echo $max_hp; ?></div>
                <div class="stat-label">Health</div>
            </div>
            <div class="stat-card">
                <div class="stat-value" id="remainingHp"><?php echo $current_hp; ?></div>
                <div class="stat-label">Remaining HP</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?php echo $gold; ?> <i class="fas fa-coins"></i></div>
                <div class="stat-label">Gold</div>
            </div>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Level Requirement Warning -->
        <?php if ($user_level < 10): ?>
            <div class="level-warning">
                <i class="fas fa-exclamation-triangle"></i>
                <h3>WARNING: BOSS ARENA CHALLENGE</h3>
                <p>You are Level <?php echo $user_level; ?>. Boss Arena is recommended for Level 10+ adventurers.</p>
                <p><strong>Proceed with extreme caution!</strong></p>
            </div>
        <?php endif; ?>

        <!-- Boss Challenge Section -->
        <section class="boss-section" id="bossSection">
            <h2 class="boss-title">ACCEPT THE CHALLENGE</h2>
            <p class="boss-subtitle">Only the bravest adventurers dare to face the Boss Arena</p>
            
            <div class="boss-card" id="bossCard">
                <div class="boss-icon">🐉</div>
                <div class="boss-name">EPIC BOSS QUEST</div>
                <div class="boss-rewards">+600 XP • +150 Gold</div>
                <div class="boss-desc">Legendary challenges that will test your limits</div>
                <div class="boss-warning">EXTREME HP DRAIN • BOSS BATTLE</div>
            </div>
        </section>

        <!-- Quest Form Section (Hidden Initially) -->
        <section class="quest-form-section">
            <button class="btn-back" id="backButton" style="display: none;">
                <i class="fas fa-arrow-left"></i> Back to Arena
            </button>

            <div class="quest-form" id="questForm">
                <!-- HP Warning -->
                <div class="hp-warning">
                    <i class="fas fa-skull-crossbones"></i>
                    <h3>BOSS ARENA HP DRAIN WARNING</h3>
                    <p>Your HP will decrease RAPIDLY over time until this boss quest is completed. Failure means PERMANENT HP loss!</p>
                </div>

                <!-- Deduction Preview -->
                <div class="deduction-preview" id="deductionPreview">
                    <h3><i class="fas fa-heart-crack"></i> BOSS HP DRAIN FORECAST</h3>
                    <div class="deduction-grid">
                        <div class="deduction-item">
                            <div class="deduction-value" id="previewTotalDeduction">0</div>
                            <div class="deduction-label">Total HP Loss</div>
                        </div>
                        <div class="deduction-item">
                            <div class="deduction-value" id="previewDeductionPerInterval">0</div>
                            <div class="deduction-label">Loss per Interval</div>
                        </div>
                        <div class="deduction-item">
                            <div class="deduction-value" id="previewInterval">-</div>
                            <div class="deduction-label">Drain Interval</div>
                        </div>
                        <div class="deduction-item">
                            <div class="deduction-value" id="previewFinalHp"><?php echo $current_hp; ?></div>
                            <div class="deduction-label">Final HP</div>
                        </div>
                    </div>
                </div>

                <form method="POST" id="questFormElement">
                    <input type="hidden" id="difficulty" name="difficulty" value="epic" required>
                    
                    <!-- Quest Title -->
                    <div class="form-group">
                        <label class="form-label" for="title">
                            <i class="fas fa-crown"></i> BOSS QUEST TITLE
                        </label>
                        <input type="text" id="title" name="title" class="form-input" required 
                               placeholder="Enter your boss quest title">
                    </div>

                    <!-- Quest Description -->
                    <div class="form-group">
                        <label class="form-label" for="description">
                            <i class="fas fa-scroll"></i> BOSS QUEST DESCRIPTION
                        </label>
                        <textarea id="description" name="description" class="form-textarea" required 
                                  placeholder="Describe your epic challenge and objectives"></textarea>
                    </div>

                    <!-- Quest Category -->
                    <div class="form-group">
                        <label class="form-label" for="category">
                            <i class="fas fa-compass"></i> Category
                        </label>
                        <select id="category" name="category" class="form-select" required>
                            <option value="">Select a category</option>
                            <option value="work">Work</option>
                            <option value="study">Study</option>
                            <option value="health">Health</option>
                            <option value="personal">Personal</option>
                            <option value="creative">Creative</option>
                            <option value="financial">Financial</option>
                            <option value="social">Social</option>
                            <option value="other">Other</option>
                        </select>
                    </div>

                    <!-- Quest Deadline -->
                    <div class="form-group">
                        <label class="form-label" for="due_date">
                            <i class="fas fa-hourglass"></i> BOSS DEFEAT DEADLINE
                        </label>
                        <input type="datetime-local" id="due_date" name="due_date" class="form-input" required 
                               min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <!-- Quest Tasks -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tasks"></i> BOSS PHASES
                        </label>
                        <div class="todo-system" id="todoContainer">
                            <div class="todo-item">
                                <input type="text" name="todo[]" class="form-input todo-input" 
                                       placeholder="Enter a boss phase/task" required>
                                <button type="button" class="btn-remove-todo" onclick="removeTodo(this)" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-todo" onclick="addTodo()">
                            <i class="fas fa-plus"></i> Add Boss Phase
                        </button>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-dragon"></i> SUMMON BOSS QUEST
                    </button>
                </form>
            </div>
        </section>
    </div>

    <script>
        // Initialize todo count
        let todoCount = 1;

        // Boss card selection
        document.getElementById('bossCard').addEventListener('click', function() {
            // Show quest form
            document.getElementById('bossSection').style.display = 'none';
            document.getElementById('questForm').classList.add('active');
            document.getElementById('backButton').style.display = 'block';
            
            // Set minimum datetime for due date
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);
            document.getElementById('due_date').min = minDateTime;
            
            // Initialize calculation
            calculateHpPreview();
        });

        // Back button
        document.getElementById('backButton').addEventListener('click', function() {
            document.getElementById('bossSection').style.display = 'flex';
            document.getElementById('questForm').classList.remove('active');
            this.style.display = 'none';
            
            // Reset form
            document.getElementById('questFormElement').reset();
            document.getElementById('deductionPreview').classList.remove('active');
            
            // Reset todos
            const container = document.getElementById('todoContainer');
            container.innerHTML = `
                <div class="todo-item">
                    <input type="text" name="todo[]" class="form-input todo-input" 
                           placeholder="Enter a boss phase/task" required>
                    <button type="button" class="btn-remove-todo" onclick="removeTodo(this)" disabled>
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            `;
            todoCount = 1;
            updateRemoveButtons();
        });

        // Add todo function
        function addTodo() {
            todoCount++;
            const container = document.getElementById('todoContainer');
            const todoItem = document.createElement('div');
            todoItem.className = 'todo-item';
            todoItem.innerHTML = `
                <input type="text" name="todo[]" class="form-input todo-input" 
                       placeholder="Enter boss phase ${todoCount}" required>
                <button type="button" class="btn-remove-todo" onclick="removeTodo(this)">
                    <i class="fas fa-times"></i>
                </button>
            `;
            container.appendChild(todoItem);
            
            updateRemoveButtons();
            calculateHpPreview();
        }

        // Remove todo function
        function removeTodo(button) {
            if (todoCount > 1) {
                button.parentElement.remove();
                todoCount--;
                updateRemoveButtons();
                calculateHpPreview();
            }
        }

        // Update remove buttons state
        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.btn-remove-todo');
            removeButtons.forEach(btn => {
                btn.disabled = todoCount <= 1;
            });
        }

        // Calculate HP deduction preview for BOSS
        function calculateHpPreview() {
            const dueDate = document.getElementById('due_date').value;
            const currentTodos = document.querySelectorAll('.todo-input').length;
            
            if (!dueDate) {
                document.getElementById('deductionPreview').classList.remove('active');
                return;
            }
            
            const now = new Date();
            const deadline = new Date(dueDate);
            const timeRemaining = deadline.getTime() - now.getTime();
            
            if (timeRemaining <= 0) {
                document.getElementById('deductionPreview').classList.remove('active');
                return;
            }
            
            const secondsRemaining = timeRemaining / 1000;
            
            // BOSS difficulty multiplier - more severe
            const bossMultiplier = 4;
            
            // Calculate base deduction for BOSS (higher base)
            const baseDeduction = 30 * bossMultiplier * (1 + (currentTodos * 0.15));
            let totalHpToDeduct = Math.ceil(baseDeduction);
            
            let interval, deductionPerInterval, intervals;
            
            // Determine interval based on time remaining
            if (secondsRemaining <= 300) {
                interval = 'minutely';
                intervals = Math.max(1, Math.ceil(secondsRemaining / 60));
                deductionPerInterval = Math.ceil(totalHpToDeduct / intervals);
            } 
            else if (secondsRemaining <= 3600) {
                interval = 'minutely';
                intervals = Math.max(1, Math.ceil(secondsRemaining / 60));
                deductionPerInterval = Math.ceil(totalHpToDeduct / intervals);
            } 
            else if (secondsRemaining <= 86400) {
                interval = 'hourly';
                intervals = Math.max(1, Math.ceil(secondsRemaining / 3600));
                deductionPerInterval = Math.ceil(totalHpToDeduct / intervals);
            } 
            else {
                interval = 'daily';
                intervals = Math.max(1, Math.ceil(secondsRemaining / 86400));
                deductionPerInterval = Math.ceil(totalHpToDeduct / intervals);
            }
            
            // Ensure minimum deduction
            deductionPerInterval = Math.max(1, deductionPerInterval);
            
            // Adjust total to account for rounding
            const adjustedTotalDeduct = deductionPerInterval * intervals;
            if (adjustedTotalDeduct > totalHpToDeduct) {
                totalHpToDeduct = adjustedTotalDeduct;
            }
            
            // Update preview elements
            document.getElementById('previewTotalDeduction').textContent = totalHpToDeduct;
            document.getElementById('previewDeductionPerInterval').textContent = deductionPerInterval;
            document.getElementById('previewInterval').textContent = interval;
            
            const currentHp = <?php echo $current_hp; ?>;
            const finalHp = Math.max(0, currentHp - totalHpToDeduct);
            document.getElementById('previewFinalHp').textContent = finalHp;
            document.getElementById('remainingHp').textContent = finalHp;
            
            // Color code based on final HP
            const finalHpElement = document.getElementById('previewFinalHp');
            const remainingHpElement = document.getElementById('remainingHp');
            
            if (finalHp <= 0) {
                finalHpElement.style.color = '#ff0000';
                remainingHpElement.style.color = '#ff0000';
            } else if (finalHp <= 30) {
                finalHpElement.style.color = '#ff6b6b';
                remainingHpElement.style.color = '#ff6b6b';
            } else {
                finalHpElement.style.color = '#90ee90';
                remainingHpElement.style.color = '#90ee90';
            }
            
            // Show the deduction preview
            document.getElementById('deductionPreview').classList.add('active');
        }

        // Event listeners for real-time calculation
        document.getElementById('due_date').addEventListener('change', calculateHpPreview);
        
        // Recalculate when todos change
        document.addEventListener('input', function(e) {
            if (e.target.classList.contains('todo-input')) {
                setTimeout(calculateHpPreview, 100);
            }
        });

        // Form validation
        document.getElementById('questFormElement').addEventListener('submit', function(e) {
            const todos = document.querySelectorAll('.todo-input');
            let hasEmptyTodo = false;
            let emptyTodos = [];
            
            // Check for empty todos
            todos.forEach((todo, index) => {
                if (todo.value.trim() === '') {
                    hasEmptyTodo = true;
                    emptyTodos.push(index + 1);
                }
            });
            
            if (hasEmptyTodo) {
                e.preventDefault();
                alert(`Boss Arena requires all phases to be filled! Empty phases: ${emptyTodos.join(', ')}`);
            }
        });

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>
</body>
</html>