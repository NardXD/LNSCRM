function getTwilioDeviceClass() {
    return window.TwilioVoiceSDK?.Device || window.Twilio?.Device || null;
}

function bindTwilioVoiceSdk() {
    const Device = getTwilioDeviceClass();
    if (!Device) {
        return false;
    }

    if (!window.TwilioVoiceSDK) {
        window.TwilioVoiceSDK = { Device };
    }

    window.dispatchEvent(new Event('twilio-voice-sdk-ready'));

    return true;
}

function whenTwilioVoiceSdkReady(timeoutMs = 20000) {
    return new Promise((resolve, reject) => {
        if (bindTwilioVoiceSdk()) {
            resolve(getTwilioDeviceClass());
            return;
        }

        const started = Date.now();
        const onReady = () => {
            if (bindTwilioVoiceSdk()) {
                cleanup();
                resolve(getTwilioDeviceClass());
            } else if (Date.now() - started >= timeoutMs) {
                cleanup();
                reject(new Error('Twilio Voice SDK failed to load'));
            }
        };

        const timer = setInterval(onReady, 50);
        window.addEventListener('twilio-voice-sdk-ready', onReady);
        window.addEventListener('load', onReady);

        function cleanup() {
            clearInterval(timer);
            window.removeEventListener('twilio-voice-sdk-ready', onReady);
            window.removeEventListener('load', onReady);
        }
    });
}

window.getTwilioDeviceClass = getTwilioDeviceClass;
window.whenTwilioVoiceSdkReady = whenTwilioVoiceSdkReady;
bindTwilioVoiceSdk();

export { getTwilioDeviceClass, bindTwilioVoiceSdk, whenTwilioVoiceSdkReady };
