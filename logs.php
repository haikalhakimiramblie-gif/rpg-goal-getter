<?php
session_start();
require_once 'config.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Get user's completed goals from goal_logs
$sql = "SELECT 
            COUNT(*) as total_goals_completed,
            COALESCE(SUM(exp_earned), 0) as total_exp_earned,
            COALESCE(SUM(gold_earned), 0) as total_gold_earned,
            COUNT(*) as logged_goals
        FROM goal_logs 
        WHERE user_id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$user_stats = $stmt->fetch(PDO::FETCH_ASSOC);

// Get individual completed goals from goal_logs
$sql = "SELECT * FROM goal_logs WHERE user_id = ? ORDER BY completed_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([$user_id]);
$completed_goals = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Adventure Logs</title>
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
            margin-bottom: 20px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.8), rgba(25, 10, 25, 0.9));
            border: 2px solid var(--accent-gold);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }

        .stat-icon {
            font-size: 2.5rem;
            color: var(--accent-gold);
            margin-bottom: 15px;
        }

        .stat-value {
            font-family: 'Cinzel', serif;
            font-size: 2rem;
            color: var(--accent-gold-light);
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 1rem;
            color: var(--text-primary);
        }

        .logs-section {
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

        .logs-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 25px;
        }

        .log-card {
            background: linear-gradient(135deg, rgba(60, 30, 60, 0.6), rgba(40, 20, 40, 0.7));
            border: 2px solid var(--accent-gold-dark);
            border-radius: 12px;
            padding: 25px;
            cursor: pointer;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .log-card:hover {
            border-color: var(--accent-gold);
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(212, 175, 55, 0.3);
        }

        .log-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transition: left 0.5s ease;
        }

        .log-card:hover::before {
            left: 100%;
        }

        .log-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 15px;
        }

        .log-title {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 1.3rem;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .difficulty-badge {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            text-transform: uppercase;
            white-space: nowrap;
        }

        .difficulty-easy {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
            border: 1px solid #00ff00;
        }

        .difficulty-medium {
            background: rgba(255, 165, 0, 0.2);
            color: #ffa500;
            border: 1px solid #ffa500;
        }

        .difficulty-hard {
            background: rgba(255, 0, 0, 0.2);
            color: #ff0000;
            border: 1px solid #ff0000;
        }

        .difficulty-epic {
            background: rgba(128, 0, 128, 0.2);
            color: #800080;
            border: 1px solid #800080;
        }

        .log-description {
            color: var(--text-primary);
            line-height: 1.5;
            margin-bottom: 20px;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .log-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding: 12px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
            border: 1px solid var(--accent-gold-dark);
        }

        .meta-item {
            text-align: center;
            flex: 1;
        }

        .meta-label {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-bottom: 5px;
            text-transform: uppercase;
        }

        .meta-value {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 1rem;
        }

        .log-rewards {
            display: flex;
            gap: 15px;
            justify-content: center;
        }

        .reward {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 8px 15px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 6px;
            border: 1px solid var(--accent-gold-dark);
            font-size: 0.9rem;
        }

        .xp-reward {
            color: var(--accent-gold-light);
        }

        .gold-reward {
            color: #ffd700;
        }

        .completion-date {
            text-align: center;
            color: var(--text-secondary);
            font-size: 0.9rem;
            margin-top: 15px;
            padding-top: 10px;
            border-top: 1px solid rgba(212, 175, 55, 0.3);
        }

        /* Detail Modal */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.8);
            z-index: 1000;
            backdrop-filter: blur(5px);
        }

        .modal-overlay.active {
            display: flex;
            justify-content: center;
            align-items: center;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .detail-modal {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.95), rgba(25, 10, 25, 0.98));
            border: 3px solid var(--accent-gold);
            border-radius: 20px;
            padding: 30px;
            max-width: 800px;
            width: 95%;
            max-height: 90vh;
            overflow-y: auto;
            position: relative;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from { transform: scale(0.9); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid var(--accent-gold);
        }

        .modal-title {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 1.8rem;
            margin-bottom: 10px;
            line-height: 1.3;
        }

        .close-btn {
            background: none;
            border: none;
            color: var(--accent-gold);
            font-size: 2rem;
            cursor: pointer;
            padding: 5px 10px;
            transition: all 0.3s ease;
            margin-left: 20px;
        }

        .close-btn:hover {
            color: var(--accent-gold-light);
            transform: scale(1.1);
        }

        .modal-description {
            color: var(--text-primary);
            line-height: 1.6;
            margin-bottom: 25px;
            font-size: 1.1rem;
        }

        .modal-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-item-large {
            text-align: center;
            padding: 15px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 8px;
            border: 1px solid var(--accent-gold);
        }

        .stat-label-large {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
        }

        .stat-value-large {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold-light);
            font-size: 1.3rem;
        }

        .modal-rewards {
            display: flex;
            gap: 20px;
            justify-content: center;
            margin-bottom: 25px;
        }

        .reward-large {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 15px 25px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 10px;
            border: 2px solid var(--accent-gold);
            font-size: 1.2rem;
            font-family: 'Cinzel', serif;
        }

        .modal-feedback {
            margin-top: 25px;
            padding: 20px;
            background: rgba(212, 175, 55, 0.05);
            border-radius: 10px;
            border-left: 4px solid var(--accent-gold);
        }

        .feedback-label {
            font-family: 'Cinzel', serif;
            color: var(--accent-gold);
            font-size: 1.2rem;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .feedback-content {
            color: var(--text-primary);
            font-size: 1rem;
            line-height: 1.6;
            font-style: italic;
        }

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

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .logs-grid {
                grid-template-columns: 1fr;
            }
            
            .log-header {
                flex-direction: column;
                gap: 10px;
            }
            
            .log-meta {
                flex-direction: column;
                gap: 10px;
            }
            
            .meta-item {
                width: 100%;
            }
            
            .log-rewards {
                flex-direction: column;
            }
            
            .modal-stats {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .modal-rewards {
                flex-direction: column;
                align-items: center;
            }
            
            .detail-modal {
                padding: 20px;
                margin: 20px;
            }
            
            .header h1 {
                font-size: 2rem;
            }
        }

        @media (max-width: 480px) {
            .modal-stats {
                grid-template-columns: 1fr;
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
        </div>

        <div class="header">
            <h1><i class="fas fa-scroll"></i> ADVENTURE LOGS</h1>
            <p>Click on any quest to view detailed information about your completed adventures!</p>
        </div>

        <!-- Statistics Overview -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-trophy"></i>
                </div>
                <div class="stat-value"><?php echo $user_stats['total_goals_completed'] ?? 0; ?></div>
                <div class="stat-label">Quests Completed</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-star"></i>
                </div>
                <div class="stat-value"><?php echo $user_stats['total_exp_earned'] ?? 0; ?></div>
                <div class="stat-label">Total Experience</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-coins"></i>
                </div>
                <div class="stat-value"><?php echo $user_stats['total_gold_earned'] ?? 0; ?></div>
                <div class="stat-label">Gold Collected</div>
            </div>
            
            <div class="stat-card">
                <div class="stat-icon">
                    <i class="fas fa-history"></i>
                </div>
                <div class="stat-value"><?php echo $user_stats['logged_goals'] ?? 0; ?></div>
                <div class="stat-label">Logged Adventures</div>
            </div>
        </div>

        <!-- Completed Goals Logs -->
        <div class="logs-section">
            <div class="section-title">
                <i class="fas fa-book-open"></i>
                COMPLETED QUEST ARCHIVES
            </div>

            <?php if (!empty($completed_goals)): ?>
                <div class="logs-grid">
                    <?php foreach ($completed_goals as $goal): ?>
                        <div class="log-card" onclick="showQuestDetails(<?php echo htmlspecialchars(json_encode($goal)); ?>)">
                            <div class="log-header">
                                <div class="log-title">
                                    <?php echo htmlspecialchars($goal['goal_title']); ?>
                                </div>
                                <span class="difficulty-badge difficulty-<?php echo $goal['difficulty']; ?>">
                                    <?php echo ucfirst($goal['difficulty']); ?>
                                </span>
                            </div>
                            
                            <div class="log-description">
                                <?php echo htmlspecialchars($goal['goal_description']); ?>
                            </div>

                            <div class="log-meta">
                                <div class="meta-item">
                                    <div class="meta-label">Category</div>
                                    <div class="meta-value">
                                        <?php echo !empty($goal['category']) ? ucfirst($goal['category']) : 'General'; ?>
                                    </div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Tasks</div>
                                    <div class="meta-value"><?php echo $goal['todo_count']; ?></div>
                                </div>
                                <div class="meta-item">
                                    <div class="meta-label">Time</div>
                                    <div class="meta-value">
                                        <?php echo $goal['completion_time'] ? $goal['completion_time'] . 'd' : '<1d'; ?>
                                    </div>
                                </div>
                            </div>

                            <div class="log-rewards">
                                <div class="reward xp-reward">
                                    <i class="fas fa-star"></i>
                                    +<?php echo $goal['exp_earned']; ?> XP
                                </div>
                                <?php if ($goal['gold_earned'] > 0): ?>
                                <div class="reward gold-reward">
                                    <i class="fas fa-coins"></i>
                                    +<?php echo $goal['gold_earned']; ?> Gold
                                </div>
                                <?php endif; ?>
                            </div>

                            <div class="completion-date">
                                Completed on <?php echo date('M j, Y', strtotime($goal['completed_at'])); ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-scroll"></i>
                    <h3>No Completed Quests Yet</h3>
                    <p>Your adventure logs are empty. Complete some quests to start building your legend!</p>
                    <a href="create_goal.php" class="btn-back" style="margin-top: 20px; display: inline-block;">
                        <i class="fas fa-plus"></i> CREATE YOUR FIRST QUEST
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Detail Modal -->
    <div class="modal-overlay" id="modalOverlay">
        <div class="detail-modal" id="detailModal">
            <div class="modal-header">
                <div style="flex: 1;">
                    <h2 class="modal-title" id="modalTitle"></h2>
                    <span class="difficulty-badge" id="modalDifficulty"></span>
                </div>
                <button class="close-btn" onclick="hideQuestDetails()">&times;</button>
            </div>
            
            <div class="modal-description" id="modalDescription"></div>
            
            <div class="modal-stats">
                <div class="stat-item-large">
                    <div class="stat-label-large">Category</div>
                    <div class="stat-value-large" id="modalCategory"></div>
                </div>
                <div class="stat-item-large">
                    <div class="stat-label-large">Tasks Completed</div>
                    <div class="stat-value-large" id="modalTasks"></div>
                </div>
                <div class="stat-item-large">
                    <div class="stat-label-large">Completion Time</div>
                    <div class="stat-value-large" id="modalTime"></div>
                </div>
                <div class="stat-item-large">
                    <div class="stat-label-large">Completed On</div>
                    <div class="stat-value-large" id="modalDate"></div>
                </div>
            </div>

            <div class="modal-rewards">
                <div class="reward-large xp-reward">
                    <i class="fas fa-star"></i>
                    <span id="modalXP">+0 XP</span>
                </div>
                <div class="reward-large gold-reward" id="modalGoldContainer">
                    <i class="fas fa-coins"></i>
                    <span id="modalGold">+0 Gold</span>
                </div>
            </div>

            <div class="modal-feedback" id="modalFeedback">
                <div class="feedback-label">
                    <i class="fas fa-pen"></i> Your Reflection
                </div>
                <div class="feedback-content" id="modalFeedbackContent"></div>
            </div>
        </div>
    </div>

    <script>
        let currentQuest = null;

        function showQuestDetails(quest) {
            currentQuest = quest;
            
            // Populate modal with quest data
            document.getElementById('modalTitle').textContent = quest.goal_title;
            document.getElementById('modalDescription').textContent = quest.goal_description;
            document.getElementById('modalCategory').textContent = quest.category ? quest.category.charAt(0).toUpperCase() + quest.category.slice(1) : 'General';
            document.getElementById('modalTasks').textContent = quest.todo_count;
            document.getElementById('modalTime').textContent = quest.completion_time ? quest.completion_time + ' days' : 'Same day';
            document.getElementById('modalDate').textContent = new Date(quest.completed_at).toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });
            document.getElementById('modalXP').textContent = '+' + quest.exp_earned + ' XP';
            
            // Update difficulty badge
            const difficultyBadge = document.getElementById('modalDifficulty');
            difficultyBadge.textContent = quest.difficulty.charAt(0).toUpperCase() + quest.difficulty.slice(1);
            difficultyBadge.className = 'difficulty-badge difficulty-' + quest.difficulty;
            
            // Handle gold reward
            const goldContainer = document.getElementById('modalGoldContainer');
            const goldElement = document.getElementById('modalGold');
            if (quest.gold_earned > 0) {
                goldElement.textContent = '+' + quest.gold_earned + ' Gold';
                goldContainer.style.display = 'flex';
            } else {
                goldContainer.style.display = 'none';
            }
            
            // Handle feedback
            const feedbackSection = document.getElementById('modalFeedback');
            const feedbackContent = document.getElementById('modalFeedbackContent');
            if (quest.feedback && quest.feedback.trim() !== '') {
                feedbackContent.textContent = quest.feedback;
                feedbackSection.style.display = 'block';
            } else {
                feedbackSection.style.display = 'none';
            }
            
            // Show modal
            document.getElementById('modalOverlay').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function hideQuestDetails() {
            document.getElementById('modalOverlay').classList.remove('active');
            document.body.style.overflow = 'auto';
            currentQuest = null;
        }

        // Close modal when clicking outside
        document.getElementById('modalOverlay').addEventListener('click', function(e) {
            if (e.target === this) {
                hideQuestDetails();
            }
        });

        // Close modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape' && document.getElementById('modalOverlay').classList.contains('active')) {
                hideQuestDetails();
            }
        });

        // Add animations to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.log-card');
            cards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    card.style.transition = 'all 0.5s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });

        // Prevent modal from closing when clicking inside modal
        document.getElementById('detailModal').addEventListener('click', function(e) {
            e.stopPropagation();
        });
    </script>
</body>
</html>