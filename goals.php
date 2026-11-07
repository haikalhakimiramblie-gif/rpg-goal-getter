<?php
session_start();
require_once 'config.php';

// Include QuestManager class
require_once 'QuestManager.php';
$questManager = new QuestManager($pdo);

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Process overdue quests and HP deductions automatically
try {
    $failed_quests = $questManager->processOverdueQuests($user_id);
    $hp_deductions = $questManager->processHPDeductions($user_id);
    
    // If any quests were failed, reload user data
    if ($failed_quests > 0) {
        $user_sql = "SELECT * FROM users WHERE id = ?";
        $stmt = $pdo->prepare($user_sql);
        $stmt->execute([$user_id]);
        $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Update local variables
        $current_hp = $user_data['current_hp'];
        $_SESSION['current_hp'] = $current_hp;
    }
    
} catch (Exception $e) {
    // Log error but don't break the page
    error_log("Quest processing error: " . $e->getMessage());
}

// Get user data from database instead of session
try {
    $user_sql = "SELECT u.*, 
                        COALESCE(us.total_goals_completed, 0) as total_goals_completed,
                        COALESCE(us.total_exp_earned, 0) as total_exp_earned,
                        COALESCE(us.total_gold_earned, 0) as total_gold_earned,
                        COALESCE(us.current_streak, 0) as current_streak,
                        COALESCE(us.longest_streak, 0) as longest_streak,
                        COALESCE(us.total_quests_failed, 0) as total_quests_failed
                 FROM users u 
                 LEFT JOIN user_stats us ON u.id = us.user_id 
                 WHERE u.id = ?";
    $stmt = $pdo->prepare($user_sql);
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception("User not found");
    }
    
    // Set user data
    $user_level = $user_data['level'] ?? 1;
    $user_exp = $user_data['exp'] ?? 0;
    $current_hp = $user_data['current_hp'] ?? 100;
    $max_hp = $user_data['max_hp'] ?? 100;
    $gold = $user_data['gold'] ?? 0;
    
    // Update session variables for consistency
    $_SESSION['user_level'] = $user_level;
    $_SESSION['user_exp'] = $user_exp;
    $_SESSION['current_hp'] = $current_hp;
    $_SESSION['max_hp'] = $max_hp;
    $_SESSION['gold'] = $gold;
    
} catch (Exception $e) {
    die("Error loading user data: " . $e->getMessage());
}

// Handle todo completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_todo'])) {
    $todo_id = $_POST['todo_id'];
    $goal_id = $_POST['goal_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Mark todo as completed
        $sql = "UPDATE goal_todos SET completed = 1 WHERE id = ? AND goal_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$todo_id, $goal_id]);
        
        // Update pending attacks count
        $sql = "UPDATE goals SET pending_attacks = pending_attacks + 1 WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goal_id]);
        
        $pdo->commit();
        header("Location: goals.php?selected_goal=" . $goal_id . "&todo_completed=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: goals.php?error=Error completing task: " . $e->getMessage());
        exit();
    }
}

// Handle attack button
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['attack_monster'])) {
    $goal_id = $_POST['goal_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get goal details and monster info
        $sql = "SELECT g.*, 
                       m.monster_hp, m.current_hp as monster_current_hp, m.monster_folder,
                       b.boss_hp, b.current_hp as boss_current_hp, b.boss_folder,
                       g.todo_count, g.pending_attacks,
                       (SELECT COUNT(*) FROM goal_todos WHERE goal_id = g.id AND completed = 1) as completed_count
                FROM goals g
                LEFT JOIN monsters m ON g.id = m.goal_id
                LEFT JOIN bosses b ON g.id = b.goal_id
                WHERE g.id = ? AND g.user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goal_id, $user_id]);
        $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($goal && $goal['pending_attacks'] > 0) {
            // Determine if it's a monster or boss and get the correct HP values
            if ($goal['monster_hp'] !== null) {
                $max_hp = $goal['monster_hp'];
                $current_hp = $goal['monster_current_hp'];
                $is_monster = true;
            } else {
                $max_hp = $goal['boss_hp'];
                $current_hp = $goal['boss_current_hp'];
                $is_monster = false;
            }
            
            // Calculate damage based on pending attacks
            $damage_per_todo = ceil($max_hp / $goal['todo_count']);
            $total_damage = $damage_per_todo * $goal['pending_attacks'];
            $new_hp = max(0, $current_hp - $total_damage);
            
            // Update monster/boss HP
            if ($is_monster) {
                $sql = "UPDATE monsters SET current_hp = ? WHERE goal_id = ?";
            } else {
                $sql = "UPDATE bosses SET current_hp = ? WHERE goal_id = ?";
            }
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_hp, $goal_id]);
            
            // Reset pending attacks counter
            $sql = "UPDATE goals SET pending_attacks = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
        }
        
        $pdo->commit();
        header("Location: goals.php?selected_goal=" . $goal_id . "&attack_success=1&damage=" . $total_damage);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: goals.php?error=Error attacking monster: " . $e->getMessage());
        exit();
    }
}

