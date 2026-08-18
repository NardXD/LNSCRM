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
            input: ['resources/css/app.css', 'resources/js/app.js'],
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
