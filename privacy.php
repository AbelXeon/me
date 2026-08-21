<?php
// privacy.php
require_once 'config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Privacy Policy - LEYKUN</title>
    <link rel="stylesheet" href="assets/css/legal.css">
</head>
<body>

    <div class="legal-topbar">
        <div class="legal-brand">
            <div class="brand-mark">
                <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a10 10 0 1 0 10 10"/>
                    <path d="M12 6a6 6 0 1 0 6 6"/>
                    <circle cx="12" cy="12" r="1.5" fill="currentColor" stroke="none"/>
                </svg>
            </div>
            <div>
                <div class="legal-brand-name">LEYKUN</div>
                <div class="legal-brand-tagline">Social Media Management</div>
            </div>
        </div>

        <a href="index.php" class="back-link">
            <svg viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M19 12H5"/><path d="M12 19l-7-7 7-7"/></svg>
            Back to Home
        </a>
    </div>

    <div class="legal-shell">
        <div class="legal-layout">

            <aside class="legal-toc">
                <div class="legal-toc-title">On this page</div>
                <nav>
                    <a href="#information-we-collect">Information We Collect</a>
                    <a href="#social-media-data">Social Media Data</a>
                    <a href="#media-storage">Media Storage</a>
                    <a href="#data-usage">Data Usage</a>
                    <a href="#cookies-sessions">Cookies &amp; Sessions</a>
                    <a href="#security">Security</a>
                    <a href="#third-party-sharing">Third-Party Sharing</a>
                    <a href="#data-retention">Data Retention</a>
                    <a href="#your-rights">Your Rights</a>
                    <a href="#childrens-privacy">Children's Privacy</a>
                    <a href="#changes">Changes to This Policy</a>
                </nav>
            </aside>

            <div class="legal-card">
                <h1>Privacy Policy</h1>
                <p class="legal-updated">Last Updated: <?php echo date('F j, Y'); ?></p>

                <section id="information-we-collect">
                    <h2>1. Information We Collect</h2>
                    <p>We collect the information you provide directly to us when you create an account: your first and last name, username, email address, and password (stored as a secure hash, never in plain text).</p>
                    <p>For security purposes, we also log basic sign-in metadata such as your IP address and the time of each login attempt.</p>
                </section>

                <section id="social-media-data">
                    <h2>2. Social Media Data</h2>
                    <p>To publish content on your behalf, we store the connection details for each social platform you choose to link:</p>
                    <ul>
                        <li><strong>Facebook, Instagram, LinkedIn, and TikTok</strong> — connected via each platform's official login (OAuth). We store the access token, refresh token (where applicable), and your connected account identifier.</li>
                        <li><strong>Telegram</strong> — connected by you supplying a Bot Token and Channel/Chat ID, since Telegram does not offer an OAuth login flow for bots.</li>
                    </ul>
                    <p>This information is used strictly to publish content on your behalf, at your request, to the platforms you've connected.</p>
                </section>

                <section id="media-storage">
                    <h2>3. Media Storage</h2>
                    <p>Images and videos you upload for posting are stored securely on our servers and are only accessible to your account. These files are kept until you delete the associated post or your account.</p>
                </section>

                <section id="data-usage">
                    <h2>4. Data Usage</h2>
                    <p>We use your data to:</p>
                    <ul>
                        <li>Maintain your user account.</li>
                        <li>Schedule and publish posts to your connected platforms.</li>
                        <li>Send account-related emails, such as email verification codes and password reset codes.</li>
                        <li>Provide technical support and improve the service.</li>
                    </ul>
                </section>

                <section id="cookies-sessions">
                    <h2>5. Cookies &amp; Sessions</h2>
                    <p>We use a single session cookie to keep you signed in. We do not use advertising or third-party analytics cookies.</p>
                </section>

                <section id="security">
                    <h2>6. Security</h2>
                    <p>We implement security measures including password hashing, encrypted storage of connection tokens, and CSRF protection on account forms to protect your information. However, please remember that no method of electronic storage is 100% secure.</p>
                </section>

                <section id="third-party-sharing">
                    <h2>7. Third-Party Sharing</h2>
                    <p>We do not sell your data. Your content is only shared with the third-party platforms you explicitly choose to connect and publish to, via their official APIs (Meta Graph API for Facebook/Instagram, the LinkedIn API, the TikTok API, and the Telegram Bot API).</p>
                </section>

                <section id="data-retention">
                    <h2>8. Data Retention</h2>
                    <p>We retain your account data and uploaded media for as long as your account remains active. If you delete a post, its associated media is removed. If you delete your account, your profile data and remaining media are removed from our active database.</p>
                </section>

                <section id="your-rights">
                    <h2>9. Your Rights</h2>
                    <p>You can view or edit your account information at any time through the Account Settings page.</p>
                </section>

                <section id="childrens-privacy">
                    <h2>10. Children's Privacy</h2>
                    <p>Our service is not directed to individuals under the age of 13, and we do not knowingly collect personal information from children.</p>
                </section>

                <section id="changes">
                    <h2>11. Changes to This Policy</h2>
                    <p>We may update this Privacy Policy from time to time. If we make material changes, we will update the "Last Updated" date at the top of this page.</p>
                </section>
            </div>

        </div>

        <div class="legal-footer">
            &copy; <?php echo date('Y'); ?> LEYKUN Social Media Management. All rights reserved. &middot;
            <a href="terms.php">Terms of Service</a>
        </div>
    </div>

</body>
</html>