console.log('📦📦📦 app.js STARTING 📦📦📦');
console.log('📍 Page:', typeof window !== 'undefined' ? window.location.pathname : 'N/A');

import './bootstrap';
console.log('✅ bootstrap imported');

import './twilio-sdk';
import './twilio-call';

// Load twilio-global so incoming calls work on every page
console.log('📦 app.js: About to import twilio-global...');
import './twilio-global';
console.log('📦 app.js: twilio-global import statement completed');

import './contact-history-panel';
console.log('📦 app.js: contact-history-panel imported');

console.log('📦📦📦 app.js COMPLETED 📦📦📦');
