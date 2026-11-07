<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$error = "";
$success = "";

// Get current month's quest counts for each difficulty
$current_month = date('Y-m');
$monthly_limits = [
    'easy' => 90,
    'medium' => 45,
    'hard' => 15,
    'epic' => 3
];

$monthly_counts = [];
foreach ($monthly_limits as $difficulty => $limit) {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM goals 
        WHERE user_id = ? 
        AND difficulty = ? 
        AND YEAR(created_at) = YEAR(CURRENT_DATE) 
        AND MONTH(created_at) = MONTH(CURRENT_DATE)
    ");
    $stmt->execute([$user_id, $difficulty]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $monthly_counts[$difficulty] = $result['count'];
}

// XP rewards based on difficulty
$xp_rewards = [
    'easy' => 10,
    'medium' => 25,
    'hard' => 50,
    'epic' => 100
];

// Gold rewards based on difficulty
$gold_rewards = [
    'easy' => 5,
    'medium' => 8,
    'hard' => 12,
    'epic' => 30
];

// Monster HP based on difficulty
$monster_hp = [
    'easy' => 50,
    'medium' => 100,
    'hard' => 200,
    'epic' => 500  // Boss HP
];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $category = $_POST['category'];
    $difficulty = $_POST['difficulty'];
    $due_date = $_POST['due_date'] ?: null;
    $todo_count = $_POST['todo_count'] ?? 1;
    $todo_names = $_POST['todo_name'] ?? [];
    
    // Auto-set XP and Gold based on difficulty
    $exp_reward = $xp_rewards[$difficulty];
    $gold_reward = $gold_rewards[$difficulty];
    
    // Check monthly limit
    if ($monthly_counts[$difficulty] >= $monthly_limits[$difficulty]) {
        $error = "You have reached your monthly limit for {$difficulty} quests! (Limit: {$monthly_limits[$difficulty]} per month)";
    } else {
        try {
            $pdo->beginTransaction();
            
            // Insert the goal
            $stmt = $pdo->prepare("INSERT INTO goals (user_id, title, description, category, difficulty, exp_reward, gold_reward, due_date, todo_count) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$user_id, $title, $description, $category, $difficulty, $exp_reward, $gold_reward, $due_date, $todo_count]);
            $goal_id = $pdo->lastInsertId();
            
            // Insert todo items
            for ($i = 0; $i < $todo_count; $i++) {
                $todo_name = $todo_names[$i] ?? "Task " . ($i + 1);
                $stmt = $pdo->prepare("INSERT INTO goal_todos (goal_id, todo_name, completed, position) VALUES (?, ?, ?, ?)");
                $stmt->execute([$goal_id, $todo_name, 0, $i + 1]);
            }
            
            // Assign monster or boss based on difficulty
            if ($difficulty == 'epic') {
                // Assign boss for epic quests - randomly choose boss1 or boss2
                $boss_hp = $monster_hp['epic'];
                $stmt = $pdo->prepare("INSERT INTO bosses (goal_id, boss_hp, current_hp) VALUES (?, ?, ?)");
                $stmt->execute([$goal_id, $boss_hp, $boss_hp]);
            } else {
                // Assign regular monster for easy/medium/hard quests - randomly choose monster1 to monster5
                $monster_hp_value = $monster_hp[$difficulty];
                $stmt = $pdo->prepare("INSERT INTO monsters (goal_id, monster_hp, current_hp) VALUES (?, ?, ?)");
                $stmt->execute([$goal_id, $monster_hp_value, $monster_hp_value]);
            }
            
            $pdo->commit();
            
            $_SESSION['success'] = "New {$difficulty} quest created successfully! +{$exp_reward} XP • +{$gold_reward} Gold";
            header("Location: dashboard.php");
            exit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Error creating quest: " . $e->getMessage();
        }
    }
}

// Get today's date for the calendar min attribute
$today = date('Y-m-d');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Quest - RPG Goal Getter</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0a050a;
            --bg-secondary: #1a0f1a;
            --accent-gold: #d4af37;
            --accent-gold-light: #f0e68c;
            --accent-gold-dark: #8b6914;
            --text-primary: #e6d6b3;
            --text-secondary: #b8860b;
            --success-green: #90ee90;
            --error-red: #ff6b6b;
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: radial-gradient(ellipse at top, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            color: var(--text-primary);
            font-family: 'EB Garamond', serif;
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: linear-gradient(135deg, rgba(50, 25, 50, 0.95) 0%, rgba(30, 15, 30, 0.98) 100%);
            padding: 50px;
            border-radius: 20px;
            border: 3px solid var(--accent-gold);
            box-shadow: 0 20px 50px rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }
        
        .container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, 
                transparent, 
                var(--accent-gold), 
                var(--accent-gold-light), 
                var(--accent-gold), 
                transparent);
            animation: borderGlow 3s ease-in-out infinite;
        }
        
        @keyframes borderGlow {
            0%, 100% { opacity: 0.7; }
            50% { opacity: 1; }
        }
        
        h1 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            text-align: center;
            margin-bottom: 40px;
            font-size: 3rem;
            text-shadow: 0 4px 15px rgba(212, 175, 55, 0.4);
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light), var(--accent-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: 2px;
        }
        
        .header-icon {
            display: block;
            text-align: center;
            font-size: 3.5rem;
            margin-bottom: 20px;
            color: var(--accent-gold);
            text-shadow: 0 0 20px rgba(212, 175, 55, 0.5);
            animation: iconFloat 4s ease-in-out infinite;
        }
        
        @keyframes iconFloat {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-10px) scale(1.05); }
        }
        
        .form-section {
            margin-bottom: 40px;
            padding: 30px;
            background: rgba(40, 20, 40, 0.6);
            border-radius: 15px;
            border: 2px solid var(--accent-gold-dark);
            transition: all 0.3s ease;
        }
        
        .form-section:hover {
            border-color: var(--accent-gold);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.2);
        }
        
        .section-title {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-size: 1.4rem;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--accent-gold-dark);
        }
        
        .section-title i {
            font-size: 1.6rem;
            text-shadow: 0 0 10px rgba(212, 175, 55, 0.5);
        }
        
        .form-group {
            margin-bottom: 30px;
        }
        
        label {
            display: block;
            margin-bottom: 12px;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            letter-spacing: 1px;
        }
        
        input, select, textarea {
            width: 100%;
            padding: 18px 20px;
            background: linear-gradient(135deg, rgba(60, 30, 60, 0.9) 0%, rgba(40, 20, 40, 0.95) 100%);
            border: 2px solid var(--accent-gold-dark);
            border-radius: 12px;
            color: var(--text-primary);
            font-family: 'EB Garamond', serif;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: inset 0 4px 10px rgba(0, 0, 0, 0.4);
        }
        
        input:focus, select:focus, textarea:focus {
            outline: none;
            border-color: var(--accent-gold);
            box-shadow: 0 0 25px rgba(212, 175, 55, 0.6), inset 0 4px 10px rgba(0, 0, 0, 0.4);
            transform: translateY(-3px);
        }
        
        input::placeholder, textarea::placeholder {
            color: rgba(230, 214, 179, 0.6);
            font-size: 1rem;
        }
        
        textarea {
            resize: vertical;
            min-height: 120px;
            line-height: 1.6;
        }
        
        /* Custom Calendar Styling */
        input[type="date"] {
            position: relative;
            font-size: 1.1rem;
        }
        
        input[type="date"]::-webkit-calendar-picker-indicator {
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="%23d4af37" viewBox="0 0 16 16"><path d="M3.5 0a.5.5 0 0 1 .5.5V1h8V.5a.5.5 0 0 1 1 0V1h1a2 2 0 0 1 2 2v11a2 2 0 0 1-2 2H2a2 2 0 0 1-2-2V3a2 2 0 0 1 2-2h1V.5a.5.5 0 0 1 .5-.5zM2 2a1 1 0 0 0-1 1v11a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1V3a1 1 0 0 0-1-1H2z"/><path d="M2.5 4a.5.5 0 0 1 .5-.5h10a.5.5 0 0 1 .5.5v1a.5.5 0 0 1-.5.5H3a.5.5 0 0 1-.5-.5V4z"/></svg>') no-repeat;
            cursor: pointer;
            padding: 8px;
            border-radius: 5px;
            transition: all 0.3s ease;
        }
        
        input[type="date"]::-webkit-calendar-picker-indicator:hover {
            transform: scale(1.1);
        }
        
        /* Firefox calendar styling */
        input[type="date"] {
            color-scheme: dark;
        }
        
        button {
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-dark));
            color: var(--bg-primary);
            padding: 20px 40px;
            border: none;
            border-radius: 12px;
            font-family: 'Cinzel', serif;
            font-size: 1.3rem;
            cursor: pointer;
            width: 100%;
            transition: all 0.3s ease;
            font-weight: bold;
            letter-spacing: 2px;
            text-transform: uppercase;
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
            position: relative;
            overflow: hidden;
        }
        
        button:hover:not(:disabled) {
            background: linear-gradient(135deg, var(--accent-gold-light), var(--accent-gold));
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(212, 175, 55, 0.6);
            letter-spacing: 3px;
        }
        
        button:disabled {
            background: linear-gradient(135deg, #666, #888);
            color: #999;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }
        
        .back-link {
            display: block;
            text-align: center;
            margin-top: 35px;
            color: var(--accent-gold);
            text-decoration: none;
            font-family: 'Cinzel', serif;
            padding: 15px;
            transition: all 0.3s ease;
            border-radius: 8px;
            font-size: 1.1rem;
            border: 2px solid transparent;
        }
        
        .back-link:hover {
            color: var(--accent-gold-light);
            background: rgba(212, 175, 55, 0.1);
            text-decoration: none;
            border-color: var(--accent-gold);
            transform: translateY(-2px);
        }
        
        .error-message {
            background: rgba(139, 0, 0, 0.3);
            border: 2px solid var(--error-red);
            color: var(--error-red);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            backdrop-filter: blur(10px);
            font-size: 1.1rem;
        }
        
        .success-message {
            background: rgba(0, 100, 0, 0.3);
            border: 2px solid var(--success-green);
            color: var(--success-green);
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 30px;
            text-align: center;
            backdrop-filter: blur(10px);
            font-size: 1.1rem;
        }
        
        .form-hint {
            font-size: 0.95rem;
            color: var(--text-secondary);
            margin-top: 8px;
            font-style: italic;
            line-height: 1.5;
        }
        
        .difficulty-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
            margin-top: 15px;
        }
        
        .difficulty-option {
            padding: 25px 20px;
            border: 3px solid transparent;
            border-radius: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            background: rgba(40, 20, 40, 0.8);
            position: relative;
            overflow: hidden;
        }
        
        .difficulty-option::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 6px;
            height: 100%;
            transition: all 0.3s ease;
        }
        
        .difficulty-option:hover {
            transform: translateY(-5px) scale(1.02);
            border-color: var(--accent-gold);
            box-shadow: 0 10px 30px rgba(212, 175, 55, 0.3);
        }
        
        .difficulty-option.selected {
            border-color: var(--accent-gold-light);
            background: rgba(212, 175, 55, 0.15);
            transform: translateY(-3px);
        }
        
        .difficulty-easy::before { background: #00ff00; }
        .difficulty-medium::before { background: #ffa500; }
        .difficulty-hard::before { background: #ff0000; }
        .difficulty-epic::before { background: #800080; }
        
        .difficulty-name {
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--accent-gold-light);
        }
        
        .difficulty-stats {
            font-size: 1rem;
            color: var(--text-primary);
            line-height: 1.5;
        }
        
        .monthly-limit {
            margin-top: 20px;
            padding: 25px;
            background: rgba(212, 175, 55, 0.15);
            border: 2px solid var(--accent-gold);
            border-radius: 15px;
            text-align: center;
        }
        
        .limit-title {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 20px;
            font-size: 1.3rem;
        }
        
        .limit-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .limit-item {
            padding: 15px;
            border-radius: 10px;
            background: rgba(40, 20, 40, 0.8);
            border: 1px solid var(--accent-gold-dark);
        }
        
        .limit-count {
            font-weight: bold;
            font-size: 1.3rem;
            color: var(--accent-gold-light);
        }
        
        .rewards-display {
            text-align: center;
            padding: 30px;
            background: rgba(212, 175, 55, 0.2);
            border: 3px solid var(--accent-gold);
            border-radius: 15px;
            margin: 25px 0;
        }
        
        .rewards-label {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 25px;
            font-size: 1.5rem;
            text-shadow: 0 2px 10px rgba(212, 175, 55, 0.3);
        }
        
        .rewards-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }
        
        .reward-item {
            padding: 20px;
            border-radius: 12px;
            background: rgba(40, 20, 40, 0.9);
            border: 2px solid var(--accent-gold-dark);
            transition: all 0.3s ease;
        }
        
        .reward-item:hover {
            transform: translateY(-3px);
            border-color: var(--accent-gold);
        }
        
        .reward-type {
            font-size: 1.1rem;
            color: var(--text-secondary);
            margin-bottom: 10px;
            font-family: 'Cinzel', serif;
        }
        
        .reward-value {
            font-size: 2rem;
            font-weight: bold;
            text-shadow: 0 0 15px rgba(212, 175, 55, 0.6);
        }
        
        .xp-value {
            color: var(--accent-gold-light);
        }
        
        .gold-value {
            color: #ffd700;
        }
        
        .todo-section {
            background: rgba(212, 175, 55, 0.15);
            border: 2px solid var(--accent-gold);
            border-radius: 15px;
            padding: 30px;
            margin: 25px 0;
        }
        
        .todo-title {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 25px;
            text-align: center;
            font-size: 1.4rem;
        }
        
        .todo-controls {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: 20px;
            margin-bottom: 25px;
            align-items: end;
        }
        
        .todo-input-group {
            display: flex;
            flex-direction: column;
        }
        
        .todo-input-group label {
            margin-bottom: 10px;
        }
        
        .btn-small {
            padding: 15px 25px;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-dark));
            color: var(--bg-primary);
            border: none;
            border-radius: 10px;
            font-family: 'Cinzel', serif;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1.1rem;
            font-weight: bold;
        }
        
        .btn-small:hover {
            background: linear-gradient(135deg, var(--accent-gold-light), var(--accent-gold));
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(212, 175, 55, 0.4);
        }
        
        .todo-list {
            display: none;
            margin-top: 25px;
        }
        
        .todo-list.active {
            display: block;
        }
        
        .todo-item {
            display: flex;
            align-items: center;
            gap: 20px;
            margin-bottom: 15px;
            padding: 20px;
            background: rgba(40, 20, 40, 0.9);
            border-radius: 10px;
            border-left: 4px solid var(--accent-gold);
            transition: all 0.3s ease;
        }
        
        .todo-item:hover {
            transform: translateX(5px);
            border-left-color: var(--accent-gold-light);
        }
        
        .todo-number {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-weight: bold;
            font-size: 1.3rem;
            min-width: 40px;
            text-align: center;
        }
        
        .todo-input {
            flex: 1;
            margin: 0;
            font-size: 1.1rem;
            padding: 15px 20px;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .container {
                padding: 30px 20px;
                margin: 10px;
            }
            
            h1 {
                font-size: 2.2rem;
            }
            
            .header-icon {
                font-size: 2.8rem;
            }
            
            .form-section {
                padding: 20px;
            }
            
            .difficulty-grid {
                grid-template-columns: 1fr;
            }
            
            .limit-grid {
                grid-template-columns: 1fr;
            }
            
            .rewards-grid {
                grid-template-columns: 1fr;
            }
            
            .todo-controls {
                grid-template-columns: 1fr;
            }
            
            button, .btn-small {
                padding: 18px 25px;
            }
        }
        
        @media (max-width: 480px) {
            body {
                padding: 10px;
            }
            
            .container {
                padding: 25px 15px;
            }
            
            h1 {
                font-size: 1.8rem;
            }
            
            .header-icon {
                font-size: 2.2rem;
            }
            
            .form-section {
                padding: 15px;
            }
            
            input, select, textarea {
                padding: 15px;
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-icon">
            <i class="fas fa-plus"></i>
        </div>
        <h1>CREATE NEW QUEST</h1>
        
        <?php if (!empty($error)): ?>
            <div class="error-message">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <!-- Monthly Limits Display -->
        <div class="form-section">
            <div class="section-title">
                <i class="fas fa-chart-bar"></i>
                <span>MONTHLY QUEST LIMITS</span>
            </div>
            <div class="limit-grid">
                <?php foreach ($monthly_limits as $difficulty => $limit): ?>
                    <div class="limit-item difficulty-<?php echo $difficulty; ?>">
                        <div class="difficulty-name" style="text-transform: capitalize;">
                            <?php echo $difficulty; ?>
                        </div>
                        <div class="difficulty-stats">
                            <span class="limit-count"><?php echo $monthly_counts[$difficulty]; ?></span>
                            / <?php echo $limit; ?> quests
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <form method="POST" id="questForm">
            <!-- Basic Information Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-scroll"></i>
                    <span>QUEST INFORMATION</span>
                </div>
                
                <div class="form-group">
                    <label for="title">
                        <i class="fas fa-heading"></i> QUEST TITLE:
                    </label>
                    <input type="text" id="title" name="title" required 
                           placeholder="Enter your quest title (e.g., 'Slay the Procrastination Dragon')"
                           maxlength="255">
                    <div class="form-hint">Give your quest an epic name that inspires you to greatness!</div>
                </div>
                
                <div class="form-group">
                    <label for="description">
                        <i class="fas fa-book-open"></i> QUEST DESCRIPTION:
                    </label>
                    <textarea id="description" name="description" rows="4" 
                              placeholder="Describe your quest in detail. What must be accomplished? What challenges await? What treasures lie at the end of this journey?"></textarea>
                    <div class="form-hint">A detailed description helps you stay focused on your objective and remember why this quest matters.</div>
                </div>
                
                <div class="form-group">
                    <label for="category">
                        <i class="fas fa-tags"></i> QUEST CATEGORY:
                    </label>
                    <select id="category" name="category">
                        <option value="personal">🧠 Personal Growth</option>
                        <option value="work">💼 Work & Career</option>
                        <option value="health">❤️ Health & Fitness</option>
                        <option value="learning">📚 Learning & Skills</option>
                        <option value="social">👥 Social & Relationships</option>
                        <option value="financial">💰 Financial</option>
                        <option value="creative">🎨 Creative</option>
                        <option value="other">🔮 Other</option>
                    </select>
                </div>
            </div>
            
            <!-- Difficulty Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-shield-alt"></i>
                    <span>QUEST DIFFICULTY</span>
                </div>
                <div class="difficulty-grid" id="difficultyOptions">
                    <div class="difficulty-option difficulty-easy" data-difficulty="easy">
                        <div class="difficulty-name">Easy Quest</div>
                        <div class="difficulty-stats">90/month<br>50 HP Monster</div>
                    </div>
                    <div class="difficulty-option difficulty-medium" data-difficulty="medium">
                        <div class="difficulty-name">Medium Quest</div>
                        <div class="difficulty-stats">45/month<br>100 HP Monster</div>
                    </div>
                    <div class="difficulty-option difficulty-hard" data-difficulty="hard">
                        <div class="difficulty-name">Hard Quest</div>
                        <div class="difficulty-stats">15/month<br>200 HP Monster</div>
                    </div>
                    <div class="difficulty-option difficulty-epic" data-difficulty="epic">
                        <div class="difficulty-name">Epic Quest</div>
                        <div class="difficulty-stats">3/month<br>500 HP Boss</div>
                    </div>
                </div>
                <input type="hidden" id="difficulty" name="difficulty" value="medium" required>
            </div>
            
            <!-- To-Do List Section -->
            <div class="form-section todo-section">
                <div class="section-title">
                    <i class="fas fa-tasks"></i>
                    <span>QUEST TO-DO LIST</span>
                </div>
                <div class="todo-controls">
                    <div class="todo-input-group">
                        <label for="todo_count">NUMBER OF TASKS:</label>
                        <input type="number" id="todo_count" name="todo_count" value="1" min="1" max="20" 
                               class="form-control" onchange="updateTodoList()">
                    </div>
                    <button type="button" class="btn-small" onclick="updateTodoList()">
                        <i class="fas fa-sync-alt"></i> UPDATE TASKS
                    </button>
                </div>
                <div class="form-hint">Set how many tasks you need to complete this quest (e.g., 5 chapters to study, 3 workout sessions, 10 pages to write)</div>
                
                <div class="todo-list" id="todoList">
                    <!-- To-do items will be generated here by JavaScript -->
                </div>
            </div>
            
            <!-- Rewards Display -->
            <div class="rewards-display">
                <div class="rewards-label">QUEST REWARDS</div>
                <div class="rewards-grid">
                    <div class="reward-item">
                        <div class="reward-type">EXPERIENCE POINTS</div>
                        <div class="reward-value xp-value" id="xpDisplay">+25 XP</div>
                    </div>
                    <div class="reward-item">
                        <div class="reward-type">GOLD COINS</div>
                        <div class="reward-value gold-value" id="goldDisplay">+8 Gold</div>
                    </div>
                </div>
                <div class="form-hint">Rewards are automatically calculated based on your chosen quest difficulty</div>
            </div>
            
            <!-- Deadline Section -->
            <div class="form-section">
                <div class="section-title">
                    <i class="fas fa-calendar-day"></i>
                    <span>QUEST TIMELINE</span>
                </div>
                <div class="form-group">
                    <label for="due_date">
                        <i class="fas fa-hourglass-half"></i> QUEST DEADLINE (OPTIONAL):
                    </label>
                    <input type="date" id="due_date" name="due_date" 
                           min="<?php echo $today; ?>"
                           onchange="validateDate()">
                    <div class="form-hint">Choose a future date to complete your quest. The sands of time wait for no adventurer!</div>
                </div>
            </div>
            
            <button type="submit" id="submitButton">
                <i class="fas fa-rocket"></i> FORGE QUEST & BEGIN ADVENTURE
            </button>
        </form>
        
        <a href="dashboard.php" class="back-link">
            <i class="fas fa-arrow-left"></i> RETURN TO COMMAND CENTER
        </a>
    </div>

    <script>
        // XP rewards mapping
        const xpRewards = {
            'easy': 10,
            'medium': 25,
            'hard': 50,
            'epic': 100
        };
        
        // Gold rewards mapping
        const goldRewards = {
            'easy': 5,
            'medium': 8,
            'hard': 12,
            'epic': 30
        };
        
        // Monthly limits
        const monthlyLimits = {
            'easy': 90,
            'medium': 45,
            'hard': 15,
            'epic': 3
        };
        
        // Current monthly counts from PHP
        const monthlyCounts = {
            'easy': <?php echo $monthly_counts['easy']; ?>,
            'medium': <?php echo $monthly_counts['medium']; ?>,
            'hard': <?php echo $monthly_counts['hard']; ?>,
            'epic': <?php echo $monthly_counts['epic']; ?>
        };
        
        // Set minimum date to today
        document.getElementById('due_date').min = new Date().toISOString().split('T')[0];
        
        // Difficulty selection
        const difficultyOptions = document.querySelectorAll('.difficulty-option');
        const difficultyInput = document.getElementById('difficulty');
        const xpDisplay = document.getElementById('xpDisplay');
        const goldDisplay = document.getElementById('goldDisplay');
        const submitButton = document.getElementById('submitButton');
        const todoList = document.getElementById('todoList');
        
        // Update to-do list based on count
        function updateTodoList() {
            const count = parseInt(document.getElementById('todo_count').value);
            const todoList = document.getElementById('todoList');
            
            if (count > 0) {
                todoList.classList.add('active');
                todoList.innerHTML = '';
                
                for (let i = 0; i < count; i++) {
                    const todoItem = document.createElement('div');
                    todoItem.className = 'todo-item';
                    todoItem.innerHTML = `
                        <div class="todo-number">${i + 1}.</div>
                        <input type="text" class="todo-input form-control" 
                               name="todo_name[]" 
                               placeholder="Enter task ${i + 1} (e.g., Chapter ${i + 1}, Workout ${i + 1}, Page ${i + 1})"
                               value="Task ${i + 1}">
                    `;
                    todoList.appendChild(todoItem);
                }
            } else {
                todoList.classList.remove('active');
                todoList.innerHTML = '';
            }
        }
        
        difficultyOptions.forEach(option => {
            option.addEventListener('click', function() {
                const difficulty = this.getAttribute('data-difficulty');
                
                // Remove selected class from all options
                difficultyOptions.forEach(opt => opt.classList.remove('selected'));
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Update hidden input
                difficultyInput.value = difficulty;
                
                // Update rewards display
                updateRewardsDisplay(difficulty);
                
                // Check if limit reached
                checkLimit(difficulty);
            });
        });
        
        // Initialize with medium selected
        document.querySelector('.difficulty-option[data-difficulty="medium"]').classList.add('selected');
        
        function updateRewardsDisplay(difficulty) {
            const xp = xpRewards[difficulty];
            const gold = goldRewards[difficulty];
            
            xpDisplay.textContent = `+${xp} XP`;
            goldDisplay.textContent = `+${gold} Gold`;
        }
        
        function checkLimit(difficulty) {
            const currentCount = monthlyCounts[difficulty];
            const limit = monthlyLimits[difficulty];
            
            if (currentCount >= limit) {
                submitButton.disabled = true;
                submitButton.innerHTML = `<i class="fas fa-ban"></i> MONTHLY LIMIT REACHED (${currentCount}/${limit})`;
                showNotification(`You've reached your monthly limit for ${difficulty} quests!`, 'error');
            } else {
                submitButton.disabled = false;
                submitButton.innerHTML = `<i class="fas fa-rocket"></i> FORGE QUEST & BEGIN ADVENTURE`;
            }
        }
        
        // Validate date is not in the past
        function validateDate() {
            const dateInput = document.getElementById('due_date');
            const selectedDate = new Date(dateInput.value);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            
            if (selectedDate < today) {
                alert('⚠️ Time travel not allowed! Please choose a future date for your quest.');
                dateInput.value = '';
                dateInput.focus();
                return false;
            }
            return true;
        }
        
        // Form submission validation
        document.getElementById('questForm').addEventListener('submit', function(e) {
            const title = document.getElementById('title').value.trim();
            const dueDate = document.getElementById('due_date').value;
            const difficulty = difficultyInput.value;
            const todoCount = document.getElementById('todo_count').value;
            
            if (!title) {
                e.preventDefault();
                alert('🗺️ Every quest needs a name! Please enter a quest title.');
                document.getElementById('title').focus();
                return false;
            }
            
            if (dueDate && !validateDate()) {
                e.preventDefault();
                return false;
            }
            
            if (todoCount < 1) {
                e.preventDefault();
                alert('❌ You must have at least one task to complete!');
                document.getElementById('todo_count').focus();
                return false;
            }
            
            // Check limit again before submission
            if (monthlyCounts[difficulty] >= monthlyLimits[difficulty]) {
                e.preventDefault();
                alert(`❌ You have reached your monthly limit for ${difficulty} quests!`);
                return false;
            }
            
            // Show loading effect
            const button = this.querySelector('button[type="submit"]');
            const originalText = button.innerHTML;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> FORGING QUEST...';
            button.disabled = true;
            
            setTimeout(() => {
                button.innerHTML = originalText;
                button.disabled = false;
            }, 2000);
            
            return true;
        });
        
        // Add some interactive effects
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('focus', function() {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = '0 0 25px rgba(212, 175, 55, 0.6)';
            });
            
            element.addEventListener('blur', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'inset 0 4px 10px rgba(0, 0, 0, 0.4)';
            });
        });
        
        // Prevent past date selection by keyboard
        document.getElementById('due_date').addEventListener('keydown', function(e) {
            e.preventDefault();
        });
        
        // Initialize the form
        document.addEventListener('DOMContentLoaded', function() {
            // Set placeholder for date input
            const dateInput = document.getElementById('due_date');
            dateInput.placeholder = 'Select a future date';
            
            // Focus on title input
            document.getElementById('title').focus();
            
            // Initialize to-do list
            updateTodoList();
            
            // Check initial limit
            checkLimit('medium');
        });
        
        function showNotification(message, type) {
            // Create notification element
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 30px;
                right: 30px;
                padding: 20px 25px;
                background: ${type === 'error' ? 'rgba(139, 0, 0, 0.95)' : 'rgba(0, 100, 0, 0.95)'};
                color: white;
                border-radius: 12px;
                border-left: 6px solid ${type === 'error' ? '#ff0000' : '#00ff00'};
                z-index: 10000;
                font-family: 'Cinzel', serif;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.4);
                font-size: 1.1rem;
                max-width: 400px;
            `;
            notification.innerHTML = `<i class="fas fa-${type === 'error' ? 'exclamation-triangle' : 'check-circle'}"></i> ${message}`;
            
            document.body.appendChild(notification);
            
            // Remove after 5 seconds
            setTimeout(() => {
                notification.remove();
            }, 5000);
        }
    </script>
</body>
</html>