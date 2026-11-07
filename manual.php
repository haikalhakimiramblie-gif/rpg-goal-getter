<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Manual - RPG Goal Getter</title>
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
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Custom Cursor */
        .cursor {
            position: fixed;
            width: 32px;
            height: 32px;
            pointer-events: none;
            z-index: 9999;
            transition: transform 0.1s ease;
            background: url('data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 32 32"><path fill="%23d4af37" d="M16 2L2 16l14 14 14-14L16 2zm0 4l10 10-10 10L6 16 16 6z"/></svg>') center/contain no-repeat;
        }

        .cursor-follower {
            position: fixed;
            width: 16px;
            height: 16px;
            background: rgba(212, 175, 55, 0.3);
            border-radius: 50%;
            pointer-events: none;
            z-index: 9998;
            transition: transform 0.15s ease;
        }

        .cursor.clicked {
            animation: cursorClick 0.3s ease;
        }

        @keyframes cursorClick {
            0% { transform: scale(1); }
            50% { transform: scale(0.8); }
            100% { transform: scale(1); }
        }

        /* Click Animation */
        .click-effect {
            position: absolute;
            width: 20px;
            height: 20px;
            pointer-events: none;
            z-index: 9997;
            background: radial-gradient(circle, var(--accent-gold) 0%, transparent 70%);
            border-radius: 50%;
            animation: clickRipple 0.6s ease-out forwards;
        }

        @keyframes clickRipple {
            0% {
                transform: scale(0);
                opacity: 0.8;
            }
            100% {
                transform: scale(3);
                opacity: 0;
            }
        }

        body {
            background: radial-gradient(ellipse at top, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            color: var(--text-primary);
            line-height: 1.7;
            overflow-x: hidden;
            min-height: 100vh;
            font-family: 'EB Garamond', serif;
            transition: background 2s ease;
            cursor: none;
        }

        /* Forest Background */
        .forest-background {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -3;
            background: url('images/forest.png') center/cover no-repeat;
            opacity: 0.3;
            transform: scale(1.2);
        }

        /* Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 15px 5%;
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.7) 0%, rgba(25, 10, 25, 0.8) 100%);
            border-bottom: 2px solid var(--accent-gold-dark);
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 100;
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

        /* Back Button */
        .back-button {
            margin-left: auto;
        }

        .btn {
            padding: 10px 25px;
            border: none;
            border-radius: 2px;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            font-family: 'Cinzel', serif;
            letter-spacing: 1.2px;
            text-transform: uppercase;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(5px);
            cursor: none;
            white-space: nowrap;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-back {
            background: linear-gradient(135deg, rgba(80, 50, 80, 0.9), rgba(60, 35, 60, 0.95));
            color: var(--accent-gold-light);
            border: 1px solid var(--accent-gold);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.5);
        }

        .btn-back:hover {
            background: linear-gradient(135deg, rgba(90, 60, 90, 1), rgba(70, 45, 70, 1));
            transform: translateY(-2px);
            box-shadow: 0 6px 18px rgba(212, 175, 55, 0.4);
        }

        /* Manual Container */
        .manual-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 5%;
            position: relative;
            z-index: 10;
        }

        .manual-header {
            text-align: center;
            margin-bottom: 50px;
        }

        .manual-header h2 {
            font-size: 3rem;
            margin-bottom: 20px;
            background: linear-gradient(135deg, var(--accent-gold), var(--accent-gold-light), var(--accent-gold-dark));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-family: 'Cinzel', serif;
            letter-spacing: 3px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.5);
        }

        .manual-header p {
            font-size: 1.2rem;
            color: var(--text-primary);
            max-width: 800px;
            margin: 0 auto;
            line-height: 1.6;
        }

        /* Table of Contents */
        .table-of-contents {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.7) 0%, rgba(25, 10, 25, 0.8) 100%);
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            padding: 30px;
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
        }

        .toc-header {
            text-align: center;
            margin-bottom: 25px;
        }

        .toc-header h3 {
            font-size: 1.8rem;
            color: var(--accent-gold);
            font-family: 'Cinzel', serif;
        }

        .toc-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .toc-item {
            display: flex;
            align-items: center;
            gap: 15px;
            padding: 12px 20px;
            background: rgba(30, 15, 30, 0.6);
            border-radius: 8px;
            border-left: 4px solid var(--accent-gold-dark);
            transition: all 0.3s ease;
            text-decoration: none;
            color: var(--text-light);
        }

        .toc-item:hover {
            background: rgba(40, 20, 40, 0.8);
            transform: translateX(10px);
            border-left-color: var(--accent-gold);
        }

        .toc-number {
            background: var(--accent-gold);
            color: var(--text-dark);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cinzel', serif;
            font-weight: bold;
            font-size: 0.9rem;
        }

        .toc-text {
            font-size: 1.1rem;
            flex: 1;
        }

        .toc-icon {
            color: var(--accent-gold);
            font-size: 1.2rem;
        }

        /* Manual Sections */
        .manual-section {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.7) 0%, rgba(25, 10, 25, 0.8) 100%);
            border: 2px solid var(--accent-gold);
            border-radius: 10px;
            padding: 40px;
            backdrop-filter: blur(10px);
            margin-bottom: 40px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s ease forwards;
        }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .section-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 30px;
            padding-bottom: 15px;
            border-bottom: 2px solid var(--accent-gold-dark);
        }

        .section-number {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, var(--accent-gold-dark), var(--accent-gold));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Cinzel', serif;
            font-size: 1.5rem;
            color: var(--text-dark);
            font-weight: bold;
        }

        .section-title {
            flex: 1;
        }

        .section-title h3 {
            font-size: 2rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 5px;
        }

        .section-title p {
            color: var(--text-secondary);
            font-size: 1rem;
        }

        /* Image Display */
        .manual-image {
            width: 100%;
            margin-bottom: 30px;
            text-align: center;
            border: 2px solid var(--accent-gold-dark);
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
        }

        .manual-image img {
            width: 100%;
            height: auto;
            display: block;
        }

        /* Instructions */
        .instructions {
            background: linear-gradient(135deg, rgba(30, 15, 30, 0.6), rgba(20, 10, 20, 0.7));
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 30px;
            margin-bottom: 30px;
        }

        .instructions h4 {
            font-size: 1.5rem;
            color: var(--accent-gold-light);
            font-family: 'Cinzel', serif;
            margin-bottom: 20px;
            text-align: center;
        }

        .instructions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
        }

        .instruction-step {
            background: rgba(40, 20, 40, 0.8);
            border: 1px solid var(--accent-gold-dark);
            border-radius: 8px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .instruction-step:hover {
            transform: translateY(-5px);
            border-color: var(--accent-gold);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .step-header {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .step-icon {
            width: 40px;
            height: 40px;
            background: var(--accent-gold);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-dark);
            font-size: 1.2rem;
        }

        .step-title {
            font-family: 'Cinzel', serif;
            font-size: 1.2rem;
            color: var(--accent-gold-light);
        }

        .step-description {
            color: var(--text-light);
            line-height: 1.6;
        }

        /* Tips Section */
        .tips {
            background: linear-gradient(135deg, rgba(184, 134, 11, 0.1), rgba(139, 105, 20, 0.2));
            border: 1px solid var(--accent-gold);
            border-radius: 8px;
            padding: 20px;
            margin-top: 20px;
        }

        .tips h5 {
            font-size: 1.2rem;
            color: var(--accent-gold);
            font-family: 'Cinzel', serif;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .tips ul {
            padding-left: 20px;
            color: var(--text-light);
        }

        .tips li {
            margin-bottom: 8px;
            line-height: 1.6;
        }

        /* Navigation Buttons */
        .section-navigation {
            display: flex;
            justify-content: space-between;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid var(--accent-gold-dark);
        }

        .nav-btn {
            background: linear-gradient(135deg, rgba(40, 20, 40, 0.9), rgba(30, 15, 30, 0.95));
            color: var(--accent-gold-light);
            border: 1px solid var(--accent-gold-dark);
            padding: 12px 25px;
            font-size: 0.9rem;
            border-radius: 2px;
            font-family: 'Cinzel', serif;
            text-transform: uppercase;
            letter-spacing: 1px;
            cursor: none;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }

        .nav-btn:hover {
            background: linear-gradient(135deg, rgba(50, 25, 50, 0.95), rgba(40, 20, 40, 0.95));
            border-color: var(--accent-gold);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(212, 175, 55, 0.3);
        }

        .nav-btn.prev {
            margin-right: auto;
        }

        .nav-btn.next {
            margin-left: auto;
        }

        /* Footer */
        footer {
            text-align: center;
            padding: 25px 5%;
            background: linear-gradient(135deg, rgba(25, 10, 25, 0.7) 0%, rgba(15, 5, 15, 0.8) 100%);
            border-top: 1px solid var(--accent-gold-dark);
            backdrop-filter: blur(5px);
            margin-top: 60px;
        }

        footer p {
            font-size: 0.8rem;
            color: var(--text-secondary);
            font-family: 'Cinzel', serif;
            letter-spacing: 1px;
        }

        /* Responsive */
        @media (max-width: 992px) {
            .manual-header h2 {
                font-size: 2.5rem;
            }
            
            .manual-section {
                padding: 30px;
            }
            
            .instructions-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 768px) {
            .manual-header h2 {
                font-size: 2rem;
            }
            
            .manual-header p {
                font-size: 1.1rem;
            }
            
            .manual-section {
                padding: 20px;
            }
            
            .section-header {
                flex-direction: column;
                text-align: center;
                gap: 10px;
            }
            
            .section-title h3 {
                font-size: 1.6rem;
            }
            
            header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
                padding: 20px 5%;
            }
            
            .back-button {
                margin-left: 0;
                margin-top: 10px;
            }
            
            .section-navigation {
                flex-direction: column;
                gap: 15px;
            }
            
            .nav-btn {
                width: 100%;
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .manual-header h2 {
                font-size: 1.8rem;
            }
            
            .btn {
                padding: 10px 20px;
                font-size: 0.8rem;
            }
            
            .logo h1 {
                font-size: 1.5rem;
            }
            
            .toc-item {
                padding: 10px 15px;
            }
            
            .toc-text {
                font-size: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Custom Cursor -->
    <div class="cursor"></div>
    <div class="cursor-follower"></div>

    <!-- Forest Background -->
    <div class="forest-background"></div>

    <!-- Header -->
    <header>
        <div class="logo">
            <h1>RPG Goal Getter</h1>
        </div>
        
        <!-- Back Button -->
        <div class="back-button">
            <a href="index.html" class="btn btn-back">
                <i class="fas fa-arrow-left"></i> BACK TO HOMEPAGE
            </a>
        </div>
    </header>

    <!-- Manual Container -->
    <div class="manual-container">
        <!-- Manual Header -->
        <div class="manual-header">
            <h2><i class="fas fa-scroll"></i> ANCIENT SCROLL OF KNOWLEDGE</h2>
            <p>Master the Art of Productivity Questing - Your Complete Guide to RPG Goal Getter</p>
        </div>

        <!-- Table of Contents -->
        <div class="table-of-contents">
            <div class="toc-header">
                <h3><i class="fas fa-list"></i> TABLE OF CONTENTS</h3>
            </div>
            <div class="toc-list">
                <a href="#section1" class="toc-item">
                    <div class="toc-number">1</div>
                    <div class="toc-text">The Grand Entrance - Homepage</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section2" class="toc-item">
                    <div class="toc-number">2</div>
                    <div class="toc-text">Begin Your Quest - Character Creation</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section3" class="toc-item">
                    <div class="toc-number">3</div>
                    <div class="toc-text">Adventurer's Login - Returning to the Realm</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section4" class="toc-item">
                    <div class="toc-number">4</div>
                    <div class="toc-text">Adventurer's Dashboard - Command Center</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section5" class="toc-item">
                    <div class="toc-number">5</div>
                    <div class="toc-text">Quest Management - The Battle System</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section6" class="toc-item">
                    <div class="toc-number">6</div>
                    <div class="toc-text">Choose Your Challenge - Difficulty Selection</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section7" class="toc-item">
                    <div class="toc-number">7</div>
                    <div class="toc-text">Forge New Quest - Quest Creation Form</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section8" class="toc-item">
                    <div class="toc-number">8</div>
                    <div class="toc-text">Chronicles of Glory - Adventure Logs</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
                <a href="#section9" class="toc-item">
                    <div class="toc-number">9</div>
                    <div class="toc-text">Quest Archive Details - Completion Analysis</div>
                    <div class="toc-icon"><i class="fas fa-arrow-right"></i></div>
                </a>
            </div>
        </div>

        <!-- Section 1: Homepage -->
        <div class="manual-section" id="section1">
            <div class="section-header">
                <div class="section-number">1</div>
                <div class="section-title">
                    <h3>The Grand Entrance - Homepage</h3>
                    <p>Your journey begins at the main portal</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual1.jpeg" alt="RPG Goal Getter Homepage">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-map"></i> NAVIGATION GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-scroll"></i></div>
                            <div class="step-title">USER MANUAL</div>
                        </div>
                        <div class="step-description">
                            Consult this ancient scroll (you're reading it now!) to learn all the secrets of RPG Goal Getter.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-dragon"></i></div>
                            <div class="step-title">LOGIN</div>
                        </div>
                        <div class="step-description">
                            Enter the enchanted portal to access your character dashboard and continue your questing journey.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-crown"></i></div>
                            <div class="step-title">BEGIN QUEST</div>
                        </div>
                        <div class="step-description">
                            Start your epic adventure by creating a new hero character and setting up your first quests.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-gem"></i></div>
                            <div class="step-title">START YOUR QUEST</div>
                        </div>
                        <div class="step-description">
                            The main call to action - begin your legendary journey by creating your first character.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-lightbulb"></i> SAGE ADVICE</h5>
                <ul>
                    <li>New adventurers should click "BEGIN QUEST" to start their journey</li>
                    <li>Returning heroes use "LOGIN" to continue their epic saga</li>
                    <li>Read the manual whenever you need guidance on game mechanics</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section2" class="nav-btn next">
                    Next: Character Creation <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 2: Registration -->
        <div class="manual-section" id="section2">
            <div class="section-header">
                <div class="section-number">2</div>
                <div class="section-title">
                    <h3>Begin Your Quest - Character Creation</h3>
                    <p>Forge your legend through registration</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual2.jpeg" alt="Character Registration Page">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-user-plus"></i> CHARACTER CREATION GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">1</div>
                            <div class="step-title">Scroll Through the Story</div>
                        </div>
                        <div class="step-description">
                            Embark on your journey by scrolling down through seven mystical images that guide you to the character creation portal.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">2</div>
                            <div class="step-title">Choose Your Hero Name</div>
                        </div>
                        <div class="step-description">
                            Enter your heroic name (minimum 3 characters). This will be your identity throughout the realm.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">3</div>
                            <div class="step-title">Scroll Address</div>
                        </div>
                        <div class="step-description">
                            Provide your email address for magical communications and account recovery.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">4</div>
                            <div class="step-title">Forge Your Password</div>
                        </div>
                        <div class="step-description">
                            Create a strong password (minimum 6 characters) to protect your character from dark forces.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">5</div>
                            <div class="step-title">Confirm Your Password</div>
                        </div>
                        <div class="step-description">
                            Repeat your password to ensure magical security and prevent casting errors.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">6</div>
                            <div class="step-title">Choose Your Identity</div>
                        </div>
                        <div class="step-description">
                            Select your character's identity: Male, Female, or Other. This determines your character's appearance.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-shield-alt"></i> SECURITY TIPS</h5>
                <ul>
                    <li>Use a memorable but secure hero name - you'll see it on your dashboard</li>
                    <li>Your password is encrypted with ancient magic - never share it</li>
                    <li>If a hero name already exists, choose a different legendary name</li>
                    <li>After registration, you'll see a magical portal animation as your character is created</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section1" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Homepage
                </a>
                <a href="#section3" class="nav-btn next">
                    Next: Login Portal <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 3: Login -->
        <div class="manual-section" id="section3">
            <div class="section-header">
                <div class="section-number">3</div>
                <div class="section-title">
                    <h3>Adventurer's Login - Returning to the Realm</h3>
                    <p>Access your character and continue your journey</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual3.jpeg" alt="Login Page">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-sign-in-alt"></i> AUTHENTICATION GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-user"></i></div>
                            <div class="step-title">Username or Email</div>
                        </div>
                        <div class="step-description">
                            Enter either your hero name or scroll address (email) - both will work to identify you.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-key"></i></div>
                            <div class="step-title">Password</div>
                        </div>
                        <div class="step-description">
                            Input your forged password. If forgotten, use the "Forgotten your magical password?" link.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-door-open"></i></div>
                            <div class="step-title">Enter Realm</div>
                        </div>
                        <div class="step-description">
                            Click this button to authenticate and enter your personal dashboard. Watch the portal animation!
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-plus"></i></div>
                            <div class="step-title">New Adventurer?</div>
                        </div>
                        <div class="step-description">
                            If you don't have an account yet, click "Begin your quest here" to go to the registration page.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-magic"></i> LOGIN TIPS</h5>
                <ul>
                    <li>Use the "BACK TO QUEST" button to return to homepage without logging in</li>
                    <li>Upon successful login, you'll see your character stats loading in a magical portal</li>
                    <li>Login credentials are case-sensitive - check your spelling</li>
                    <li>The DEMO MODE indicator shows this is a demonstration version</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section2" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Character Creation
                </a>
                <a href="#section4" class="nav-btn next">
                    Next: Dashboard <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 4: Dashboard -->
        <div class="manual-section" id="section4">
            <div class="section-header">
                <div class="section-number">4</div>
                <div class="section-title">
                    <h3>Adventurer's Dashboard - Command Center</h3>
                    <p>Your main hub for all questing activities</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual4.jpeg" alt="User Dashboard">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-tachometer-alt"></i> DASHBOARD GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-user-circle"></i></div>
                            <div class="step-title">Character Panel</div>
                        </div>
                        <div class="step-description">
                            Your character changes appearance based on health. Click to see it jiggle! Shows Level, HP, and XP progress.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-scroll"></i></div>
                            <div class="step-title">Active Quests</div>
                        </div>
                        <div class="step-description">
                            Number of quests currently in progress. Click to go to the Quest Management page.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-trophy"></i></div>
                            <div class="step-title">Quests Conquered</div>
                        </div>
                        <div class="step-description">
                            Total number of quests you've successfully completed. Click to view your achievement log.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-dragon"></i></div>
                            <div class="step-title">BOSS ARENA</div>
                        </div>
                        <div class="step-description">
                            Enter epic challenges and defeat mighty bosses for legendary rewards. Red-themed for danger!
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-flag"></i></div>
                            <div class="step-title">QUEST BOARD</div>
                        </div>
                        <div class="step-description">
                            Main interface for daily quest management. Click "VIEW QUEST" to see and manage all your quests.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-book"></i></div>
                            <div class="step-title">ADVENTURE LOG</div>
                        </div>
                        <div class="step-description">
                            View detailed log of all your activities, completed quests, and achievements.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-heartbeat"></i> HEALTH SYSTEM</h5>
                <ul>
                    <li><strong>Above 60 HP:</strong> Healthy (green status) - Your character looks normal</li>
                    <li><strong>30-60 HP:</strong> Wounded (yellow status) - Keep pushing forward!</li>
                    <li><strong>Below 30 HP:</strong> Critical (red status) - Your character appears injured. Complete quests to heal!</li>
                    <li>Level up grants increased max HP and gold rewards</li>
                    <li>Excess XP carries over to next level</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section3" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Login Portal
                </a>
                <a href="#section5" class="nav-btn next">
                    Next: Quest Management <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 5: Goals/Quests -->
        <div class="manual-section" id="section5">
            <div class="section-header">
                <div class="section-number">5</div>
                <div class="section-title">
                    <h3>Quest Management - The Battle System</h3>
                    <p>Conquer goals through monster combat</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual5.jpeg" alt="Quest Management Page">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-swords"></i> BATTLE SYSTEM GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">⚔️</div>
                            <div class="step-title">Quest Cards</div>
                        </div>
                        <div class="step-description">
                            Each quest shows: Title, Description, Rewards (XP/Gold), HP Deduction schedule, and Monster info. Click to battle!
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">📋</div>
                            <div class="step-title">Task Completion</div>
                        </div>
                        <div class="step-description">
                            Complete individual tasks to enable attack damage. Each task = specific damage amount against monster.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">⚡</div>
                            <div class="step-title">Attack System</div>
                        </div>
                        <div class="step-description">
                            Click ATTACK to deal all accumulated damage at once! Damage = (Tasks completed × Damage per task).
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">👹</div>
                            <div class="step-title">Monster States</div>
                        </div>
                        <div class="step-description">
                            Monsters change appearance: Normal (100% HP), Angry (<60% HP), Injured (<30% HP), Dead (0% HP).
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">⏰</div>
                            <div class="step-title">Deadline System</div>
                        </div>
                        <div class="step-description">
                            Missed deadlines deduct HP at regular intervals. Complete quests before deadlines to avoid penalties!
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">🏆</div>
                            <div class="step-title">Quest Completion</div>
                        </div>
                        <div class="step-description">
                            Defeat monster → Provide feedback → Claim rewards (XP & Gold). Feedback is required for completion.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-skull-crossbones"></i> COMBAT STRATEGY</h5>
                <ul>
                    <li><strong>Regular Monsters:</strong> Have Normal and Injured states</li>
                    <li><strong>Boss Monsters:</strong> Have Sleep, Normal, Angry, and Injured states</li>
                    <li>Complete all tasks before attacking for maximum damage</li>
                    <li>Prioritize quests with approaching deadlines</li>
                    <li>Failed quests (red cards) cannot be completed - learn from them!</li>
                    <li>The page auto-refreshes every 5 minutes to update HP and deadlines</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section4" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Dashboard
                </a>
                <a href="#section6" class="nav-btn next">
                    Next: Difficulty Selection <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 6: Difficulty Selection -->
        <div class="manual-section" id="section6">
            <div class="section-header">
                <div class="section-number">6</div>
                <div class="section-title">
                    <h3>Choose Your Challenge - Difficulty Selection</h3>
                    <p>Forge quests with appropriate risk and reward</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual6.jpeg" alt="Difficulty Selection Page">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-shield-alt"></i> DIFFICULTY GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-shield"></i></div>
                            <div class="step-title">Easy Difficulty</div>
                        </div>
                        <div class="step-description">
                            Perfect for beginners: +50 XP, +10 Gold. Light HP drain that's easy to manage.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-sword"></i></div>
                            <div class="step-title">Medium Difficulty</div>
                        </div>
                        <div class="step-description">
                            For experienced adventurers: +100 XP, +25 Gold. Moderate HP drain requires attention.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-crosshairs"></i></div>
                            <div class="step-title">Hard Difficulty</div>
                        </div>
                        <div class="step-description">
                            Challenging quests: +200 XP, +50 Gold. Heavy HP drain demands serious commitment.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-dragon"></i></div>
                            <div class="step-title">Epic Difficulty</div>
                        </div>
                        <div class="step-description">
                            Legendary challenges: +400 XP, +100 Gold. Severe HP drain with boss battles!
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-balance-scale"></i> DIFFICULTY STRATEGY</h5>
                <ul>
                    <li><strong>Easy:</strong> 20 HP base deduction × 1 multiplier</li>
                    <li><strong>Medium:</strong> 20 HP base deduction × 1.5 multiplier</li>
                    <li><strong>Hard:</strong> 20 HP base deduction × 2 multiplier</li>
                    <li><strong>Epic:</strong> 20 HP base deduction × 3 multiplier (with boss monster)</li>
                    <li>Each additional task adds 10% to the total HP deduction</li>
                    <li>Choose Epic for important goals you're absolutely committed to completing</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section5" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Quest Management
                </a>
                <a href="#section7" class="nav-btn next">
                    Next: Quest Creation Form <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 7: Quest Creation -->
        <div class="manual-section" id="section7">
            <div class="section-header">
                <div class="section-number">7</div>
                <div class="section-title">
                    <h3>Forge New Quest - Quest Creation Form</h3>
                    <p>Craft your destiny with detailed planning</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual7.jpeg" alt="Quest Creation Form">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-hammer"></i> QUEST FORGING GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">1</div>
                            <div class="step-title">HP Drain Warning</div>
                        </div>
                        <div class="step-description">
                            Serious warning appears - your HP WILL decrease until quest completion. Failure = permanent HP loss!
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">2</div>
                            <div class="step-title">Quest Title</div>
                        </div>
                        <div class="step-description">
                            Name your quest clearly. This appears on your quest cards and adventure logs.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">3</div>
                            <div class="step-title">Quest Description</div>
                        </div>
                        <div class="step-description">
                            Detailed description of what you need to accomplish. Be specific for better motivation.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">4</div>
                            <div class="step-title">Category Selection</div>
                        </div>
                        <div class="step-description">
                            Choose from: Work, Study, Health, Personal, Creative, Financial, Social, or Other.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">5</div>
                            <div class="step-title">Due Date & Time</div>
                        </div>
                        <div class="step-description">
                            Set your deadline. HP drain calculations adjust based on time remaining.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon">6</div>
                            <div class="step-title">Task Management</div>
                        </div>
                        <div class="step-description">
                            Add individual tasks with "+ Add Task". Each task increases HP deduction but breaks goals into manageable steps.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-calculator"></i> HP DRAIN CALCULATION</h5>
                <ul>
                    <li><strong>Time-Based Intervals:</strong> 
                        <br>• Less than 5 mins: Minutely deductions
                        <br>• Less than 1 hour: Minutely deductions  
                        <br>• Less than 24 hours: Hourly deductions
                        <br>• More than 24 hours: Daily deductions</li>
                    <li>HP deduction preview updates in real-time as you change tasks and deadlines</li>
                    <li>Final HP shown turns red if you'll reach 0 HP - choose easier difficulty or later deadline!</li>
                    <li>Epic difficulty creates a boss with DOUBLE the HP of regular monsters</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section6" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Difficulty Selection
                </a>
                <a href="#section8" class="nav-btn next">
                    Next: Adventure Logs <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 8: Adventure Logs -->
        <div class="manual-section" id="section8">
            <div class="section-header">
                <div class="section-number">8</div>
                <div class="section-title">
                    <h3>Chronicles of Glory - Adventure Logs</h3>
                    <p>Review your completed quests and achievements</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual8.jpeg" alt="Adventure Logs Page">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-book-open"></i> LOG NAVIGATION GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-trophy"></i></div>
                            <div class="step-title">Quests Completed</div>
                        </div>
                        <div class="step-description">
                            Total number of quests you've successfully conquered. Your primary achievement metric.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-star"></i></div>
                            <div class="step-title">Total Experience</div>
                        </div>
                        <div class="step-description">
                            Sum of all XP earned from completed quests. Shows your overall progress.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-coins"></i></div>
                            <div class="step-title">Gold Collected</div>
                        </div>
                        <div class="step-description">
                            Total gold earned. Use this to track your in-game wealth accumulation.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-history"></i></div>
                            <div class="step-title">Legend Adventures</div>
                        </div>
                        <div class="step-description">
                            Number of logged adventures. Each completed quest creates a permanent log entry.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-scroll"></i></div>
                            <div class="step-title">Quest Archive Grid</div>
                        </div>
                        <div class="step-description">
                            Click any quest card to view detailed completion information and your reflections.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-arrow-left"></i></div>
                            <div class="step-title">Back to Dashboard</div>
                        </div>
                        <div class="step-description">
                            Return to your main command center to continue your adventure.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-chart-line"></i> LOG ANALYSIS</h5>
                <ul>
                    <li><strong>Color-Coded Difficulty:</strong> Green (Easy), Orange (Medium), Red (Hard), Purple (Epic)</li>
                    <li><strong>Completion Time:</strong> Shows how many days it took to complete the quest</li>
                    <li><strong>Task Count:</strong> Number of individual tasks within the quest</li>
                    <li><strong>Rewards Display:</strong> XP always shown, Gold only if earned (some quests give XP only)</li>
                    <li>Empty state shows motivational message when you have no completed quests</li>
                    <li>Logs are permanent records - they help track long-term progress patterns</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section7" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Quest Creation
                </a>
                <a href="#section9" class="nav-btn next">
                    Next: Quest Details <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>

        <!-- Section 9: Quest Details -->
        <div class="manual-section" id="section9">
            <div class="section-header">
                <div class="section-number">9</div>
                <div class="section-title">
                    <h3>Quest Archive Details - Completion Analysis</h3>
                    <p>Dive deep into your completed adventures</p>
                </div>
            </div>

            <!-- Image Display -->
            <div class="manual-image">
                <img src="images/manual9.jpeg" alt="Quest Details Modal">
            </div>

            <!-- Instructions -->
            <div class="instructions">
                <h4><i class="fas fa-search"></i> DETAIL VIEW GUIDE</h4>
                <div class="instructions-grid">
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-tag"></i></div>
                            <div class="step-title">Category Display</div>
                        </div>
                        <div class="step-description">
                            Shows the quest category (Study, Work, Health, etc.) at the top for quick reference.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-tasks"></i></div>
                            <div class="step-title">Tasks Completed</div>
                        </div>
                        <div class="step-description">
                            Exact number of tasks you completed within this quest. More tasks = more complex quests.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-clock"></i></div>
                            <div class="step-title">Completion Time</div>
                        </div>
                        <div class="step-description">
                            How long it took: "Same Day" for quick wins, or number of days for longer quests.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-calendar"></i></div>
                            <div class="step-title">Exact Completion Time</div>
                        </div>
                        <div class="step-description">
                            Precise date and time when you defeated the monster and completed the quest.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-star"></i></div>
                            <div class="step-title">Experience Rewards</div>
                        </div>
                        <div class="step-description">
                            XP earned based on difficulty. Essential for leveling up your character.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-coins"></i></div>
                            <div class="step-title">Gold Rewards</div>
                        </div>
                        <div class="step-description">
                            Gold earned (only shown if quest provided gold rewards). Epic quests give most gold.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-pen"></i></div>
                            <div class="step-title">Your Reflection</div>
                        </div>
                        <div class="step-description">
                            The feedback you provided when completing the quest. Valuable for learning and improvement.
                        </div>
                    </div>
                    <div class="instruction-step">
                        <div class="step-header">
                            <div class="step-icon"><i class="fas fa-times"></i></div>
                            <div class="step-title">Close Modal</div>
                        </div>
                        <div class="step-description">
                            Click the X button or press ESC to return to the Adventure Logs overview.
                        </div>
                    </div>
                </div>
            </div>

            <div class="tips">
                <h5><i class="fas fa-lightbulb"></i> REFLECTION BENEFITS</h5>
                <ul>
                    <li><strong>Learning Tool:</strong> Your reflections help identify what worked well and what didn't</li>
                    <li><strong>Motivation:</strong> Reading past successes boosts confidence for future quests</li>
                    <li><strong>Pattern Recognition:</strong> Notice which categories/difficulties you excel at</li>
                    <li><strong>Improvement Tracking:</strong> See how your completion times improve over months</li>
                    <li>Always provide meaningful feedback - it becomes valuable data for your growth journey</li>
                    <li>Use the modal's close button (X) or ESC key for quick navigation back to logs</li>
                </ul>
            </div>

            <div class="section-navigation">
                <a href="#section8" class="nav-btn prev">
                    <i class="fas fa-arrow-left"></i> Previous: Adventure Logs
                </a>
                <a href="#section1" class="nav-btn next">
                    Back to Top <i class="fas fa-arrow-up"></i>
                </a>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer>
        <p><i class="fas fa-scroll"></i> © 2023 RPG GOAL GETTER - ANCIENT KNOWLEDGE VAULT <i class="fas fa-dragon"></i></p>
        <p style="margin-top: 10px; font-size: 0.7rem;">Consult this manual whenever you need guidance on your productivity quest</p>
    </footer>

    <script>
        // Custom Cursor Logic
        const cursor = document.querySelector('.cursor');
        const cursorFollower = document.querySelector('.cursor-follower');
        
        document.addEventListener('mousemove', (e) => {
            cursor.style.left = e.clientX + 'px';
            cursor.style.top = e.clientY + 'px';
            
            setTimeout(() => {
                cursorFollower.style.left = e.clientX + 'px';
                cursorFollower.style.top = e.clientY + 'px';
            }, 100);
        });
        
        document.addEventListener('click', (e) => {
            // Add click animation to cursor
            cursor.classList.add('clicked');
            setTimeout(() => {
                cursor.classList.remove('clicked');
            }, 300);
            
            // Create click effect
            const clickEffect = document.createElement('div');
            clickEffect.className = 'click-effect';
            clickEffect.style.left = e.clientX + 'px';
            clickEffect.style.top = e.clientY + 'px';
            document.body.appendChild(clickEffect);
            
            // Remove effect after animation
            setTimeout(() => {
                clickEffect.remove();
            }, 600);
        });

        // Remove cursor for touch devices
        if ('ontouchstart' in window || navigator.maxTouchPoints) {
            cursor.style.display = 'none';
            cursorFollower.style.display = 'none';
        }

        // Smooth scrolling for table of contents links
        document.querySelectorAll('.toc-item').forEach(link => {
            link.addEventListener('click', function(e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                const targetSection = document.querySelector(targetId);
                
                window.scrollTo({
                    top: targetSection.offsetTop - 100,
                    behavior: 'smooth'
                });
            });
        });

        // Smooth scrolling for navigation buttons
        document.querySelectorAll('.nav-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                if (this.getAttribute('href').startsWith('#')) {
                    e.preventDefault();
                    const targetId = this.getAttribute('href');
                    const targetSection = document.querySelector(targetId);
                    
                    window.scrollTo({
                        top: targetSection.offsetTop - 100,
                        behavior: 'smooth'
                    });
                }
            });
        });

        // Animate sections as they come into view
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.6s ease forwards';
                }
            });
        }, observerOptions);

        // Observe all manual sections
        document.querySelectorAll('.manual-section').forEach(section => {
            observer.observe(section);
        });

        // Highlight current section in TOC based on scroll position
        window.addEventListener('scroll', () => {
            const sections = document.querySelectorAll('.manual-section');
            const scrollPosition = window.scrollY + 150;
            
            sections.forEach(section => {
                const sectionTop = section.offsetTop;
                const sectionHeight = section.offsetHeight;
                const sectionId = section.getAttribute('id');
                
                if (scrollPosition >= sectionTop && scrollPosition < sectionTop + sectionHeight) {
                    // Remove active class from all TOC items
                    document.querySelectorAll('.toc-item').forEach(item => {
                        item.style.background = 'rgba(30, 15, 30, 0.6)';
                        item.style.borderLeftColor = 'var(--accent-gold-dark)';
                    });
                    
                    // Add active class to current TOC item
                    const currentTocItem = document.querySelector(`.toc-item[href="#${sectionId}"]`);
                    if (currentTocItem) {
                        currentTocItem.style.background = 'rgba(40, 20, 40, 0.8)';
                        currentTocItem.style.borderLeftColor = 'var(--accent-gold)';
                    }
                }
            });
        });

        // Initialize first section as active
        document.querySelector('.toc-item[href="#section1"]').style.background = 'rgba(40, 20, 40, 0.8)';
        document.querySelector('.toc-item[href="#section1"]').style.borderLeftColor = 'var(--accent-gold)';
    </script>
</body>
</html>