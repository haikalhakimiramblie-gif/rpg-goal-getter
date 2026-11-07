<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$current_tab = isset($_GET['tab']) ? $_GET['tab'] : 'exp';

// Get leaderboard data based on selected tab
if ($current_tab === 'gold') {
    // Leaderboard by current gold
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.level, 
                u.exp, 
                u.gold,
                u.max_hp,
                u.current_hp,
                COALESCE(us.total_goals_completed, 0) as total_goals_completed,
                COALESCE(us.total_exp_earned, 0) as total_exp_earned,
                COALESCE(us.total_gold_earned, 0) as total_gold_earned,
                COALESCE(us.current_streak, 0) as current_streak,
                COALESCE(us.longest_streak, 0) as longest_streak
            FROM users u 
            LEFT JOIN user_stats us ON u.id = us.user_id 
            ORDER BY u.gold DESC, u.level DESC, u.exp DESC 
            LIMIT 50";
} else {
    // Leaderboard by level and exp (default)
    $sql = "SELECT 
                u.id, 
                u.username, 
                u.level, 
                u.exp, 
                u.gold,
                u.max_hp,
                u.current_hp,
                COALESCE(us.total_goals_completed, 0) as total_goals_completed,
                COALESCE(us.total_exp_earned, 0) as total_exp_earned,
                COALESCE(us.total_gold_earned, 0) as total_gold_earned,
                COALESCE(us.current_streak, 0) as current_streak,
                COALESCE(us.longest_streak, 0) as longest_streak
            FROM users u 
            LEFT JOIN user_stats us ON u.id = us.user_id 
            ORDER BY u.level DESC, u.exp DESC, u.gold DESC 
            LIMIT 50";
}

$stmt = $pdo->prepare($sql);
$stmt->execute();
$leaderboard_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current user's rank
if ($current_tab === 'gold') {
    $rank_sql = "SELECT COUNT(*) as user_rank 
                 FROM users 
                 WHERE gold > (SELECT gold FROM users WHERE id = ?)";
} else {
    $rank_sql = "SELECT COUNT(*) as user_rank 
                 FROM users 
                 WHERE (level > (SELECT level FROM users WHERE id = ?)) 
                 OR (level = (SELECT level FROM users WHERE id = ?) AND exp > (SELECT exp FROM users WHERE id = ?))";
}

$stmt = $pdo->prepare($rank_sql);
if ($current_tab === 'gold') {
    $stmt->execute([$user_id]);
} else {
    $stmt->execute([$user_id, $user_id, $user_id]);
}
$user_rank_result = $stmt->fetch(PDO::FETCH_ASSOC);
$user_rank = $user_rank_result['user_rank'] + 1; // Convert from 0-based to 1-based

// Get current user data
$user_sql = "SELECT 
                u.*,
                COALESCE(us.total_goals_completed, 0) as total_goals_completed,
                COALESCE(us.total_exp_earned, 0) as total_exp_earned,
                COALESCE(us.total_gold_earned, 0) as total_gold_earned,
                COALESCE(us.current_streak, 0) as current_streak,
                COALESCE(us.longest_streak, 0) as longest_streak
            FROM users u 
            LEFT JOIN user_stats us ON u.id = us.user_id 
            WHERE u.id = ?";
