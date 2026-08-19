<?php

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - LEYKUN</title>

    <link rel="stylesheet" href="assets/css/layout.css">

    <?php if (!empty($pageCss)): ?>
    <link rel="stylesheet" href="<?php echo htmlspecialchars($pageCss); ?>">
    <?php endif; ?>
</head>

<body>

    <div class="app-shell">

        <!-- ===================== SIDEBAR OVERLAY (mobile) ===================== -->
        <div class="sidebar-overlay" id="sidebarOverlay" onclick="closeSidebar()"></div>


        <!-- ===================== SIDEBAR ===================== -->
        <aside class="sidebar" id="sidebar">

            <div class="sidebar-brand">

                <div class="brand-mark">
                    <svg viewBox="0 0 24 24" fill="none" stroke-width="2"
                         stroke-linecap="round" stroke-linejoin="round">
                        <path d="M12 2a10 10 0 1 0 10 10"/>
                        <path d="M12 6a6 6 0 1 0 6 6"/>
                        <circle cx="12" cy="12" r="1.5"
                                fill="currentColor" stroke="none"/>
                    </svg>
                </div>

                <div class="sidebar-brand-text">
                    <div class="brand-name">LEYKUN</div>
                    <div class="brand-tagline">Social Media Management</div>
                </div>

                <button type="button"
                        class="sidebar-close"
                        onclick="closeSidebar()"
                        aria-label="Close menu">

                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round">
                        <path d="M18 6 6 18"/>
                        <path d="M6 6l12 12"/>
                    </svg>

                </button>

            </div>


            <div class="sidebar-section-label">Menu</div>

            <ul class="nav-list">

                <!-- ===================== DASHBOARD ===================== -->
                <li>
                    <a href="dashboard.php"
                       class="nav-item <?php echo ($activeNav ?? '') === 'dashboard' ? 'active' : ''; ?>">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <rect x="3" y="3" width="7" height="9" rx="1.5"/>
                            <rect x="14" y="3" width="7" height="5" rx="1.5"/>
                            <rect x="14" y="12" width="7" height="9" rx="1.5"/>
                            <rect x="3" y="16" width="7" height="5" rx="1.5"/>

                        </svg>

                        Dashboard
                    </a>
                </li>


                <!-- ===================== CREATE POST ===================== -->
                <li>
                    <a href="create-post.php"
                       class="nav-item <?php echo ($activeNav ?? '') === 'create-post' ? 'active' : ''; ?>">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <path d="M12 20h9"/>
                            <path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4Z"/>

                        </svg>

                        Create Post
                    </a>
                </li>


                <!-- ===================== PLATFORM SETTINGS ===================== -->
                <li>
                    <a href="settings.php"
                       class="nav-item <?php echo ($activeNav ?? '') === 'settings' ? 'active' : ''; ?>">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <circle cx="12" cy="12" r="3"/>

                            <path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.68 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 19.4 9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>

                        </svg>

                        Platform Settings
                    </a>
                </li>


                <!-- ===================== POST HISTORY ===================== -->
                <li>
                    <a href="post-history.php"
                       class="nav-item <?php echo ($activeNav ?? '') === 'post-history' ? 'active' : ''; ?>">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <path d="M3 3v18h18"/>
                            <rect x="7" y="12" width="3" height="6" rx="0.5"/>
                            <rect x="12.5" y="8" width="3" height="10" rx="0.5"/>
                            <rect x="18" y="5" width="3" height="13" rx="0.5"/>

                        </svg>

                        Post History
                    </a>
                </li>


                <!-- ===================== HELP & SUPPORT ===================== -->
                <li>
                    <a href="help-support.php"
                       class="nav-item <?php echo ($activeNav ?? '') === 'help-support' ? 'active' : ''; ?>">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <circle cx="12" cy="12" r="9"/>

                            <path d="M9.5 9a2.5 2.5 0 1 1 4.5 1.5c-.8.8-2 1.2-2 2.5"/>

                            <path d="M12 17h.01"/>

                        </svg>

                        Help &amp; Support
                    </a>
                </li>


                <!-- ===================== ACCOUNT SETTINGS ===================== -->
                <li>
                    <a href="account-settings.php"
                       class="nav-item <?php echo ($activeNav ?? '') === 'account-settings' ? 'active' : ''; ?>">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <circle cx="12" cy="8" r="4"/>
                            <path d="M4 21a8 8 0 0 1 16 0"/>

                        </svg>

                        Account Settings
                    </a>
                </li>

            </ul>


            <!-- ===================== SIDEBAR FOOTER ===================== -->
            <div class="sidebar-footer">

                <a href="logout.php" class="btn-logout">

                    <svg viewBox="0 0 24 24"
                         fill="none"
                         stroke-width="2"
                         stroke-linecap="round"
                         stroke-linejoin="round">

                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/>
                        <path d="M16 17l5-5-5-5"/>
                        <path d="M21 12H9"/>

                    </svg>

                    Logout

                </a>

            </div>

        </aside>


        <!-- ===================== MAIN CONTENT ===================== -->
        <main class="main">

            <div class="topbar">

                <div class="topbar-left">

                    <button type="button"
                            class="hamburger-btn"
                            onclick="openSidebar()"
                            aria-label="Open menu">

                        <svg viewBox="0 0 24 24"
                             fill="none"
                             stroke-width="2"
                             stroke-linecap="round"
                             stroke-linejoin="round">

                            <path d="M3 6h18"/>
                            <path d="M3 12h18"/>
                            <path d="M3 18h18"/>

                        </svg>

                    </button>


                    <div>

                        <h1>
                            <?php echo htmlspecialchars($topbarTitle ?? $pageTitle); ?>
                        </h1>

                        <?php if (!empty($showBackBtn)): ?>

                            <a href="dashboard.php" class="back-btn">

                                <svg viewBox="0 0 24 24"
                                     fill="none"
                                     stroke-width="2"
                                     stroke-linecap="round"
                                     stroke-linejoin="round">

                                    <path d="M19 12H5"/>
                                    <path d="M12 19l-7-7 7-7"/>

                                </svg>

                                Back to Dashboard

                            </a>

                        <?php endif; ?>

                    </div>

                </div>


                <!-- ===================== USER INFO ===================== -->
                <div class="user-info">

                    <span class="welcome">
                        Welcome,
                        <strong>
                            <?php echo htmlspecialchars(getCurrentUsername()); ?>
                        </strong>
                    </span>

                </div>

            </div>