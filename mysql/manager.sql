$sql = "SELECT g.*, 
               COUNT(gt.id) as total_todos,
               SUM(CASE WHEN gt.completed = 1 THEN 1 ELSE 0 END) as completed_todos,
               g.pending_attacks,
               g.total_hp_deducted
        FROM goals g 
        LEFT JOIN goal_todos gt ON g.id = gt.goal_id 
        WHERE g.user_id = ? 
        AND g.failed = 0  // Only show non-failed quests
        GROUP BY g.id 
        ORDER BY g.created_at DESC";