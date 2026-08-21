<?php
// terms.php
require_once 'config/database.php';
session_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - LEYKUN</title>
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
                    <a href="#acceptance">Acceptance of Terms</a>
                    <a href="#eligibility">Eligibility</a>
                    <a href="#user-responsibilities">User Responsibilities</a>
                    <a href="#content-conduct">Content &amp; Conduct</a>
                    <a href="#platform-access">Platform Access</a>
                    <a href="#intellectual-property">Intellectual Property</a>
                    <a href="#limitation-liability">Limitation of Liability</a>
                    <a href="#termination">Termination</a>
                    <a href="#changes">Changes to These Terms</a>
                </nav>
            </aside>

            <div class="legal-card">
                <h1>Terms of Service</h1>
                <p class="legal-updated">Last Updated: <?php echo date('F j, Y'); ?></p>

                <section id="acceptance">
                    <h2>1. Acceptance of Terms</h2>
                    <p>By creating an account and using this Social Media Manager platform, you agree to be bound by these Terms of Service and all applicable laws and regulations.</p>
                </section>

                <section id="eligibility">
                    <h2>2. Eligibility</h2>
                    <p>You must be at least 13 years old to create an account. By registering, you confirm that the information you provide is accurate and that you meet this age requirement.</p>
                </section>

                <section id="user-responsibilities">
                    <h2>3. User Responsibilities</h2>
                    <p>You are responsible for maintaining the confidentiality of your account credentials and for all activity that occurs under your account. For platforms that require it (such as Telegram), you are responsible for providing your own valid Bot Token and Channel/Chat ID in Platform Settings. For platforms connected via OAuth (Facebook, Instagram, LinkedIn, TikTok), you are responsible for keeping your connection to those platforms authorized and up to date.</p>
                </section>

                <section id="content-conduct">
                    <h2>4. Content &amp; Conduct</h2>
                    <p>You retain all rights to the media (images and videos) and captions you upload. However, you agree not to use the service to post:</p>
                    <ul>
                        <li>Illegal or prohibited content.</li>
                        <li>Spam, automated unwanted messages, or phishing links.</li>
                        <li>Content that violates the Terms of Service of the social platforms you connect (e.g., Facebook, Instagram, LinkedIn, TikTok, Telegram).</li>
                    </ul>
                </section>

                <section id="platform-access">
                    <h2>5. Platform Access</h2>
                    <p>This tool uses third-party APIs (Meta Graph API for Facebook/Instagram, the LinkedIn API, the TikTok API, and the Telegram Bot API) to publish content on your behalf. We are not responsible for any changes, downtime, or account suspensions imposed by these third-party platforms.</p>
                </section>

                <section id="intellectual-property">
                    <h2>6. Intellectual Property</h2>
                    <p>The LEYKUN platform, including its design, branding, and underlying software, is our property. This does not affect your ownership of the content you upload and publish, as described in Section 4.</p>
                </section>

                <section id="limitation-liability">
                    <h2>7. Limitation of Liability</h2>
                    <p>The service is provided "as is." We do not guarantee that your posts will always be published at the exact scheduled time due to potential server or API delays. We shall not be liable for any data loss or account issues resulting from the use of this tool.</p>
                </section>

                <section id="termination">
                    <h2>8. Termination</h2>
                    <p>We reserve the right to suspend or terminate your account if you are found to be in violation of these terms. You may also delete your own account at any time from Account Settings.</p>
                </section>

                <section id="changes">
                    <h2>9. Changes to These Terms</h2>
                    <p>We may update these Terms of Service from time to time. If we make material changes, we will update the "Last Updated" date at the top of this page. Continued use of the service after changes take effect constitutes acceptance of the revised terms.</p>
                </section>
            </div>

        </div>

        <div class="legal-footer">
            &copy; <?php echo date('Y'); ?> LEYKUN Social Media Management. All rights reserved. &middot;
            <a href="privacy.php">Privacy Policy</a>
        </div>
    </div>

</body>
</html>