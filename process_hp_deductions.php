<?php
/**
 * HP DEDUCTION AUTOMATION SCRIPT
 * Run this script every minute via cron job
 * Cron command: * * * * * /usr/bin/php /path/to/process_hp_deductions.php
 */

require_once 'config.php';

// Set execution time limit
set_time_limit(300);

// Log function
function logMessage($message) {
    $log_file = __DIR__ . '/cron_logs/hp_deduction_' . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    file_put_contents($log_file, "[$timestamp] $message\n", FILE_APPEND);
}

try {
    logMessage("=== HP Deduction Process Started ===");
    
    // Get current timestamp
    $now = date('Y-m-d H:i:s');
    
    // Find all active goals that need HP deduction
    $sql = "SELECT g.*, u.current_hp, u.max_hp 
            FROM goals g
            JOIN users u ON g.user_id = u.id
            WHERE g.status = 'active' 
            AND g.next_deduction <= ?
            AND g.due_date > ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$now, $now]);
    $goals_to_process = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($goals_to_process) . " goals to process");
    
    foreach ($goals_to_process as $goal) {
        $pdo->beginTransaction();
        
        try {
            $goal_id = $goal['id'];
            $user_id = $goal['user_id'];
            $current_hp = $goal['current_hp'];
            $hp_to_deduct = $goal['hp_deduction_per_interval'];
            $new_hp = max(0, $current_hp - $hp_to_deduct);
            
            // Deduct HP from user
            $update_sql = "UPDATE users SET current_hp = ? WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$new_hp, $user_id]);
            
            // Calculate next deduction time
            $next_deduction = new DateTime($goal['next_deduction']);
            
            switch ($goal['deduction_interval']) {
                case 'minutely':
                    $next_deduction->modify('+1 minute');
                    break;
                case 'hourly':
                    $next_deduction->modify('+1 hour');
                    break;
                case 'daily':
                    $next_deduction->modify('+1 day');
                    break;
            }
            
            // Update goal with next deduction time
            $update_goal_sql = "UPDATE goals SET next_deduction = ? WHERE id = ?";
            $update_goal_stmt = $pdo->prepare($update_goal_sql);
            $update_goal_stmt->execute([$next_deduction->format('Y-m-d H:i:s'), $goal_id]);
            
            // Log the deduction in goal_logs
            $log_sql = "INSERT INTO goal_logs (goal_id, user_id, action, hp_change, timestamp) 
                       VALUES (?, ?, 'hp_deduction', ?, ?)";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([$goal_id, $user_id, -$hp_to_deduct, $now]);
            
            // Log the deduction in system_logs
            $system_log_sql = "INSERT INTO system_logs (user_id, action, details, timestamp) 
                              VALUES (?, 'hp_deduction', ?, ?)";
            $system_log_stmt = $pdo->prepare($system_log_sql);
            $details = "HP deducted for goal '{$goal['title']}': -{$hp_to_deduct} HP (Current: {$new_hp}/{$goal['max_hp']})";
            $system_log_stmt->execute([$user_id, $details, $now]);
            
            $pdo->commit();
            logMessage("Successfully deducted {$hp_to_deduct} HP from user {$user_id} for goal '{$goal['title']}'");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            logMessage("ERROR processing goal {$goal_id}: " . $e->getMessage());
        }
    }
    
    logMessage("=== HP Deduction Process Completed ===\n");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR: " . $e->getMessage());
}

// Now check for expired goals and auto-fail them
try {
    logMessage("=== Checking for Expired Goals ===");
    
    $sql = "SELECT g.*, u.username 
            FROM goals g
            JOIN users u ON g.user_id = u.id
            WHERE g.status = 'active' 
            AND g.due_date <= ?";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$now]);
    $expired_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    logMessage("Found " . count($expired_goals) . " expired goals");
    
    foreach ($expired_goals as $goal) {
        $pdo->beginTransaction();
        
        try {
            $goal_id = $goal['id'];
            $user_id = $goal['user_id'];
            
            // Update goal status to failed
            $update_sql = "UPDATE goals 
                          SET status = 'failed', 
                              completed_at = ?,
                              feedback = 'Quest failed: Time ran out before completion' 
                          WHERE id = ?";
            $update_stmt = $pdo->prepare($update_sql);
            $update_stmt->execute([$now, $goal_id]);
            
            // Check if it's an epic quest (has boss)
            $boss_check = $pdo->prepare("SELECT id FROM bosses WHERE goal_id = ?");
            $boss_check->execute([$goal_id]);
            $has_boss = $boss_check->fetch();
            
            if ($has_boss) {
                // Update boss status
                $update_boss = "UPDATE bosses SET defeated = 0 WHERE goal_id = ?";
                $pdo->prepare($update_boss)->execute([$goal_id]);
            } else {
                // Update monster status
                $update_monster = "UPDATE monsters SET defeated = 0 WHERE goal_id = ?";
                $pdo->prepare($update_monster)->execute([$goal_id]);
            }
            
            // Log the failure
            $log_sql = "INSERT INTO goal_logs (goal_id, user_id, action, details, timestamp) 
                       VALUES (?, ?, 'quest_failed', 'Quest expired without completion', ?)";
            $log_stmt = $pdo->prepare($log_sql);
            $log_stmt->execute([$goal_id, $user_id, $now]);
            
            // System log
            $system_log_sql = "INSERT INTO system_logs (user_id, action, details, timestamp) 
                              VALUES (?, 'quest_failed', ?, ?)";
            $system_log_stmt = $pdo->prepare($system_log_sql);
            $details = "Quest '{$goal['title']}' failed due to time expiration";
            $system_log_stmt->execute([$user_id, $details, $now]);
            
            $pdo->commit();
            logMessage("Quest '{$goal['title']}' (ID: {$goal_id}) marked as failed for user {$user_id}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            logMessage("ERROR failing goal {$goal_id}: " . $e->getMessage());
        }
    }
    
    logMessage("=== Expired Goals Check Completed ===\n");
    
} catch (Exception $e) {
    logMessage("FATAL ERROR in expiry check: " . $e->getMessage());
}
?>