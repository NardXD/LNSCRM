(function(){const b=window.__integrationsConfig||{},S={voiceWebhook:b.voiceWebhook||"",smsWebhook:b.smsWebhook||"",phoneSystemUrl:b.phoneSystemUrl||"",viberChatUrl:b.viberChatUrl||"",whatsappChatUrl:b.whatsappChatUrl||"",facebookChatUrl:b.facebookChatUrl||""},u=[{id:"paypal",name:"PayPal",description:"Accept payments via PayPal. Process transactions securely and manage your PayPal account.",category:"payment",icon:"💳",status:"connected",features:["Payment processing","Refund management","Transaction history","Webhook support"]},{id:"stripe",name:"Stripe",description:"Accept credit card payments with Stripe. Secure payment processing with global support.",category:"payment",icon:"💳",status:"disconnected",features:["Credit card processing","Subscription billing","Payment intents","3D Secure"]},{id:"wise",name:"Wise",description:"Send and receive international payments with Wise. Low-cost transfers in 50+ currencies.",category:"payment",icon:"🌍",status:"disconnected",features:["International transfers","Multi-currency accounts","Batch payments","Real-time exchange rates"]},{id:"gmail",name:"Gmail",description:"Connect your Gmail account to send emails (e.g. quotations, invoices). Uses Gmail SMTP with App Password.",category:"communication",icon:"📧",status:"disconnected",features:["Send emails via SMTP","Quotation emails","Invoice emails","Company-wide configuration"]},{id:"google-login",name:"Google Login",description:"Enable users to sign in with their Google account. Quick and secure authentication.",category:"productivity",icon:"🔐",status:"connected",features:["OAuth 2.0","Single sign-on","Account linking","Security compliance"]},{id:"openai",name:"OpenAI",description:"AI-powered assistance using OpenAI. Get intelligent responses and automation suggestions.",category:"automation",icon:"🤖",status:"disconnected",features:["Chat assistance","Content generation","Smart suggestions","Text analysis"]},{id:"twilio",name:"Twilio",description:"Connect your Twilio account for phone, WhatsApp, Viber, Facebook Messenger, and SMS using standard Twilio APIs (Voice, Messages).",category:"communication",icon:"📞",status:"disconnected",features:["Phone system","WhatsApp, Viber & Messenger","SMS","Browser calling","Call logging"]},{id:"viber",name:"Viber Business",description:"Send and receive Viber messages through your Twilio account using a Viber Business Sender.",category:"communication",icon:"💬",status:"disconnected",features:["1:1 chat via Twilio","Images & files","Welcome message","Webhook callbacks","Open / call in Viber"]},{id:"whatsapp",name:"WhatsApp Business",description:"Send and receive WhatsApp messages through your Twilio account using a WhatsApp-enabled sender number.",category:"communication",icon:"📱",status:"disconnected",features:["1:1 chat via Twilio","Images & documents","Webhook callbacks","24h messaging window","Open in WhatsApp"]},{id:"facebook",name:"Facebook & Instagram",description:"Facebook Messenger via Twilio, plus Instagram Direct via native Meta webhooks.",category:"communication",icon:"📘",status:"disconnected",features:["Twilio Messenger","Instagram Direct via Meta","Images & files","Meta webhooks","Welcome message"]},{id:"calendar",name:"Google Calendar",description:"Configure Google Calendar OAuth so users can connect their calendars from the Calendar page.",category:"productivity",icon:"📅",status:"disconnected",features:["Google Calendar sync","Per-company OAuth credentials","Connect from Calendar page"]},{id:"outlook",name:"Microsoft Outlook",description:"Configure Microsoft Outlook OAuth for Inbox mail (personal & shared mailboxes). Calendar uses the same personal Inbox account.",category:"productivity",icon:"📧",status:"disconnected",features:["Outlook Inbox / shared mail","Personal calendar via Inbox account","Per-company OAuth credentials","Personal & shared mailbox connection"]},{id:"storeganise",name:"Storeganise",description:"Connect your Storeganise self-storage platform. Sync unit rentals, sites, and storage operations with the CRM.",category:"productivity",icon:"🏢",status:"disconnected",features:["Unit rental sync","Site management","Admin API access","Webhook notifications"]},{id:"front",name:"Front.com",description:"One-time import of Front conversation tags, internal comments, and discussion threads into LNSCRM. Connect your Front API token, map inboxes, and run the imports.",category:"communication",icon:"🏷️",status:"disconnected",features:["Import inbox tags","Import internal comments","Import discussion threads","Inbox mapping","Dry-run preview","Import history"]}];let g="all",m=null,T=0;function h(t="all"){const e=document.getElementById("integrationsGrid"),o=t==="all"?u:u.filter(n=>n.category===t);e.innerHTML=o.map(n=>`
            <div class="integration-card" onclick="openIntegrationModal('${n.id}')">
                <div class="integration-header">
                    <div class="integration-icon-wrapper ${n.id}">
                        ${n.icon}
                    </div>
                    <span class="integration-status ${n.status}">
                        ${n.status==="connected"?"Connected":"Not Connected"}
                    </span>
                </div>
                <h3 class="integration-name">${n.name}</h3>
                <p class="integration-description">${n.description}</p>
                <div class="integration-footer">
                    <span class="integration-category">${n.category}</span>
                    <button class="integration-action" onclick="event.stopPropagation(); openIntegrationModal('${n.id}')">
                        ${n.status==="connected"?"Configure":"Connect"}
                    </button>
                </div>
            </div>
        `).join("")}document.querySelectorAll(".category-btn").forEach(t=>{t.addEventListener("click",function(){document.querySelectorAll(".category-btn").forEach(e=>e.classList.remove("active")),this.classList.add("active"),g=this.dataset.category,h(g)})});async function me(t){if(t==="gmail"){try{const e=await fetch("/api/integrations/gmail");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="gmail");if(n)return n.status=o.status||(o.integration.is_active?"connected":"disconnected"),o.integration}}catch(e){console.error("Error loading Gmail integration:",e)}return null}if(t==="wise"){try{const e=await fetch("/api/integrations/wise");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="wise");if(n)return n.status=o.status||(o.integration.is_active?"connected":"disconnected"),o.integration}}catch(e){console.error("Error loading Wise integration:",e)}return null}if(t==="twilio"){try{const e=await fetch("/api/integrations/twilio");if(!e.ok)return null;const o=await e.json(),n=u.find(r=>r.id==="twilio");return n&&(n.status=o.status??"disconnected"),o.integration?{...o.integration,status:o.status??"disconnected",missing_fields:o.missing_fields||[]}:{status:o.status??"disconnected",missing_fields:o.missing_fields||[]}}catch(e){console.error("Error loading Twilio integration:",e)}return null}if(t==="viber"){try{const e=await fetch("/api/integrations/viber");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="viber");return n&&(n.status=o.status??"disconnected"),{...o.integration,status:o.status??"disconnected"}}}catch(e){console.error("Error loading Viber integration:",e)}return null}if(t==="whatsapp"){try{const e=await fetch("/api/integrations/whatsapp");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="whatsapp");return n&&(n.status=o.status??"disconnected"),{...o.integration,status:o.status??"disconnected"}}}catch(e){console.error("Error loading WhatsApp integration:",e)}return null}if(t==="facebook"){try{const e=await fetch("/api/integrations/facebook");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="facebook");return n&&(n.status=o.status??"disconnected"),{...o.integration,status:o.status??"disconnected"}}}catch(e){console.error("Error loading Facebook integration:",e)}return null}if(t==="openai"){try{const e=await fetch("/api/integrations/openai");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="openai");return n&&(n.status=o.status||(o.integration.is_active?"connected":"disconnected")),o.integration}}catch(e){console.error("Error loading OpenAI integration:",e)}return null}if(t==="storeganise"){try{const e=await fetch("/api/integrations/storeganise");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="storeganise");return n&&(n.status=o.status||(o.integration.is_active?"connected":"disconnected")),o.integration}}catch(e){console.error("Error loading Storeganise integration:",e)}return null}if(t==="front"){try{const e=await fetch("/api/integrations/front");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="front");return n&&(n.status=o.status||(o.integration.is_active?"connected":"disconnected")),{...o.integration,status:o.status??"disconnected"}}}catch(e){console.error("Error loading Front integration:",e)}return null}if(t==="stripe")try{const e=await fetch("/api/integrations/stripe");if(!e.ok)return null;const o=await e.json();if(o.integration){const n=u.find(r=>r.id==="stripe");if(n)return n.status=o.status||(o.integration.is_active&&o.integration.secret_key?"connected":"disconnected"),o.integration}}catch(e){console.error("Error loading Stripe integration:",e)}if(t==="calendar"){try{const e=await fetch(b.calendarOauthSettingsUrl||"/api/calendar/oauth-settings");if(!e.ok)return null;const o=await e.json(),n=u.find(r=>r.id==="calendar");return n&&(n.status=o.google_configured?"connected":"disconnected"),o}catch(e){console.error("Error loading Google Calendar OAuth settings:",e)}return null}if(t==="outlook"){try{const e=await fetch(b.calendarOauthSettingsUrl||"/api/calendar/oauth-settings");if(!e.ok)return null;const o=await e.json(),n=u.find(r=>r.id==="outlook");return n&&(n.status=o.outlook_configured?"connected":"disconnected"),o}catch(e){console.error("Error loading Outlook OAuth settings:",e)}return null}return null}async function N(t){const e=u.find(s=>s.id===t);if(!e)return;m=e;let o=null;(t==="gmail"||t==="twilio"||t==="viber"||t==="whatsapp"||t==="facebook"||t==="wise"||t==="stripe"||t==="openai"||t==="storeganise"||t==="front"||t==="calendar"||t==="outlook")&&(o=await me(t)),window.existingIntegration=o,document.getElementById("modalIcon").innerHTML=`
            <div class="integration-icon-wrapper ${e.id}" style="width: 64px; height: 64px; font-size: 2rem;">
                ${e.icon}
            </div>
        `,document.getElementById("modalName").textContent=e.name,document.getElementById("modalDescription").textContent=e.description,o?.status&&(e.status=o.status);const n=e.status==="connected"?'<span class="integration-status connected">Connected</span>':'<span class="integration-status disconnected">Not Connected</span>';document.getElementById("modalStatus").innerHTML=n;const r=`
            <div class="details-section">
                <div class="details-title">Features</div>
                <div class="details-list">
                    ${e.features.map(s=>`
                        <div class="detail-item">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                            <span>${s}</span>
                        </div>
                    `).join("")}
                </div>
            </div>
        `;document.getElementById("integrationDetails").innerHTML=r;let i="";const l={account_sid:"Account SID",auth_token:"Auth Token",app_sid:"App SID",api_key:"API Key",api_secret:"API Secret"},a=e.id==="twilio"&&o?.missing_fields?.length?o.missing_fields.map(s=>l[s]||s):[];e.status==="connected"?i=`
                <div class="connected-info">
                    <div class="connected-info-title">✓ Successfully Connected</div>
                    <div class="connected-info-text">This integration is active and working properly.</div>
                </div>
                <div class="config-form">
                    ${$(e.id,o)}
                </div>
            `:e.id==="twilio"&&o?.account_sid?i=`
                <div class="connected-info" style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.35);">
                    <div class="connected-info-title" style="color: #b45309;">Incomplete Twilio configuration</div>
                    <div class="connected-info-text">Saved settings are missing required values${a.length?": "+a.join(", "):""}. Fill in all fields and save to verify with Twilio.</div>
                </div>
                <div class="config-form">
                    ${$(e.id,o)}
                </div>
            `:e.id==="front"&&o?.has_token?i=`
                <div class="connected-info" style="background: rgba(245, 158, 11, 0.12); border-color: rgba(245, 158, 11, 0.35);">
                    <div class="connected-info-title" style="color: #b45309;">⚠ Token saved but not verified</div>
                    <div class="connected-info-text" style="color:#b45309;">Front rejected the last check${o.verify_error?": "+y(o.verify_error):"."} Paste a fresh API token and save to reconnect.</div>
                </div>
                <div class="config-form">
                    ${$(e.id,o)}
                </div>
            `:i=`
                <div class="config-form">
                    ${$(e.id,o)}
                </div>
            `,document.getElementById("integrationConfig").innerHTML=i;const c=document.getElementById("modalActionBtn"),p=document.querySelector(".modal-footer");if(e.status==="connected"&&t==="wise"){c.textContent="Save",c.className="btn-primary",c.onclick=()=>ge();const s=p?.querySelector(".wise-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".wise-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger wise-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect Wise?")&&fe()},p.insertBefore(d,c)}}else if(e.status==="connected"&&t==="stripe"){c.textContent="Save",c.className="btn-primary",c.onclick=()=>H();const s=p?.querySelector(".stripe-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".stripe-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger stripe-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect Stripe?")&&he()},p.insertBefore(d,c)}}else if(e.status==="connected"&&t==="openai"){c.textContent="Save",c.className="btn-primary",c.onclick=()=>K();const s=p?.querySelector(".openai-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".openai-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger openai-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect OpenAI?")&&Se()},p.insertBefore(d,c)}}else if(e.status==="connected"&&t==="storeganise"){c.textContent="Save",c.className="btn-primary",c.onclick=()=>G();const s=p?.querySelector(".storeganise-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".storeganise-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger storeganise-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect Storeganise?")&&be()},p.insertBefore(d,c)}}else if((e.status==="connected"||o?.has_token)&&t==="front"){c.textContent="Save token",c.className="btn-primary",c.onclick=()=>I(!1);const s=p?.querySelector(".front-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".front-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger front-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect Front?")&&ve()},p.insertBefore(d,c)}}else if(e.status==="connected"&&t==="twilio"){c.textContent="Save",c.className="btn-primary",c.onclick=()=>q();const s=p?.querySelector(".twilio-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".twilio-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger twilio-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect Twilio?")&&U()},p.insertBefore(d,c)}}else if(e.status==="connected"&&t==="facebook"){c.textContent="Save",c.className="btn-primary",c.onclick=()=>ce();const s=p?.querySelector(".facebook-disconnect-btn");if(s&&(s.style.display=""),p&&!p.querySelector(".facebook-disconnect-btn")){const d=document.createElement("button");d.className="btn-secondary btn-danger facebook-disconnect-btn",d.textContent="Disconnect",d.onclick=()=>{confirm("Disconnect Facebook?")&&C()},p.insertBefore(d,c)}}else t==="calendar"?(c.textContent="Save settings",c.className="btn-primary",c.onclick=()=>le("google"),p?.querySelectorAll(".wise-disconnect-btn, .gmail-disconnect-btn, .stripe-disconnect-btn, .openai-disconnect-btn, .storeganise-disconnect-btn, .front-disconnect-btn, .twilio-disconnect-btn, .facebook-disconnect-btn").forEach(s=>{s&&(s.style.display="none")})):t==="outlook"?(c.textContent="Save settings",c.className="btn-primary",c.onclick=()=>le("outlook"),p?.querySelectorAll(".wise-disconnect-btn, .gmail-disconnect-btn, .stripe-disconnect-btn, .openai-disconnect-btn, .storeganise-disconnect-btn, .front-disconnect-btn, .twilio-disconnect-btn, .facebook-disconnect-btn").forEach(s=>{s&&(s.style.display="none")})):p?.querySelectorAll(".wise-disconnect-btn, .gmail-disconnect-btn, .stripe-disconnect-btn, .openai-disconnect-btn, .storeganise-disconnect-btn, .front-disconnect-btn, .twilio-disconnect-btn, .facebook-disconnect-btn").forEach(s=>{s&&(s.style.display="none")});if(t==="calendar"||t==="outlook"||t==="front"&&o?.has_token||(e.status==="connected"&&t!=="wise"&&t!=="gmail"&&t!=="stripe"&&t!=="openai"&&t!=="storeganise"&&t!=="front"&&t!=="twilio"&&t!=="facebook"?(c.textContent="Disconnect",c.className="btn-primary btn-danger",c.onclick=()=>C()):(e.status!=="connected"||t!=="wise"&&t!=="stripe"&&t!=="openai"&&t!=="storeganise"&&t!=="front"&&t!=="twilio"&&t!=="facebook")&&(c.textContent="Connect",c.className="btn-primary",c.onclick=()=>C())),t==="wise"){const s=document.getElementById("modalActionBtn");s&&(s.disabled=!0,s.style.opacity="0.5",s.style.cursor="not-allowed"),ue()}document.getElementById("integrationModal").classList.add("active"),document.body.style.overflow="hidden",t==="front"&&e.status==="connected"&&Y(o)}function $(t,e=null){return{paypal:`
                <div class="form-group">
                    <label class="form-label">PayPal Client ID</label>
                    <input type="text" class="form-input" placeholder="Enter PayPal Client ID" value="AK-1234567890">
                    <span class="form-help">Find this in your PayPal Developer Dashboard</span>
                </div>
                <div class="form-group">
                    <label class="form-label">PayPal Secret</label>
                    <input type="password" class="form-input" placeholder="Enter PayPal Secret" value="••••••••">
                </div>
            `,stripe:`
                <div class="form-group">
                    <label class="form-label">Stripe Publishable Key</label>
                    <input type="text" class="form-input" id="stripe-publishable-key" placeholder="pk_live_... or pk_test_..." value="${e&&e.publishable_key?e.publishable_key:""}">
                    <span class="form-help">Find this in your Stripe Dashboard under Developers → API keys</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Stripe Secret Key</label>
                    <input type="password" class="form-input" id="stripe-secret-key" placeholder="sk_live_... or sk_test_..." value="">
                    <span class="form-help">Required for payment links.${e&&e.secret_key?" Leave blank to keep current value.":""}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Webhook Signing Secret</label>
                    <input type="password" class="form-input" id="stripe-webhook-secret" placeholder="whsec_..." value="">
                    <span class="form-help">Required for automatic invoice status updates when customers pay via Stripe Checkout.${e&&e.webhook_secret?" Leave blank to keep current value.":" See setup instructions below."}</span>
                </div>
                <div class="webhook-setup-section" style="background: var(--bg-card); border: 1px solid var(--border); border-radius: 8px; padding: 1rem; margin-top: 1rem;">
                    <div class="details-title" style="margin-bottom: 0.75rem;">How to set up Stripe Webhooks</div>
                    <ol style="margin: 0; padding-left: 1.25rem; font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.7;">
                        <li>Go to <a href="https://dashboard.stripe.com/webhooks" target="_blank" rel="noopener">Stripe Dashboard → Developers → Webhooks</a></li>
                        <li>Click <strong>Add endpoint</strong></li>
                        <li>Enter your webhook URL: <code style="background: var(--bg-primary); padding: 0.2em 0.4em; border-radius: 4px; font-size: 0.8em; word-break: break-all;">${b.stripeWebhookUrl||""}</code></li>
                        <li>Select events: <strong>checkout.session.completed</strong> (invoices), <strong>customer.subscription.updated</strong>, <strong>customer.subscription.deleted</strong> (subscriptions)</li>
                        <li>Click <strong>Add endpoint</strong>, then reveal and copy the <strong>Signing secret</strong> (starts with whsec_)</li>
                        <li>Paste the signing secret into the Webhook Signing Secret field above and click Save</li>
                    </ol>
                    <p style="margin: 0.75rem 0 0; font-size: 0.75rem; color: var(--text-muted);">Your webhook URL: <strong>${b.stripeWebhookUrl||""}</strong></p>
                </div>
            `,gmail:`
                <div class="form-group">
                    <label class="form-label">Gmail Address</label>
                    <input type="email" class="form-input" id="gmail-email" placeholder="your-email@gmail.com" value="${e&&e.email?e.email:""}">
                    <span class="form-help">The Gmail address used to send emails (quotations, invoices, etc.)</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Gmail App Password</label>
                    <input type="password" class="form-input" id="gmail-app-password" placeholder="Enter 16-character App Password" value="">
                    <span class="form-help">Generate an App Password in Google Account: Security → 2-Step Verification → App passwords. Leave blank to keep current when updating.</span>
                </div>
            `,wise:`
                <div class="form-group">
                    <label class="form-label">API Token</label>
                    <input type="password" class="form-input" id="wise-api-token" placeholder="${e&&e.api_token?"Leave blank to keep current token":"Enter Wise API Token"}" value="">
                    <span class="form-help">Generate an API token from your Wise Business account settings${e&&e.api_token?" — paste a new token to reload profiles":""}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Profile ID</label>
                    <select class="form-input" id="wise-profile-id" disabled style="cursor: not-allowed;">
                        <option value="">${e&&e.api_token?"Loading profiles…":"— Paste your API token above to load profiles —"}</option>
                    </select>
                    <span class="form-help" id="wise-profile-help">${e&&e.api_token?"Fetching your Wise profiles…":"Profiles will load automatically once you enter your API token."}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">
                        <input type="checkbox" id="wise-sandbox" ${e&&e.is_sandbox?"checked":""}> Use Sandbox (testing)
                    </label>
                    <span class="form-help">Enable for testing with Wise Sandbox</span>
                </div>
                ${b.canViewWiseRecipients?`
                <div class="form-group">
                    <a href="${b.wiseRecipientsUrl||"/wise-recipients"}" class="btn-secondary" style="display:inline-flex;text-decoration:none;">Manage recipients &amp; employees</a>
                    <span class="form-help">Assign Wise recipient IDs to employees on the dedicated page.</span>
                </div>
                `:""}
            `,"google-login":`
                <div class="form-group">
                    <label class="form-label">Google Client ID</label>
                    <input type="text" class="form-input" placeholder="Enter Google Client ID" value="1234567890.apps.googleusercontent.com">
                </div>
                <div class="form-group">
                    <label class="form-label">Google Client Secret</label>
                    <input type="password" class="form-input" placeholder="Enter Client Secret" value="••••••••">
                </div>
            `,openai:`
                <div class="form-group">
                    <label class="form-label">OpenAI API Key</label>
                    <input type="password" class="form-input" id="openai-api-key" placeholder="${e&&e.api_key?"Leave blank to keep current key":"sk-..."}">
                    <span class="form-help">Get your API key from platform.openai.com. Used by the AI Assistant.</span>
                </div>
            `,storeganise:`
                <div class="form-group">
                    <label class="form-label">Business code</label>
                    <input type="text" class="form-input" id="storeganise-business-code" placeholder="yourbusiness" value="${e&&e.business_code?e.business_code:""}">
                    <span class="form-help">The subdomain from your Storeganise admin URL, e.g. <code>locnstor</code> for https://locnstor.storeganise.com — not your email address. You can also paste the full URL.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Admin API key</label>
                    <input type="password" class="form-input" id="storeganise-api-key" placeholder="${e&&e.api_key?"Leave blank to keep current key":"Enter API key"}">
                    <span class="form-help">Create an API key in Storeganise → Admin → Settings → Developer. Use <code>Authorization: ApiKey &lt;key&gt;</code>.</span>
                </div>
                ${e&&e.webhook_url?`
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${e.webhook_url}</code>
                    <span class="form-help">Add this URL in Storeganise Developer settings to receive move-in, move-out, and rental events.</span>
                </div>
                `:""}
            `,front:`
                <div class="form-group">
                    <label class="form-label">Front API token</label>
                    <input type="password" class="form-input" id="front-api-token" placeholder="${e&&e.has_token?"Leave blank to keep current token":"Paste bearer token"}">
                    <span class="form-help">Create a token in Front → Settings → Developers with scopes <code>tags:read</code>, <code>conversations:read</code>, <code>comments:read</code>, and optionally <code>inboxes:read</code> and <code>teammates:read</code>. <code>conversations:read</code> and <code>comments:read</code> are required to import internal comments and discussion threads. Paste the token only — do not include <code>Bearer</code>.</span>
                    <div id="front-token-error" class="form-help" style="color:#b91c1c;display:none;margin-top:0.5rem;"></div>
                </div>
                <div id="front-import-panel" class="front-import-panel" ${e&&e.status==="connected"?"":"hidden"}>
                    <h4 style="font-size:0.9375rem;font-weight:600;margin:0 0 0.5rem;">Import inbox tags</h4>
                    <p class="form-help" style="margin-bottom:0.75rem;">Sync mail into LNSCRM first (<strong>Inbox → Sync</strong> or <code>php artisan inbox:sync-mail --full</code>), then map inboxes and run the imports below.</p>
                    <div id="front-mapping-wrap">
                        <span class="form-help">Loading inbox mapping…</span>
                    </div>
                    <label style="display:flex;align-items:center;gap:0.5rem;margin-top:0.75rem;font-size:0.8125rem;">
                        <input type="checkbox" id="front-include-private">
                        Include private Front tags
                    </label>
                    <div class="front-import-actions">
                        <button type="button" class="btn-secondary" id="front-dry-run-btn" onclick="handleFrontImport(true)">Preview (dry run)</button>
                        <button type="button" class="btn-primary" id="front-import-btn" onclick="handleFrontImport(false)">Run import</button>
                        <button type="button" class="btn-secondary" id="front-reset-progress-btn" onclick="handleFrontResetProgress()" title="Forget which conversations were already synced and any in-progress resume point, so the next run rescans everything.">Reset sync progress</button>
                    </div>
                    <div id="front-import-loading" class="front-import-loading" hidden>
                        <div class="front-import-spinner" aria-hidden="true"></div>
                        <div style="flex:1;">
                            <div id="front-import-loading-label">Processing…</div>
                            <div class="front-import-loading-bar" aria-hidden="true"><span id="front-import-loading-fill"></span></div>
                        </div>
                    </div>
                    <div id="front-import-results"></div>
                    <div class="front-import-section">
                        <h4 style="font-size:0.9375rem;font-weight:600;margin:0 0 0.5rem;">Import internal comments</h4>
                        <p class="form-help" style="margin-bottom:0.75rem;">Copies Front conversation comments onto matched LNSCRM threads, including attached files and images. Authors are matched to CRM users by email or name; unmatched comments keep the original Front author name. If comments were imported earlier without files, reset comment progress and run again to backfill attachments.</p>
                        <div class="front-import-actions" style="margin-top:0;">
                            <button type="button" class="btn-secondary" id="front-comment-dry-run-btn" onclick="handleFrontCommentImport(true)">Preview comments</button>
                            <button type="button" class="btn-primary" id="front-comment-import-btn" onclick="handleFrontCommentImport(false)">Import comments</button>
                            <button type="button" class="btn-secondary" id="front-comment-reset-progress-btn" onclick="handleFrontCommentResetProgress()" title="Forget which conversations were already scanned for comments, so the next run rescans everything.">Reset comment progress</button>
                        </div>
                        <div id="front-comment-import-loading" class="front-import-loading" hidden>
                            <div class="front-import-spinner" aria-hidden="true"></div>
                            <div style="flex:1;">
                                <div id="front-comment-import-loading-label">Processing…</div>
                                <div class="front-import-loading-bar" aria-hidden="true"><span id="front-comment-import-loading-fill"></span></div>
                            </div>
                        </div>
                        <div id="front-comment-import-results"></div>
                    </div>
                    <div class="front-import-section">
                        <h4 style="font-size:0.9375rem;font-weight:600;margin:0 0 0.5rem;">Import discussion threads</h4>
                        <p class="form-help" style="margin-bottom:0.75rem;">Copies Front discussion threads into <strong>/discussions</strong> as internal teammate discussions. Comment authors and followers become participants when they match a CRM user by email or name. Unmatched comments are posted as you, with the original Front author name prefixed.</p>
                        <div class="front-import-actions" style="margin-top:0;">
                            <button type="button" class="btn-secondary" id="front-discussion-dry-run-btn" onclick="handleFrontDiscussionImport(true)">Preview discussions</button>
                            <button type="button" class="btn-primary" id="front-discussion-import-btn" onclick="handleFrontDiscussionImport(false)">Import discussions</button>
                            <button type="button" class="btn-secondary" id="front-discussion-reset-progress-btn" onclick="handleFrontDiscussionResetProgress()" title="Forget which discussion threads were already scanned, so the next run rescans everything.">Reset discussion progress</button>
                        </div>
                        <div id="front-discussion-import-loading" class="front-import-loading" hidden>
                            <div class="front-import-spinner" aria-hidden="true"></div>
                            <div style="flex:1;">
                                <div id="front-discussion-import-loading-label">Processing…</div>
                                <div class="front-import-loading-bar" aria-hidden="true"><span id="front-discussion-import-loading-fill"></span></div>
                            </div>
                        </div>
                        <div id="front-discussion-import-results"></div>
                    </div>
                </div>
            `,calendar:`
                <p class="form-help" style="margin-bottom: 1rem;">Configure Google Calendar OAuth so users can connect their calendars. Add credentials and copy the redirect URL when creating the OAuth app.</p>
                <div class="oauth-section" style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem;">Google Calendar</h4>
                    <details class="oauth-steps" style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                        <summary style="cursor: pointer; font-size: 0.8125rem; font-weight: 500; color: var(--accent);">How to configure Google Calendar OAuth</summary>
                        <ol style="margin: 0.75rem 0 0; padding-left: 1.25rem; font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.6;">
                            <li>Go to <a href="https://console.cloud.google.com/" target="_blank" rel="noopener">Google Cloud Console</a></li>
                            <li>Create or select a project → APIs &amp; Services → Credentials</li>
                            <li>Create credentials → OAuth 2.0 Client ID (Application type: Web application)</li>
                            <li>Add Authorized redirect URI: <code id="calendarGoogleRedirectUrl" style="background: var(--bg-card); padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; word-break: break-all;">${e&&e.redirect_url_google?e.redirect_url_google:'${CFG.googleCalendarCallbackUrl || "/calendar/connect/google/callback"}'}</code></li>
                            <li>Enable Google Calendar API: APIs &amp; Services → Library → search "Google Calendar API" → Enable</li>
                            <li>Copy the Client ID and Client Secret below</li>
                        </ol>
                    </details>
                    <div class="form-group">
                        <label class="form-label">Client ID</label>
                        <input type="text" class="form-input" id="oauth-google-client-id" placeholder="${e&&e.google_configured?"(configured)":"xxxxx.apps.googleusercontent.com"}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input type="password" class="form-input" id="oauth-google-client-secret" placeholder="${e&&e.google_configured?"(leave blank to keep)":"GOCSPX-xxxxx"}">
                    </div>
                </div>
                <p class="form-help">Leave fields blank to keep existing values. Credentials are stored per company.</p>
            `,outlook:`
                <p class="form-help" style="margin-bottom: 1rem;">Configure Microsoft Outlook OAuth for Inbox mail. Calendar shows events from the personal Microsoft 365 account connected in Inbox (no separate calendar login).</p>
                <div class="oauth-section" style="margin-bottom: 1.5rem;">
                    <h4 style="font-size: 0.9375rem; font-weight: 600; margin-bottom: 0.75rem;">Microsoft Outlook</h4>
                    <details class="oauth-steps" style="background: var(--bg-primary); border: 1px solid var(--border); border-radius: 8px; padding: 0.75rem 1rem; margin-bottom: 1rem;">
                        <summary style="cursor: pointer; font-size: 0.8125rem; font-weight: 500; color: var(--accent);">How to configure Outlook (Inbox + Calendar)</summary>
                        <ol style="margin: 0.75rem 0 0; padding-left: 1.25rem; font-size: 0.8125rem; color: var(--text-secondary); line-height: 1.6;">
                            <li>Go to <a href="https://portal.azure.com/" target="_blank" rel="noopener">Azure Portal</a> → Microsoft Entra ID</li>
                            <li>App registrations → New registration. For single-tenant apps, also copy the <strong>Directory (tenant) ID</strong>.</li>
                            <li>Add Redirect URI (Platform Web):
                                <div style="margin-top:0.25rem;">Inbox: <code id="inboxOutlookRedirectUrl" style="background: var(--bg-card); padding: 0.125rem 0.375rem; border-radius: 4px; font-size: 0.75rem; word-break: break-all;">${e&&e.redirect_url_outlook_mail?e.redirect_url_outlook_mail:'${CFG.outlookMailCallbackUrl || "/inbox/connect/outlook/callback"}'}</code></div>
                            </li>
                            <li>Certificates &amp; secrets → New client secret → copy the value</li>
                            <li>API permissions → Add: Calendars.ReadWrite, User.Read, Mail.ReadWrite, Mail.Send, Mail.ReadWrite.Shared, offline_access</li>
                            <li>Copy Application (client) ID and client secret below. If the app is <strong>single-tenant</strong>, paste the Directory (tenant) ID too (required — using /common will fail with AADSTS50194).</li>
                            <li>Users connect personal/shared mail from <strong>Inbox</strong>. <strong>Calendar</strong> then shows, creates, updates, and shares events from that personal account.</li>
                        </ol>
                    </details>
                    <div class="form-group">
                        <label class="form-label">Client ID (Application ID)</label>
                        <input type="text" class="form-input" id="oauth-microsoft-client-id" placeholder="${e&&e.outlook_configured?"(configured)":"xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx"}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Client Secret</label>
                        <input type="password" class="form-input" id="oauth-microsoft-client-secret" placeholder="${e&&e.outlook_configured?"(leave blank to keep)":"xxxx~xxxxxxxxxxxxxxxxxxxx"}">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tenant ID (required for single-tenant apps)</label>
                        <input type="text" class="form-input" id="oauth-microsoft-tenant-id" value="${e&&e.microsoft_tenant_id?e.microsoft_tenant_id:""}" placeholder="xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx or leave blank for multi-tenant (/common)">
                        <span class="form-help">Azure Portal → Microsoft Entra ID → Overview → Directory (tenant) ID</span>
                    </div>
                </div>
                <p class="form-help">Leave fields blank to keep existing values. Credentials are stored per company.</p>
            `,twilio:`
                <div class="form-group">
                    <label class="form-label">Account SID</label>
                    <input type="text" class="form-input" id="twilio-account-sid" placeholder="AC..." value="${e&&e.account_sid||""}">
                    <span class="form-help">Twilio Console → Account → API keys & tokens (starts with AC).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Auth Token</label>
                    <input type="password" class="form-input" id="twilio-auth-token" placeholder="Enter Auth Token" value="">
                    <span class="form-help">Live Auth Token${e&&e.auth_token?" (leave blank to keep current)":""}.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">App SID (optional — CRM browser calling)</label>
                    <input type="text" class="form-input" id="twilio-app-sid" placeholder="AP..." value="${e&&e.app_sid||""}">
                    <span class="form-help">TwiML App SID from Twilio Console → Voice → TwiML Apps.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">API Key (optional — CRM browser calling)</label>
                    <input type="text" class="form-input" id="twilio-api-key" placeholder="SK..." value="${e&&e.api_key||""}">
                </div>
                <div class="form-group">
                    <label class="form-label">API Secret (optional — CRM browser calling)</label>
                    <input type="password" class="form-input" id="twilio-api-secret" placeholder="Enter API Secret" value="">
                    <span class="form-help">${e&&e.api_secret?"Leave blank to keep current.":"From Twilio Console → Account → API keys & tokens."}</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Voice webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${S.voiceWebhook}</code>
                    <span class="form-help">Set this as the Voice URL (HTTP POST) on your Twilio numbers / TwiML App.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">SMS webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${S.smsWebhook}</code>
                    <span class="form-help">Set this as the Messaging URL (HTTP POST) on your Twilio numbers. Numbers bought in-app are configured automatically.</span>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">Powers the phone system &amp; messaging</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Paste live <strong>Account SID</strong> + <strong>Auth Token</strong> (required for WhatsApp, Viber, Facebook Messenger, SMS, phone).</li>
                        <li>For in-CRM browser calling, also add <strong>App SID</strong>, <strong>API Key</strong>, and <strong>API Secret</strong>.</li>
                        <li>Then configure WhatsApp / Viber / Facebook senders under their own cards.</li>
                    </ol>
                </div>
            `,viber:`
                <div class="form-group">
                    <label class="form-label">Viber Sender ID</label>
                    <input type="text" class="form-input" id="viber-sender-id" value="${e&&e.sender_id?e.sender_id:""}" placeholder="From Twilio Console → Messaging → Senders → Viber">
                    <span class="form-help">Your Twilio Viber Business Sender ID (not a Meta/Viber bot token).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Display name (optional)</label>
                    <input type="text" class="form-input" id="viber-bot-name" value="${e&&e.bot_name?e.bot_name:""}" placeholder="Support">
                </div>
                <div class="form-group">
                    <label class="form-label">Welcome Message (optional)</label>
                    <textarea class="form-input" id="viber-welcome-message" rows="3" placeholder="Hi! Thanks for messaging us. How can we help?">${e&&e.welcome_message?e.welcome_message:""}</textarea>
                    <span class="form-help">Sent once when a customer starts a new conversation.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${e&&e.webhook_url?e.webhook_url:"Saved after you connect — must be public HTTPS"}</code>
                    <span class="form-help">Paste this as the inbound webhook URL on your Twilio Viber sender / Messaging Service.</span>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">How it works</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Connect <strong>Twilio</strong> first under Integrations (Account SID / Auth Token).</li>
                        <li>Enable Viber Business Messaging in the Twilio Console and create a Viber sender.</li>
                        <li>Paste the Sender ID here, then set the Webhook URL above on that sender.</li>
                        <li>Customer messages appear in <a href="${S.viberChatUrl}">Viber</a>.</li>
                    </ol>
                </div>
            `,whatsapp:`
                <div class="form-group">
                    <label class="form-label">WhatsApp From Number</label>
                    <input type="text" class="form-input" id="whatsapp-from-number" value="${e&&e.from_number?e.from_number:""}" placeholder="+15551234567">
                    <span class="form-help">E.164 WhatsApp-enabled number from Twilio (Sandbox or approved sender).</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Business name (optional)</label>
                    <input type="text" class="form-input" id="whatsapp-business-name" value="${e&&e.business_name?e.business_name:""}" placeholder="Acme Support">
                </div>
                <div class="form-group">
                    <label class="form-label">Welcome Message (optional)</label>
                    <textarea class="form-input" id="whatsapp-welcome-message" rows="3" placeholder="Hi! Thanks for messaging us. How can we help?">${e&&e.welcome_message?e.welcome_message:""}</textarea>
                </div>
                ${e&&(e.business_name||e.display_phone_number||e.from_number)?`
                <div class="form-group">
                    <label class="form-label">Connected number</label>
                    <div style="font-size:0.9rem;color:var(--text-primary);">${e.business_name||""}${e.display_phone_number||e.from_number?" · "+(e.display_phone_number||e.from_number):""}</div>
                </div>`:""}
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${e&&e.webhook_url?e.webhook_url:"Saved after you connect — must be public HTTPS"}</code>
                    <span class="form-help">Paste this as the inbound webhook URL on your Twilio WhatsApp sender / Messaging Service. Status callbacks use the shared Twilio SMS status URL.</span>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">How it works</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Connect <strong>Twilio</strong> first under Integrations.</li>
                        <li>Enable WhatsApp in the Twilio Console (Sandbox or production sender).</li>
                        <li>Paste the WhatsApp from number here and point the sender webhook to the URL above.</li>
                        <li>Customer messages appear in <a href="${S.whatsappChatUrl}">WhatsApp</a>. Free-form replies work within the 24-hour window.</li>
                    </ol>
                </div>
            `,facebook:`
                <div class="form-group">
                    <label class="form-label">Facebook Page ID</label>
                    <input type="text" class="form-input" id="facebook-page-id" value="${e&&e.page_id?e.page_id:""}" placeholder="222764457920914">
                    <span class="form-help">From Twilio Console → Facebook Messenger, or from Graph <code>/me/accounts</code>.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Page name (optional)</label>
                    <input type="text" class="form-input" id="facebook-page-name" value="${e&&e.page_name?e.page_name:""}" placeholder="Loc&amp;Stor 24/7 Self Storage Philippines">
                </div>
                <div class="form-group">
                    <label class="form-label">Page Access Token (required for Instagram)</label>
                    <input type="password" class="form-input" id="facebook-page-access-token" value="" placeholder="${e&&e.has_page_access_token?"•••••••• (leave blank to keep)":"EAAB… long-lived Page token"}">
                    <span class="form-help">Must be a <strong>Page</strong> token (not User) that does not expire. In <a href="https://developers.facebook.com/tools/explorer/" target="_blank" rel="noopener">Graph API Explorer</a> get a User token with <code>pages_messaging</code>, <code>pages_manage_metadata</code>, <code>pages_read_engagement</code>, <code>instagram_basic</code>, and <code>instagram_manage_messages</code>, then switch the token dropdown to your Page. Missing the two <code>instagram_*</code> scopes is the most common reason Instagram DMs never arrive even though Messenger works fine. Explorer tokens expire in 1–2 hours.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Meta App Secret (recommended)</label>
                    <input type="password" class="form-input" id="facebook-app-secret" value="" placeholder="${e&&e.has_app_secret?"•••••••• (leave blank to keep)":"From Meta App → Settings → Basic"}">
                    <span class="form-help">Verifies Instagram webhook signatures from Meta.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Instagram account ID</label>
                    <input type="text" class="form-input" id="facebook-instagram-id" value="${e&&e.instagram_business_account_id?e.instagram_business_account_id:""}" placeholder="17841400107695807">
                    <span class="form-help">Instagram Business Account ID (not @username). Saved automatically from the Page token when possible.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Instagram username (optional)</label>
                    <input type="text" class="form-input" id="facebook-instagram-username" value="${e&&e.instagram_username?e.instagram_username:""}" placeholder="locnstor247">
                </div>
                <div class="form-group">
                    <label class="form-label">Welcome Message (optional)</label>
                    <textarea class="form-input" id="facebook-welcome-message" rows="3" placeholder="Hi! Thanks for messaging us. How can we help?">${e&&e.welcome_message?e.welcome_message:""}</textarea>
                    <span class="form-help">Sent once when a customer starts a new conversation.</span>
                </div>
                ${e&&(e.page_name||e.page_id)?`
                <div class="form-group">
                    <label class="form-label">Connected sender</label>
                    <div style="font-size:0.9rem;color:var(--text-primary);">${e.page_name||"Facebook Page"} · ${e.page_id||""}${e.instagram_username?" · @"+e.instagram_username:""}</div>
                </div>`:""}
                <div class="form-group">
                    <label class="form-label">Webhook URL</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${e&&e.webhook_url?e.webhook_url:"Saved after you connect — must be public HTTPS"}</code>
                    <span class="form-help">Paste this in Meta App → Messenger settings for both Facebook and Instagram (Callback URL). Also keep it on the Twilio Messenger sender for Facebook chat.</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Verify token</label>
                    <code style="display:block;background:var(--bg-primary);padding:0.5rem 0.65rem;border-radius:6px;font-size:0.78rem;word-break:break-all;">${e&&e.webhook_verify_token?e.webhook_verify_token:"Generated after you save"}</code>
                    <span class="form-help">Paste this as Verify Token in the same Meta webhook settings.</span>
                </div>
                ${e&&e.page_id?`
                <div class="form-group">
                    <label class="form-label">Sync old Messenger messages</label>
                    <div style="display:flex;gap:0.5rem;align-items:center;flex-wrap:wrap;">
                        <select class="form-input" id="facebook-sync-days" style="max-width:11rem;">
                            <option value="30">Last 30 days</option>
                            <option value="90" selected>Last 90 days</option>
                            <option value="365">Last 12 months</option>
                            <option value="0">All available in Twilio</option>
                        </select>
                        <button type="button" class="btn-secondary" id="facebook-sync-btn" onclick="syncFacebookHistory(event)">Sync Messenger inbox</button>
                    </div>
                    <span class="form-help" id="facebook-sync-help">Imports the Facebook Page inbox, including replies sent from Messenger. Instagram Direct arrives live through Meta webhooks.</span>
                </div>`:""}
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">Replies sent from Messenger</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>Save a <strong>Page Access Token that does not expire</strong>. Graph API Explorer tokens die in 1–2 hours. Use a long-lived User token, then switch the dropdown to the Page.</li>
                        <li>In Meta for Developers open your app → <strong>Webhooks</strong> → <strong>Page</strong> (not Graph API Explorer). Add the CRM Callback URL and Verify Token, then subscribe to <code>messages</code>. Optionally also subscribe to <code>message_echoes</code> there — that is a webhook field, not a Graph <code>?fields=</code> value. Sync still imports Page Inbox replies through Graph conversations.</li>
                        <li>On <a href="${S.facebookChatUrl}">Facebook &amp; Instagram</a>, click the download Sync button to import Page Inbox history, including messages you sent from Messenger to customers.</li>
                    </ol>
                </div>
                <div class="integration-setup-tips" style="margin-top:1rem;padding:0.85rem 1rem;border:1px solid var(--border);border-radius:8px;background:var(--bg-primary);font-size:0.82rem;line-height:1.5;">
                    <strong style="display:block;margin-bottom:0.5rem;color:var(--text-primary);">Instagram Direct</strong>
                    <ol style="margin:0;padding-left:1.2rem;color:var(--text-secondary);">
                        <li>In <a href="https://developers.facebook.com/apps" target="_blank" rel="noopener">Meta for Developers</a> → your app → <strong>App Review → Permissions and Features</strong>, confirm <code>instagram_basic</code> and <code>instagram_manage_messages</code> show Advanced Access (not just Standard). Without this, Meta will not deliver Instagram DMs to any token, even a correctly scoped one.</li>
                        <li>Save this form with a <strong>Page Access Token</strong> that includes those two scopes (see above). The Instagram account ID field should fill in automatically after saving — if it stays blank, the token is missing a scope or the Page has no linked Instagram professional account.</li>
                        <li>In Meta for Developers open your app → <strong>Messenger → Instagram settings</strong>.</li>
                        <li>Set Callback URL to the Webhook URL above and Verify Token to the token above. Subscribe to <code>messages</code> under the <strong>Instagram</strong> tab specifically — this is separate from the Page's <code>messages</code> subscription.</li>
                        <li>In Instagram: Settings → Messages and story replies → Message controls → allow <strong>Connected tools</strong>.</li>
                        <li>The webhook URL must be public HTTPS. Then DMs to @${e&&e.instagram_username?e.instagram_username:"yourpage"} appear in <a href="${S.facebookChatUrl}">Facebook &amp; Instagram</a>.</li>
                    </ol>
                </div>
            `}[t]||"<p>No configuration required.</p>"}function v(){const t=document.getElementById("modalActionBtn"),e=document.getElementById("wise-profile-id");if(!t||!e)return;const o=e.value&&e.value!=="";t.disabled=!o,t.style.opacity=o?"":"0.5",t.style.cursor=o?"":"not-allowed"}async function F(t,e,o){const n=document.getElementById("wise-profile-id"),r=document.getElementById("wise-profile-help");if(n){n.disabled=!0,n.style.cursor="not-allowed",n.innerHTML='<option value="">Loading profiles…</option>',r&&(r.textContent="Fetching profiles from Wise…"),v();try{const i=document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||"",l={is_sandbox:!!e};t&&(l.api_token=t);const a=await fetch("/api/integrations/wise/profiles",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":i},body:JSON.stringify(l)});let c={};try{c=await a.json()}catch(p){console.error("Wise profiles: server returned non-JSON (status "+a.status+"):",p),r&&(r.textContent="Server error (HTTP "+a.status+"). Check console for details."),n.innerHTML='<option value="">— Server error —</option>',v();return}if(a.ok&&c.profiles&&c.profiles.length){if(typeof c.resolved_is_sandbox=="boolean"){const s=document.getElementById("wise-sandbox");s&&(s.checked=c.resolved_is_sandbox)}n.innerHTML='<option value="">— Select a profile —</option>'+c.profiles.map(s=>`<option value="${s.id}">${s.name} (${s.type}) — ID: ${s.id}</option>`).join("");const p=o||(c.profiles.length===1?String(c.profiles[0].id):"");if(p)if(n.querySelector(`option[value="${p}"]`))n.value=p;else{const d=document.createElement("option");d.value=p,d.textContent=`Profile ID: ${p} (current)`,n.insertBefore(d,n.options[1]),n.value=p}if(n.disabled=!1,n.style.cursor="",r){const s=c.profiles.length===1?"Profile loaded and selected.":`${c.profiles.length} profiles found. Select one to continue.`;r.textContent=c.warning?`${c.warning} ${s}`:s}}else n.innerHTML='<option value="">— Could not load profiles —</option>',r&&(r.textContent=c.error||"Could not load profiles. Check your API token.")}catch(i){console.error(i),n.innerHTML='<option value="">— Error loading profiles —</option>',r&&(r.textContent="Error fetching profiles. Please try again.")}v()}}async function ue(){const t=document.getElementById("wise-api-token"),e=document.getElementById("wise-profile-id");if(!t||!e)return;e.addEventListener("change",v);const o=window.existingIntegration;o&&o.api_token?await F(null,o.is_sandbox,o.profile_id?String(o.profile_id):null):v();function n(){const l=t.value,a=document.getElementById("wise-sandbox")?.checked||!1,c=o?.profile_id?String(o.profile_id):null;if(!l&&o&&o.api_token){F(null,a,c);return}!l||l.length<20||F(l,a,c)}let r=null;t.addEventListener("input",function(){const l=this.value;if(clearTimeout(r),!l){if(o&&o.api_token)F(null,document.getElementById("wise-sandbox")?.checked,o.profile_id?String(o.profile_id):null);else{e.innerHTML='<option value="">— Paste your API token above to load profiles —</option>',e.disabled=!0,e.style.cursor="not-allowed";const a=document.getElementById("wise-profile-help");a&&(a.textContent="Profiles will load automatically once you enter your API token."),v()}return}if(l.length<20){e.innerHTML='<option value="">— Keep typing… —</option>',e.disabled=!0,e.style.cursor="not-allowed",v();return}e.innerHTML='<option value="">Loading profiles…</option>',e.disabled=!0,e.style.cursor="not-allowed",v(),r=setTimeout(()=>{n()},800)});const i=document.getElementById("wise-sandbox");i&&i.addEventListener("change",function(){clearTimeout(r),(t.value.length>=20||o&&o.api_token)&&n()})}async function q(){const t=document.getElementById("twilio-account-sid")?.value?.trim()||"",e=document.getElementById("twilio-auth-token")?.value||"",o=document.getElementById("twilio-app-sid")?.value?.trim()||"",n=document.getElementById("twilio-api-key")?.value?.trim()||"",r=document.getElementById("twilio-api-secret")?.value||"",i=window.existingIntegration;if(!t){alert("Please enter your Account SID.");return}if(!t.startsWith("AC")){alert("Account SID must start with AC.");return}if(!e&&!(i&&i.auth_token)){alert("Please provide your Auth Token.");return}if(o&&!o.startsWith("AP")){alert("App SID must start with AP.");return}if(n&&!n.startsWith("SK")){alert("API Key must start with SK.");return}if(n&&!r&&!(i&&i.api_secret)||!n&&r){alert("API Key and API Secret must both be provided together (or left blank).");return}try{const l=await fetch("/api/integrations/twilio",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({account_sid:t,auth_token:e,app_sid:o||null,api_key:n||null,api_secret:r||null})}),a=await l.json();if(l.ok)m.status="connected",alert("Twilio connected successfully."),f(),h(g);else{const c=a.errors?Object.values(a.errors).flat().join(`
`):"";alert((a.error?a.error+(c?`

`+c:""):c)||"Error saving Twilio integration.")}}catch(l){console.error(l),alert("Error saving Twilio integration. Please try again.")}}async function U(){try{(await fetch("/api/integrations/twilio",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m&&(m.status="disconnected"),alert("Twilio has been disconnected."),f(),h(g)):alert("Error disconnecting Twilio. Please try again.")}catch(t){console.error(t),alert("Error disconnecting Twilio. Please try again.")}}async function fe(){try{(await fetch("/api/integrations/wise",{method:"DELETE",headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert("Wise has been disconnected."),f(),h(g)):alert("Error disconnecting. Please try again.")}catch(t){console.error(t),alert("Error disconnecting Wise. Please try again.")}}async function ge(){const t=document.getElementById("wise-profile-id")?.value?.trim()||"",e=document.getElementById("wise-sandbox")?.checked||!1,o=document.getElementById("wise-api-token")?.value||"";try{const n={profile_id:t||null,is_sandbox:e};o&&(n.api_token=o);const r=await fetch("/api/integrations/wise",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify(n)}),i=await r.json();r.ok?(alert("Wise integration saved successfully."),f(),h(g)):alert(i.error||(i.errors?Object.values(i.errors).flat().join(", "):"Error saving. Please try again."))}catch(n){console.error(n),alert("Error saving Wise integration. Please try again.")}}async function he(){try{(await fetch("/api/integrations/stripe",{method:"DELETE",headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert("Stripe has been disconnected."),f(),h(g)):alert("Error disconnecting. Please try again.")}catch(t){console.error(t),alert("Error disconnecting Stripe. Please try again.")}}async function H(){const t=document.getElementById("stripe-publishable-key")?.value?.trim()||"",e=document.getElementById("stripe-secret-key")?.value||"",o=document.getElementById("stripe-webhook-secret")?.value||"",n=window.existingIntegration&&window.existingIntegration.secret_key;if(!e&&!n){alert("Please enter your Stripe Secret Key.");return}const r=document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||"";if(!r){alert("CSRF token missing. Please refresh the page and try again.");return}try{const i={publishable_key:t||null,_token:r};e&&(i.secret_key=e),o&&(i.webhook_secret=o);const l=await fetch(b.stripeStoreUrl||"/api/integrations/stripe",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":r,"X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(i)});let a={};try{const c=await l.text();a=c?JSON.parse(c):{}}catch{console.error("Stripe response parse failed. Status:",l.status,"Body:",text?.substring?.(0,200)),alert("Invalid server response (status "+l.status+"). Check browser console (F12).");return}if(l.ok)alert("Stripe integration saved successfully. You can now generate payment links in Billing."),m.status="connected",f(),h(g);else{const c=a.error||a.message||(a.errors?Object.values(a.errors).flat().join(", "):null);alert(c||"Request failed (status "+l.status+")"),c||console.error("Stripe save failed:",l.status,a)}}catch(i){console.error("Stripe save error:",i),alert("Error saving Stripe integration: "+(i.message||"Please try again."))}}async function K(){const t=document.getElementById("openai-api-key")?.value?.trim()||"",e=window.existingIntegration&&window.existingIntegration.api_key;if(!t&&!e){alert("Please enter your OpenAI API key. Leave blank to keep the current key.");return}try{const o=await fetch("/api/integrations/openai",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({api_key:t||null})}),n=await o.json();o.ok?(m.status="connected",alert("OpenAI integration saved successfully. The AI Assistant will use this API key."),f(),h(g)):alert(n.error||(n.errors?Object.values(n.errors).flat().join(", "):"Error saving. Please try again."))}catch(o){console.error("Error:",o),alert("Error saving OpenAI integration. Please try again.")}}async function G(){const t=document.getElementById("storeganise-business-code")?.value?.trim().toLowerCase()||"",e=document.getElementById("storeganise-api-key")?.value?.trim()||"",o=window.existingIntegration&&window.existingIntegration.api_key;if(!t){alert("Please enter your Storeganise business code.");return}if(!e&&!o){alert("Please enter your Storeganise Admin API key.");return}try{const n=await fetch("/api/integrations/storeganise",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({business_code:t,api_key:e||null})}),r=await n.json();n.ok?(m.status="connected",alert("Storeganise integration saved successfully."),f(),h(g)):alert(r.error||(r.errors?Object.values(r.errors).flat().join(", "):"Error saving. Please try again."))}catch(n){console.error("Error:",n),alert("Error saving Storeganise integration. Please try again.")}}async function be(){try{(await fetch("/api/integrations/storeganise",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert("Storeganise has been disconnected."),f(),h(g)):alert("Error disconnecting. Please try again.")}catch(t){console.error("Error:",t),alert("Error disconnecting. Please try again.")}}function X(t,e=!1,o=null){const n=document.getElementById("front-import-results");if(!n||!t)return;const r=Array.isArray(t.unmatched_samples)?t.unmatched_samples:[],i=o?new Date(o).toLocaleString():new Date().toLocaleString(),l=t.import_mode==="tags"?"Tag-based matching across all shared inboxes":"Front inbox mapping";n.innerHTML=`
            <div class="front-import-results">
                <h4>${e?"Preview results":"Import results"} <span style="font-weight:400;color:var(--text-secondary);">· ${i}</span></h4>
                <p class="form-help" style="margin:0 0 0.75rem;">${y(l)}</p>
                ${t.front_inbox_warning?`<p class="form-help" style="margin:0 0 0.75rem;color:#b45309;">${y(String(t.front_inbox_warning))}</p>`:""}
                ${e&&t.preview_limit?`<p class="form-help" style="margin:0 0 0.75rem;color:#b45309;">Preview shows the first ${t.preview_limit} Front conversations only. Run import to process all.</p>`:""}
                <dl>
                    <dt>Mapped inboxes</dt><dd>${t.mapped_inboxes??0}</dd>
                    ${e?`<dt>Conversations scanned</dt><dd>${t.conversations_scanned??0}</dd>`:""}
                    ${!e&&t.conversations_already_synced?`<dt>Already synced (skipped)</dt><dd>${t.conversations_already_synced}</dd>`:""}
                    <dt>Front conversations with tags</dt><dd>${t.front_conversations_with_tags??0}</dd>
                    <dt>Matched conversations</dt><dd>${t.conversations_matched??0}</dd>
                    <dt>Unmatched conversations</dt><dd>${t.conversations_unmatched??0}</dd>
                    <dt>Tags created</dt><dd>${t.tags_created??0}</dd>
                    <dt>Existing tags reused</dt><dd>${t.tags_existing??0}</dd>
                    <dt>Tag links ${e?"would apply":"applied"}</dt><dd>${t.tags_applied??0}</dd>
                    ${t.lead_labels_applied?`<dt>Lead labels ${e?"would apply":"applied"}</dt><dd>${t.lead_labels_applied}</dd>`:""}
                </dl>
                ${r.length?`<ul class="front-unmatched-list">${r.map(a=>`<li>${y(String(a))}</li>`).join("")}</ul>`:""}
            </div>
        `}function z(t){const e=document.getElementById("front-import-results");e&&(e.innerHTML=`
            <div class="front-import-results" style="border-color:#fecaca;">
                <h4 style="color:#b91c1c;margin-bottom:0.5rem;">Import failed</h4>
                <p class="form-help" style="margin:0;color:#b91c1c;">${y(String(t||"Unknown error"))}</p>
            </div>
        `)}function y(t){return t.replace(/&/g,"&amp;").replace(/</g,"&lt;").replace(/>/g,"&gt;").replace(/"/g,"&quot;")}function V(){const t=[];return document.querySelectorAll("[data-front-inbox-id]").forEach(e=>{const o=e.getAttribute("data-front-inbox-id"),n=parseInt(e.value,10),r=e.closest("tr")?.querySelector("td")?.textContent?.trim()||o;o&&n>0&&t.push({frontId:o,sharedId:n,frontName:r})}),t}function J(){return{mapped_inboxes:0,conversations_scanned:0,conversations_already_synced:0,front_conversations_with_tags:0,conversations_matched:0,conversations_unmatched:0,tags_created:0,tags_existing:0,tags_applied:0,unmatched_samples:[]}}function M(t,e){return e&&(["mapped_inboxes","conversations_scanned","conversations_already_synced","front_conversations_with_tags","conversations_matched","conversations_unmatched","tags_created","tags_existing","tags_applied"].forEach(n=>{t[n]=(Number(t[n])||0)+(Number(e[n])||0)}),e.import_mode&&(t.import_mode=e.import_mode),e.preview_limit&&(t.preview_limit=e.preview_limit),e.preview_limited&&(t.preview_limited=e.preview_limited),e.front_inbox_warning&&(t.front_inbox_warning=e.front_inbox_warning),e.inbox_errors?.length&&(t.inbox_errors=[...t.inbox_errors||[],...e.inbox_errors]),(e.unmatched_samples||[]).forEach(n=>{(t.unmatched_samples||[]).length<10&&!(t.unmatched_samples||[]).includes(n)&&(t.unmatched_samples=[...t.unmatched_samples||[],n])})),t}function j(){return[document.getElementById("front-dry-run-btn"),document.getElementById("front-import-btn"),document.getElementById("front-comment-dry-run-btn"),document.getElementById("front-comment-import-btn"),document.getElementById("front-discussion-dry-run-btn"),document.getElementById("front-discussion-import-btn")]}function w(t,e="Processing…",o=null){const n=document.getElementById("front-import-loading"),r=document.getElementById("front-import-loading-label"),i=document.getElementById("front-import-loading-fill");j().forEach(a=>{a&&(a.disabled=!!t)});const l=o==null?null:Math.max(0,Math.min(100,Math.round(o)));r&&(r.textContent=l===null?e:`${e} ${l}%`),n&&(n.hidden=!t),i&&(l===null?(i.classList.remove("determinate"),i.style.width=""):(i.classList.add("determinate"),i.style.width=`${l}%`))}async function R(t){const e=await t.text();let o={};if(e)try{o=JSON.parse(e)}catch{throw new Error(`Server returned HTTP ${t.status} with a non-JSON response. ${e.slice(0,240)}`)}if(!t.ok)throw new Error(o.error||o.message||`Request failed with HTTP ${t.status}.`);return o}async function B(t,e,o,n=!0,r=null,i=null){const l={dry_run:t,include_private:!!document.getElementById("front-include-private")?.checked,inbox_map:e,front_inbox_id:o||null,persist_results:n};r&&(l.page_url=r),i&&(l.result_stats=i);const a=await fetch("/api/integrations/front/import-tags",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify(l)});return R(a)}async function ye(t,e,o,n=0,r=100){const i=J();if(t){w(!0,`${o} ${e.frontName} (first 100)…`,n);const p=await B(t,{[e.frontId]:e.sharedId},e.frontId,!1);return M(i,p.stats||{}),i}let l=null,a=0,c="";do{a+=1;const p=1-1/(a+1),s=n+(r-n)*p;w(!0,`${o} ${e.frontName} – page ${a}${c}…`,s);const d=await B(t,{[e.frontId]:e.sharedId},e.frontId,!1,l);M(i,d.stats||{}),d.stats?.resumed_from&&(c=` (resuming after ${d.stats.resumed_from} already done)`),l=d.has_more&&d.next_page_url?d.next_page_url:null}while(l);return i}async function Y(t=null){const e=document.getElementById("front-import-panel"),o=document.getElementById("front-mapping-wrap");if(!(!e||!o)){e.hidden=!1,T=0,t?.last_import_stats&&X(t.last_import_stats,!!t.last_import_dry_run,t.last_import_at||null),t?.last_comment_import_stats&&Z(t.last_comment_import_stats,!!t.last_comment_import_dry_run,t.last_comment_import_at||null),t?.last_discussion_import_stats&&re(t.last_discussion_import_stats,!!t.last_discussion_import_dry_run,t.last_discussion_import_at||null);try{const n=await fetch("/api/integrations/front/mapping",{headers:{Accept:"application/json"}}),r=await n.json();if(!n.ok){const a=r.error||"Could not load inbox mapping.";o.innerHTML=`
                    <div class="form-help" style="color:#b45309;margin-bottom:0.75rem;">${y(a)}</div>
                    <div class="form-help">Preview and import can still run using tag-based matching across all shared inboxes.</div>
                `;return}r.front_error&&(o.innerHTML=`
                    <div class="form-help" style="color:#b45309;margin-bottom:0.75rem;">Could not list Front inboxes: ${y(r.front_error)}</div>
                    <div class="form-help" style="margin-bottom:0.75rem;">Preview and import will use tag-based matching across your ${(r.shared_inboxes||[]).length} shared inbox(es).</div>
                `);const i=(r.shared_inboxes||[]).map(a=>{const c=a.email?`${a.name} (${a.email})`:a.name;return`<option value="${a.id}">${y(c)}</option>`}).join("");T=(r.rows||[]).length;const l=(r.rows||[]).map(a=>`
                <tr>
                    <td>${y(a.front_name||a.front_id)}</td>
                    <td>
                        <select class="form-input" data-front-inbox-id="${y(a.front_id)}" style="min-width:220px;">
                            <option value="">— Skip —</option>
                            ${i}
                        </select>
                    </td>
                </tr>
            `).join("");if(l){const a=r.front_error?o.innerHTML:"";o.innerHTML=`${a}
                    <label class="form-label">Map Front inboxes to LNSCRM shared inboxes</label>
                    <table class="front-mapping-table">
                        <thead><tr><th>Front inbox</th><th>LNSCRM shared inbox</th></tr></thead>
                        <tbody>${l}</tbody>
                    </table>
                `}else r.front_error||(o.innerHTML=`
                    <div class="form-help" style="margin-bottom:0.75rem;">No Front inboxes returned. Preview and import will use tag-based matching across your ${(r.shared_inboxes||[]).length} shared inbox(es).</div>
                    <div class="form-help">Local shared inboxes: ${(r.shared_inboxes||[]).map(a=>y(a.name)).join(", ")||"none found"}</div>
                `);(r.rows||[]).forEach(a=>{const c=o.querySelector(`[data-front-inbox-id="${a.front_id}"]`);c&&a.shared_inbox_id&&(c.value=String(a.shared_inbox_id))})}catch(n){console.error("Error loading Front mapping:",n),o.innerHTML='<span class="form-help" style="color:#ef4444;">Could not load inbox mapping. You can still try Preview — import will match by tags across all shared inboxes.</span>'}}}function k(t,e="error"){const o=document.getElementById("front-token-error");o&&(t?(o.textContent=t,o.style.display="block",o.style.color=e==="warning"?"#b45309":"#b91c1c"):(o.style.display="none",o.textContent=""))}async function I(t=!0){const e=document.getElementById("front-api-token")?.value?.trim()||"",o=window.existingIntegration&&window.existingIntegration.has_token;if(!e&&!o)return k("Please enter your Front API token."),!1;k("");try{const n=await fetch("/api/integrations/front",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({api_token:e||null})}),r=await n.json().catch(()=>({}));if(!n.ok){const l=r.error||(r.errors?Object.values(r.errors).flat().join(", "):"")||`Could not save Front token (HTTP ${n.status}).`;return k(l),!1}const i=r.status==="connected";if(m.status=r.status||"disconnected",window.existingIntegration={...window.existingIntegration||{},api_token:"***hidden***",has_token:!0,is_active:!0,status:m.status,verify_error:r.verify_warning||null},r.verify_warning&&k(`Token saved, but Front could not verify it: ${r.verify_warning}`,"warning"),t&&i)f(),h(g);else if(i){const l=document.getElementById("front-api-token");l&&(l.value="");const a=document.getElementById("front-import-panel");a&&(a.hidden=!1),await Y(window.existingIntegration)}else await N("front");return i}catch(n){return console.error("Error:",n),k("Error saving Front integration. Please try again."),!1}}async function ve(){try{(await fetch("/api/integrations/front",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert("Front has been disconnected."),f(),h(g)):alert("Error disconnecting Front.")}catch(t){console.error("Error:",t),alert("Error disconnecting Front.")}}async function D(){if(!confirm("Reset Front sync progress? The next run will rescan every conversation from the start instead of resuming or skipping ones already synced."))return;const t=document.getElementById("front-reset-progress-btn");t&&(t.disabled=!0);try{(await fetch("/api/integrations/front/import-progress",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?document.getElementById("front-import-results")?.replaceChildren():alert("Error resetting Front sync progress.")}catch(e){console.error("Error:",e),alert("Error resetting Front sync progress.")}finally{t&&(t.disabled=!1)}}async function Q(t=!1){const e=window.existingIntegration&&window.existingIntegration.has_token,o=document.getElementById("front-api-token")?.value?.trim()||"";if(!e&&!o){k("Save your Front API token first.");return}if(o&&!await I(!1))return;const n=V(),r=t?"Previewing":"Importing";if(n.length===0&&T>0){z('Every Front inbox is set to "— Skip —", so there is nothing to import. Map at least one Front inbox to a LNSCRM shared inbox first.');return}const i=J();w(!0,`${r}…`,n.length?0:null),document.getElementById("front-import-results")?.replaceChildren();try{if(n.length===0){w(!0,t?`${r} (first 100)…`:`${r} tags across all shared inboxes…`,null);const a=await B(t,{},null);M(i,a.stats||{}),w(!0,"Finishing…",100)}else{for(let a=0;a<n.length;a++){const c=n[a],p=a/n.length*100,s=(a+1)/n.length*100;w(!0,`${r} ${c.frontName} (${a+1} of ${n.length})…`,p);const d=await ye(t,c,r,p,s);M(i,d)}w(!0,"Finishing…",100),await B(t,{},null,!0,null,i)}const l=new Date().toISOString();X(i,t,l),window.existingIntegration={...window.existingIntegration||{},last_import_stats:i,last_import_dry_run:t,last_import_at:l}}catch(l){console.error("Front import error:",l),z(l.message||"Front tag import failed. Please try again.")}finally{w(!1)}}function Z(t,e=!1,o=null){const n=document.getElementById("front-comment-import-results");if(!n||!t)return;const r=Array.isArray(t.unmatched_samples)?t.unmatched_samples:[],i=o?new Date(o).toLocaleString():new Date().toLocaleString();n.innerHTML=`
            <div class="front-import-results">
                <h4>${e?"Comment preview":"Comment import results"} <span style="font-weight:400;color:var(--text-secondary);">· ${i}</span></h4>
                ${e&&t.preview_limit?`<p class="form-help" style="margin:0 0 0.75rem;color:#b45309;">Preview shows the first ${t.preview_limit} Front conversations only. Run import to process all.</p>`:""}
                <dl>
                    <dt>Mapped inboxes</dt><dd>${t.mapped_inboxes??0}</dd>
                    ${e?`<dt>Conversations scanned</dt><dd>${t.conversations_scanned??0}</dd>`:""}
                    ${!e&&t.conversations_already_synced?`<dt>Already scanned (skipped)</dt><dd>${t.conversations_already_synced}</dd>`:""}
                    <dt>Matched conversations</dt><dd>${t.conversations_matched??0}</dd>
                    <dt>Unmatched conversations</dt><dd>${t.conversations_unmatched??0}</dd>
                    <dt>Conversations with comments</dt><dd>${t.conversations_with_comments??0}</dd>
                    <dt>Comments ${e?"would import":"imported"}</dt><dd>${t.comments_imported??0}</dd>
                    <dt>Attachments ${e?"would import":"imported"}</dt><dd>${t.attachments_imported??0}</dd>
                    ${t.attachments_failed?`<dt>Attachments skipped</dt><dd>${t.attachments_failed}</dd>`:""}
                    <dt>Already imported (skipped)</dt><dd>${t.comments_existing??0}</dd>
                    ${t.comments_unmatched_author?`<dt>Authors not matched to CRM users</dt><dd>${t.comments_unmatched_author}</dd>`:""}
                </dl>
                ${r.length?`<ul class="front-unmatched-list">${r.map(l=>`<li>${y(String(l))}</li>`).join("")}</ul>`:""}
            </div>
        `}function ee(t){const e=document.getElementById("front-comment-import-results");e&&(e.innerHTML=`
            <div class="front-import-results" style="border-color:#fecaca;">
                <h4 style="color:#b91c1c;margin-bottom:0.5rem;">Comment import failed</h4>
                <p class="form-help" style="margin:0;color:#b91c1c;">${y(String(t||"Unknown error"))}</p>
            </div>
        `)}function te(){return{mapped_inboxes:0,conversations_scanned:0,conversations_already_synced:0,conversations_matched:0,conversations_unmatched:0,conversations_with_comments:0,comments_imported:0,comments_existing:0,comments_unmatched_author:0,attachments_imported:0,attachments_failed:0,unmatched_samples:[]}}function L(t,e){return e&&(["mapped_inboxes","conversations_scanned","conversations_already_synced","conversations_matched","conversations_unmatched","conversations_with_comments","comments_imported","comments_existing","comments_unmatched_author","attachments_imported","attachments_failed"].forEach(n=>{t[n]=(Number(t[n])||0)+(Number(e[n])||0)}),e.import_mode&&(t.import_mode=e.import_mode),e.preview_limit&&(t.preview_limit=e.preview_limit),e.preview_limited&&(t.preview_limited=e.preview_limited),e.inbox_errors?.length&&(t.inbox_errors=[...t.inbox_errors||[],...e.inbox_errors]),(e.unmatched_samples||[]).forEach(n=>{(t.unmatched_samples||[]).length<10&&!(t.unmatched_samples||[]).includes(n)&&(t.unmatched_samples=[...t.unmatched_samples||[],n])})),t}function _(t,e="Processing…",o=null){const n=document.getElementById("front-comment-import-loading"),r=document.getElementById("front-comment-import-loading-label"),i=document.getElementById("front-comment-import-loading-fill");j().forEach(a=>{a&&(a.disabled=!!t)});const l=o==null?null:Math.max(0,Math.min(100,Math.round(o)));r&&(r.textContent=l===null?e:`${e} ${l}%`),n&&(n.hidden=!t),i&&(l===null?(i.classList.remove("determinate"),i.style.width=""):(i.classList.add("determinate"),i.style.width=`${l}%`))}async function O(t,e,o,n=!0,r=null,i=null){const l={dry_run:t,inbox_map:e,front_inbox_id:o||null,persist_results:n};r&&(l.page_url=r),i&&(l.result_stats=i);const a=await fetch("/api/integrations/front/import-comments",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify(l)});return R(a)}async function we(t,e,o,n=0,r=100){const i=te();if(t){_(!0,`${o} ${e.frontName} (first 100)…`,n);const p=await O(t,{[e.frontId]:e.sharedId},e.frontId,!1);return L(i,p.stats||{}),i}let l=null,a=0,c="";do{a+=1;const p=1-1/(a+1),s=n+(r-n)*p;_(!0,`${o} ${e.frontName} – page ${a}${c}…`,s);const d=await O(t,{[e.frontId]:e.sharedId},e.frontId,!1,l);L(i,d.stats||{}),d.stats?.resumed_from&&(c=` (resuming after ${d.stats.resumed_from} already done)`),l=d.has_more&&d.next_page_url?d.next_page_url:null}while(l);return i}async function oe(){if(!confirm("Reset Front comment sync progress? The next run will rescan every conversation for comments instead of resuming or skipping ones already scanned. Existing imported comments are kept."))return;const t=document.getElementById("front-comment-reset-progress-btn");t&&(t.disabled=!0);try{(await fetch("/api/integrations/front/comment-import-progress",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?document.getElementById("front-comment-import-results")?.replaceChildren():alert("Error resetting Front comment sync progress.")}catch(e){console.error("Error:",e),alert("Error resetting Front comment sync progress.")}finally{t&&(t.disabled=!1)}}async function ne(t=!1){const e=window.existingIntegration&&window.existingIntegration.has_token,o=document.getElementById("front-api-token")?.value?.trim()||"";if(!e&&!o){k("Save your Front API token first.");return}if(o&&!await I(!1))return;const n=V(),r=t?"Previewing comments in":"Importing comments from";if(n.length===0&&T>0){ee('Every Front inbox is set to "— Skip —", so there is nothing to import. Map at least one Front inbox to a LNSCRM shared inbox first.');return}const i=te();_(!0,`${r}…`,n.length?0:null),document.getElementById("front-comment-import-results")?.replaceChildren();try{if(n.length===0){_(!0,t?`${r} (first 100)…`:`${r} mapped inboxes…`,null);const a=await O(t,{},null);L(i,a.stats||{}),_(!0,"Finishing…",100)}else{for(let a=0;a<n.length;a++){const c=n[a],p=a/n.length*100,s=(a+1)/n.length*100;_(!0,`${r} ${c.frontName} (${a+1} of ${n.length})…`,p);const d=await we(t,c,r,p,s);L(i,d)}_(!0,"Finishing…",100),await O(t,{},null,!0,null,i)}const l=new Date().toISOString();Z(i,t,l),window.existingIntegration={...window.existingIntegration||{},last_comment_import_stats:i,last_comment_import_dry_run:t,last_comment_import_at:l}}catch(l){console.error("Front comment import error:",l),ee(l.message||"Front comment import failed. Please try again.")}finally{_(!1)}}function re(t,e=!1,o=null){const n=document.getElementById("front-discussion-import-results");if(!n||!t)return;const r=Array.isArray(t.unmatched_samples)?t.unmatched_samples:[],i=o?new Date(o).toLocaleString():new Date().toLocaleString();n.innerHTML=`
            <div class="front-import-results">
                <h4>${e?"Discussion preview":"Discussion import results"} <span style="font-weight:400;color:var(--text-secondary);">· ${i}</span></h4>
                ${e&&t.preview_limit?`<p class="form-help" style="margin:0 0 0.75rem;color:#b45309;">Preview shows the first ${t.preview_limit} Front conversations only. Run import to process all.</p>`:""}
                <dl>
                    <dt>Conversations scanned</dt><dd>${t.conversations_scanned??0}</dd>
                    <dt>Skipped (not discussions)</dt><dd>${t.conversations_skipped??0}</dd>
                    ${!e&&t.discussions_already_synced?`<dt>Already scanned (skipped)</dt><dd>${t.discussions_already_synced}</dd>`:""}
                    <dt>Discussions found</dt><dd>${t.discussions_found??0}</dd>
                    <dt>Discussions ${e?"would import":"imported"}</dt><dd>${t.discussions_imported??0}</dd>
                    <dt>Discussions with comments</dt><dd>${t.discussions_with_comments??0}</dd>
                    <dt>Messages ${e?"would import":"imported"}</dt><dd>${t.messages_imported??0}</dd>
                    <dt>Already imported (skipped)</dt><dd>${t.messages_existing??0}</dd>
                    ${t.messages_unmatched_author?`<dt>Authors not matched to CRM users</dt><dd>${t.messages_unmatched_author}</dd>`:""}
                    ${t.discussions_skipped_no_users?`<dt>Skipped (no matching users)</dt><dd>${t.discussions_skipped_no_users}</dd>`:""}
                </dl>
                ${r.length?`<ul class="front-unmatched-list">${r.map(l=>`<li>${y(String(l))}</li>`).join("")}</ul>`:""}
            </div>
        `}function ke(t){const e=document.getElementById("front-discussion-import-results");e&&(e.innerHTML=`
            <div class="front-import-results" style="border-color:#fecaca;">
                <h4 style="color:#b91c1c;margin-bottom:0.5rem;">Discussion import failed</h4>
                <p class="form-help" style="margin:0;color:#b91c1c;">${y(String(t))}</p>
            </div>
        `)}function _e(){return{conversations_scanned:0,conversations_skipped:0,discussions_found:0,discussions_already_synced:0,discussions_imported:0,discussions_with_comments:0,discussions_skipped_no_users:0,messages_imported:0,messages_existing:0,messages_unmatched_author:0,messages_skipped_no_user:0,unmatched_samples:[]}}function se(t,e){return e&&(["conversations_scanned","conversations_skipped","discussions_found","discussions_already_synced","discussions_imported","discussions_with_comments","discussions_skipped_no_users","messages_imported","messages_existing","messages_unmatched_author","messages_skipped_no_user"].forEach(n=>{t[n]=(Number(t[n])||0)+(Number(e[n])||0)}),e.preview_limit&&(t.preview_limit=e.preview_limit),e.preview_limited&&(t.preview_limited=e.preview_limited),e.comment_errors?.length&&(t.comment_errors=[...t.comment_errors||[],...e.comment_errors]),(e.unmatched_samples||[]).forEach(n=>{(t.unmatched_samples||[]).length<10&&!(t.unmatched_samples||[]).includes(n)&&(t.unmatched_samples=[...t.unmatched_samples||[],n])})),t}function x(t,e="Processing…",o=null){const n=document.getElementById("front-discussion-import-loading"),r=document.getElementById("front-discussion-import-loading-label"),i=document.getElementById("front-discussion-import-loading-fill");j().forEach(a=>{a&&(a.disabled=!!t)});const l=o==null?null:Math.max(0,Math.min(100,Math.round(o)));r&&(r.textContent=l===null?e:`${e} ${l}%`),n&&(n.hidden=!t),i&&(l===null?(i.classList.remove("determinate"),i.style.width=""):(i.classList.add("determinate"),i.style.width=`${l}%`))}async function W(t,e=!0,o=null,n=null){const r={dry_run:t,persist_results:e};o&&(r.page_url=o),n&&(r.result_stats=n);const i=await fetch("/api/integrations/front/import-discussions",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-Requested-With":"XMLHttpRequest","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify(r)});return R(i)}async function ie(){if(!confirm("Reset Front discussion sync progress? The next run will rescan every conversation for discussion threads instead of resuming or skipping ones already scanned. Existing imported chats are kept."))return;const t=document.getElementById("front-discussion-reset-progress-btn");t&&(t.disabled=!0);try{(await fetch("/api/integrations/front/discussion-import-progress",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?document.getElementById("front-discussion-import-results")?.replaceChildren():alert("Error resetting Front discussion sync progress.")}catch(e){console.error("Error:",e),alert("Error resetting Front discussion sync progress.")}finally{t&&(t.disabled=!1)}}async function ae(t=!1){const e=window.existingIntegration&&window.existingIntegration.has_token,o=document.getElementById("front-api-token")?.value?.trim()||"";if(!e&&!o){k("Save your Front API token first.");return}if(o&&!await I(!1))return;const n=t?"Previewing discussions":"Importing discussions",r=_e();x(!0,`${n}…`,0),document.getElementById("front-discussion-import-results")?.replaceChildren();try{if(t){x(!0,`${n} (first 100)…`,20);const l=await W(!0,!1);se(r,l.stats||{})}else{let l=null,a=0,c="";do{a+=1;const p=Math.min(95,100-100/(a+1));x(!0,`${n} – page ${a}${c}…`,p);const s=await W(!1,!1,l);se(r,s.stats||{}),s.stats?.resumed_from&&(c=` (resuming after ${s.stats.resumed_from} already done)`),l=s.has_more&&s.next_page_url?s.next_page_url:null}while(l)}x(!0,"Finishing…",100),await W(t,!0,null,r);const i=new Date().toISOString();re(r,t,i),window.existingIntegration={...window.existingIntegration||{},last_discussion_import_stats:r,last_discussion_import_dry_run:t,last_discussion_import_at:i}}catch(i){console.error("Front discussion import error:",i),ke(i.message||"Front discussion import failed. Please try again.")}finally{x(!1)}}async function le(t="google"){const e=t==="outlook"?{microsoft_client_id:document.getElementById("oauth-microsoft-client-id")?.value?.trim()||"",microsoft_client_secret:document.getElementById("oauth-microsoft-client-secret")?.value||"",microsoft_tenant_id:document.getElementById("oauth-microsoft-tenant-id")?.value?.trim()||""}:{google_client_id:document.getElementById("oauth-google-client-id")?.value?.trim()||"",google_client_secret:document.getElementById("oauth-google-client-secret")?.value||""},o=t==="outlook"?"Microsoft Outlook":"Google Calendar";try{const n=await fetch(b.calendarOauthSettingsStoreUrl||"/api/calendar/oauth-settings",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||"","X-Requested-With":"XMLHttpRequest"},body:JSON.stringify(e)}),r=await n.json();if(n.ok){const i=u.find(a=>a.id==="calendar"),l=u.find(a=>a.id==="outlook");i&&(i.status=r.google_configured?"connected":"disconnected"),l&&(l.status=r.outlook_configured?"connected":"disconnected"),alert(r.message||`${o} OAuth settings saved.`),f(),h(g)}else alert(r.error||r.message||"Failed to save settings.")}catch(n){console.error(n),alert(`Failed to save ${o} OAuth settings.`)}}async function Se(){try{(await fetch("/api/integrations/openai",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert("OpenAI has been disconnected."),f(),h(g)):alert("Error disconnecting. Please try again.")}catch(t){console.error("Error:",t),alert("Error disconnecting. Please try again.")}}async function C(){if(m)if(m.status==="connected"&&m.id!=="wise"){if(confirm(`Are you sure you want to disconnect ${m.name}?`))if(m.id==="wise")try{(await fetch("/api/integrations/wise",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert(m.name+" has been disconnected."),f(),h(g)):alert("Error disconnecting integration. Please try again.")}catch(t){console.error("Error:",t),alert("Error disconnecting integration. Please try again.")}else if(m.id==="twilio")await U();else if(m.id==="viber")try{(await fetch("/api/integrations/viber",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert(`${m.name} has been disconnected.`),f(),h(g)):alert("Error disconnecting integration. Please try again.")}catch(t){console.error("Error:",t),alert("Error disconnecting integration. Please try again.")}else if(m.id==="whatsapp")try{(await fetch("/api/integrations/whatsapp",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert(`${m.name} has been disconnected.`),f(),h(g)):alert("Error disconnecting integration. Please try again.")}catch(t){console.error("Error:",t),alert("Error disconnecting integration. Please try again.")}else if(m.id==="facebook")try{(await fetch("/api/integrations/facebook",{method:"DELETE",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).ok?(m.status="disconnected",alert(`${m.name} has been disconnected.`),f(),h(g)):alert("Error disconnecting integration. Please try again.")}catch(t){console.error("Error:",t),alert("Error disconnecting integration. Please try again.")}else m.status="disconnected",alert(`${m.name} has been disconnected.`),f(),h(g)}else{if(m.id==="stripe"){H();return}if(m.id==="gmail"){const t=document.getElementById("gmail-email")?.value?.trim()||"",e=document.getElementById("gmail-app-password")?.value||"";if(!t){alert("Please enter your Gmail address.");return}if(!e){alert("Please enter your Gmail App Password.");return}try{const o=await fetch("/api/integrations/gmail",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({email:t,app_password:e})}),n=await o.json();o.ok?(m.status="connected",alert("Gmail has been connected successfully! You can now send emails from Quotation Builder and other features."),f(),h(g)):alert(n.error||(n.errors?Object.values(n.errors).flat().join(", "):"Error connecting. Please try again."))}catch(o){console.error("Error:",o),alert("Error connecting Gmail. Please try again.")}}else if(m.id==="openai")K();else if(m.id==="storeganise")G();else if(m.id==="front")I(!1);else if(m.id==="wise"){const t=document.getElementById("wise-api-token")?.value||"",e=document.getElementById("wise-profile-id")?.value||"",o=document.getElementById("wise-sandbox")?.checked||!1,n=window.existingIntegration&&window.existingIntegration.api_token;if(!t&&!n){alert("Please enter your Wise API Token.");return}try{const r=await fetch("/api/integrations/wise",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({api_token:t,profile_id:e||null,is_sandbox:o})}),i=await r.json();r.ok?(m.status="connected",alert(m.name+" has been connected successfully!"),f(),h(g)):alert(i.error||(i.errors?Object.values(i.errors).flat().join(", "):"Error connecting integration. Please try again."))}catch(r){console.error("Error:",r),alert("Error connecting Wise. Please try again.")}}else if(m.id==="twilio")await q();else if(m.id==="viber"){const t=document.getElementById("viber-sender-id")?.value?.trim()||"",e=document.getElementById("viber-bot-name")?.value?.trim()||"",o=document.getElementById("viber-welcome-message")?.value||"";if(!t){alert("Please enter your Twilio Viber Sender ID.");return}try{const n=await fetch("/api/integrations/viber",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({sender_id:t,bot_name:e||null,welcome_message:o})}),r=await n.json();if(n.ok){m.status="connected";let i="Viber Business has been connected successfully via Twilio!";r.integration?.webhook_url&&(i+=`

Paste this webhook URL on your Twilio Viber sender:
`+r.integration.webhook_url),alert(i),f(),h(g)}else alert(r.error||(r.errors?Object.values(r.errors).flat().join(", "):"Error connecting Viber."))}catch(n){console.error("Error:",n),alert("Error connecting Viber. Please try again.")}}else if(m.id==="whatsapp"){const t=document.getElementById("whatsapp-from-number")?.value?.trim()||"",e=document.getElementById("whatsapp-business-name")?.value?.trim()||"",o=document.getElementById("whatsapp-welcome-message")?.value||"";if(!t){alert("Please enter your WhatsApp from number (E.164).");return}try{const n=await fetch("/api/integrations/whatsapp",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({from_number:t,business_name:e||null,welcome_message:o})}),r=await n.json();if(n.ok){m.status="connected";let i="WhatsApp Business has been connected successfully via Twilio!";r.integration?.webhook_url&&(i+=`

Paste this webhook URL on your Twilio WhatsApp sender:
`+r.integration.webhook_url),alert(i),f(),h(g)}else alert(r.error||(r.errors?Object.values(r.errors).flat().join(", "):"Error connecting WhatsApp."))}catch(n){console.error("Error:",n),alert("Error connecting WhatsApp. Please try again.")}}else m.id==="facebook"?await ce():(alert(`Connecting to ${m.name}...`),m.status="connected",alert(`${m.name} has been connected successfully!`),f(),h(g))}}async function ce(){const t=document.getElementById("facebook-page-id")?.value?.trim()||"",e=document.getElementById("facebook-page-name")?.value?.trim()||"",o=document.getElementById("facebook-page-access-token")?.value?.trim()||"",n=document.getElementById("facebook-app-secret")?.value?.trim()||"",r=document.getElementById("facebook-instagram-id")?.value?.trim()||"",i=document.getElementById("facebook-instagram-username")?.value?.trim()||"",l=document.getElementById("facebook-welcome-message")?.value||"";if(!t){alert("Please enter your Facebook Page ID from Twilio Console.");return}try{const a={page_id:t,page_name:e||null,instagram_business_account_id:r||null,instagram_username:i||null,welcome_message:l};o&&(a.page_access_token=o),n&&(a.app_secret=n);const c=await fetch("/api/integrations/facebook",{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify(a)}),p=await c.json();if(c.ok){m.status="connected";let s="Facebook & Instagram settings saved.";p.integration?.instagram_graph?s+=`

Instagram Direct is ready. Paste the Webhook URL and Verify Token into Meta App → Messenger → Instagram settings.`:p.integration?.has_page_access_token||(s+=`

Add a Page Access Token so Instagram DMs can use Meta webhooks.`),p.integration?.webhook_url&&(s+=`

Webhook URL:
`+p.integration.webhook_url),p.integration?.webhook_verify_token&&(s+=`
Verify token:
`+p.integration.webhook_verify_token),alert(s),f(),h(g)}else alert(p.error||(p.errors?Object.values(p.errors).flat().join(", "):"Error saving Facebook."))}catch(a){console.error("Error:",a),alert("Error saving Facebook. Please try again.")}}async function de(t){t?.preventDefault();const e=document.getElementById("facebook-sync-days")?.value||"90",o=document.getElementById("facebook-sync-btn"),n=document.getElementById("facebook-sync-help"),r=o?.textContent;o&&(o.disabled=!0,o.textContent="Syncing…"),n&&(n.textContent="Importing Facebook inbox and Twilio history… this can take a few minutes.");try{let i;try{i=await fetch("/api/facebook/sync",{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({days:Number(e),limit:2e3})})}catch{throw new Error("Could not reach the server. If Sync was still Pending, it timed out — try Last 30 days.")}const l=await i.json().catch(()=>({}));if(!i.ok){const pe=[502,504,524,408].includes(i.status);throw new Error(l.message||l.error||(pe?"Sync timed out. Try Last 30 days, or add a Page Access Token under Integrations.":"Sync failed (HTTP "+i.status+")."))}const a=l.data||{},c=Number(a.imported||0),p=Number(a.skipped||0),s=Number(a.scanned||0),d=Number(a.days||e)===0?"all available Twilio history":`the last ${a.days||e} days`,P=a.hint||"",A=c?`Imported ${c} message${c===1?"":"s"} from ${d}${p?` (${p} already in CRM)`:""}.`:s?`No new messages. Found ${s} in ${d}; they are already in the CRM.`:`No Messenger history found in ${d}.`,E=P?A+`

`+P:A;n&&(n.textContent=E),alert(E)}catch(i){console.error("Error:",i);const l=i.message||"Could not sync old Facebook messages.";n&&(n.textContent=l),alert(l)}finally{o&&(o.disabled=!1,o.textContent=r||"Sync Messenger inbox")}}function f(){document.getElementById("integrationModal").classList.remove("active"),document.body.style.overflow="",m=null}document.getElementById("integrationModal").addEventListener("click",function(t){t.target===this&&f()}),document.addEventListener("keydown",function(t){t.key==="Escape"&&f()});async function Ee(){const t=u.find(s=>s.id==="gmail");if(t)try{const s=await fetch("/api/integrations/gmail");if(s.ok){const d=await s.json();d.integration&&d.status==="connected"&&(t.status="connected")}}catch(s){console.error("Error loading Gmail integration on init:",s)}const e=u.find(s=>s.id==="wise");if(e)try{const s=await fetch("/api/integrations/wise");if(s.ok){const d=await s.json();d.integration&&d.status==="connected"&&(e.status="connected")}}catch(s){console.error("Error loading Wise integration on init:",s)}const o=u.find(s=>s.id==="twilio");if(o)try{const s=await fetch("/api/integrations/twilio");if(s.ok){const d=await s.json();o.status=d.status??"disconnected"}}catch(s){console.error("Error loading Twilio integration on init:",s)}const n=u.find(s=>s.id==="viber");if(n)try{const s=await fetch("/api/integrations/viber");if(s.ok){const d=await s.json();d.integration&&(n.status=d.status??"disconnected")}}catch(s){console.error("Error loading Viber integration on init:",s)}const r=u.find(s=>s.id==="whatsapp");if(r)try{const s=await fetch("/api/integrations/whatsapp");if(s.ok){const d=await s.json();d.integration&&(r.status=d.status??"disconnected")}}catch(s){console.error("Error loading WhatsApp integration on init:",s)}const i=u.find(s=>s.id==="facebook");if(i)try{const s=await fetch("/api/integrations/facebook");if(s.ok){const d=await s.json();d.integration&&(i.status=d.status??"disconnected")}}catch(s){console.error("Error loading Facebook integration on init:",s)}const l=u.find(s=>s.id==="stripe");if(l)try{const s=await fetch("/api/integrations/stripe");if(s.ok){const d=await s.json();d.integration&&d.status==="connected"&&(l.status="connected")}}catch(s){console.error("Error loading Stripe integration on init:",s)}const a=u.find(s=>s.id==="openai");if(a)try{const s=await fetch("/api/integrations/openai");if(s.ok){const d=await s.json();d.integration&&d.status==="connected"&&(a.status="connected")}}catch(s){console.error("Error loading OpenAI integration on init:",s)}const c=u.find(s=>s.id==="storeganise");if(c)try{const s=await fetch("/api/integrations/storeganise");if(s.ok){const d=await s.json();d.integration&&d.status==="connected"&&(c.status="connected")}}catch(s){console.error("Error loading Storeganise integration on init:",s)}const p=u.find(s=>s.id==="front");if(p)try{const s=await fetch("/api/integrations/front");if(s.ok){const d=await s.json();d.integration&&d.status==="connected"&&(p.status="connected")}}catch(s){console.error("Error loading Front integration on init:",s)}try{const s=await fetch(b.calendarOauthSettingsUrl||"/api/calendar/oauth-settings");if(s.ok){const d=await s.json(),P=u.find(E=>E.id==="calendar"),A=u.find(E=>E.id==="outlook");P&&(P.status=d.google_configured?"connected":"disconnected"),A&&(A.status=d.outlook_configured?"connected":"disconnected")}}catch(s){console.error("Error loading Calendar/Outlook OAuth status on init:",s)}h()}Ee(),typeof f=="function"&&(window.closeIntegrationModal=f),typeof ne=="function"&&(window.handleFrontCommentImport=ne),typeof oe=="function"&&(window.handleFrontCommentResetProgress=oe),typeof ae=="function"&&(window.handleFrontDiscussionImport=ae),typeof ie=="function"&&(window.handleFrontDiscussionResetProgress=ie),typeof Q=="function"&&(window.handleFrontImport=Q),typeof D=="function"&&(window.handleFrontResetProgress=D),typeof C=="function"&&(window.handleIntegrationAction=C),typeof N=="function"&&(window.openIntegrationModal=N),typeof de=="function"&&(window.syncFacebookHistory=de)})();
