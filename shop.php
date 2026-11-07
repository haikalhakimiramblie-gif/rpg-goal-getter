<?php
// PHP code for shop - MUST BE AT THE VERY TOP
session_start();

// Check if user is logged in, if not redirect to login
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Database connection
require_once 'config.php';

// Get user data from session
$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Initialize variables
$message = '';
$message_type = ''; // success, error, info

// Fetch user's data including gold, level, and XP
try {
    $stmt = $pdo->prepare("SELECT gold, current_hp, max_hp, level, exp FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user_data) {
        throw new Exception("User not found!");
    }
    
    $user_gold = $user_data['gold'];
    $current_hp = $user_data['current_hp'];
    $max_hp = $user_data['max_hp'];
    $user_level = $user_data['level'];
    $user_xp = $user_data['exp'];
    
    // Fetch available shop items
    $items_stmt = $pdo->prepare("SELECT * FROM shop_items WHERE available = TRUE ORDER BY price ASC");
    $items_stmt->execute();
    $shop_items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch user's inventory
    $inv_stmt = $pdo->prepare("
        SELECT si.*, ui.quantity 
        FROM user_inventory ui 
        JOIN shop_items si ON ui.item_id = si.id 
        WHERE ui.user_id = ?
    ");
    $inv_stmt->execute([$user_id]);
    $user_inventory = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    die("Error loading shop: " . $e->getMessage());
}

// Handle item purchase
if (isset($_POST['purchase_item'])) {
    $item_id = $_POST['item_id'];
    
    try {
        // Get item details
        $stmt = $pdo->prepare("SELECT * FROM shop_items WHERE id = ? AND available = TRUE");
        $stmt->execute([$item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item) {
            throw new Exception("Item not available!");
        }
        
        // Check if user has enough gold
        if ($user_gold < $item['price']) {
            throw new Exception("Not enough gold! You need " . $item['price'] . " gold, but you only have " . $user_gold . " gold.");
        }
        
        // Start transaction
        $pdo->beginTransaction();
        
        // Deduct gold from user
        $stmt = $pdo->prepare("UPDATE users SET gold = gold - ? WHERE id = ?");
        $stmt->execute([$item['price'], $user_id]);
        
        // Check if user already has this item
        $stmt = $pdo->prepare("SELECT * FROM user_inventory WHERE user_id = ? AND item_id = ?");
        $stmt->execute([$user_id, $item_id]);
        $existing_item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($existing_item) {
            // Update quantity
            $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = quantity + 1 WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$user_id, $item_id]);
        } else {
            // Add new item to inventory
            $stmt = $pdo->prepare("INSERT INTO user_inventory (user_id, item_id, quantity) VALUES (?, ?, 1)");
            $stmt->execute([$user_id, $item_id]);
        }
        
        // Apply item effect immediately if it's a potion
        $effect_message = "";
        if ($item['item_type'] == 'potion') {
            switch($item['effect_type']) {
                case 'heal':
                    $new_hp = min($max_hp, $current_hp + $item['effect_value']);
                    $hp_restored = $new_hp - $current_hp;
                    
                    // Update HP
                    $stmt = $pdo->prepare("UPDATE users SET current_hp = ? WHERE id = ?");
                    $stmt->execute([$new_hp, $user_id]);
                    
                    $effect_message = " and restored " . $hp_restored . " HP!";
                    $current_hp = $new_hp; // Update local variable for display
                    break;
                    
                case 'xp_boost':
                    // Add XP to user
                    $new_xp = $user_xp + $item['effect_value'];
                    $stmt = $pdo->prepare("UPDATE users SET exp = ? WHERE id = ?");
                    $stmt->execute([$new_xp, $user_id]);
                    
                    $effect_message = " and gained " . $item['effect_value'] . " XP!";
                    $user_xp = $new_xp; // Update local variable
                    break;
            }
        }
        
        $pdo->commit();
        
        // Update user gold for display
        $user_gold -= $item['price'];
        
        $message = "Successfully purchased " . $item['name'] . " for " . $item['price'] . " gold" . $effect_message;
        $message_type = "success";
        
        // Refresh inventory
        $inv_stmt->execute([$user_id]);
        $user_inventory = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $message = "Purchase failed: " . $e->getMessage();
        $message_type = "error";
    }
}

