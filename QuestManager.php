<?php
class QuestManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    public function processOverdueQuests($user_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Get overdue quests that haven't been failed yet
            $sql = "SELECT g.*, 
                           u.current_hp as user_current_hp,
                           u.max_hp as user_max_hp,
                           m.monster_hp, m.current_hp as monster_current_hp,
                           b.boss_hp, b.current_hp as boss_current_hp
                    FROM goals g 
                    LEFT JOIN users u ON g.user_id = u.id 
                    LEFT JOIN monsters m ON g.id = m.goal_id 
                    LEFT JOIN bosses b ON g.id = b.goal_id 
                    WHERE g.user_id = ? 
                    AND g.due_date <= NOW() 
                    AND g.failed = 0 
                    AND g.status != 'failed'";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $overdue_quests = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($overdue_quests as $quest) {
                $this->failQuest($quest);
            }
            
            $this->pdo->commit();
            return count($overdue_quests);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    private function failQuest($quest) {
        // Calculate HP deduction
        $hp_to_deduct = $quest['total_hp_to_deduct'] ?? 0;
        
        // Get user's current HP
        $user_sql = "SELECT current_hp, max_hp FROM users WHERE id = ?";
        $user_stmt = $this->pdo->prepare($user_sql);
        $user_stmt->execute([$quest['user_id']]);
        $user_data = $user_stmt->fetch(PDO::FETCH_ASSOC);
        
        $new_hp = max(0, $user_data['current_hp'] - $hp_to_deduct);
        
        // Update user HP
        $update_sql = "UPDATE users SET current_hp = ? WHERE id = ?";
        $update_stmt = $this->pdo->prepare($update_sql);
        $update_stmt->execute([$new_hp, $quest['user_id']]);
        
        // Log the failed quest
        $log_sql = "INSERT INTO goal_logs 
                    (user_id, goal_title, goal_description, category, difficulty, 
                     todo_count, exp_earned, gold_earned, completion_time, feedback, status) 
                    VALUES (?, ?, ?, ?, ?, ?, 0, 0, ?, ?, 'failed')";
        $log_stmt = $this->pdo->prepare($log_sql);
        
        // Calculate time passed in days
        $created_at = new DateTime($quest['created_at']);
        $now = new DateTime();
        $completion_time = $created_at->diff($now)->days;
        
        $auto_feedback = "Quest failed automatically due to missing deadline. HP deducted: " . $hp_to_deduct;
        
        $log_stmt->execute([
            $quest['user_id'],
            $quest['title'],
            $quest['description'],
            $quest['category'],
            $quest['difficulty'],
            $quest['todo_count'],
            $completion_time,
            $auto_feedback
        ]);
        
        // Mark quest as failed
        $fail_sql = "UPDATE goals SET failed = 1, status = 'failed' WHERE id = ?";
        $fail_stmt = $this->pdo->prepare($fail_sql);
        $fail_stmt->execute([$quest['id']]);
        
        // Update user stats for failed quests
        $stats_sql = "INSERT INTO user_stats (user_id, total_quests_failed) 
                      VALUES (?, 1) 
                      ON DUPLICATE KEY UPDATE 
                      total_quests_failed = total_quests_failed + 1";
        $stats_stmt = $this->pdo->prepare($stats_sql);
        $stats_stmt->execute([$quest['user_id']]);
    }
    
    public function processHPDeductions($user_id) {
        try {
            $this->pdo->beginTransaction();
            
            // Get quests that need HP deduction
            $sql = "SELECT g.*, u.current_hp as user_current_hp
                    FROM goals g 
                    LEFT JOIN users u ON g.user_id = u.id 
                    WHERE g.user_id = ? 
                    AND g.due_date > NOW() 
                    AND g.failed = 0 
                    AND g.next_deduction <= NOW() 
                    AND g.hp_deduction_per_interval > 0";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$user_id]);
            $quests_for_deduction = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($quests_for_deduction as $quest) {
                $this->applyHPDeduction($quest);
            }
            
            $this->pdo->commit();
            return count($quests_for_deduction);
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    private function applyHPDeduction($quest) {
        $hp_to_deduct = $quest['hp_deduction_per_interval'];
        
        // Update user HP
        $new_hp = max(0, $quest['user_current_hp'] - $hp_to_deduct);
        
        $update_sql = "UPDATE users SET current_hp = ? WHERE id = ?";
        $update_stmt = $this->pdo->prepare($update_sql);
        $update_stmt->execute([$new_hp, $quest['user_id']]);
        
        // Update next deduction time based on interval
        $next_deduction_sql = "";
        switch ($quest['deduction_interval']) {
            case 'hourly':
                $next_deduction_sql = "NOW() + INTERVAL 1 HOUR";
                break;
            case 'daily':
                $next_deduction_sql = "NOW() + INTERVAL 1 DAY";
                break;
            case 'weekly':
                $next_deduction_sql = "NOW() + INTERVAL 1 WEEK";
                break;
            default:
                $next_deduction_sql = "NOW() + INTERVAL 1 DAY";
        }
        
        $next_sql = "UPDATE goals SET next_deduction = $next_deduction_sql WHERE id = ?";
        $next_stmt = $this->pdo->prepare($next_sql);
        $next_stmt->execute([$quest['id']]);
        
        // Update total HP deducted
        $total_sql = "UPDATE goals SET total_hp_deducted = COALESCE(total_hp_deducted, 0) + ? WHERE id = ?";
        $total_stmt = $this->pdo->prepare($total_sql);
        $total_stmt->execute([$hp_to_deduct, $quest['id']]);
    }
}
?>