// Handle goal completion with feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_goal'])) {
    $goal_id = $_POST['goal_id'];
    $feedback = $_POST['feedback'] ?? '';
    
    // Check if feedback is provided
    if (empty(trim($feedback))) {
        header("Location: goals.php?selected_goal=" . $goal_id . "&error=Please provide feedback before completing the quest!");
        exit();
    }
    
    try {
        $pdo->beginTransaction();
        
        // Get goal details
        $sql = "SELECT * FROM goals WHERE id = ? AND user_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goal_id, $user_id]);
        $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($goal) {
            // Calculate rewards
            $exp_reward = $goal['exp_reward'];
            $gold_reward = $goal['gold_reward'] ?? 0;
            
            // Update user stats
            $new_exp = $user_exp + $exp_reward;
            $new_gold = $gold + $gold_reward;
            
            // Check for level up
            $exp_needed = $user_level * 100;
            $new_level = $user_level;
            if ($new_exp >= $exp_needed) {
                $new_level++;
                $new_exp = $new_exp - $exp_needed;
                $new_max_hp = 100 + ($new_level * 10);
                $new_current_hp = $new_max_hp;
            } else {
                $new_max_hp = $max_hp;
                $new_current_hp = $current_hp;
            }
            
            // Update user
            $sql = "UPDATE users SET level = ?, exp = ?, max_hp = ?, current_hp = ?, gold = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_level, $new_exp, $new_max_hp, $new_current_hp, $new_gold, $user_id]);
            
            // Update user stats
            $sql = "INSERT INTO user_stats (user_id, total_goals_completed, total_exp_earned, total_gold_earned) 
                    VALUES (?, 1, ?, ?) 
                    ON DUPLICATE KEY UPDATE 
                    total_goals_completed = total_goals_completed + 1, 
                    total_exp_earned = total_exp_earned + ?, 
                    total_gold_earned = total_gold_earned + ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$user_id, $exp_reward, $gold_reward, $exp_reward, $gold_reward]);
            
            // Log the completed goal in goal_logs with feedback
            $sql = "INSERT INTO goal_logs (user_id, goal_title, goal_description, category, difficulty, todo_count, exp_earned, gold_earned, completion_time, feedback, status) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'completed')";
            $stmt = $pdo->prepare($sql);
            
            // Calculate completion time in days
            $created_at = new DateTime($goal['created_at']);
            $completed_at = new DateTime();
            $completion_time = $created_at->diff($completed_at)->days;
            
            $stmt->execute([
                $user_id,
                $goal['title'],
                $goal['description'],
                $goal['category'],
                $goal['difficulty'],
                $goal['todo_count'],
                $exp_reward,
                $gold_reward,
                $completion_time,
                $feedback
            ]);
            
            // Delete goal and related data
            $sql = "DELETE FROM goals WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
            
            $sql = "DELETE FROM monsters WHERE goal_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
            
            $sql = "DELETE FROM bosses WHERE goal_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
            
            $sql = "DELETE FROM goal_todos WHERE goal_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
            
            $pdo->commit();
            
            // Update session
            $_SESSION['user_level'] = $new_level;
            $_SESSION['user_exp'] = $new_exp;
            $_SESSION['max_hp'] = $new_max_hp;
            $_SESSION['current_hp'] = $new_current_hp;
            $_SESSION['gold'] = $new_gold;
            
            // Update local variables for display
            $user_level = $new_level;
            $user_exp = $new_exp;
            $max_hp = $new_max_hp;
            $current_hp = $new_current_hp;
            $gold = $new_gold;
            
            header("Location: goals.php?success=Goal completed! +" . $exp_reward . " XP • +" . $gold_reward . " Gold");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: goals.php?error=Error completing goal: " . $e->getMessage());
        exit();
    }
}

// Get user's goals with todo counts and pending attacks
$sql = "SELECT g.*, 
               COUNT(gt.id) as total_todos,
               SUM(CASE WHEN gt.completed = 1 THEN 1 ELSE 0 END) as completed_todos,
               g.pending_attacks,
               g.total_hp_deducted
        FROM goals g 
        LEFT JOIN goal_todos gt ON g.id = gt.goal_id 
        WHERE g.user_id = ? 
        AND g.failed = 0
        GROUP BY g.id 
        ORDER BY g.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get monsters, bosses, and todos for goals
$goal_monsters = [];
$goal_todos = [];

