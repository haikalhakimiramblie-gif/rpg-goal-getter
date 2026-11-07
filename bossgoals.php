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
    error_log("Boss quest processing error: " . $e->getMessage());
}

// Get user data from database
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
    
} catch (Exception $e) {
    die("Error loading user data: " . $e->getMessage());
}

// Handle form submission for creating boss quest ON THIS PAGE
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_boss_quest'])) {
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
        header("Location: bossgoals.php");
        exit();
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
        header("Location: bossgoals.php");
        exit();
    }
}

// Handle todo completion for BOSS QUESTS
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
        header("Location: bossgoals.php?selected_goal=" . $goal_id . "&todo_completed=1");
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: bossgoals.php?error=Error completing boss phase: " . $e->getMessage());
        exit();
    }
}

// Handle attack button for BOSS
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['attack_boss'])) {
    $goal_id = $_POST['goal_id'];
    
    try {
        $pdo->beginTransaction();
        
        // Get goal details and boss info
        $sql = "SELECT g.*, 
                       b.boss_hp, b.current_hp as boss_current_hp, b.boss_folder,
                       g.todo_count, g.pending_attacks,
                       (SELECT COUNT(*) FROM goal_todos WHERE goal_id = g.id AND completed = 1) as completed_count
                FROM goals g
                LEFT JOIN bosses b ON g.id = b.goal_id
                WHERE g.id = ? AND g.user_id = ? AND g.difficulty = 'epic'";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$goal_id, $user_id]);
        $goal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($goal && $goal['pending_attacks'] > 0) {
            $max_hp = $goal['boss_hp'];
            $current_hp = $goal['boss_current_hp'];
            
            // Calculate damage based on pending attacks
            $damage_per_todo = ceil($max_hp / $goal['todo_count']);
            $total_damage = $damage_per_todo * $goal['pending_attacks'];
            $new_hp = max(0, $current_hp - $total_damage);
            
            // Update boss HP
            $sql = "UPDATE bosses SET current_hp = ? WHERE goal_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$new_hp, $goal_id]);
            
            // Reset pending attacks counter
            $sql = "UPDATE goals SET pending_attacks = 0 WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
        }
        
        $pdo->commit();
        header("Location: bossgoals.php?selected_goal=" . $goal_id . "&attack_success=1&damage=" . $total_damage);
        exit();
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: bossgoals.php?error=Error attacking boss: " . $e->getMessage());
        exit();
    }
}

// Handle boss completion with feedback
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_boss'])) {
    $goal_id = $_POST['goal_id'];
    $feedback = $_POST['feedback'] ?? '';
    
    // Check if feedback is provided
    if (empty(trim($feedback))) {
        header("Location: bossgoals.php?selected_goal=" . $goal_id . "&error=Please provide feedback before defeating the boss!");
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
            // BOSS rewards - higher than regular
            $exp_reward = $goal['exp_reward'] ?? 600;
            $gold_reward = $goal['gold_reward'] ?? 150;
            
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
            
            // Log the completed boss in goal_logs with feedback
            $sql = "INSERT INTO goal_logs (user_id, goal_title, goal_description, category, difficulty, todo_count, exp_earned, gold_earned, completion_time, feedback, status) 
                    VALUES (?, ?, ?, ?, 'epic', ?, ?, ?, ?, ?, 'completed')";
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
            
            $sql = "DELETE FROM bosses WHERE goal_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
            
            $sql = "DELETE FROM goal_todos WHERE goal_id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$goal_id]);
            
            $pdo->commit();
            
            header("Location: bossgoals.php?success=BOSS DEFEATED! +" . $exp_reward . " XP • +" . $gold_reward . " Gold");
            exit();
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        header("Location: bossgoals.php?error=Error defeating boss: " . $e->getMessage());
        exit();
    }
}

// Get user's BOSS goals (only epic difficulty) with todo counts and pending attacks
$sql = "SELECT g.*, 
               COUNT(gt.id) as total_todos,
               SUM(CASE WHEN gt.completed = 1 THEN 1 ELSE 0 END) as completed_todos,
               g.pending_attacks,
               g.total_hp_deducted,
               b.boss_hp, b.current_hp as boss_current_hp, b.boss_folder,
               DATEDIFF(g.due_date, NOW()) as days_remaining
        FROM goals g 
        LEFT JOIN goal_todos gt ON g.id = gt.goal_id 
        LEFT JOIN bosses b ON g.id = b.goal_id
        WHERE g.user_id = ? 
        AND g.failed = 0
        AND g.difficulty = 'epic'
        GROUP BY g.id 
        ORDER BY g.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$boss_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get todos for each boss goal
