/**
 * Notification sounds for live view events (worker side).
 */
window.LiveViewNotify = (function () {
    let audioContext = null;
    let primed = false;

    function getContext() {
        const AudioCtx = window.AudioContext || window.webkitAudioContext;
        if (!AudioCtx) {
            return null;
        }

        if (!audioContext) {
            audioContext = new AudioCtx();
        }

        return audioContext;
    }

    function prime() {
        const ctx = getContext();
        if (!ctx || primed) {
            return;
        }

        primed = true;

        if (ctx.state === 'suspended') {
            ctx.resume().catch(() => {});
        }
    }

    function playTone(ctx, frequency, startTime, duration, volume = 0.2) {
        const oscillator = ctx.createOscillator();
        const gain = ctx.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.setValueAtTime(frequency, startTime);

        gain.gain.setValueAtTime(0, startTime);
        gain.gain.linearRampToValueAtTime(volume, startTime + 0.02);
        gain.gain.exponentialRampToValueAtTime(0.001, startTime + duration);

        oscillator.connect(gain);
        gain.connect(ctx.destination);

        oscillator.start(startTime);
        oscillator.stop(startTime + duration + 0.05);
    }

    async function playAudioRequestSound() {
        const ctx = getContext();
        if (!ctx) {
            return;
        }

        try {
            if (ctx.state === 'suspended') {
                await ctx.resume();
            }

            const now = ctx.currentTime;
            playTone(ctx, 880, now, 0.18);
            playTone(ctx, 1175, now + 0.22, 0.22);
            playTone(ctx, 880, now + 0.44, 0.18);
        } catch (error) {
            console.warn('Live view audio request sound failed', error);
        }
    }

    if (typeof document !== 'undefined') {
        document.addEventListener('click', prime, { once: true, capture: true });
        document.addEventListener('keydown', prime, { once: true, capture: true });
    }

    return {
        prime,
        playAudioRequestSound,
    };
})();