$stmt = $pdo->prepare($user_sql);
$stmt->execute([$user_id]);
$current_user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Leaderboard</title>
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
            --rank-1: #ffd700;
            --rank-2: #c0c0c0;
            --rank-3: #cd7f32;
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

        .btn-back:hover {
            background: linear-gradient(135deg, var(--accent-gold-light), var(--accent-gold));
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
            margin-bottom: 10px;
        }

        .header p {
            color: var(--text-secondary);
            font-size: 1.1rem;
        }

        /* Tabs */
        .tabs {
            display: flex;
            justify-content: center;
            margin-bottom: 30px;
            gap: 10px;
            flex-wrap: wrap;
        }

        .tab {
            padding: 15px 30px;
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.8), rgba(25, 10, 25, 0.9));
            border: 2px solid var(--accent-gold-dark);
            border-radius: 10px;
            color: var(--text-primary);
            text-decoration: none;
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .tab:hover {
            border-color: var(--accent-gold);
            transform: translateY(-2px);
        }

        .tab.active {
            background: linear-gradient(135deg, var(--accent-gold), #b8860b);
            color: var(--bg-primary);
            border-color: var(--accent-gold-light);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        /* User Rank Card */
        .user-rank-card {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.9), rgba(25, 10, 25, 0.95));
            border: 2px solid var(--accent-gold);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            text-align: center;
        }

        .user-rank-header {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 1.4rem;
            margin-bottom: 15px;
        }

        .user-rank-info {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .rank-stat {
            text-align: center;
            padding: 15px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
            border: 1px solid var(--accent-gold);
        }

        .rank-stat-value {
            font-family: 'Cinzel', serif;
            font-size: 1.8rem;
            color: var(--accent-gold-light);
            margin-bottom: 5px;
        }

        .rank-stat-label {
            font-size: 0.9rem;
            color: var(--text-primary);
        }

        /* Leaderboard Table */
        .leaderboard-section {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.8), rgba(25, 10, 25, 0.9));
            border: 2px solid var(--accent-gold);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .section-title {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-size: 1.8rem;
            margin-bottom: 25px;
            text-align: center;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 15px;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .leaderboard-table th {
            background: rgba(212, 175, 55, 0.2);
            color: var(--accent-gold);
            padding: 15px;
            text-align: left;
            font-family: 'Cinzel', serif;
            font-size: 1.1rem;
            border-bottom: 2px solid var(--accent-gold);
        }

        .leaderboard-table td {
            padding: 15px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            vertical-align: middle;
        }

        .leaderboard-table tr:hover {
            background: rgba(212, 175, 55, 0.1);
        }

        .leaderboard-table tr.current-user {
            background: rgba(212, 175, 55, 0.15);
            border-left: 4px solid var(--accent-gold);
        }

        /* Rank Styles */
        .rank {
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 1.2rem;
            text-align: center;
            width: 60px;
        }

        .rank-1 {
            color: var(--rank-1);
            text-shadow: 0 0 10px rgba(255, 215, 0, 0.5);
        }

        .rank-2 {
            color: var(--rank-2);
            text-shadow: 0 0 8px rgba(192, 192, 192, 0.4);
        }

        .rank-3 {
            color: var(--rank-3);
            text-shadow: 0 0 6px rgba(205, 127, 50, 0.4);
        }

        .rank-other {
            color: var(--text-primary);
        }

        /* User Info */
        .user-info {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .user-avatar {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent-gold), #b8860b);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            color: var(--bg-primary);
            font-size: 1.2rem;
        }

        .user-details {
            flex: 1;
        }

        .username {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 1.1rem;
            margin-bottom: 5px;
        }

        .user-level {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Stats */
        .stats {
            display: flex;
            gap: 20px;
            justify-content: space-around;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
            padding: 10px 15px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 6px;
            border: 1px solid var(--accent-gold-dark);
            min-width: 100px;
        }

        .stat-value {
            font-family: 'Cinzel', serif;
            font-size: 1.2rem;
            color: var(--accent-gold-light);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            text-transform: uppercase;
        }

        /* Gold specific styles */
        .gold-amount {
            font-family: 'Cinzel', serif;
            color: #ffd700;
            font-size: 1.3rem;
            font-weight: bold;
            text-align: center;
        }

        .gold-icon {
            color: #ffd700;
            margin-right: 5px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 50px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--accent-gold);
            margin-bottom: 20px;
            opacity: 0.5;
        }

        .empty-state h3 {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            margin-bottom: 15px;
            font-size: 1.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .leaderboard-table {
                display: block;
                overflow-x: auto;
            }
            
            .user-rank-info {
                grid-template-columns: 1fr;
            }
            
            .stats {
                flex-direction: column;
                gap: 10px;
            }
            
            .stat-item {
                min-width: auto;
            }
            
            .user-info {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .tabs {
                flex-direction: column;
                align-items: center;
            }
            
            .tab {
                width: 100%;
                max-width: 300px;
                text-align: center;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }

        /* Animation for rank changes */
        @keyframes highlight {
            0% { background-color: rgba(212, 175, 55, 0.3); }
            100% { background-color: rgba(212, 175, 55, 0.15); }
        }

        .leaderboard-table tr.current-user {
            animation: highlight 2s ease-in-out;
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
        </div>

        <div class="header">
            <h1><i class="fas fa-crown"></i> HALL OF HEROES</h1>
            <p>Compete with fellow adventurers and claim your place among the legends!</p>
        </div>

        <!-- Tabs -->
        <div class="tabs">
            <a href="?tab=exp" class="tab <?php echo $current_tab === 'exp' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i> LEVEL & EXP
            </a>
            <a href="?tab=gold" class="tab <?php echo $current_tab === 'gold' ? 'active' : ''; ?>">
                <i class="fas fa-coins"></i> WEALTHIEST HEROES
            </a>
        </div>

        <!-- Current User Rank -->
        <div class="user-rank-card">
            <div class="user-rank-header">
                <i class="fas fa-user"></i> YOUR CURRENT STANDING
            </div>
            <div class="user-rank-info">
                <div class="rank-stat">
                    <div class="rank-stat-value">#<?php echo $user_rank; ?></div>
                    <div class="rank-stat-label">Global Rank</div>
                </div>
                <div class="rank-stat">
                    <div class="rank-stat-value"><?php echo $current_user['level']; ?></div>
                    <div class="rank-stat-label">Level</div>
                </div>
                <div class="rank-stat">
                    <div class="rank-stat-value"><?php echo $current_user['exp']; ?></div>
                    <div class="rank-stat-label">Experience</div>
                </div>
                <div class="rank-stat">
                    <div class="rank-stat-value"><?php echo $current_user['gold']; ?> <i class="fas fa-coins gold-icon"></i></div>
                    <div class="rank-stat-label">Gold</div>
                </div>
                <div class="rank-stat">
                    <div class="rank-stat-value"><?php echo $current_user['total_goals_completed']; ?></div>
                    <div class="rank-stat-label">Quests Completed</div>
                </div>
            </div>
        </div>

        <!-- Leaderboard -->
        <div class="leaderboard-section">
            <div class="section-title">
                <i class="fas fa-trophy"></i>
                <?php echo $current_tab === 'gold' ? 'WEALTHIEST ADVENTURERS' : 'MIGHTIEST HEROES'; ?>
            </div>

            <?php if (!empty($leaderboard_data)): ?>
                <table class="leaderboard-table">
                    <thead>
                        <tr>
                            <th style="width: 80px;">Rank</th>
                            <th>Adventurer</th>
                            <?php if ($current_tab === 'gold'): ?>
                                <th style="width: 150px; text-align: center;">Gold</th>
                                <th style="width: 120px; text-align: center;">Level</th>
                                <th style="width: 120px; text-align: center;">Quests</th>
                            <?php else: ?>
                                <th style="width: 120px; text-align: center;">Level</th>
                                <th style="width: 120px; text-align: center;">EXP</th>
                                <th style="width: 120px; text-align: center;">Gold</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaderboard_data as $index => $user): 
                            $rank = $index + 1;
                            $is_current_user = $user['id'] == $user_id;
                            $rank_class = '';
                            if ($rank === 1) $rank_class = 'rank-1';
                            elseif ($rank === 2) $rank_class = 'rank-2';
                            elseif ($rank === 3) $rank_class = 'rank-3';
                            else $rank_class = 'rank-other';
                            
                            // Get first letter of username for avatar
                            $avatar_letter = strtoupper(substr($user['username'], 0, 1));
                        ?>
                            <tr class="<?php echo $is_current_user ? 'current-user' : ''; ?>">
                                <td class="rank <?php echo $rank_class; ?>">
                                    <?php if ($rank <= 3): ?>
                                        <i class="fas fa-medal"></i>
                                    <?php endif; ?>
                                    <?php echo $rank; ?>
                                </td>
                                <td>
                                    <div class="user-info">
                                        <div class="user-avatar">
                                            <?php echo $avatar_letter; ?>
                                        </div>
                                        <div class="user-details">
                                            <div class="username">
                                                <?php echo htmlspecialchars($user['username']); ?>
                                                <?php if ($is_current_user): ?>
                                                    <small style="color: var(--accent-gold);">(You)</small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="user-level">
                                                <?php if ($current_tab === 'gold'): ?>
                                                    Streak: <?php echo $user['current_streak']; ?> days
                                                <?php else: ?>
                                                    <?php echo $user['total_goals_completed']; ?> quests completed
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <?php if ($current_tab === 'gold'): ?>
                                    <td class="gold-amount">
                                        <i class="fas fa-coins gold-icon"></i>
                                        <?php echo number_format($user['gold']); ?>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $user['level']; ?></div>
                                            <div class="stat-label">Level</div>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $user['total_goals_completed']; ?></div>
                                            <div class="stat-label">Quests</div>
                                        </div>
                                    </td>
                                <?php else: ?>
                                    <td style="text-align: center;">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo $user['level']; ?></div>
                                            <div class="stat-label">Level</div>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo number_format($user['exp']); ?></div>
                                            <div class="stat-label">EXP</div>
                                        </div>
                                    </td>
                                    <td style="text-align: center;">
                                        <div class="stat-item">
                                            <div class="stat-value"><?php echo number_format($user['gold']); ?></div>
                                            <div class="stat-label">Gold</div>
                                        </div>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-users"></i>
                    <h3>No Adventurers Yet</h3>
                    <p>Be the first to embark on an epic journey and claim the top spot!</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Legend -->
        <div style="text-align: center; color: var(--text-secondary); margin-top: 20px;">
            <p><i class="fas fa-info-circle"></i> Showing top 50 adventurers • Updated in real-time</p>
        </div>
    </div>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Add hover effects to table rows
            const tableRows = document.querySelectorAll('.leaderboard-table tr');
            tableRows.forEach(row => {
                row.addEventListener('mouseenter', function() {
                    if (!this.classList.contains('current-user')) {
                        this.style.transform = 'translateX(5px)';
                        this.style.transition = 'transform 0.2s ease';
                    }
                });
                
                row.addEventListener('mouseleave', function() {
                    if (!this.classList.contains('current-user')) {
                        this.style.transform = 'translateX(0)';
                    }
                });
            });

            // Add loading animation to tabs
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(tab => {
                tab.addEventListener('click', function() {
                    tabs.forEach(t => t.classList.remove('active'));
                    this.classList.add('active');
                    
                    // Add loading state
                    const sectionTitle = document.querySelector('.section-title');
                    const originalText = sectionTitle.innerHTML;
                    sectionTitle.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Loading...';
                    
                    setTimeout(() => {
                        sectionTitle.innerHTML = originalText;
                    }, 500);
                });
            });

            // Highlight current user row
            const currentUserRow = document.querySelector('.current-user');
            if (currentUserRow) {
                setTimeout(() => {
                    currentUserRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }, 1000);
            }
        });
    </script>
</body>
</html>