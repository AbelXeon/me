function togglePlatform(platform) {
    document.getElementById(platform + '-body').classList.toggle('active');
}

// Auto-open Telegram's form if it isn't connected yet, so it's visible immediately
document.addEventListener('DOMContentLoaded', function () {
    const telegramBody = document.getElementById('telegram-body');
    if (telegramBody && telegramBody.dataset.connected === '0') {
        telegramBody.classList.add('active');
    }
});