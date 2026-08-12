<?php
// index.php
session_start();

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LEYKUN - Social Media Management</title>
    <link rel="stylesheet" href="assets/css/index.css">
</head>
<body>

    <!-- ===================== NAV ===================== -->
    <header class="site-nav">
        <div class="site-nav-inner">
            <div class="brand-block">
                <div class="brand-mark">L</div>
                <div>
                    <div class="brand-name">LEYKUN</div>
                    <div class="brand-tagline">Social Media Management</div>
                </div>
            </div>
            <nav class="nav-links">
                <a href="login.php" class="nav-link">Login</a>
                <a href="register.php" class="btn btn-primary btn-small">Get Started</a>
            </nav>
        </div>
    </header>

    <!-- ===================== HERO ===================== -->
    <section class="hero-section">
        <div class="hero-inner">
            <div class="hero-content">
                <span class="hero-eyebrow">Social media management, simplified</span>
                <h1>One dashboard for every platform you post to.</h1>
                <p>Connect Facebook, Instagram, Telegram, LinkedIn, and TikTok, then create, schedule, and publish content to all of them without switching tabs.</p>

                <div class="btn-group">
                    <a href="register.php" class="btn btn-primary">Get Started Free</a>
                    <a href="login.php" class="btn btn-secondary">Login to Your Account</a>
                </div>

                <div class="hero-platform-row">
                    <span>Works with</span>
                    <img src="https://cdn.simpleicons.org/facebook/1877F2" alt="Facebook">
                    <img src="https://cdn.simpleicons.org/instagram/E4405F" alt="Instagram">
                    <img src="https://cdn.simpleicons.org/telegram/26A5E4" alt="Telegram">
                    <img src="https://cdn.simpleicons.org/linkedin/0A66C2" alt="LinkedIn">
                    <img src="https://cdn.simpleicons.org/tiktok/000000" alt="TikTok">
                </div>
            </div>

            <div class="hero-visual" aria-hidden="true">
                <div class="hub-orbit-ring hub-orbit-ring-outer"></div>
                <div class="hub-orbit-ring hub-orbit-ring-inner"></div>
                <div class="hub-glow"></div>

                <div class="hub-center">
                    <span>L</span>
                </div>

                <div class="hub-icon hub-icon-1"><img src="https://cdn.simpleicons.org/facebook/1877F2" alt=""></div>
                <div class="hub-icon hub-icon-2"><img src="https://cdn.simpleicons.org/instagram/E4405F" alt=""></div>
                <div class="hub-icon hub-icon-3"><img src="https://cdn.simpleicons.org/telegram/26A5E4" alt=""></div>
                <div class="hub-icon hub-icon-4"><img src="https://cdn.simpleicons.org/linkedin/0A66C2" alt=""></div>
                <div class="hub-icon hub-icon-5"><img src="https://cdn.simpleicons.org/tiktok/000000" alt=""></div>

                <div class="hub-chip hub-chip-scheduled">
                    <span class="hub-chip-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M20 6 9 17l-5-5"/></svg>
                    </span>
                    Post published
                </div>
            </div>
        </div>
    </section>

    <!-- ===================== HOW IT WORKS ===================== -->
    <section class="info-section">
        <div class="section-heading">
            <span class="section-eyebrow">How it works</span>
            <h2>From idea to published, in three steps</h2>
        </div>

        <div class="steps-container">
            <div class="step">
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                </div>
                <h3>1. Connect</h3>
                <p>Link your Facebook, Instagram, Telegram, and more. <a href="how-to-connect.php">Learn how to connect &rarr;</a></p>
            </div>
            <div class="step">
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/></svg>
                </div>
                <h3>2. Create</h3>
                <p>Craft your post, upload images or videos, and write your captions in one simple editor.</p>
            </div>
            <div class="step">
                <div class="step-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4 20-7Z"/></svg>
                </div>
                <h3>3. Publish</h3>
                <p>Click publish to send your post to all platforms instantly, or schedule it for the perfect time.</p>
            </div>
        </div>
    </section>

    <!-- ===================== FEATURES ===================== -->
    <section class="features-section">
        <div class="section-heading">
            <span class="section-eyebrow">Why LEYKUN</span>
            <h2>Everything you need to stay consistent</h2>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4"/><path d="M8 2v4"/><path d="M3 10h18"/></svg>
                </div>
                <h3>Schedule ahead</h3>
                <p>Plan a week or a month of content in one sitting and let it publish itself at the right time.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>
                </div>
                <h3>One dashboard</h3>
                <p>Every connected platform, every post, and every draft lives in a single, simple view.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 3v18h18"/><rect x="7" y="12" width="3" height="6" rx="0.5"/><rect x="12.5" y="8" width="3" height="10" rx="0.5"/><rect x="18" y="5" width="3" height="13" rx="0.5"/></svg>
                </div>
                <h3>Full post history</h3>
                <p>See what's posted, what's scheduled, and what failed, with details for every platform.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-11V5l-8-3-8 3v6c0 7 8 11 8 11Z"/></svg>
                </div>
                <h3>Secure by design</h3>
                <p>Your account credentials and connected platforms are protected with modern, industry-standard practices.</p>
            </div>
        </div>
    </section>

    <!-- ===================== CTA BANNER ===================== -->
    <section class="cta-section">
        <div class="cta-card">
            <h2>Ready to simplify your social media?</h2>
            <p>Create a free account and connect your first platform in minutes.</p>
            <a href="register.php" class="btn btn-primary">Get Started Free</a>
        </div>
    </section>

    <footer>
        <p>
            &copy; <?php echo date('Y'); ?> LEYKUN. All rights reserved.
            <br>
            <a href="terms.php">Terms of Service</a> | <a href="privacy.php">Privacy Policy</a> | <a href="how-to-connect.php">Connection Guide</a>
        </p>
    </footer>

</body>
</html>