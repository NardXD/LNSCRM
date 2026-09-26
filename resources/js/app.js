import './bootstrap';

const flags = window.__crmFlags || {};

async function bootPhone() {
    if (!flags.phone) {
        return;
    }

    await import('./twilio-sdk');
    await import('./twilio-call');
    await import('./twilio-global');
}

async function bootContactHistory() {
    if (window.LnsContactHistory?.load) {
        if (!window.loadChannelContactHistory) {
            window.loadChannelContactHistory = (selector, opts) =>
                window.LnsContactHistory.load(selector, opts);
        }
        return;
    }

    // Inbox and dedicated history pages need the shared module.
    if (!document.querySelector('.chp-panel, #inboxContactHistory, #contactHistoryApp')) {
        return;
    }

    await import('./contact-history-panel');
}

Promise.all([bootPhone(), bootContactHistory()]).catch((err) => {
    console.error('CRM boot failed', err);
});
