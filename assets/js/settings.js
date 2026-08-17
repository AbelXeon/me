function togglePlatform(platform) {
    if (platform !== 'telegram') return;

    const telegramBody = document.getElementById('telegram-body');

    if (telegramBody) {
        telegramBody.classList.toggle('open');
    }
}


// Auto-open Telegram's form if it isn't connected yet
document.addEventListener('DOMContentLoaded', function () {
    const telegramBody = document.getElementById('telegram-body');

    if (telegramBody && telegramBody.dataset.connected === '0') {
        telegramBody.classList.add('open');
    }
});