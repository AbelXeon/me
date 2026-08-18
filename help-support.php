<?php
// help-support.php
require_once 'includes/auth_check.php';
requireLogin();

$pageTitle = 'Help & Support';
$activeNav = 'help-support';
$pageCss = 'assets/css/help-support.css';
$topbarTitle = 'Help & Support';
$showBackBtn = false;
require_once 'includes/layout_header.php';
?>

            <p class="help-intro">Real, step-by-step instructions for connecting each platform, creating posts, and troubleshooting the common stuff.</p>

            <div class="help-shell">

                <aside class="help-toc">
                    <div class="help-toc-title">On this page</div>
                    <nav id="tocNav">
                        <a href="#getting-started" class="toc-link">Getting Started</a>
                        <a href="#facebook-instagram" class="toc-link">Facebook &amp; Instagram</a>
                        <a href="#telegram" class="toc-link">Telegram</a>
                        <a href="#linkedin" class="toc-link">LinkedIn</a>
                        <a href="#tiktok" class="toc-link">TikTok</a>
                        <a href="#creating-posts" class="toc-link">Creating &amp; Scheduling Posts</a>
                        <a href="#managing-posts" class="toc-link">Managing Your Posts</a>
                        <a href="#faq" class="toc-link">Troubleshooting &amp; FAQ</a>
                        <a href="#contact" class="toc-link">Still need help?</a>
                    </nav>
                </aside>

                <div class="help-content">

                    <section id="getting-started" class="help-section">
                        <h2>Getting Started</h2>
                        <p>LEYKUN connects to Facebook, Instagram, Telegram, LinkedIn, and TikTok so you can write a post once and send it everywhere. Start in <strong>Platform Settings</strong> to connect your accounts, then head to <strong>Create Post</strong> to publish immediately or schedule for later.</p>
                    </section>

                    <section id="facebook-instagram" class="help-section">
                        <div class="platform-heading">
                            <img src="https://cdn.simpleicons.org/facebook/1877F2" alt="">
                            <img src="https://cdn.simpleicons.org/instagram/E4405F" alt="">
                            <h2>Facebook &amp; Instagram</h2>
                        </div>
                        <ol>
                            <li>Go to <strong>Platform Settings</strong>.</li>
                            <li>Click <strong>Connect</strong> next to Facebook &amp; Instagram.</li>
                            <li>Log into Facebook and select the Page you want to post from.</li>
                            <li>Approve the requested permissions — Page access, Instagram access, content publishing, and comment management.</li>
                        </ol>
                        <div class="help-note">
                            <strong>Important:</strong> your Instagram account needs to be a Business or Creator account linked to that Facebook Page. LEYKUN posts to Instagram through this Page connection — there's no separate Instagram login.
                        </div>
                        <p>You can post a single image, a single video (published as a Reel), or a carousel of 2 or more images to Instagram in one go.</p>
                    </section>

                    <section id="telegram" class="help-section">
                        <div class="platform-heading">
                            <img src="https://cdn.simpleicons.org/telegram/26A5E4" alt="">
                            <h2>Telegram</h2>
                        </div>
                        <p>Telegram doesn't use a login popup like the others — you connect it manually with a bot.</p>
                        <ol>
                            <li>Open Telegram, search for <strong>@BotFather</strong>, and send <code>/newbot</code> to create a new bot.</li>
                            <li>Copy the Bot Token BotFather gives you.</li>
                            <li>Open (or create) your channel, go to <strong>Administrators</strong>, and add your bot as an Admin.</li>
                            <li>Forward any message from your channel to <strong>@userinfobot</strong> to get your Channel ID — it looks like <code>-100123456789</code>.</li>
                            <li>In <strong>Platform Settings</strong>, expand Telegram and paste in your Channel Name, Bot Token, and Channel ID.</li>
                        </ol>
                    </section>

                    <section id="linkedin" class="help-section">
                        <div class="platform-heading">
                            <img src="https://cdn.jsdelivr.net/npm/simple-icons@v13/icons/linkedin.svg" 
     alt="LinkedIn" 
     width="24" 
     height="24" 
     style="filter: invert(27%) sepia(89%) saturate(1844%) hue-rotate(178deg) brightness(91%) contrast(101%);">
                            <h2>LinkedIn</h2>
                        </div>
                        <ol>
                            <li>Go to <strong>Platform Settings</strong>.</li>
                            <li>Click <strong>Connect</strong> next to LinkedIn.</li>
                            <li>Log in and authorize LEYKUN.</li>
                        </ol>
                        <div class="help-note">
                            This connects your <strong>personal LinkedIn profile</strong>, not a Company Page — posts go out under your own name.
                        </div>
                    </section>

                    <section id="tiktok" class="help-section">
                        <div class="platform-heading">
                            <img src="https://cdn.simpleicons.org/tiktok/000000" alt="">
                            <h2>TikTok</h2>
                        </div>
                        <ol>
                            <li>Go to <strong>Platform Settings</strong>.</li>
                            <li>Click <strong>Connect</strong> next to TikTok.</li>
                            <li>Log in and authorize LEYKUN.</li>
                        </ol>
                        <div class="help-note">
                            <strong>TikTok only accepts video uploads</strong> — image-only posts can't be published there. You can also turn comments off for a specific post when TikTok is selected.
                        </div>
                    </section>

                    <section id="creating-posts" class="help-section">
                        <h2>Creating &amp; Scheduling Posts</h2>
                        <p>From <strong>Create Post</strong>:</p>
                        <ul>
                            <li>Write your caption and, optionally, add links — they're appended to the end of the post.</li>
                            <li>Upload one or more images (JPG, PNG, WEBP) or a video (MP4, MOV).</li>
                            <li>Choose which connected platforms to send it to.</li>
                            <li>If Instagram or TikTok is selected, an <strong>Allow comments</strong> toggle appears — it's on by default.</li>
                            <li>Choose <strong>Post now</strong> to publish immediately, or <strong>Schedule for later</strong> to pick a date and time. Past dates, and past times on today's date, can't be selected.</li>
                        </ul>
                    </section>

                    <section id="managing-posts" class="help-section">
                        <h2>Managing Your Posts</h2>
                        <p><strong>Post History</strong> shows everything you've created. Filter by All, Posted, Scheduled, Drafts, or Failed. Click a post's media to view it full-size — if the post has multiple images, you can flip through all of them. You can also delete a post's record from there.</p>
                    </section>

                    <section id="faq" class="help-section">
                        <h2>Troubleshooting &amp; FAQ</h2>

                        <div class="faq-list">
                            <div class="faq-item">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span>Why can't I post an image to TikTok?</span>
                                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="faq-answer">
                                    <p>TikTok's posting API only accepts video. If a post you sent was image-only, it will fail on TikTok specifically even if it succeeds on your other platforms — upload a video instead.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span>Why did my Instagram carousel fail?</span>
                                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="faq-answer">
                                    <p>Instagram carousels need at least 2 images. A single image or video posts as a normal photo or Reel instead — no carousel needed for just one file.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span>It says my connection or token expired — what do I do?</span>
                                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="faq-answer">
                                    <p>Go to <strong>Platform Settings</strong>, disconnect that platform, and connect it again to refresh your access.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span>Why can't I turn off comments on Facebook or Telegram?</span>
                                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="faq-answer">
                                    <p>The comments toggle currently only applies to Instagram and TikTok posts.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span>Can I edit a post after I've created it?</span>
                                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="faq-answer">
                                    <p>Not yet — there's no edit option today. Delete the post from <strong>Post History</strong> and create a new one with the changes you need.</p>
                                </div>
                            </div>

                            <div class="faq-item">
                                <button type="button" class="faq-question" aria-expanded="false">
                                    <span>Do my images and videos need to be a certain size?</span>
                                    <svg class="faq-chevron" viewBox="0 0 24 24" fill="none" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 9l6 6 6-6"/></svg>
                                </button>
                                <div class="faq-answer">
                                    <p>Images are automatically resized and compressed before upload, so you don't need to worry about that. Videos aren't compressed, so try to keep them a reasonable file size for a smoother upload.</p>
                                </div>
                            </div>
                        </div>
                    </section>

                    <section id="contact" class="help-section">
                        <h2>Still need help?</h2>
                        <p>Can't find what you're looking for? Reach out and we'll help you sort it out.</p>
                        <a href="mailto:support@leykun.example" class="btn-primary">Email Support</a>
                    </section>

                </div>
            </div>

    <script>
        // Highlight the current section in the "On this page" nav as you scroll.
        const sections = document.querySelectorAll('.help-section');
        const tocLinks = document.querySelectorAll('.toc-link');

        const tocObserver = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting) {
                    const id = entry.target.getAttribute('id');
                    tocLinks.forEach((link) => {
                        link.classList.toggle('active', link.getAttribute('href') === '#' + id);
                    });
                }
            });
        }, { rootMargin: '-15% 0px -70% 0px' });

        sections.forEach((section) => tocObserver.observe(section));

        // FAQ accordion — click a question to reveal its answer.
        document.querySelectorAll('.faq-question').forEach((btn) => {
            btn.addEventListener('click', () => {
                const item = btn.closest('.faq-item');
                const isOpen = item.classList.toggle('open');
                btn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    </script>

<?php require_once 'includes/layout_footer.php'; ?>