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

// Function to calculate HP deduction parameters
function calculateHpDeduction($difficulty, $due_date, $todo_count) {
    $now = new DateTime();
    $deadline = new DateTime($due_date);
    $time_remaining = $deadline->getTimestamp() - $now->getTimestamp();
    
    if ($time_remaining <= 0) {
        throw new Exception("Due date must be in the future");
    }
    
    // Base HP deduction based on difficulty and todo count
    $base_multiplier = [
        'easy' => 1,
        'medium' => 1.5,
        'hard' => 2,
        'epic' => 3
    ];
    
    $base_deduction = 20 * $base_multiplier[$difficulty] * (1 + ($todo_count * 0.1));
    $total_hp_to_deduct = ceil($base_deduction);
    
    // Calculate optimal deduction interval and amount
    if ($time_remaining <= 300) { // 5 minutes or less - use minutely with immediate start
        $interval = 'minutely';
        $intervals = max(1, ceil($time_remaining / 60)); // Round UP to ensure full deduction
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 minute');
    } 
    elseif ($time_remaining <= 3600) { // 1 hour or less
        $interval = 'minutely';
        $intervals = max(1, ceil($time_remaining / 60)); // Round UP
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 minute');
    } 
    elseif ($time_remaining <= 86400) { // 24 hours or less
        $interval = 'hourly';
        $intervals = max(1, ceil($time_remaining / 3600)); // Round UP
        $deduction_per_interval = ceil($total_hp_to_deduct / $intervals);
        $next_deduction = (new DateTime())->modify('+1 hour');
    } 
    else {
        $interval = 'daily';
        $intervals = max(1, ceil($time_remaining / 86400)); // Round UP
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    $difficulty = $_POST['difficulty'];
    $due_date = $_POST['due_date'];
    
    // Calculate rewards based on difficulty
    $rewards = [
        'easy' => ['exp' => 50, 'gold' => 10],
        'medium' => ['exp' => 100, 'gold' => 25],
        'hard' => ['exp' => 200, 'gold' => 50],
        'epic' => ['exp' => 400, 'gold' => 100]
    ];
    
    $exp_reward = $rewards[$difficulty]['exp'];
    $gold_reward = $rewards[$difficulty]['gold'];
    
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
        header("Location: create_goal.php");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Calculate HP deduction parameters
        $deduction_data = calculateHpDeduction($difficulty, $due_date, $todo_count);
        
        // Insert goal with status 'active'
        $sql = "INSERT INTO goals (user_id, title, description, category, difficulty, due_date, exp_reward, gold_reward, todo_count, total_hp_to_deduct, hp_deduction_per_interval, deduction_interval, next_deduction, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            $user_id, $title, $description, $category, $difficulty, $due_date, 
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
        
        // Create monster or boss based on difficulty
        if ($difficulty == 'epic') {
            // Create boss
            $boss_hp = $deduction_data['total_hp_to_deduct'] * 2; // Boss has more HP
            $boss_folder = rand(1, 3); // Random boss appearance
            
            $sql = "INSERT INTO bosses (goal_id, boss_hp, current_hp, boss_folder) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id, $boss_hp, $boss_hp, $boss_folder]);
        } else {
            // Create regular monster
            $monster_hp = $deduction_data['total_hp_to_deduct'];
            $monster_folder = rand(1, 5); // Random monster appearance
            
            $sql = "INSERT INTO monsters (goal_id, monster_hp, current_hp, monster_folder) VALUES (?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id, $monster_hp, $monster_hp, $monster_folder]);
        }
        
        // Log goal creation
        $log_sql = "INSERT INTO goal_logs (user_id, goal_title, goal_description, category, difficulty, todo_count, exp_earned, gold_earned, status, feedback) 
                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'created', 'Quest created - awaiting completion')";
        $log_stmt = $pdo->prepare($log_sql);
        $log_stmt->execute([$user_id, $title, $description, $category, $difficulty, $todo_count, $exp_reward, $gold_reward]);
        
        $pdo->commit();
        
        $_SESSION['success'] = "Quest created successfully! Your HP will gradually decrease until you complete it.";
        header("Location: goals.php");
        exit();
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error creating quest: " . $e->getMessage();
        header("Location: create_goal.php");
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forge New Quest | RPG Goal Getter</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Roboto:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --primary-gold: #ffd700;
            --secondary-gold: #ffed4e;
            --dark-bg: #0a0a0a;
            --darker-bg: #050505;
            --card-bg: rgba(20, 20, 20, 0.8);
            --card-border: rgba(255, 215, 0, 0.3);
            --text-primary: #e6e6e6;
            --text-secondary: #b8b8b8;
            --danger: #dc3545;
            --success: #28a745;
            --warning: #ff9800;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: 
                radial-gradient(circle at 20% 30%, rgba(40, 10, 60, 0.4) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(10, 30, 60, 0.4) 0%, transparent 50%),
                linear-gradient(135deg, var(--dark-bg) 0%, var(--darker-bg) 100%);
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
                linear-gradient(135deg, rgba(30, 15, 40, 0.8) 0%, rgba(15, 10, 30, 0.9) 100%);
            border-radius: 15px;
            border: 2px solid var(--card-border);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            position: relative;
            overflow: hidden;
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            color: var(--primary-gold);
            font-size: 2.8rem;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.7);
            letter-spacing: 1px;
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
            background: rgba(30, 30, 40, 0.7);
            border: 1px solid rgba(80, 80, 100, 0.5);
            border-radius: 8px;
            transition: all 0.3s ease;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav a:hover {
            background: rgba(50, 40, 70, 0.8);
            border-color: var(--primary-gold);
            color: var(--primary-gold);
            transform: translateY(-2px);
        }

        .nav a.active {
            background: rgba(70, 50, 90, 0.9);
            border-color: var(--primary-gold);
            color: var(--primary-gold);
        }

        /* Stats */
        .stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(40, 25, 60, 0.8) 0%, rgba(25, 15, 40, 0.9) 100%);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            border: 1px solid rgba(100, 80, 150, 0.3);
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
        }

        .stat-value {
            font-size: 1.8rem;
            font-weight: bold;
            color: var(--primary-gold);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Difficulty Selection - Full Screen */
        .difficulty-section {
            min-height: 60vh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
        }

        .difficulty-title {
            font-family: 'Cinzel', serif;
            color: var(--primary-gold);
            font-size: 2.5rem;
            margin-bottom: 10px;
        }

        .difficulty-subtitle {
            color: var(--text-secondary);
            font-size: 1.2rem;
            margin-bottom: 40px;
        }

        .difficulty-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 25px;
            width: 100%;
            max-width: 1000px;
        }

        .difficulty-card {
            background: linear-gradient(135deg, rgba(40, 25, 60, 0.7) 0%, rgba(25, 15, 40, 0.8) 100%);
            border: 2px solid rgba(100, 80, 150, 0.5);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .difficulty-card:hover {
            border-color: var(--primary-gold);
            transform: translateY(-10px) scale(1.05);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .difficulty-card.selected {
            border-color: var(--primary-gold);
            background: linear-gradient(135deg, rgba(70, 40, 100, 0.8) 0%, rgba(50, 25, 75, 0.9) 100%);
            box-shadow: 0 0 30px rgba(255, 215, 0, 0.4);
            transform: translateY(-5px);
        }

        .difficulty-icon {
            font-size: 4rem;
            margin-bottom: 20px;
            color: var(--text-secondary);
            transition: all 0.3s ease;
        }

        .difficulty-card.selected .difficulty-icon,
        .difficulty-card:hover .difficulty-icon {
            color: var(--primary-gold);
            transform: scale(1.1);
        }

        .difficulty-name {
            font-weight: bold;
            margin-bottom: 15px;
            color: var(--text-primary);
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }

        .difficulty-card.selected .difficulty-name,
        .difficulty-card:hover .difficulty-name {
            color: var(--primary-gold);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .difficulty-rewards {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
            transition: all 0.3s ease;
        }

        .difficulty-card.selected .difficulty-rewards,
        .difficulty-card:hover .difficulty-rewards {
            color: var(--primary-gold);
        }

        .difficulty-desc {
            font-size: 0.9rem;
            color: #888;
            font-style: italic;
            margin-bottom: 15px;
        }

        .difficulty-warning {
            font-size: 0.8rem;
            color: var(--danger);
            margin-top: 10px;
            font-weight: bold;
        }

        /* Quest Form (Hidden Initially) */
        .quest-form {
            background: linear-gradient(135deg, rgba(30, 20, 50, 0.8) 0%, rgba(20, 15, 35, 0.9) 100%);
            border-radius: 15px;
            padding: 30px;
            border: 2px solid var(--card-border);
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
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
            color: var(--primary-gold);
            font-weight: 500;
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 14px 18px;
            background: rgba(15, 10, 25, 0.7);
            border: 1px solid rgba(100, 80, 150, 0.5);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'Roboto', sans-serif;
            transition: all 0.3s ease;
            font-size: 1rem;
        }

        .form-input:focus, .form-select:focus, .form-textarea:focus {
            outline: none;
            border-color: var(--primary-gold);
            background: rgba(25, 15, 40, 0.8);
            box-shadow: 0 0 10px rgba(255, 215, 0, 0.2);
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
            background: var(--success);
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
            background: #218838;
            transform: translateY(-2px);
        }

        /* HP Warning */
        .hp-warning {
            background: linear-gradient(135deg, rgba(80, 10, 20, 0.7) 0%, rgba(50, 5, 10, 0.8) 100%);
            border: 1px solid rgba(220, 53, 69, 0.5);
            border-radius: 10px;
            padding: 20px;
            margin: 20px 0;
            text-align: center;
        }

        .hp-warning i {
            color: var(--danger);
            font-size: 1.8rem;
            margin-bottom: 10px;
        }

        .hp-warning h3 {
            color: #ff6b6b;
            margin-bottom: 10px;
            font-size: 1.2rem;
            font-family: 'Cinzel', serif;
        }

        /* Deduction Preview */
        .deduction-preview {
            background: linear-gradient(135deg, rgba(80, 10, 20, 0.5) 0%, rgba(50, 5, 10, 0.6) 100%);
            border: 1px solid rgba(220, 53, 69, 0.5);
            border-radius: 10px;
            padding: 20px;
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
            color: #ff6b6b;
            margin-bottom: 15px;
            text-align: center;
            font-size: 1.2rem;
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
            background: rgba(30, 10, 15, 0.7);
            border-radius: 8px;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }

        .deduction-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #ff4444;
            margin-bottom: 5px;
        }

        .deduction-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
        }

        /* Submit Button */
        .btn-submit {
            width: 100%;
            padding: 18px;
            background: linear-gradient(135deg, var(--primary-gold), var(--secondary-gold));
            color: #1a1a2e;
            border: none;
            border-radius: 10px;
            font-family: 'Cinzel', serif;
            font-size: 1.3rem;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s ease;
            margin-top: 20px;
        }

        .btn-submit:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(255, 215, 0, 0.5);
        }

        /* Back Button */
        .btn-back {
            background: rgba(30, 30, 40, 0.7);
            color: var(--text-primary);
            border: 1px solid rgba(80, 80, 100, 0.5);
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
            background: rgba(50, 40, 70, 0.8);
            border-color: var(--primary-gold);
            color: var(--primary-gold);
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
            background: linear-gradient(135deg, rgba(80, 10, 20, 0.7) 0%, rgba(50, 5, 10, 0.8) 100%);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #ff6b6b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .difficulty-grid {
                grid-template-columns: 1fr;
            }
            
            .difficulty-card {
                padding: 20px;
            }
            
            .header h1 {
                font-size: 2.2rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <header class="header">
            <h1><i class="fas fa-dragon"></i> Forge New Quest</h1>
            <p>Choose your challenge and craft your destiny</p>
        </header>

        <!-- Navigation -->
        <nav class="nav">
            <a href="dashboard.php"><i class="fas fa-home"></i> Dashboard</a>
            <a href="goals.php"><i class="fas fa-scroll"></i> My Quests</a>
            <a href="create_goal.php" class="active"><i class="fas fa-plus"></i> New Quest</a>
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

        <!-- Difficulty Selection Section -->
        <section class="difficulty-section" id="difficultySection">
            <h2 class="difficulty-title">Choose Your Challenge</h2>
            <p class="difficulty-subtitle">Select a difficulty to begin crafting your quest</p>
            
            <div class="difficulty-grid">
                <div class="difficulty-card" data-difficulty="easy">
                    <div class="difficulty-icon">🛡️</div>
                    <div class="difficulty-name">Easy</div>
                    <div class="difficulty-rewards">+50 XP • +10 Gold</div>
                    <div class="difficulty-desc">Simple challenges for beginners</div>
                    <div class="difficulty-warning">Light HP Drain</div>
                </div>
                
                <div class="difficulty-card" data-difficulty="medium">
                    <div class="difficulty-icon">⚔️</div>
                    <div class="difficulty-name">Medium</div>
                    <div class="difficulty-rewards">+100 XP • +25 Gold</div>
                    <div class="difficulty-desc">Moderate challenges for experienced adventurers</div>
                    <div class="difficulty-warning">Moderate HP Drain</div>
                </div>
                
                <div class="difficulty-card" data-difficulty="hard">
                    <div class="difficulty-icon">🏹</div>
                    <div class="difficulty-name">Hard</div>
                    <div class="difficulty-rewards">+200 XP • +50 Gold</div>
                    <div class="difficulty-desc">Tough challenges for seasoned warriors</div>
                    <div class="difficulty-warning">Heavy HP Drain</div>
                </div>
                
                <div class="difficulty-card" data-difficulty="epic">
                    <div class="difficulty-icon">🐉</div>
                    <div class="difficulty-name">Epic</div>
                    <div class="difficulty-rewards">+400 XP • +100 Gold</div>
                    <div class="difficulty-desc">Legendary challenges for true heroes</div>
                    <div class="difficulty-warning">Severe HP Drain</div>
                </div>
            </div>
        </section>

        <!-- Quest Form Section (Hidden Initially) -->
        <section class="quest-form-section">
            <button class="btn-back" id="backButton" style="display: none;">
                <i class="fas fa-arrow-left"></i> Back to Difficulty Selection
            </button>

            <div class="quest-form" id="questForm">
                <!-- HP Warning -->
                <div class="hp-warning">
                    <i class="fas fa-skull-crossbones"></i>
                    <h3>HP DRAIN WARNING</h3>
                    <p>Your HP will decrease over time until this quest is completed. Failure means permanent HP loss!</p>
                </div>

                <!-- Deduction Preview -->
                <div class="deduction-preview" id="deductionPreview">
                    <h3><i class="fas fa-heart-crack"></i> HP DRAIN FORECAST</h3>
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
                    <input type="hidden" id="difficulty" name="difficulty" required>
                    
                    <!-- Quest Title -->
                    <div class="form-group">
                        <label class="form-label" for="title">
                            <i class="fas fa-crown"></i> Quest Title
                        </label>
                        <input type="text" id="title" name="title" class="form-input" required 
                               placeholder="Enter your quest title">
                    </div>

                    <!-- Quest Description -->
                    <div class="form-group">
                        <label class="form-label" for="description">
                            <i class="fas fa-scroll"></i> Quest Description
                        </label>
                        <textarea id="description" name="description" class="form-textarea" required 
                                  placeholder="Describe your quest and objectives"></textarea>
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
                            <i class="fas fa-hourglass"></i> Due Date
                        </label>
                        <input type="datetime-local" id="due_date" name="due_date" class="form-input" required 
                               min="<?php echo date('Y-m-d\TH:i'); ?>">
                    </div>

                    <!-- Quest Tasks -->
                    <div class="form-group">
                        <label class="form-label">
                            <i class="fas fa-tasks"></i> Tasks
                        </label>
                        <div class="todo-system" id="todoContainer">
                            <div class="todo-item">
                                <input type="text" name="todo[]" class="form-input todo-input" 
                                       placeholder="Enter a task" required>
                                <button type="button" class="btn-remove-todo" onclick="removeTodo(this)" disabled>
                                    <i class="fas fa-times"></i>
                                </button>
                            </div>
                        </div>
                        <button type="button" class="btn-add-todo" onclick="addTodo()">
                            <i class="fas fa-plus"></i> Add Task
                        </button>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-submit">
                        <i class="fas fa-fire"></i> CREATE QUEST
                    </button>
                </form>
            </div>
        </section>
    </div>

    <script>
        // Initialize todo count
        let todoCount = 1;
        let selectedDifficulty = '';

        // Difficulty selection
        document.querySelectorAll('.difficulty-card').forEach(option => {
            option.addEventListener('click', function() {
                selectedDifficulty = this.dataset.difficulty;
                
                // Update UI
                document.querySelectorAll('.difficulty-card').forEach(opt => {
                    opt.classList.remove('selected');
                });
                this.classList.add('selected');
                
                // Set hidden input
                document.getElementById('difficulty').value = selectedDifficulty;
                
                // Show quest form
                document.getElementById('difficultySection').style.display = 'none';
                document.getElementById('questForm').classList.add('active');
                document.getElementById('backButton').style.display = 'block';
                
                // Set minimum datetime for due date
                const now = new Date();
                const minDateTime = now.toISOString().slice(0, 16);
                document.getElementById('due_date').min = minDateTime;
                
                // Initialize calculation
                calculateHpPreview();
            });
        });

        // Back button
        document.getElementById('backButton').addEventListener('click', function() {
            document.getElementById('difficultySection').style.display = 'flex';
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
                           placeholder="Enter a task" required>
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
                       placeholder="Enter task ${todoCount}" required>
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

        // Calculate HP deduction preview
        function calculateHpPreview() {
            if (!selectedDifficulty) return;
            
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
            const baseMultiplier = {
                'easy': 1,
                'medium': 1.5,
                'hard': 2,
                'epic': 3
            };
            
            // Calculate base deduction
            const baseDeduction = 20 * baseMultiplier[selectedDifficulty] * (1 + (currentTodos * 0.1));
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
                finalHpElement.style.color = '#ff4444';
                remainingHpElement.style.color = '#ff4444';
            } else if (finalHp <= 30) {
                finalHpElement.style.color = '#ff9800';
                remainingHpElement.style.color = '#ff9800';
            } else {
                finalHpElement.style.color = '#00ff00';
                remainingHpElement.style.color = '#00ff00';
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
                alert(`Please fill in all task fields! Empty tasks: ${emptyTodos.join(', ')}`);
            }
        });

        // Initialize the page
        document.addEventListener('DOMContentLoaded', function() {
            updateRemoveButtons();
        });
    </script>
</body>
</html>