// Handle item use
if (isset($_POST['use_item'])) {
    $item_id = $_POST['item_id'];
    
    try {
        // Check if user has the item
        $stmt = $pdo->prepare("
            SELECT ui.*, si.* 
            FROM user_inventory ui 
            JOIN shop_items si ON ui.item_id = si.id 
            WHERE ui.user_id = ? AND ui.item_id = ?
        ");
        $stmt->execute([$user_id, $item_id]);
        $item = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$item || $item['quantity'] <= 0) {
            throw new Exception("You don't have this item!");
        }
        
        // Apply item effect based on type
        switch ($item['effect_type']) {
            case 'heal':
                $new_hp = min($max_hp, $current_hp + $item['effect_value']);
                $hp_restored = $new_hp - $current_hp;
                
                // Update HP
                $stmt = $pdo->prepare("UPDATE users SET current_hp = ? WHERE id = ?");
                $stmt->execute([$new_hp, $user_id]);
                
                $effect_message = "Restored " . $hp_restored . " HP!";
                $current_hp = $new_hp; // Update local variable
                break;
                
            case 'xp_boost':
                // Add XP to user
                $new_xp = $user_xp + $item['effect_value'];
                $stmt = $pdo->prepare("UPDATE users SET exp = ? WHERE id = ?");
                $stmt->execute([$new_xp, $user_id]);
                
                $effect_message = "Gained " . $item['effect_value'] . " XP!";
                $user_xp = $new_xp; // Update local variable
                break;
                
            case 'strength':
                // This would be a temporary boost - for now just show message
                $effect_message = "Gained " . $item['effect_value'] . " temporary strength!";
                break;
                
            default:
                $effect_message = "Item used!";
        }
        
        // Reduce item quantity or remove if last one
        if ($item['quantity'] > 1) {
            $stmt = $pdo->prepare("UPDATE user_inventory SET quantity = quantity - 1 WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$user_id, $item_id]);
        } else {
            $stmt = $pdo->prepare("DELETE FROM user_inventory WHERE user_id = ? AND item_id = ?");
            $stmt->execute([$user_id, $item_id]);
        }
        
        $message = "Used " . $item['name'] . " - " . $effect_message;
        $message_type = "success";
        
        // Refresh inventory
        $inv_stmt->execute([$user_id]);
        $user_inventory = $inv_stmt->fetchAll(PDO::FETCH_ASSOC);
        
    } catch (Exception $e) {
        $message = "Failed to use item: " . $e->getMessage();
        $message_type = "error";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RPG Goal Getter - Mystic Shop</title>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=EB+Garamond:wght@400;500;600&family=MedievalSharp&family=UnifrakturMaguntia&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* Base Styles */
        :root {
            --bg-primary: #0a050a;
            --bg-secondary: #1a0f1a;
            --accent-gold: #d4af37;
            --accent-gold-light: #f0e68c;
            --accent-gold-dark: #8b6914;
            --text-primary: #e6d6b3;
            --text-secondary: #b8860b;
            --text-dark: #2c1810;
            --text-light: #fff8dc;
            --success-green: #2e8b57;
            --error-red: #8b0000;
            --info-blue: #4169e1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background: radial-gradient(ellipse at top, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: 'EB Garamond', serif;
            transition: background 2s ease;
        }

        /* Header */
        .shop-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.7) 0%, rgba(25, 10, 25, 0.8) 100%);
            border-bottom: 2px solid var(--accent-gold-dark);
            backdrop-filter: blur(10px);
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .logo h1 {
            font-size: 1.8rem;
            color: var(--accent-gold);
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light), var(--accent-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Cinzel', serif;
            letter-spacing: 2px;
        }

        .user-info {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .gold-display {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(212, 175, 55, 0.2);
            padding: 8px 15px;
            border-radius: 20px;
            border: 1px solid var(--accent-gold);
        }

        .gold-display i {
            color: var(--accent-gold);
        }

        .gold-amount {
            font-weight: bold;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 2px;
            font-size: 0.8rem;
            transition: all 0.3s ease;
            font-family: 'Cinzel', serif;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-back {
            background: linear-gradient(135deg, rgba(139, 105, 20, 0.7), rgba(101, 67, 33, 0.8));
            color: var(--text-light);
            border: 1px solid var(--accent-gold);
        }

        .btn-back:hover {
            background: linear-gradient(135deg, rgba(184, 134, 11, 0.8), rgba(139, 105, 20, 0.9));
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.4);
        }

        /* Shop Content */
        .shop-content {
            padding: 40px 5%;
            max-width: 1400px;
            margin: 0 auto;
        }

        /* Message Display */
        .message {
            padding: 15px 20px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-family: 'Cinzel', serif;
            text-align: center;
            border: 1px solid;
        }

        .message-success {
            background: rgba(46, 139, 87, 0.2);
            color: #90ee90;
            border-color: var(--success-green);
        }

        .message-error {
            background: rgba(139, 0, 0, 0.2);
            color: #ff6b6b;
            border-color: var(--error-red);
        }

        .message-info {
            background: rgba(65, 105, 225, 0.2);
            color: #87cefa;
            border-color: var(--info-blue);
        }

        /* Shop Layout */
        .shop-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
        }

        @media (max-width: 1024px) {
            .shop-layout {
                grid-template-columns: 1fr;
            }
        }

        /* Section Styling */
        .shop-section {
            background: linear-gradient(135deg, rgba(50, 25, 50, 0.7) 0%, rgba(30, 15, 30, 0.8) 100%);
            border: 2px solid var(--accent-gold-dark);
            border-radius: 10px;
            padding: 30px;
            backdrop-filter: blur(10px);
            margin-bottom: 30px;
        }

        .section-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
            padding-bottom: 15px;
        }

        .section-header h2 {
            font-size: 1.5rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
        }

        /* Items Grid */
        .items-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
            gap: 20px;
        }

        .item-card {
            background: linear-gradient(135deg, rgba(60, 30, 60, 0.6) 0%, rgba(40, 20, 40, 0.7) 100%);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
            height: 100%;
        }

        .item-card:hover {
            transform: translateY(-5px);
            border-color: var(--accent-gold);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.3);
        }

        .item-icon {
            font-size: 3rem;
            margin-bottom: 15px;
            height: 80px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-name {
            font-size: 1.2rem;
            color: var(--text-light);
            margin-bottom: 10px;
            font-family: 'Cinzel', serif;
            min-height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .item-description {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 15px;
            flex-grow: 1;
        }

        .item-effect {
            font-size: 0.85rem;
            color: var(--accent-gold-light);
            margin-bottom: 15px;
            padding: 8px;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 5px;
        }

        .item-price {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            font-size: 1.1rem;
            color: var(--accent-gold-light);
            margin-bottom: 15px;
            font-family: 'Cinzel', serif;
        }

        .item-actions {
            margin-top: auto;
        }

        .btn-purchase {
            background: linear-gradient(135deg, rgba(46, 139, 87, 0.7), rgba(34, 102, 64, 0.8));
            color: var(--text-light);
            border: 1px solid #2e8b57;
            width: 100%;
            padding: 12px;
        }

        .btn-purchase:hover {
            background: linear-gradient(135deg, rgba(60, 179, 113, 0.8), rgba(46, 139, 87, 0.9));
        }

        .btn-purchase:disabled {
            background: rgba(128, 128, 128, 0.5);
            color: var(--text-secondary);
            border-color: #666;
            cursor: not-allowed;
            transform: none;
        }

        .btn-use {
            background: linear-gradient(135deg, rgba(65, 105, 225, 0.7), rgba(48, 80, 180, 0.8));
            color: var(--text-light);
            border: 1px solid #4169e1;
            width: 100%;
            padding: 12px;
        }

        .btn-use:hover {
            background: linear-gradient(135deg, rgba(80, 120, 255, 0.8), rgba(65, 105, 225, 0.9));
        }

        /* Inventory List */
        .inventory-list {
            display: flex;
            flex-direction: column;
            gap: 15px;
        }

        .inventory-item {
            background: linear-gradient(135deg, rgba(60, 30, 60, 0.6) 0%, rgba(40, 20, 40, 0.7) 100%);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: all 0.3s ease;
        }

        .inventory-item:hover {
            transform: translateX(5px);
            border-color: var(--accent-gold);
        }

        .item-info {
            flex: 1;
        }

        .item-name-small {
            font-size: 1rem;
            color: var(--text-light);
            margin-bottom: 5px;
            font-family: 'Cinzel', serif;
        }

        .item-quantity {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        /* Empty States */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: var(--text-secondary);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 15px;
            color: var(--accent-gold-dark);
        }

        /* Footer */
        .shop-footer {
            text-align: center;
            padding: 25px 5%;
            background: linear-gradient(135deg, rgba(25, 10, 25, 0.7) 0%, rgba(15, 5, 15, 0.8) 100%);
            border-top: 1px solid var(--accent-gold-dark);
            backdrop-filter: blur(5px);
            margin-top: 50px;
        }

        .shop-footer p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: 'Cinzel', serif;
            letter-spacing: 1px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .shop-header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .user-info {
                flex-direction: column;
                gap: 10px;
            }

            .items-grid {
                grid-template-columns: 1fr;
            }
            
            .inventory-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 10px;
            }
            
            .item-actions {
                align-self: stretch;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="shop-header">
        <div class="logo">
            <h1>RPG Goal Getter - Mystic Shop</h1>
        </div>
        <div class="user-info">
            <div class="gold-display">
                <i class="fas fa-coins"></i>
                <span class="gold-amount"><?php echo $user_gold; ?> Gold</span>
            </div>
            <a href="dashboard.php" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> BACK TO DASHBOARD
            </a>
        </div>
    </header>

    <!-- Shop Content -->
    <main class="shop-content">
        <!-- Message Display -->
        <?php if (!empty($message)): ?>
            <div class="message message-<?php echo $message_type; ?>">
                <i class="fas fa-<?php echo $message_type == 'success' ? 'check-circle' : ($message_type == 'error' ? 'exclamation-triangle' : 'info-circle'); ?>"></i>
                <?php echo $message; ?>
            </div>
        <?php endif; ?>

        <!-- Shop Layout -->
        <div class="shop-layout">
            <!-- Left Column - Shop Items -->
            <div class="left-column">
                <section class="shop-section">
                    <div class="section-header">
                        <h2><i class="fas fa-store"></i> MYSTIC SHOP ITEMS</h2>
                    </div>
                    
                    <?php if (empty($shop_items)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>The Shop is Empty</h3>
                            <p>Check back later for new magical items!</p>
                        </div>
                    <?php else: ?>
                        <div class="items-grid">
                            <?php foreach($shop_items as $item): ?>
                            <div class="item-card">
                                <div class="item-icon">
                                    <?php 
                                    // Determine icon based on item type and effect
                                    if ($item['effect_type'] == 'xp_boost') {
                                        echo '<i class="fas fa-star" style="color: #ffd700;"></i>';
                                    } else {
                                        switch($item['item_type']) {
                                            case 'potion':
                                                echo '<i class="fas fa-flask" style="color: #ff6b6b;"></i>';
                                                break;
                                            case 'weapon':
                                                echo '<i class="fas fa-gavel" style="color: #d4af37;"></i>';
                                                break;
                                            case 'armor':
                                                echo '<i class="fas fa-shield-alt" style="color: #4169e1;"></i>';
                                                break;
                                            default:
                                                echo '<i class="fas fa-gem" style="color: #9370db;"></i>';
                                        }
                                    }
                                    ?>
                                </div>
                                <div class="item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                                <div class="item-description"><?php echo htmlspecialchars($item['description']); ?></div>
                                <div class="item-effect">
                                    <strong>Effect:</strong> 
                                    <?php 
                                    switch($item['effect_type']) {
                                        case 'heal':
                                            echo "Restores " . $item['effect_value'] . " HP";
                                            break;
                                        case 'xp_boost':
                                            echo "Grants " . $item['effect_value'] . " Experience Points";
                                            break;
                                        case 'strength':
                                            echo "+" . $item['effect_value'] . " Strength for 1 hour";
                                            break;
                                        case 'defense':
                                            echo "+" . $item['effect_value'] . " Defense for 1 hour";
                                            break;
                                        default:
                                            echo "Mystical effect";
                                    }
                                    ?>
                                </div>
                                <div class="item-price">
                                    <i class="fas fa-coins"></i>
                                    <span><?php echo $item['price']; ?> Gold</span>
                                </div>
                                <div class="item-actions">
                                    <form method="POST">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="purchase_item" class="btn btn-purchase" 
                                                <?php echo ($user_gold < $item['price']) ? 'disabled' : ''; ?>>
                                            <?php if ($user_gold < $item['price']): ?>
                                                <i class="fas fa-lock"></i> NEED <?php echo $item['price'] - $user_gold; ?> MORE GOLD
                                            <?php else: ?>
                                                <i class="fas fa-shopping-cart"></i> PURCHASE
                                            <?php endif; ?>
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </div>

            <!-- Right Column - Inventory & Stats -->
            <div class="right-column">
                <section class="shop-section">
                    <div class="section-header">
                        <h2><i class="fas fa-backpack"></i> YOUR INVENTORY</h2>
                    </div>
                    
                    <?php if (empty($user_inventory)): ?>
                        <div class="empty-state">
                            <i class="fas fa-box-open"></i>
                            <h3>Inventory Empty</h3>
                            <p>Purchase items from the shop to fill your inventory!</p>
                        </div>
                    <?php else: ?>
                        <div class="inventory-list">
                            <?php foreach($user_inventory as $item): ?>
                            <div class="inventory-item">
                                <div class="item-info">
                                    <div class="item-name-small"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="item-quantity">Quantity: <?php echo $item['quantity']; ?></div>
                                </div>
                                <div class="item-actions">
                                    <form method="POST">
                                        <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                        <button type="submit" name="use_item" class="btn btn-use">
                                            <i class="fas fa-potion"></i> USE
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <!-- Player Stats -->
                <section class="shop-section">
                    <div class="section-header">
                        <h2><i class="fas fa-user"></i> YOUR STATS</h2>
                    </div>
                    <div style="color: var(--text-secondary);">
                        <p><strong>Level:</strong> <?php echo $user_level; ?></p>
                        <p><strong>Experience:</strong> <?php echo $user_xp; ?> XP</p>
                        <p><strong>Health:</strong> <?php echo $current_hp; ?>/<?php echo $max_hp; ?> HP</p>
                        <p><strong>Gold:</strong> <?php echo $user_gold; ?></p>
                        <div style="margin-top: 10px; height: 8px; background: rgba(139, 0, 0, 0.3); border-radius: 4px; overflow: hidden;">
                            <div style="height: 100%; background: linear-gradient(90deg, #8b0000, #ff0000, #ff6b6b); width: <?php echo ($current_hp / $max_hp) * 100; ?>%;"></div>
                        </div>
                    </div>
                </section>

                <!-- Shop Info -->
                <section class="shop-section">
                    <div class="section-header">
                        <h2><i class="fas fa-info-circle"></i> SHOP INFORMATION</h2>
                    </div>
                    <div style="color: var(--text-secondary);">
                        <p><strong>Available Items:</strong></p>
                        <ul style="margin-left: 20px; margin-top: 10px;">
                            <li>Healing Potions - Restore HP</li>
                            <li>Experience Elixirs - Grant XP</li>
                            <li>Strength Potions - Boost strength</li>
                            <li>Weapons & Armor - Enhance abilities</li>
                        </ul>
                        <p style="margin-top: 15px;"><strong>Tip:</strong> Use health potions when low on HP, and XP potions to level up faster!</p>
                    </div>
                </section>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="shop-footer">
        <p><i class="fas fa-scroll"></i> © 2023 RPG GOAL GETTER - MYSTIC SHOP <i class="fas fa-dragon"></i></p>
    </footer>

    <script>
        // Add some interactive effects
        document.addEventListener('DOMContentLoaded', function() {
            // Animate items on page load
            const items = document.querySelectorAll('.item-card');
            items.forEach((item, index) => {
                item.style.opacity = '0';
                item.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    item.style.transition = 'all 0.5s ease';
                    item.style.opacity = '1';
                    item.style.transform = 'translateY(0)';
                }, index * 100);
            });
            
            // Add purchase confirmation
            const purchaseButtons = document.querySelectorAll('.btn-purchase:not(:disabled)');
            purchaseButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const itemName = this.closest('.item-card').querySelector('.item-name').textContent;
                    const itemPrice = this.closest('.item-card').querySelector('.item-price span').textContent;
                    if (!confirm(`Are you sure you want to purchase ${itemName} for ${itemPrice}?`)) {
                        e.preventDefault();
                    }
                });
            });
            
            // Add use confirmation
            const useButtons = document.querySelectorAll('.btn-use');
            useButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    const itemName = this.closest('.inventory-item').querySelector('.item-name-small').textContent;
                    if (!confirm(`Are you sure you want to use ${itemName}?`)) {
                        e.preventDefault();
                    }
                });
            });
        });
    </script>
</body>
</html>