<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once 'includes/auth_check.php';
requireLogin(); 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Social Media Manager</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <style>
        /* Blurred Modal Styling */
        .modal-overlay {
            display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%;
            background: rgba(0,0,0,0.6); backdrop-filter: blur(10px);
            justify-content: center; align-items: center; z-index: 9999;
        }
        .modal-card { background: white; padding: 40px; border-radius: 20px; width: 90%; max-width: 400px; text-align: center; }
        .modal-card input { width: 100%; padding: 12px; margin: 10px 0; border: 1px solid #ddd; border-radius: 6px; }
        .btn-action { width: 100%; padding: 12px; background: #667eea; color: white; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-top: 10px; }
        .btn-cancel { background: none; border: none; color: #666; cursor: pointer; margin-top: 15px; }
        .link-change-pass { color: #667eea; text-decoration: none; font-size: 14px; font-weight: bold; cursor: pointer; }
    </style>
</head>

<body>
    <div class="header">
        <h1>📱 Social Media Manager</h1>
        <div class="user-info">
            <span>Welcome, <strong><?php echo htmlspecialchars(getCurrentUsername()); ?></strong></span>
            <!-- Change Password Link -->
            <a href="javascript:void(0)" onclick="openChangePassModal()" class="link-change-pass">Change Password</a>
            <a href="logout.php" class="btn-logout">Logout</a>
        </div>
    </div>

    <div class="container">
        <div class="welcome-card">
            <h2>Welcome to Your Social Media Dashboard!</h2>
            <p>Manage all your social media accounts from one place</p>
        </div>

        <div class="nav-cards">
            <a href="create-post.php" class="nav-card">
                <div class="icon">✍️</div>
                <h3>Create Post</h3>
                <p>Create and publish posts to multiple platforms</p>
            </a>
            <a href="settings.php" class="nav-card">
                <div class="icon">⚙️</div>
                <h3>Platform Settings</h3>
                <p>Connect your social media accounts</p>
            </a>
            <a href="post-history.php" class="nav-card">
                <div class="icon">📊</div>
                <h3>Post History</h3>
                <p>View all your published and scheduled posts</p>
            </a>
        </div>
    </div>

    <!-- CHANGE PASSWORD MODAL -->
    <div id="changePassModal" class="modal-overlay">
        <div class="modal-card">
            <h2>Change Password</h2>
            <p>Enter your details below to update your password.</p>
            
            <input type="password" id="curr_pass" placeholder="Current Password">
            <input type="password" id="new_pass" placeholder="New Password (8+ chars)">
            <input type="password" id="confirm_new_pass" placeholder="Confirm New Password">
            
            <button type="button" onclick="submitChangePassword()" class="btn-action" id="changeBtn">Update Password</button>
            <button type="button" onclick="closeChangePassModal()" class="btn-cancel">Cancel</button>
        </div>
    </div>

    <script>
        function openChangePassModal() { document.getElementById('changePassModal').style.display = 'flex'; }
        function closeChangePassModal() { 
            document.getElementById('changePassModal').style.display = 'none'; 
            // Clear inputs
            document.getElementById('curr_pass').value = '';
            document.getElementById('new_pass').value = '';
            document.getElementById('confirm_new_pass').value = '';
        }

        function submitChangePassword() {
            const curr = document.getElementById('curr_pass').value;
            const np = document.getElementById('new_pass').value;
            const cnp = document.getElementById('confirm_new_pass').value;
            const btn = document.getElementById('changeBtn');

            if(!curr || !np || !cnp) { alert("Please fill all fields"); return; }
            if(np !== cnp) { alert("New passwords do not match"); return; }
            if(np.length < 8) { alert("New password must be at least 8 characters"); return; }

            btn.innerText = "Updating...";
            btn.disabled = true;

            fetch('auth_ajax.php?action=change_password', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `current_password=${encodeURIComponent(curr)}&new_password=${encodeURIComponent(np)}`
            })
            .then(r => r.json())
            .then(data => {
                btn.innerText = "Update Password";
                btn.disabled = false;
                if(data.success) {
                    alert("Password changed successfully!");
                    closeChangePassModal();
                } else {
                    alert(data.message);
                }
            })
            .catch(err => {
                alert("Error connecting to server");
                btn.disabled = false;
            });
        }
    </script>
</body>
</html>