$boss_todos = [];
foreach ($boss_goals as $goal) {
    $sql = "SELECT * FROM goal_todos WHERE goal_id = ? ORDER BY position";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$goal['id']]);
    $boss_todos[$goal['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Function to get boss image based on HP
function getBossImage($boss_data, $current_hp, $max_hp) {
    $base_path = 'images/boss' . ($boss_data['boss_folder'] ?? '1') . '/';
    
    $hp_percentage = ($current_hp / $max_hp) * 100;
    
    if ($current_hp <= 0) {
        $image = $base_path . 'dead.png';
    } elseif ($hp_percentage == 100) {
        $image = $base_path . 'sleep.png';
    } elseif ($hp_percentage <= 60 && $hp_percentage > 30) {
        $image = $base_path . 'angry.png';
    } elseif ($hp_percentage <= 30 && $hp_percentage > 0) {
        $image = $base_path . 'injured.png';
    } else {
        $image = $base_path . 'normal.png';
    }
    
    // Check if file exists, if not use a default
    if (!file_exists($image)) {
        $image = 'images/boss1/normal.png';
    }
    
    return $image;
}

// Function to calculate damage per todo for boss
function getBossDamagePerTodo($max_hp, $todo_count) {
    return ceil($max_hp / $todo_count);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Boss Arena Quests | RPG Goal Getter</title>
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
            --box-bg: rgba(30, 10, 10, 0.6);
            --modal-overlay: rgba(0, 0, 0, 0.85);
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
            padding: 20px;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        /* Navigation */
        .navigation {
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, rgba(80, 20, 20, 0.8), rgba(60, 15, 15, 0.9));
            color: var(--text-primary);
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 68, 68, 0.5);
        }

        .btn-create {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 2px solid #ff0000;
        }

        .btn-back:hover {
            background: linear-gradient(135deg, rgba(100, 25, 25, 0.9), rgba(70, 20, 20, 1));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 68, 68, 0.4);
            border-color: var(--primary-red);
        }

        .btn-create:hover {
            background: linear-gradient(135deg, #ff0000, var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(255, 68, 68, 0.6);
        }

        /* Header */
        .header {
            text-align: center;
            margin-bottom: 30px;
            padding: 30px;
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

        /* User Stats */
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
            background: linear-gradient(135deg, rgba(80, 15, 15, 0.8) 0%, rgba(60, 10, 10, 0.9) 100%);
            border-radius: 8px;
            border: 1px solid rgba(255, 68, 68, 0.4);
            min-width: 120px;
        }

        .stat-value {
            font-family: 'Cinzel', serif;
            font-size: 1.4rem;
            color: var(--primary-red);
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-top: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Main Content Area */
        .main-content {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }

        /* Boxes Grid - Minimum 3 boxes always visible */
        .boxes-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
            margin-top: 20px;
        }

        /* Box Style - Each quest or empty slot */
        .box {
            background: linear-gradient(135deg, var(--box-bg), rgba(40, 15, 15, 0.8));
            border: 3px solid rgba(255, 68, 68, 0.4);
            border-radius: 15px;
            min-height: 250px;
            display: flex;
            flex-direction: column;
            transition: all 0.3s ease;
            overflow: hidden;
            position: relative;
            cursor: pointer;
        }

        .box:hover {
            transform: translateY(-5px);
            border-color: var(--primary-red);
            box-shadow: 0 15px 30px rgba(255, 68, 68, 0.3);
        }

        /* Empty Box (Add New) */
        .box.empty {
            background: linear-gradient(135deg, rgba(50, 20, 20, 0.7), rgba(30, 10, 10, 0.8));
            border: 3px dashed rgba(255, 68, 68, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .box.empty:hover {
            background: linear-gradient(135deg, rgba(60, 25, 25, 0.8), rgba(40, 15, 15, 0.9));
            border: 3px dashed var(--primary-red);
        }

        .plus-sign {
            font-size: 5rem;
            color: rgba(255, 68, 68, 0.6);
            transition: all 0.3s ease;
        }

        .box.empty:hover .plus-sign {
            color: var(--primary-red);
            transform: scale(1.2);
        }

        .empty-label {
            position: absolute;
            bottom: 20px;
            width: 100%;
            text-align: center;
            color: var(--text-secondary);
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
        }

        /* Quest Box */
        .quest-box {
            padding: 25px;
            height: 100%;
            display: flex;
            flex-direction: column;
        }

        .quest-box.failed {
            background: linear-gradient(135deg, rgba(100, 10, 10, 0.7), rgba(70, 5, 5, 0.8));
            border-color: var(--danger);
            cursor: default;
        }

        .quest-box.failed:hover {
            border-color: var(--danger);
            box-shadow: 0 15px 30px rgba(255, 0, 0, 0.3);
            transform: none;
        }

        /* Quest Header */
        .quest-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .quest-title {
            font-family: 'Cinzel', serif;
            color: var(--boss-gold);
            font-size: 1.4rem;
            margin-bottom: 5px;
            flex: 1;
        }

        .quest-box.failed .quest-title {
            color: #ff6b6b;
            text-decoration: line-through;
        }

        .quest-category {
            font-size: 0.85rem;
            color: var(--text-secondary);
            background: rgba(255, 68, 68, 0.1);
            padding: 3px 8px;
            border-radius: 12px;
            border: 1px solid rgba(255, 68, 68, 0.3);
            white-space: nowrap;
        }

        /* Quest Description */
        .quest-description {
            color: var(--text-primary);
            margin-bottom: 20px;
            line-height: 1.5;
            font-size: 1rem;
            flex: 1;
            overflow: hidden;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
        }

        /* Progress Section */
        .progress-section {
            margin: 15px 0;
            padding: 15px;
            background: rgba(30, 10, 10, 0.8);
            border-radius: 10px;
            border: 1px solid rgba(255, 68, 68, 0.3);
        }

        .progress-title {
            font-family: 'Cinzel', serif;
            color: var(--boss-gold);
            margin-bottom: 12px;
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .progress-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
        }

        .progress-item {
            display: flex;
            flex-direction: column;
        }

        .progress-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
            gap: 5px;
            margin-bottom: 3px;
        }

        .progress-value {
            font-family: 'Cinzel', serif;
            color: var(--primary-red);
            font-weight: bold;
            font-size: 1.1rem;
        }

        /* Enter Button */
        .enter-section {
            margin-top: auto;
            text-align: center;
        }

        .enter-btn {
            display: inline-block;
            width: 100%;
            padding: 12px;
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            transition: all 0.3s ease;
            border: 2px solid rgba(255, 68, 68, 0.6);
            cursor: pointer;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.95rem;
        }

        .enter-btn:hover {
            background: linear-gradient(135deg, #ff0000, var(--primary-red));
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(255, 68, 68, 0.6);
            border-color: white;
        }

        .enter-btn:disabled {
            background: linear-gradient(135deg, #666, #888);
            cursor: not-allowed;
            transform: none;
            border-color: #666;
        }

        /* Failed Badge */
        .failed-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: linear-gradient(135deg, var(--danger), #ff0000);
            color: white;
            padding: 5px 12px;
            border-radius: 15px;
            font-size: 0.8rem;
            font-family: 'Cinzel', serif;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* ================================ */
        /* MODAL OVERLAY - DIM BACKGROUND */
        /* ================================ */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: var(--modal-overlay);
            backdrop-filter: blur(5px);
            z-index: 1000;
            animation: fadeIn 0.3s ease;
        }

        .modal-overlay.active {
            display: block;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        /* ================================ */
        /* CREATE BOSS MODAL */
        /* ================================ */
        .create-boss-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(50, 15, 15, 0.95) 0%, rgba(30, 10, 10, 0.98) 100%);
            border: 3px solid var(--primary-red);
            border-radius: 20px;
            padding: 30px;
            z-index: 1001;
            width: 95%;
            max-width: 600px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 40px rgba(255, 0, 0, 0.5);
            animation: modalSlideIn 0.4s ease;
        }

        .create-boss-modal.active {
            display: block;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 15px;
            border-bottom: 2px solid rgba(255, 68, 68, 0.6);
        }

        .modal-header h2 {
            font-family: 'Cinzel', serif;
            color: var(--boss-gold);
            font-size: 1.8rem;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--primary-red);
            font-size: 2rem;
            cursor: pointer;
            padding: 5px 10px;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            color: white;
            transform: scale(1.1);
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 20px;
        }

        .form-label {
            display: block;
            margin-bottom: 8px;
            color: var(--primary-red);
            font-weight: 500;
            font-family: 'Cinzel', serif;
            font-size: 1rem;
        }

        .form-input, .form-select, .form-textarea {
            width: 100%;
            padding: 12px 15px;
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
            min-height: 100px;
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
            padding: 12px 15px;
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
            padding: 10px 18px;
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

        /* Form Actions */
        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 30px;
        }

        .btn-form {
            padding: 15px 25px;
            border: none;
            border-radius: 8px;
            font-family: 'Cinzel', serif;
            cursor: pointer;
            transition: all 0.3s ease;
            font-size: 1rem;
            flex: 1;
        }

        .btn-cancel-form {
            background: linear-gradient(135deg, #666, #888);
            color: white;
            border: 1px solid #777;
        }

        .btn-submit-form {
            background: linear-gradient(135deg, var(--primary-red), var(--secondary-red));
            color: white;
            border: 1px solid #ff0000;
        }

        .btn-form:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 20px rgba(255, 255, 255, 0.2);
        }

        /* ================================ */
        /* BOSS BATTLE MODAL */
        /* ================================ */
        .boss-battle-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(50, 15, 15, 0.95) 0%, rgba(30, 10, 10, 0.98) 100%);
            border: 3px solid var(--primary-red);
            border-radius: 20px;
            padding: 30px;
            z-index: 1001;
            width: 95%;
            max-width: 800px;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 0 40px rgba(255, 0, 0, 0.5);
            animation: modalSlideIn 0.4s ease;
        }

        .boss-battle-modal.active {
            display: block;
        }

        /* Battle Content Styles */
        .battle-content {
            margin-bottom: 20px;
        }

        .boss-image-container {
            width: 250px;
            height: 250px;
            margin: 0 auto 20px;
            border: 3px solid rgba(255, 215, 0, 0.5);
            border-radius: 10px;
            background-color: rgba(0, 0, 0, 0.3);
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
        }

        .boss-hp-bar {
            width: 100%;
            max-width: 400px;
            margin: 15px auto;
        }

        .hp-bar-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
            color: var(--boss-gold);
            font-family: 'Cinzel', serif;
        }

        .hp-bar {
            width: 100%;
            height: 25px;
            background: rgba(0, 0, 0, 0.5);
            border: 2px solid var(--primary-red);
            border-radius: 12px;
            overflow: hidden;
        }

        .hp-fill {
            height: 100%;
            background: linear-gradient(90deg, #ff0000, #ff6b6b);
            transition: width 0.5s ease;
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

        .phases-section {
            margin: 25px 0;
            padding: 20px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 12px;
            border: 1px solid rgba(212, 175, 55, 0.3);
        }

        .phases-section h3 {
            font-family: 'Cinzel', serif;
            color: var(--boss-gold);
            margin-bottom: 15px;
            text-align: center;
        }

        .phase-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .phase-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 15px;
            background: rgba(40, 20, 40, 0.8);
            border-radius: 8px;
            border-left: 4px solid rgba(255, 215, 0, 0.6);
        }

        .phase-item.completed {
            opacity: 0.7;
            border-left-color: #00ff00;
        }

        .phase-name {
            flex: 1;
            color: var(--text-primary);
        }

        .phase-name.completed {
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

        /* ================================ */
        /* FEEDBACK MODAL */
        /* ================================ */
        .feedback-modal {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: linear-gradient(135deg, rgba(50, 15, 15, 0.95) 0%, rgba(30, 10, 10, 0.98) 100%);
            border: 3px solid var(--boss-gold);
            border-radius: 20px;
            padding: 30px;
            z-index: 1002;
            width: 95%;
            max-width: 500px;
            backdrop-filter: blur(10px);
            animation: modalSlideIn 0.4s ease;
        }

        .feedback-modal.active {
            display: block;
        }

        /* Messages */
        .message {
            padding: 15px 20px;
            margin: 15px 0;
            border-radius: 8px;
            text-align: center;
            font-weight: bold;
            font-size: 1.1rem;
        }

        .message.success {
            background: linear-gradient(135deg, rgba(20, 80, 30, 0.8) 0%, rgba(10, 50, 20, 0.9) 100%);
            border: 2px solid #00ff00;
            color: #90ee90;
        }

        .message.error {
            background: linear-gradient(135deg, rgba(100, 10, 10, 0.8) 0%, rgba(70, 5, 5, 0.9) 100%);
            border: 2px solid #ff0000;
            color: #ff6b6b;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .boxes-grid {
                grid-template-columns: 1fr;
            }
            
            .navigation {
                flex-direction: column;
                align-items: stretch;
            }
            
            .btn-back, .btn-create {
                width: 100%;
                justify-content: center;
            }
            
            .progress-grid {
                grid-template-columns: 1fr;
            }
            
            .create-boss-modal,
            .boss-battle-modal,
            .feedback-modal {
                width: 95%;
                padding: 20px;
            }
            
            .boss-image-container {
                width: 200px;
                height: 200px;
            }
            
            .form-actions,
            .battle-actions {
                flex-direction: column;
            }
            
            .btn-form {
                width: 100%;
            }
        }

        @media (min-width: 769px) and (max-width: 1024px) {
            .boxes-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (min-width: 1025px) {
            .boxes-grid {
                grid-template-columns: repeat(3, 1fr);
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Navigation -->
        <div class="navigation">
            <a href="dashboard.php" class="btn-back">
                <i class="fas fa-arrow-left"></i>
                BACK TO DASHBOARD
            </a>
            <a href="boss_arena.php" class="btn-create">
                <i class="fas fa-dragon"></i>
                BOSS ARENA
            </a>
        </div>

        <!-- Header -->
        <div class="header">
            <h1><i class="fas fa-dragon"></i> BOSS QUESTS</h1>
            <p>Face your epic challenges and prove your strength</p>
            
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
            </div>
        </div>

        <!-- Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="message success">
                <i class="fas fa-trophy"></i> <?php echo $_SESSION['success']; ?>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $_SESSION['error']; ?>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <?php if (isset($_GET['success'])): ?>
            <div class="message success">
                <i class="fas fa-trophy"></i> <?php echo htmlspecialchars($_GET['success']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['error'])): ?>
            <div class="message error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($_GET['error']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['todo_completed'])): ?>
            <div class="message success">
                <i class="fas fa-check-circle"></i> Boss phase completed! Ready to attack.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['attack_success'])): ?>
            <div class="message success">
                <i class="fas fa-bolt"></i> BOSS ATTACK! Dealt <?php echo $_GET['damage']; ?> damage!
            </div>
        <?php endif; ?>

        <!-- Main Content - Just the boxes grid -->
        <div class="main-content">
            <!-- Boxes Grid - Minimum 3 boxes -->
            <div class="boxes-grid" id="boxesGrid">
                <?php 
                // Display existing boss quests
                foreach ($boss_goals as $goal): ?>
                    <?php if ($goal['failed']): ?>
                        <!-- Failed Boss Quest -->
                        <div class="box">
                            <div class="quest-box failed">
                                <div class="failed-badge">
                                    <i class="fas fa-skull-crossbones"></i> FAILED
                                </div>
                                <div class="quest-header">
                                    <div class="quest-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                                </div>
                                
                                <div class="quest-description">
                                    <?php echo htmlspecialchars($goal['description']); ?>
                                </div>
                                
                                <div style="margin-top: auto; color: #ff6b6b; font-size: 0.9rem;">
                                    <i class="fas fa-clock"></i> Missed: <?php echo date('M j, Y', strtotime($goal['due_date'])); ?>
                                </div>
                            </div>
                        </div>
                    <?php else: ?>
                        <!-- Active Boss Quest -->
                        <div class="box" onclick="showBossBattle(<?php echo $goal['id']; ?>)">
                            <div class="quest-box">
                                <div class="quest-header">
                                    <div class="quest-title"><?php echo htmlspecialchars($goal['title']); ?></div>
                                    <span class="quest-category"><?php echo htmlspecialchars($goal['category']); ?></span>
                                </div>
                                
                                <div class="quest-description">
                                    <?php echo htmlspecialchars($goal['description']); ?>
                                </div>
                                
                                <!-- Progress Section -->
                                <div class="progress-section">
                                    <div class="progress-title">
                                        <i class="fas fa-tasks"></i> PROGRESS
                                    </div>
                                    <div class="progress-grid">
                                        <div class="progress-item">
                                            <span class="progress-label">
                                                <i class="fas fa-fire"></i> Attack
                                            </span>
                                            <span class="progress-value">
                                                <?php 
                                                $damage_per_todo = getBossDamagePerTodo($goal['boss_hp'], $goal['todo_count']);
                                                $pending_damage = $damage_per_todo * $goal['pending_attacks'];
                                                echo $pending_damage > 0 ? $pending_damage : '0';
                                                ?>
                                            </span>
                                        </div>
                                        <div class="progress-item">
                                            <span class="progress-label">
                                                <i class="fas fa-heartbeat"></i> Boss HP
                                            </span>
                                            <span class="progress-value">
                                                <?php echo $goal['boss_current_hp']; ?>/<?php echo $goal['boss_hp']; ?>
                                            </span>
                                        </div>
                                        <div class="progress-item">
                                            <span class="progress-label">
                                                <i class="fas fa-hourglass"></i> Deadline
                                            </span>
                                            <span class="progress-value">
                                                <?php echo abs($goal['days_remaining']); ?>d
                                            </span>
                                        </div>
                                        <div class="progress-item">
                                            <span class="progress-label">
                                                <i class="fas fa-tasks"></i> Phases
                                            </span>
                                            <span class="progress-value">
                                                <?php echo $goal['completed_todos']; ?>/<?php echo $goal['todo_count']; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Enter Button -->
                                <div class="enter-section">
                                    <button class="enter-btn" onclick="event.stopPropagation(); showBossBattle(<?php echo $goal['id']; ?>)">
                                        ENTER
                                    </button>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- Empty Boxes (Add New) -->
                <?php 
                $totalBoxes = max(3, count($boss_goals) + 1); // Minimum 3 boxes, at least one empty
                $emptyBoxes = $totalBoxes - count($boss_goals);
                
                for ($i = 0; $i < $emptyBoxes; $i++): ?>
                    <div class="box empty" onclick="showCreateBossModal()">
                        <div class="plus-sign">+</div>
                        <div class="empty-label">Add New Boss Quest</div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>

    <!-- ================================ -->
    <!-- MODAL OVERLAY (DIM BACKGROUND) -->
    <!-- ================================ -->
    <div class="modal-overlay" id="modalOverlay"></div>

    <!-- ================================ -->
    <!-- CREATE BOSS QUEST MODAL -->
    <!-- ================================ -->
    <div class="create-boss-modal" id="createBossModal">
        <div class="modal-header">
            <h2><i class="fas fa-dragon"></i> CREATE BOSS QUEST</h2>
            <button class="close-btn" onclick="hideCreateBossModal()">&times;</button>
        </div>
        
        <form method="POST" id="questForm">
            <input type="hidden" name="create_boss_quest" value="1">
            
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

            <!-- Form Actions -->
            <div class="form-actions">
                <button type="button" class="btn-form btn-cancel-form" onclick="hideCreateBossModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" class="btn-form btn-submit-form">
                    <i class="fas fa-dragon"></i> Create Boss Quest
                </button>
            </div>
        </form>
    </div>

    <!-- ================================ -->
    <!-- BOSS BATTLE MODALS -->
    <!-- ================================ -->
    <?php foreach ($boss_goals as $goal): ?>
        <?php if (!$goal['failed']):
            $max_hp = $goal['boss_hp'];
            $current_hp = $goal['boss_current_hp'];
            $hp_percentage = ($current_hp / $max_hp) * 100;
            $damage_per_todo = getBossDamagePerTodo($max_hp, $goal['todo_count']);
            $todos = $boss_todos[$goal['id']] ?? [];
            $all_todos_completed = count($todos) > 0 && $goal['completed_todos'] >= $goal['todo_count'];
            $pending_damage = $damage_per_todo * $goal['pending_attacks'];
            $total_damage_from_todos = $damage_per_todo * $goal['completed_todos'];
        ?>
        <div class="boss-battle-modal" id="bossBattleModal-<?php echo $goal['id']; ?>">
            <div class="modal-header">
                <h2><?php echo htmlspecialchars($goal['title']); ?></h2>
                <button class="close-btn" onclick="hideBossBattle(<?php echo $goal['id']; ?>)">&times;</button>
            </div>
            
            <div class="battle-content">
                <div style="color: var(--text-primary); margin-bottom: 20px; line-height: 1.6;">
                    <?php echo htmlspecialchars($goal['description']); ?>
                </div>

                <!-- HP Deduction Info -->
                <?php if ($goal['due_date']): ?>
                    <div style="background: rgba(100, 10, 10, 0.3); border: 1px solid #ff4444; border-radius: 8px; padding: 15px; margin: 15px 0;">
                        <div style="display: flex; align-items: center; gap: 10px; color: #ff6b6b; margin-bottom: 10px;">
                            <i class="fas fa-heart-crack"></i>
                            <strong>BOSS HP DRAIN: -<?php echo $goal['hp_deduction_per_interval']; ?> HP per <?php echo $goal['deduction_interval']; ?></strong>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                            Next drain: <?php echo date('M j, g:i A', strtotime($goal['next_deduction'])); ?>
                        </div>
                    </div>

                    <div style="background: rgba(255, 152, 0, 0.2); border: 1px solid #ff9800; border-radius: 8px; padding: 15px; margin: 15px 0;">
                        <div style="display: flex; align-items: center; gap: 10px; color: #ff9800; margin-bottom: 10px;">
                            <i class="fas fa-hourglass"></i>
                            <strong>DEADLINE: <?php echo date('M j, Y g:i A', strtotime($goal['due_date'])); ?></strong>
                        </div>
                        <div style="color: var(--text-secondary); font-size: 0.9rem;">
                            <?php 
                                $now = new DateTime();
                                $due_date = new DateTime($goal['due_date']);
                                if ($due_date < $now) {
                                    echo '<span style="color: #ff0000; font-weight: bold;">OVERDUE! Defeat boss immediately!</span>';
                                } else {
                                    $time_remaining = $now->diff($due_date);
                                    echo 'Time remaining: ' . $time_remaining->format('%a days, %h hours, %i minutes');
                                }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($goal['pending_attacks'] > 0): ?>
                    <div class="attack-info">
                        <h4><i class="fas fa-bolt"></i> READY FOR EPIC ATTACK!</h4>
                        <p>You have <strong><?php echo $goal['pending_attacks']; ?></strong> completed boss phases ready!</p>
                        <p>Your next attack will deal <strong><?php echo $pending_damage; ?> damage</strong> to the boss!</p>
                    </div>
                <?php endif; ?>
                
                <div class="boss-hp-bar">
                    <div class="hp-bar-label">
                        <span>BOSS <?php echo $goal['boss_folder']; ?> HP</span>
                        <span><?php echo $current_hp; ?>/<?php echo $max_hp; ?></span>
                    </div>
                    <div class="hp-bar">
                        <div class="hp-fill" style="width: <?php echo $hp_percentage; ?>%;"></div>
                    </div>
                </div>
                
                <div style="text-align: center; color: var(--boss-gold); margin: 15px 0; font-family: 'Cinzel', serif;">
                    <i class="fas fa-bolt"></i> 
                    <?php if ($goal['completed_todos'] > 0): ?>
                        <?php if ($goal['pending_attacks'] > 0): ?>
                            <strong style="color: #ff0000;">READY TO ATTACK FOR <?php echo $pending_damage; ?> DAMAGE!</strong>
                        <?php else: ?>
                            Phases have dealt <strong><?php echo $total_damage_from_todos; ?> damage</strong> so far
                        <?php endif; ?>
                    <?php else: ?>
                        Each completed boss phase enables <strong><?php echo $damage_per_todo; ?> damage</strong> per attack
                    <?php endif; ?>
                </div>
                
                <div class="boss-image-container" 
                     style="background-image: url('<?php echo getBossImage($goal, $current_hp, $max_hp); ?>')">
                </div>

                <div class="battle-actions">
                    <?php if ($goal['pending_attacks'] > 0): ?>
                        <form method="POST">
                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                            <button type="submit" name="attack_boss" class="btn-form btn-submit-form">
                                <i class="fas fa-bolt"></i> EPIC ATTACK! (<?php echo $pending_damage; ?> DMG)
                            </button>
                        </form>
                    <?php else: ?>
                        <button class="btn-form btn-cancel-form" disabled>
                            <i class="fas fa-bolt"></i> Complete phases to attack
                        </button>
                    <?php endif; ?>
                </div>

                <?php if (!empty($todos)): ?>
                    <div class="phases-section">
                        <h3><i class="fas fa-tasks"></i> BOSS PHASES</h3>
                        <div class="phase-list">
                            <?php foreach ($todos as $todo): ?>
                                <div class="phase-item <?php echo $todo['completed'] ? 'completed' : ''; ?>">
                                    <?php if (!$todo['completed']): ?>
                                        <form method="POST" style="display: flex; align-items: center; width: 100%;">
                                            <input type="hidden" name="todo_id" value="<?php echo $todo['id']; ?>">
                                            <input type="hidden" name="goal_id" value="<?php echo $goal['id']; ?>">
                                            <button type="submit" name="complete_todo" class="btn-form" 
                                                    style="padding: 8px 15px; font-size: 0.9rem; min-width: auto; background: linear-gradient(135deg, #d4af37, #b8860b); color: var(--dark-bg); border: 1px solid var(--boss-gold);"
                                                    title="Complete this phase to enable <?php echo $damage_per_todo; ?> damage">
                                                <i class="fas fa-check"></i> Complete
                                            </button>
                                            <span class="phase-name"><?php echo htmlspecialchars($todo['todo_name']); ?></span>
                                        </form>
                                    <?php else: ?>
                                        <i class="fas fa-check-circle" style="color: #00ff00; font-size: 1.2rem;"></i>
                                        <span class="phase-name completed"><?php echo htmlspecialchars($todo['todo_name']); ?></span>
                                        <small style="color: var(--boss-gold); margin-left: auto;">+<?php echo $damage_per_todo; ?> damage enabled</small>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endif; ?>
                
                <div class="battle-actions">
                    <?php if ($all_todos_completed || $current_hp <= 0): ?>
                        <button type="button" class="btn-form btn-submit-form" onclick="showFeedbackModal(<?php echo $goal['id']; ?>)">
                            <i class="fas fa-trophy"></i> DEFEAT BOSS & CLAIM VICTORY
                        </button>
                    <?php else: ?>
                        <button class="btn-form btn-cancel-form" disabled>
                            <i class="fas fa-lock"></i> Defeat boss to claim victory
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>
    <?php endforeach; ?>

    <!-- ================================ -->
    <!-- FEEDBACK MODAL -->
    <!-- ================================ -->
    <div class="feedback-modal" id="feedbackModal">
        <div class="modal-header">
            <h3><i class="fas fa-scroll"></i> BOSS DEFEAT REVIEW</h3>
            <button class="close-btn" onclick="hideFeedbackModal()">&times;</button>
        </div>
        <form id="feedbackForm" method="POST">
            <input type="hidden" name="goal_id" id="feedbackGoalId">
            <div class="form-group">
                <label class="form-label" for="feedback">
                    <i class="fas fa-pen"></i> Victory Reflection:
                </label>
                <textarea 
                    name="feedback" 
                    id="feedback" 
                    class="form-textarea" 
                    placeholder="Reflect on your epic battle... What challenges did you overcome? What did you learn from this boss fight? How will this victory help you grow?"
                    required
                ></textarea>
            </div>
            <div style="color: #ff6b6b; text-align: center; margin: 10px 0;">
                <i class="fas fa-exclamation-circle"></i> Reflection is required to claim victory
            </div>
            <div class="form-actions">
                <button type="button" class="btn-form btn-cancel-form" onclick="hideFeedbackModal()">
                    <i class="fas fa-times"></i> Cancel
                </button>
                <button type="submit" name="complete_boss" class="btn-form btn-submit-form">
                    <i class="fas fa-trophy"></i> Claim Victory
                </button>
            </div>
        </form>
    </div>

    <script>
        // Initialize todo count for create form
        let todoCount = 1;

        // ================================
        // CREATE BOSS MODAL FUNCTIONS
        // ================================
        function showCreateBossModal() {
            document.getElementById('modalOverlay').classList.add('active');
            document.getElementById('createBossModal').classList.add('active');
            
            // Set minimum datetime for due date
            const now = new Date();
            const minDateTime = now.toISOString().slice(0, 16);
            document.getElementById('due_date').min = minDateTime;
            
            // Reset form
            document.getElementById('questForm').reset();
            
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
        }

        function hideCreateBossModal() {
            document.getElementById('modalOverlay').classList.remove('active');
            document.getElementById('createBossModal').classList.remove('active');
        }

        // ================================
        // BOSS BATTLE MODAL FUNCTIONS
        // ================================
        function showBossBattle(goalId) {
            document.getElementById('modalOverlay').classList.add('active');
            document.getElementById('bossBattleModal-' + goalId).classList.add('active');
        }

        function hideBossBattle(goalId) {
            document.getElementById('modalOverlay').classList.remove('active');
            document.getElementById('bossBattleModal-' + goalId).classList.remove('active');
        }

        // ================================
        // FEEDBACK MODAL FUNCTIONS
        // ================================
        function showFeedbackModal(goalId) {
            document.getElementById('feedbackGoalId').value = goalId;
            document.getElementById('feedbackModal').classList.add('active');
            document.getElementById('modalOverlay').classList.add('active');
        }

        function hideFeedbackModal() {
            document.getElementById('feedbackModal').classList.remove('active');
            document.getElementById('modalOverlay').classList.remove('active');
            document.getElementById('feedback').value = '';
        }

        // ================================
        // TODO SYSTEM FUNCTIONS
        // ================================
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
        }

        function removeTodo(button) {
            if (todoCount > 1) {
                button.parentElement.remove();
                todoCount--;
                updateRemoveButtons();
            }
        }

        function updateRemoveButtons() {
            const removeButtons = document.querySelectorAll('.btn-remove-todo');
            removeButtons.forEach(btn => {
                btn.disabled = todoCount <= 1;
            });
        }

        // ================================
        // EVENT LISTENERS
        // ================================
        
        // Close modal when clicking overlay
        document.getElementById('modalOverlay').addEventListener('click', function() {
            this.classList.remove('active');
            document.querySelectorAll('.create-boss-modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
            document.querySelectorAll('.boss-battle-modal.active').forEach(modal => {
                modal.classList.remove('active');
            });
            hideFeedbackModal();
        });

        // Auto-show boss battle if selected from URL
        <?php if (isset($_GET['selected_goal'])): ?>
            document.addEventListener('DOMContentLoaded', function() {
                showBossBattle(<?php echo $_GET['selected_goal']; ?>);
            });
        <?php endif; ?>

        // Add confirmation for phase completion
        document.querySelectorAll('form[action*="complete_todo"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const damage = this.querySelector('button').getAttribute('title').match(/\d+/)[0];
                if (!confirm(`Complete this boss phase and enable ${damage} damage for your next attack?`)) {
                    e.preventDefault();
                }
            });
        });

        // Add confirmation for boss attack
        document.querySelectorAll('form[action*="attack_boss"]').forEach(form => {
            form.addEventListener('submit', function(e) {
                const damage = this.querySelector('button').textContent.match(/\d+/)[0];
                if (!confirm(`Launch EPIC ATTACK dealing ${damage} damage to the boss?`)) {
                    e.preventDefault();
                }
            });
        });

        // Handle feedback form validation
        document.getElementById('feedbackForm').addEventListener('submit', function(e) {
            const feedback = document.getElementById('feedback').value.trim();
            if (!feedback) {
                e.preventDefault();
                alert('Please provide your victory reflection before defeating the boss!');
                document.getElementById('feedback').focus();
            }
        });

        // Handle create form validation
        document.getElementById('questForm').addEventListener('submit', function(e) {
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
            
            // Auto-refresh page every 5 minutes to update HP and deadlines
            setTimeout(function() {
                location.reload();
            }, 5 * 60 * 1000);
        });

        // Prevent box click when clicking inside the box buttons
        document.querySelectorAll('.enter-btn').forEach(btn => {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
            });
        });
    </script>
</body>
</html>