foreach ($goals as $goal) {
    // Get monster/boss info
    $sql = "SELECT *, 'monster' as type FROM monsters WHERE goal_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goal['id']]);
    $monster = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($monster) {
        $goal_monsters[$goal['id']] = $monster;
    } else {
        $sql = "SELECT *, 'boss' as type FROM bosses WHERE goal_id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goal['id']]);
        $boss = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($boss) {
            $goal_monsters[$goal['id']] = $boss;
        }
    }
    
    // Get todos
    $sql = "SELECT * FROM goal_todos WHERE goal_id = ? ORDER BY position";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goal['id']]);
    $goal_todos[$goal['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get monster image based on HP
function getMonsterImage($monster_data, $current_hp, $max_hp) {
    $base_path = 'images/';
    
    // Determine folder based on monster type and assigned folder
    if ($monster_data['type'] == 'boss') {
        $folder = 'boss' . ($monster_data['boss_folder'] ?? '1');
        
        // Boss-specific image logic
        $hp_percentage = ($current_hp / $max_hp) * 100;
        
        if ($current_hp <= 0) {
            $image = $base_path . $folder . '/dead.png';
        } elseif ($hp_percentage == 100) {
            $image = $base_path . $folder . '/sleep.png';
        } elseif ($hp_percentage <= 60 && $hp_percentage > 30) {
            $image = $base_path . $folder . '/angry.png';
        } elseif ($hp_percentage <= 30 && $hp_percentage > 0) {
            $image = $base_path . $folder . '/injured.png';
        } else {
            $image = $base_path . $folder . '/normal.png';
        }
    } else {
        $folder = 'monster' . ($monster_data['monster_folder'] ?? '1');
        
        // Regular monster logic
        $hp_percentage = ($current_hp / $max_hp) * 100;
        
        if ($current_hp <= 0) {
            $image = $base_path . $folder . '/dead.png';
        } elseif ($hp_percentage <= 30) {
            $image = $base_path . $folder . '/injured.png';
        } else {
            $image = $base_path . $folder . '/normal.png';
        }
    }
    
    // Check if file exists, if not use a default
    if (!file_exists($image)) {
        // Fallback to monster1 if the specific folder doesn't exist
        if ($monster_data['type'] == 'boss') {
            $image = $base_path . 'boss1/normal.png';
        } else {
            $image = $base_path . 'monster1/normal.png';
        }
    }
    
    return $image;
}

// Function to calculate damage per todo
function getDamagePerTodo($max_hp, $todo_count) {
    return ceil($max_hp / $todo_count);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Your Quests</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=EB+Garamond:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --bg-primary: #0a050a;
            --bg-secondary: #1a0f1a;
            --accent-gold: #d4af37;
            --accent-gold-light: #f0e68c;
            --text-primary: #e6d6b3;
            --text-secondary: #b8860b;
            --danger-red: #ff4444;
            --warning-orange: #ff9800;
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
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .navigation {
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--accent-gold), #b8860b);
            color: var(--bg-primary);
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 2px solid var(--accent-gold);
        }

        .btn-create {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, #006400, #00ff00);
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 2px solid #90ee90;
        }

        .btn-back:hover, .btn-create:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }

        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.8), rgba(25, 10, 25, 0.9));
            border: 2px solid var(--accent-gold);
            border-radius: 15px;
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-size: 2.5rem;
            margin-bottom: 20px;
        }

        .user-stats {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
            flex-wrap: wrap;
        }

        .stat {
            text-align: center;
            padding: 15px 25px;
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid var(--accent-gold);
            border-radius: 8px;
            min-width: 120px;
        }

        .stat-value {
            font-family: 'Cinzel', serif;
            font-size: 1.4rem;
            color: var(--accent-gold-light);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-primary);
            margin-top: 5px;
        }

        .goals-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .goal-card {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.8), rgba(25, 10, 25, 0.9));
            border: 2px solid var(--accent-gold);
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
        }

        .goal-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-gold-light);
            box-shadow: 0 15px 30px rgba(212, 175, 55, 0.3);
        }

        .goal-card.failed {
            border-color: var(--danger-red);
            background: linear-gradient(135deg, rgba(139, 0, 0, 0.3), rgba(90, 0, 0, 0.4));
            cursor: default;
        }

        .goal-card.failed:hover {
            border-color: #ff6b6b;
            box-shadow: 0 15px 30px rgba(255, 0, 0, 0.3);
            transform: none;
        }

        .goal-card h3 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 12px;
            font-size: 1.3rem;
        }

        .goal-card.failed h3 {
            color: #ff6b6b;
            text-decoration: line-through;
        }

        .goal-card p {
            color: var(--text-primary);
            margin-bottom: 15px;
            line-height: 1.5;
        }

        .goal-rewards {
            display: flex;
            gap: 15px;
            margin: 15px 0;
        }

        .reward {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.95rem;
            color: var(--accent-gold-light);
        }

        .todo-progress {
            margin: 15px 0;
            padding: 12px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
            border: 1px solid var(--accent-gold-dark);
        }

        .progress-text {
            font-size: 0.9rem;
            color: var(--text-primary);
            text-align: center;
        }

        .progress-text i {
            color: var(--accent-gold);
        }

        .monster-info {
            margin-top: 10px;
            font-size: 0.9rem;
            color: var(--accent-gold);
            text-align: center;
        }

        .monster-info i {
            margin-right: 5px;
        }

        .hp-deduction-info {
            margin-top: 10px;
            padding: 8px;
            background: rgba(255, 0, 0, 0.1);
            border-radius: 5px;
            border-left: 3px solid var(--danger-red);
            font-size: 0.85rem;
        }

        .hp-deduction-info i {
            color: var(--danger-red);
            margin-right: 5px;
        }

        .deadline-info {
            margin-top: 8px;
            padding: 8px;
            background: rgba(255, 152, 0, 0.1);
            border-radius: 5px;
            border-left: 3px solid var(--warning-orange);
            font-size: 0.85rem;
        }

        .deadline-info i {
            color: var(--warning-orange);
            margin-right: 5px;
        }

        .failed-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--danger-red);
            color: white;
            padding: 5px 10px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-family: 'Cinzel', serif;
        }

        .failed-info {
            margin-top: 10px;
            padding: 10px;
            background: rgba(255, 0, 0, 0.2);
            border-radius: 5px;
            border-left: 3px solid var(--danger-red);
            font-size: 0.9rem;
            color: #ff6b6b;
        }

        .failed-info i {
            margin-right: 5px;
        }

        .monster-display {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.95), rgba(25, 10, 25, 0.98));
            border: 3px solid var(--accent-gold);
            border-radius: 20px;
            padding: 30px;
            z-index: 1000;
            width: 95%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            backdrop-filter: blur(10px);
        }

        .monster-display.active {
            display: block;
        }

        .monster-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent-gold);
        }

        .monster-header h2 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-size: 1.8rem;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--accent_gold);
            font-size: 1.8rem;
            cursor: pointer;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            color: var(--accent-gold-light);
            transform: scale(1.1);
        }

        .quest-description {
            color: var(--text-primary);
            margin-bottom: 20px;
            line-height: 1.6;
            font-size: 1.1rem;
        }

        .battle-area {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin: 25px 0;
        }

        .monster-image {
            width: 250px;
            height: 250px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            margin-bottom: 20px;
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            background-color: rgba(0, 0, 0, 0.3);
        }

        .hp-bar-container {
            width: 100%;
            max-width: 400px;
            margin: 15px 0;
        }

        .hp-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: var(--accent-gold);
            font-family: 'Cinzel', serif;
        }

        .hp-bar {
            width: 100%;
            height: 25px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid var(--accent-gold);
            border-radius: 12px;
            overflow: hidden;
        }

        .hp-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff0000, #ff6b6b);
            transition: width 0.5s ease;
        }

        .todo-section {
            margin: 25px 0;
            padding: 20px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 12px;
            border: 1px solid var(--accent-gold);
        }

        .todo-section h3 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 15px;
            text-align: center;
        }

        .todo-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .todo-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: rgba(40, 20, 40, 0.8);
            border-radius: 8px;
            border-left: 4px solid var(--accent-gold);
        }

        .todo-item.completed {
            opacity: 0.7;
            border-left-color: #00ff00;
        }

        .todo-name {
            flex: 1;
            color: var(--text-primary);
        }

        .todo-name.completed {
            text-decoration: line-through;
            color: var(--text-secondary);
        }

        .battle-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 25px;
            flex-wrap: wrap;
        }

        .btn {
            padding: 12px 25px;
            border: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            min-width: 160px;
        }

        .btn-todo {
            background: linear-gradient(135deg, #d4af37, #b8860b);
            color: var(--bg-primary);
            border: 1px solid var(--accent-gold);
        }

        .btn-complete {
            background: linear-gradient(135deg, #006400, #00ff00);
            color: white;
            border: 1px solid #90ee90;
        }

        .btn-attack {
            background: linear-gradient(135deg, #8b0000, #ff0000);
            color: white;
            border: 1px solid #ff6b6b;
        }

        .btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.2);
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 999;
        }

        .overlay.active {
            display: block;
        }

        .message {
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .message.success {
            background: rgba(0, 100, 0, 0.3);
            border: 2px solid #00ff00;
            color: #90ee90;
        }

        .message.error {
            background: rgba(139, 0, 0, 0.3);
            border: 2px solid #ff0000;
            color: #ff6b6b;
        }

        .damage-info {
            text-align: center;
            color: var(--accent-gold);
            margin: 10px 0;
            font-family: 'Cinzel', serif;
        }

        .combat-explanation {
            background: rgba(212, 175, 55, 0.1);
            border: 1px solid var(--accent-gold);
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }

        .combat-explanation h4 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 10px;
        }

        .attack-info {
            background: rgba(139, 0, 0, 0.2);
            border: 1px solid #ff4444;
            border-radius: 8px;
            padding: 15px;
            margin: 15px 0;
            text-align: center;
        }

        .attack-info h4 {
            font-family: 'Cinzel', serif;
            color: #ff6b6b;
            margin-bottom: 10px;
        }

        /* Feedback Modal Styles */
        .feedback-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.95), rgba(25, 10, 25, 0.98));
            border: 3px solid var(--accent-gold);
            border-radius: 20px;
            padding: 30px;
            z-index: 1001;
            width: 95%;
            max-width: 500px;
            backdrop-filter: blur(10px);
        }

        .feedback-modal.active {
            display: block;
        }

        .feedback-header {
            text-align: center;
            margin-bottom: 20px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent-gold);
        }

        .feedback-header h3 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-size: 1.5rem;
        }

        .feedback-form {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .feedback-label {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            margin-bottom: 5px;
            font-size: 1.1rem;
        }

        .feedback-textarea {
            width: 100%;
            min-height: 150px;
            padding: 15px;
            background: rgba(212, 175, 55, 0.1);
            border: 2px solid var(--accent-gold);
            border-radius: 8px;
            color: var(--text-primary);
            font-family: 'EB Garamond', serif;
            font-size: 1rem;
            resize: vertical;
        }

        .feedback-textarea:focus {
            outline: none;
            border-color: var(--accent-gold-light);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }

        .feedback-actions {
            display: flex;
            gap: 15px;
            justify-content: center;
            margin-top: 20px;
        }

        .btn-cancel {
            background: linear-gradient(135deg, #8b0000, #ff0000);
            color: white;
            border: 1px solid #ff6b6b;
        }

        .btn-submit {
            background: linear-gradient(135deg, #006400, #00ff00);
            color: white;
            border: 1px solid #90ee90;
        }

        .feedback-required {
            color: #ff6b6b;
            font-size: 0.9rem;
            text-align: center;
            margin-top: 10px;
        }

        @media (max-width: 768px) {
            .goals-grid {
                grid-template-columns: 1fr;
            }
            
            .monster-display {
                width: 95%;
                padding: 20px;
            }
            
            .monster-image {
                width: 200px;
                height: 200px;
            }
            
            .battle-actions {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                max-width: 250px;
            }
            
            .user-stats {
                gap: 15px;
            }
            
            .stat {
                padding: 10px 15px;
                min-width: 100px;
            }

            .feedback-actions {
                flex-direction: column;
            }

            .navigation {
                flex-direction: column;
                gap: 15px;
                align-items: flex-start;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation Back Button -->
        <div class="navigation">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                BACK TO DASHBOARD
            </a>
            <a href="create_goal.php" class="btn-create">
                <i class="fas fa-plus"></i>
                CREATE NEW QUEST
            </a>
        </div>

        <div class="header">
            <h1><i class="fas fa-scroll"></i> YOUR QUESTS</h1>
            <div class="user-stats">
                <div class="stat">
                    <div class="stat-value">Level <?php echo $user_level; ?></div>
                    <div class="stat-label">Adventurer</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $current_hp; ?>/<?php echo $max_hp; ?></div>
                    <div class="stat-label">Health</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $user_exp; ?> XP</div>
                    <div class="stat-label">Experience</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $gold; ?> <i class="fas fa-coins"></i></div>
                    <div class="stat-label">Gold</div>
                </div>
                <div class="stat">
                    <div class="stat-value"><?php echo $user_data['total_quests_failed']; ?></div>
                    <div class="stat-label">Quests Failed</div>
                </div>
            </div>
        </div>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['todo_completed'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> Task completed! Ready to attack.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['attack_success'])): ?>
            <div class="message success">
                <i class="fas fa-bolt"></i> Attack successful! Dealt <?php echo $_GET['damage']; ?> damage to the enemy!
            </div>
        <?php endif; ?>

        <?php if ($failed_quests > 0): ?>
            <div class="message error">
                <i class="fas fa-skull-crossbones"></i> <?php echo $failed_quests; ?> quest(s) failed due to missed deadlines!
            </div>
        <?php endif; ?>

        <?php if ($hp_deductions > 0): ?>
            <div class="message error">
                <i class="fas fa-heartbeat"></i> HP deducted from <?php echo $hp_deductions; ?> active quest(s)!
            </div>
        <?php endif; ?>

        <div class="goals-grid">
            <?php foreach ($goals as $goal): ?>
                <?php if ($goal['failed']): ?>
                    <!-- Failed Quest Card -->
                    <div class="goal-card failed">
                        <div class="failed-badge">
                            <i class="fas fa-skull-crossbones"></i> FAILED
                        </div>
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <p><?php echo htmlspecialchars($goal['description']); ?></p>
                        <div class="failed-info">
                            <i class="fas fa-clock"></i> Missed deadline: <?php echo date('M j, Y g:i A', strtotime($goal['due_date'])); ?>
                            <?php if ($goal['total_hp_deducted'] > 0): ?>
                                <br><i class="fas fa-heartbeat"></i> HP Lost: <?php echo $goal['total_hp_deducted']; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php else: ?>
                    <!-- Active Quest Card -->
                    <div class="goal-card" onclick="showMonster(<?php echo $goal['id']; ?>)">
                        <h3><?php echo htmlspecialchars($goal['title']); ?></h3>
                        <p><?php echo htmlspecialchars($goal['description']); ?></p>
                        <div class="goal-rewards">
                            <div class="reward">
                                <i class="fas fa-star"></i> +<?php echo $goal['exp_reward']; ?> XP
                            </div>
                            <?php if ($goal['gold_reward'] > 0): ?>
                            <div class="reward">
                                <i class="fas fa-coins"></i> +<?php echo $goal['gold_reward']; ?> Gold
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($goal['todo_count'] > 1): ?>
                            <div class="todo-progress">
                                <div class="progress-text">
                                    <i class="fas fa-tasks"></i> 
                                    Progress: <?php echo $goal['completed_todos']; ?>/<?php echo $goal['todo_count']; ?> tasks
                                </div>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (isset($goal_monsters[$goal['id']])): 
                            $monster = $goal_monsters[$goal['id']];
                            $max_hp = $monster['monster_hp'] ?? $monster['boss_hp'];
                            $current_hp = $monster['current_hp'];
                            $damage_per_todo = getDamagePerTodo($max_hp, $goal['todo_count']);
                            $monster_type = ($goal['difficulty'] == 'epic') ? 'Boss' : 'Monster';
                            $monster_folder = $monster['monster_folder'] ?? $monster['boss_folder'] ?? '1';
                            $pending_damage = $damage_per_todo * $goal['pending_attacks'];
                        ?>
                            <div class="monster-info">
                                <i class="fas fa-skull"></i> 
                                <?php echo $monster_type; ?> (<?php echo $monster_type; ?> <?php echo $monster_folder; ?>) HP: 
                                <?php echo $current_hp; ?>/<?php echo $max_hp; ?>
                                <?php if ($goal['pending_attacks'] > 0): ?>
                                    <br><small style="color: #ff6b6b;">Ready to deal <?php echo $pending_damage; ?> damage!</small>
                                <?php else: ?>
                                    <br><small>Each task enables <?php echo $damage_per_todo; ?> damage on attack</small>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- HP Deduction Information -->
                        <?php if ($goal['due_date']): ?>
                            <div class="hp-deduction-info">
                                <i class="fas fa-heartbeat"></i>
                                <strong>HP Deduction Active:</strong> 
                                -<?php echo $goal['hp_deduction_per_interval']; ?> HP per <?php echo $goal['deduction_interval']; ?>
                                <br>
                                <small>Total HP to lose: <?php echo $goal['total_hp_to_deduct']; ?> | Next: <?php echo date('M j, g:i A', strtotime($goal['next_deduction'])); ?></small>
                            </div>

                            <div class="deadline-info">
                                <i class="fas fa-clock"></i>
                                <strong>Deadline:</strong> <?php echo date('M j, Y g:i A', strtotime($goal['due_date'])); ?>
                                <?php 
                                    $now = new DateTime();
                                    $due_date = new DateTime($goal['due_date']);
                                    $time_remaining = $now->diff($due_date);
                                    
                                    if ($due_date < $now) {
                                        echo '<br><small style="color: var(--danger-red);">OVERDUE!</small>';
                                    } else {
                                        echo '<br><small>Time remaining: ' . $time_remaining->format('%a days, %h hours') . '</small>';
                                    }
                                ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>

        <?php if (empty($goals)): ?>
            <div style="text-align: center; padding: 50px;">
                <h2 style="color: var(--accent-gold); margin-bottom: 20px; font-family: 'Cinzel', serif;">No Quests Available</h2>
                <p style="color: var(--text-primary); font-size: 1.1rem; margin-bottom: 30px;">Create your first quest to begin your adventure!</p>
                <a href="create_goal.php" style="display: inline-block; padding: 15px 30px; 
                   background: var(--accent-gold); color: var(--bg-primary); text-decoration: none; 
                   border-radius: 8px; font-family: 'Cinzel', serif; font-size: 1.1rem;
                   transition: all 0.3s ease; border: 2px solid var(--accent-gold);">
                    <i class="fas fa-plus"></i> Create First Quest
                </a>
            </div>
        <?php endif; ?>
    </div>

    <!-- Monster Display Modal -->
    <div class="overlay" id="overlay"></div>
    
    <!-- Feedback Modal -->
    <div class="feedback-modal" id="feedbackModal">
        <div class="feedback-header">
            <h3><i class="fas fa-scroll"></i> QUEST COMPLETION REVIEW</h3>
            <p style="color: var(--text-secondary); margin-top: 10px;">Share your thoughts on this completed quest</p>
        </div>
        <form id="feedbackForm" method="POST" class="feedback-form">
            <input type="hidden" name="goal_id" id="feedbackGoalId">
            <div>
                <label class="feedback-label" for="feedback">
                    <i class="fas fa-pen"></i> Self-Review & Feedback:
                </label>
                <textarea 
                    name="feedback" 
                    id="feedback" 
                    class="feedback-textarea" 
                    placeholder="Reflect on your journey... What went well? What did you learn? How do you feel about completing this quest? Your feedback helps you grow as an adventurer!"
                    required
                ></textarea>
            </div>
            <div class="feedback-required">
                <i class="fas fa-exclamation-circle"></i> Reflection is required to complete your quest
            </div>
            <div class="feedback-actions">
                <button type="button" class="btn btn-cancel" onclick="hideFeedbackModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="complete_goal" class="btn btn-submit">
                    <i class="fas fa-trophy"></i> Complete Quest
                </button>
            </div>
        </form>
    </div>
    
    <?php foreach ($goals as $goal): ?>
        <?php if (isset($goal_monsters[$goal['id']]) && !$goal['failed']):
            $monster = $goal_monsters[$goal['id']];
            $max_hp = $monster['monster_hp'] ?? $monster['boss_hp'];
            $current_hp = $monster['current_hp'];
            $hp_percentage = ($current_hp / $max_hp) * 100;
            $damage_per_todo = getDamagePerTodo($max_hp, $goal['todo_count']);
            $todos = $goal_todos[$goal['id']] ?? [];
            $all_todos_completed = count($todos) > 0 && $goal['completed_todos'] >= $goal['todo_count'];
            $monster_type = ($goal['difficulty'] == 'epic') ? 'Boss' : 'Monster';
            $monster_folder = $monster['monster_folder'] ?? $monster['boss_folder'] ?? '1';
            $pending_damage = $damage_per_todo * $goal['pending_attacks'];
            $total_damage_from_todos = $damage_per_todo * $goal['completed_todos'];
        ?>
        <div class="monster-display" id="monster-<?php echo $goal['id']; ?>">
            <div class="monster-header">
                <h2><?php echo htmlspecialchars($goal['title']); ?></h2>
                <button class="close-btn" onclick="hideMonster(<?php echo $goal['id']; ?>)">&times;</button>
            </div>
            
            <div class="quest-description">
                <?php echo htmlspecialchars($goal['description']); ?>
            </div>

            <!-- HP Deduction Info in Modal -->
            <?php if ($goal['due_date']): ?>
                <div class="hp-deduction-info">
                    <i class="fas fa-heartbeat"></i>
                    <strong>HP Deduction Active:</strong> 
                    -<?php echo $goal['hp_deduction_per_interval']; ?> HP per <?php echo $goal['deduction_interval']; ?>
                    <br>
                    <small>Total HP to lose: <?php echo $goal['total_hp_to_deduct']; ?> | Next deduction: <?php echo date('M j, g:i A', strtotime($goal['next_deduction'])); ?></small>
                </div>

                <div class="deadline-info">
                    <i class="fas fa-clock"></i>
                    <strong>Deadline:</strong> <?php echo date('M j, Y g:i A', strtotime($goal['due_date'])); ?>
                    <?php 
                        $now = new DateTime();
                        $due_date = new DateTime($goal['due_date']);
                        if ($due_date < $now) {
                            echo '<br><small style="color: var(--danger-red);"><strong>OVERDUE! Complete this quest immediately!</strong></small>';
                        } else {
                            $time_remaining = $now->diff($due_date);
                            echo '<br><small>Time remaining: ' . $time_remaining->format('%a days, %h hours, %i minutes') . '</small>';
                        }
                    ?>
                </div>
            <?php endif; ?>

            <div class="combat-explanation">
                <h4><i class="fas fa-info-circle"></i> COMBAT SYSTEM</h4>
                <p>Complete tasks to enable attacks! Each completed task adds <strong><?php echo $damage_per_todo; ?> damage</strong> to your next attack.</p>
                <p>Click the ATTACK button to deal all accumulated damage at once!</p>
            </div>

            <?php if ($goal['pending_attacks'] > 0): ?>
                <div class="attack-info">
                    <h4><i class="fas fa-bolt"></i> READY TO ATTACK!</h4>
                    <p>You have <strong><?php echo $goal['pending_attacks']; ?></strong> completed tasks ready to attack!</p>
                    <p>Your next attack will deal <strong><?php echo $pending_damage; ?> damage</strong> to the <?php echo strtolower($monster_type); ?>!</p>
                </div>
            <?php endif; ?>
            
            <div class="hp-bar-container">
                <div class="hp-bar-label">
                    <span><?php echo $monster_type; ?> HP (<?php echo $monster_type; ?> <?php echo $monster_folder; ?>)</span>
                    <span><?php echo $current_hp; ?>/<?php echo $max_hp; ?></span>
                </div>
                <div class="hp-bar">
                    <div class="hp-fill" style="width: <?php echo $hp_percentage; ?>%;"></div>
                </div>
            </div>
            
            <div class="damage-info">
                <i class="fas fa-bolt"></i> 
                <?php if ($goal['completed_todos'] > 0): ?>
                    <?php if ($goal['pending_attacks'] > 0): ?>
                        <strong style="color: #ff6b6b;">Ready to attack for <?php echo $pending_damage; ?> damage!</strong>
                    <?php else: ?>
                        Tasks have dealt <strong><?php echo $total_damage_from_todos; ?> damage</strong> so far
                    <?php endif; ?>
                <?php else: ?>
                    Each completed task enables <strong><?php echo $damage_per_todo; ?> damage</strong> on attack
                <?php endif; ?>
            </div>
            
            <div class="battle-area">
                <div class="monster-image" 
                     style="background-image: url('<?php echo getMonsterImage($monster, $current_hp, $max_hp); ?>')">
                </div>
            </div>

            <div class="battle-actions">
                <?php if ($goal['pending_attacks'] > 0): ?>
                    <form method="POST" style="display: inline;">
                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                        <button type="submit" name="attack_monster" class="btn btn-attack">
                            <i class="fas fa-bolt"></i> ATTACK! (<?php echo $pending_damage; ?> damage)
                        </button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-attack" disabled>
                        <i class="fas fa-bolt"></i> ATTACK (Complete tasks first)
                    </button>
                <?php endif; ?>
            </div>

            <?php if (!empty($todos)): ?>
                <div class="todo-section">
                    <h3><i class="fas fa-tasks"></i> QUEST TASKS - ENABLE YOUR ATTACKS!</h3>
                    <div class="todo-list">
                        <?php foreach ($todos as $todo): ?>
                            <div class="todo-item <?php echo $todo['completed'] ? 'completed' : ''; ?>">
                                <?php if (!$todo['completed']): ?>
                                    <form method="POST" style="display: flex; align-items: center; width: 100%;">
                                        <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                        <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                        <button type="submit" name="complete_todo" class="btn btn-todo" 
                                                style="padding: 8px 15px; font-size: 0.9rem; min-width: auto;"
                                                title="Complete this task to enable <?php echo $damage_per_todo; ?> damage on your next attack">
                                            <i class="fas fa-check"></i> Complete
                                        </button>
                                        <span class="todo-name"><?php echo htmlspecialchars($todo['todo_name']); ?></span>
                                    </form>
                                <?php else: ?>
                                    <i class="fas fa-check-circle" style="color: #00ff00; font-size: 1.2rem;"></i>
                                    <span class="todo-name completed"><?php echo htmlspecialchars($todo['todo_name']); ?></span>
                                    <small style="color: var(--accent-gold); margin-left: auto;">+<?php echo $damage_per_todo; ?> damage enabled</small>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="battle-actions">
                <?php if ($all_todos_completed || $current_hp <= 0): ?>
                    <button type="button" class="btn btn-complete" onclick="showFeedbackModal(<?php echo $goal['id']; ?>)">
                        <i class="fas fa-trophy"></i> Complete Quest & Claim Rewards
                    </button>
                <?php else: ?>
                    <button class="btn btn-complete" disabled>
                        <i class="fas fa-lock"></i> Defeat <?php echo $monster_type; ?> to Complete Quest
                    </button>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <script>
        function showMonster(goalId) {
            document.getElementById('overlay').classList.add('active');
            document.getElementById('monster-' + goalId).classList.add('active');
        }

        function hideMonster(goalId) {
            document.getElementById('overlay').classList.remove('active');
            document.getElementById('monster-' + goalId).classList.remove('active');
        }

        function showFeedbackModal(goalId) {
            document.getElementById('feedbackGoalId').value = goalId;
            document.getElementById('feedbackModal').classList.add('active');
            document.getElementById('overlay').classList.add('active');
        }

        function hideFeedbackModal() {
            document.getElementById('feedbackModal').classList.remove('active');
            document.getElementById('overlay').classList.remove('active');
            document.getElementById('feedback').value = '';
        }

        // Close modal when clicking overlay
        document.getElementById('overlay').addEventListener('click', function() {
            this.classList.remove('active');
            document.querySelectorAll('.monster-display.active').forEach(modal => {
                modal.classList.remove('active');
            });
            hideFeedbackModal();
        });

        // Auto-show monster if selected from URL
        <?php if (isset($_GET['selected_goal'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showMonster(<?php echo $_GET['selected_goal']; ?>);
            });
        <?php endif; ?>

        // Add confirmation for todo completion
        document.querySelectorAll('form[action*="complete_todo"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const damage = this.querySelector('button').getAttribute('title').match(/\d+/)[0];
                if (!confirm(`Complete this task and enable ${damage} damage for your next attack?`)) {
                    e.preventDefault();
                }
            });
        });

        // Add confirmation for attack
        document.querySelectorAll('form[action*="attack_monster"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const damage = this.querySelector('button').textContent.match(/\d+/)[0];
                if (!confirm(`Launch attack dealing ${damage} damage to the enemy?`)) {
                    e.preventDefault();
                }
            });
        });

        // Handle feedback form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const feedback = document.getElementById('feedback').value.trim();
            if (!feedback) {
                e.preventDefault();
                alert('Please provide your self-review and feedback before completing the quest!');
                document.getElementById('feedback').focus();
            }
        });

        // Auto-refresh page every 5 minutes to update HP and deadlines
        setTimeout(function() {
            location.reload();
        }, 5 * 60 * 1000); // 5 minutes
    </script>
</body>
</html>