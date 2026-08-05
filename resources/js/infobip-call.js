import { createInfobipRtc } from 'infobip-rtc';

// Expose factory for call.blade.php and other softphone scripts
window.createInfobipRtc = createInfobipRtc;
window.InfobipRtcSDK = { createInfobipRtc };
