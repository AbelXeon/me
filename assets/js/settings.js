function togglePlatform(platform) {
    if (platform !== 'telegram') return;

    const telegramBody = document.getElementById('telegram-body');

    if (!telegramBody) return;

    telegramBody.classList.toggle('open');

    if (telegramBody.classList.contains('open')) {
        telegramBody.style.maxHeight = telegramBody.scrollHeight + 'px';
    } else {
        telegramBody.style.maxHeight = '0px';
    }
}


document.addEventListener('DOMContentLoaded', function () {
    const telegramBody = document.getElementById('telegram-body');

    if (!telegramBody) return;

    if (telegramBody.dataset.connected === '0') {
        telegramBody.classList.add('open');
        telegramBody.style.maxHeight = telegramBody.scrollHeight + 'px';
    }
});