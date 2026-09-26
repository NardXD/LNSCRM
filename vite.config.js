import { copyFileSync, mkdirSync } from 'fs';
import { resolve } from 'path';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

function copyTwilioVoiceSdk() {
    const copy = () => {
        mkdirSync(resolve('public/vendor'), { recursive: true });
        copyFileSync(
            resolve('node_modules/@twilio/voice-sdk/dist/twilio.min.js'),
            resolve('public/vendor/twilio-voice.min.js')
        );
    };

    return {
        name: 'copy-twilio-voice-sdk',
        buildStart: copy,
        closeBundle: copy,
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/css/dashboard.css',
                'resources/css/pages/inbox.css',
                'resources/css/pages/leads.css',
                'resources/css/pages/billing.css',
                'resources/css/pages/integrations.css',
                'resources/css/pages/messaging.css',
                'resources/css/pages/user-management.css',
                'resources/css/pages/payroll.css',
                'resources/css/pages/client-management.css',
                'resources/css/pages/project-management.css',
                'resources/css/pages/team-management.css',
                'resources/css/pages/knowledge-base.css',
                'resources/css/pages/admin-control.css',
                'resources/css/pages/time-tracking.css',
                'resources/css/pages/broadcast-messaging.css',
                'resources/css/pages/employee-monitoring.css',
                'resources/css/pages/email-tracking.css',
                'resources/css/pages/leave-management.css',
                'resources/css/pages/tickets.css',
                'resources/css/pages/calendar.css',
                'resources/css/pages/discussions.css',
                'resources/css/pages/contracts.css',
                'resources/css/pages/hiring-queue.css',
                'resources/css/pages/openai.css',
                'resources/css/pages/wise-recipients.css',
                'resources/css/pages/facebook.css',
                'resources/css/pages/whatsapp.css',
                'resources/css/pages/sms.css',
                'resources/css/pages/viber.css',
                'resources/js/app.js',
                'resources/js/realtime.js',
                'resources/js/pages/inbox.js',
                'resources/js/pages/leads.js',
                'resources/js/pages/billing.js',
                'resources/js/pages/integrations.js',
                'resources/js/pages/messaging.js',
                'resources/js/pages/user-management.js',
                'resources/js/pages/payroll.js',
                'resources/js/pages/client-management.js',
                'resources/js/pages/project-management.js',
                'resources/js/pages/team-management.js',
                'resources/js/pages/knowledge-base.js',
                'resources/js/pages/admin-control.js',
                'resources/js/pages/time-tracking.js',
                'resources/js/pages/broadcast-messaging.js',
                'resources/js/pages/employee-monitoring.js',
                'resources/js/pages/email-tracking.js',
                'resources/js/pages/leave-management.js',
                'resources/js/pages/tickets.js',
                'resources/js/pages/calendar.js',
                'resources/js/pages/discussions.js',
                'resources/js/pages/contracts.js',
                'resources/js/pages/hiring-queue.js',
                'resources/js/pages/openai.js',
                'resources/js/pages/wise-recipients.js',
                'resources/js/pages/facebook.js',
                'resources/js/pages/whatsapp.js',
                'resources/js/pages/sms.js',
                'resources/js/pages/viber.js',
            ],
            refresh: true,
        }),
        tailwindcss(),
        copyTwilioVoiceSdk(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
