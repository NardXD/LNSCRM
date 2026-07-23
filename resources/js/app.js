console.log('📦📦📦 app.js STARTING 📦📦📦');
console.log('📍 Page:', typeof window !== 'undefined' ? window.location.pathname : 'N/A');

import './bootstrap';
console.log('✅ bootstrap imported');

// Load twilio-global FIRST so it's available everywhere
console.log('📦 app.js: About to import twilio-global...');
import './twilio-global';
console.log('📦 app.js: twilio-global import statement completed');

// Then load twilio-call (which might have its own device)
console.log('📦 app.js: About to import twilio-call...');
import './twilio-call';
console.log('📦 app.js: twilio-call import statement completed');

console.log('📦📦📦 app.js COMPLETED 📦📦📦');
