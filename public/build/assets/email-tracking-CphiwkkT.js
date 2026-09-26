(function(){let i=[{id:1,name:"Sales Introduction",category:"sales",subject:"Introduction to Our Services",body:`Hi {{first_name}},

I hope this email finds you well. I wanted to introduce you to our company and the services we offer...`,trackOpens:!0,trackClicks:!0},{id:2,name:"Follow-up Email",category:"follow-up",subject:"Following up on our conversation",body:`Hi {{first_name}},

I wanted to follow up on our recent conversation about {{product}}...`,trackOpens:!0,trackClicks:!0},{id:3,name:"Welcome Email",category:"welcome",subject:"Welcome to {{company}}!",body:`Hi {{first_name}},

Welcome to {{company}}! We're excited to have you on board...`,trackOpens:!0,trackClicks:!1},{id:4,name:"Product Nurture",category:"nurture",subject:"Learn more about {{product}}",body:`Hi {{first_name}},

I thought you might be interested in learning more about {{product}}...`,trackOpens:!0,trackClicks:!0}],p=[{id:1,name:"Sales Follow-up Sequence",description:"5-step follow-up sequence for sales",steps:[{templateId:1,delay:0,delayUnit:"days"},{templateId:2,delay:3,delayUnit:"days"},{templateId:2,delay:7,delayUnit:"days"},{templateId:4,delay:14,delayUnit:"days"}],active:!0,sent:245,opened:168,replied:42}],k=[{id:1,recipient:"john@example.com",subject:"Introduction to Our Services",type:"Template",sent:"2025-01-15 10:00",opened:"2025-01-15 14:30",replied:"2025-01-16 09:00",clicked:!0,status:"replied"},{id:2,recipient:"sarah@example.com",subject:"Following up on our conversation",type:"Sequence",sent:"2025-01-15 11:00",opened:"2025-01-15 15:20",replied:null,clicked:!0,status:"opened"},{id:3,recipient:"mike@example.com",subject:"Welcome to Our Company!",type:"Template",sent:"2025-01-16 09:00",opened:null,replied:null,clicked:!1,status:"pending"},{id:4,recipient:"lisa@example.com",subject:"Learn more about our product",type:"Sequence",sent:"2025-01-16 10:30",opened:"2025-01-16 11:15",replied:"2025-01-16 14:00",clicked:!0,status:"replied"}],s=0;function b(t){document.querySelectorAll(".email-tab").forEach(e=>e.classList.remove("active")),document.querySelector(`[data-tab="${t}"]`).classList.add("active"),document.querySelectorAll(".tab-content").forEach(e=>e.classList.remove("active")),document.getElementById(`${t}Tab`).classList.add("active"),t==="templates"?m():t==="sequences"?x():t==="tracking"?H():t==="analytics"?j():t==="accounts"&&(d(),r())}function m(){const t=document.getElementById("templatesGrid");t.innerHTML=i.map(e=>`
            <div class="template-card" onclick="editTemplate(${e.id})">
                <div class="template-header">
                    <div>
                        <div class="template-title">${e.name}</div>
                        <span class="template-category">${e.category}</span>
                    </div>
                </div>
                <div class="template-subject">${e.subject}</div>
                <div class="template-preview">${e.body.substring(0,150)}...</div>
                <div class="template-actions" onclick="event.stopPropagation()">
                    <button class="template-action-btn" onclick="useTemplate(${e.id})">Use</button>
                    <button class="template-action-btn" onclick="editTemplate(${e.id})">Edit</button>
                    <button class="template-action-btn" onclick="deleteTemplate(${e.id})">Delete</button>
                </div>
            </div>
        `).join("")}function w(){document.getElementById("templateModalTitle").textContent="New Email Template",document.getElementById("templateForm").reset(),document.getElementById("templateModal").classList.add("active"),document.body.style.overflow="hidden"}function v(){document.getElementById("templateModal").classList.remove("active"),document.body.style.overflow=""}function h(t){const e=i.find(n=>n.id===t);e&&(document.getElementById("templateModalTitle").textContent="Edit Email Template",document.getElementById("templateName").value=e.name,document.getElementById("templateCategorySelect").value=e.category,document.getElementById("templateSubject").value=e.subject,document.getElementById("templateBody").value=e.body,document.getElementById("templateTrackOpens").checked=e.trackOpens,document.getElementById("templateTrackClicks").checked=e.trackClicks,document.getElementById("templateModal").classList.add("active"),document.body.style.overflow="hidden")}function $(t){confirm("Are you sure you want to delete this template?")&&(i=i.filter(e=>e.id!==t),m())}function T(t){alert(`Template ${t} would be used to send an email`)}function L(t){const e=document.getElementById("templateBody"),n=e.selectionStart,o=e.selectionEnd,c=e.value,l="{{"+t+"}}";e.value=c.substring(0,n)+l+c.substring(o),e.focus(),e.setSelectionRange(n+l.length,n+l.length)}document.addEventListener("DOMContentLoaded",function(){document.querySelectorAll(".editor-btn[data-variable]").forEach(t=>{t.addEventListener("click",function(){const e=this.getAttribute("data-variable");L(e)})})});function B(){alert("Template preview would be displayed here")}function x(){const t=document.getElementById("sequencesList");t.innerHTML=p.map(e=>{const n=e.steps.map((o,c)=>{const l=i.find(u=>u.id===o.templateId);return`
                    <div class="sequence-step-preview">
                        <div class="step-number">${c+1}</div>
                        <div class="step-info">
                            <div class="step-title">${l?l.name:"Template"}</div>
                            <div class="step-delay">Send after ${o.delay} ${o.delayUnit}</div>
                        </div>
                    </div>
                `}).join("");return`
                <div class="sequence-card" onclick="editSequence(${e.id})">
                    <div class="sequence-header">
                        <div class="sequence-title">${e.name}</div>
                        <div class="sequence-stats">
                            <div class="sequence-stat">
                                <span>Sent: ${e.sent}</span>
                            </div>
                            <div class="sequence-stat">
                                <span>Opened: ${e.opened}</span>
                            </div>
                            <div class="sequence-stat">
                                <span>Replied: ${e.replied}</span>
                            </div>
                        </div>
                    </div>
                    <div class="sequence-steps-preview">
                        ${n}
                    </div>
                </div>
            `}).join("")}function E(){s=0,document.getElementById("sequenceModalTitle").textContent="New Email Sequence",document.getElementById("sequenceForm").reset(),document.getElementById("sequenceSteps").innerHTML="",document.getElementById("sequenceModal").classList.add("active"),document.body.style.overflow="hidden"}function f(){document.getElementById("sequenceModal").classList.remove("active"),document.body.style.overflow=""}function S(){s++;const t=document.getElementById("sequenceSteps"),e=`
            <div class="sequence-step" data-step-id="${s}">
                <div class="step-header">
                    <div class="step-title">Step ${s}</div>
                    <button type="button" class="step-remove" onclick="removeSequenceStep(${s})">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
                <div class="form-group">
                    <label class="form-label">Template *</label>
                    <select class="form-input step-template" required>
                        <option value="">Select a template</option>
                        ${i.map(n=>`<option value="${n.id}">${n.name}</option>`).join("")}
                    </select>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Delay *</label>
                        <input type="number" class="form-input step-delay" min="0" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Delay Unit *</label>
                        <select class="form-input step-delay-unit" required>
                            <option value="hours">Hours</option>
                            <option value="days" selected>Days</option>
                            <option value="weeks">Weeks</option>
                        </select>
                    </div>
                </div>
            </div>
        `;t.insertAdjacentHTML("beforeend",e)}function q(t){const e=document.querySelector(`[data-step-id="${t}"]`);e&&e.remove()}function I(t){const e=p.find(o=>o.id===t);if(!e)return;document.getElementById("sequenceModalTitle").textContent="Edit Email Sequence",document.getElementById("sequenceName").value=e.name,document.getElementById("sequenceDescription").value=e.description||"";const n=document.getElementById("sequenceSteps");n.innerHTML="",e.steps.forEach((o,c)=>{s=c+1;const l=`
                <div class="sequence-step" data-step-id="${s}">
                    <div class="step-header">
                        <div class="step-title">Step ${s}</div>
                        <button type="button" class="step-remove" onclick="removeSequenceStep(${s})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="18" y1="6" x2="6" y2="18"/>
                                <line x1="6" y1="6" x2="18" y2="18"/>
                            </svg>
                        </button>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Template *</label>
                        <select class="form-input step-template" required>
                            <option value="">Select a template</option>
                            ${i.map(u=>`<option value="${u.id}" ${u.id===o.templateId?"selected":""}>${u.name}</option>`).join("")}
                        </select>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Delay *</label>
                            <input type="number" class="form-input step-delay" min="0" value="${o.delay}" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Delay Unit *</label>
                            <select class="form-input step-delay-unit" required>
                                <option value="hours" ${o.delayUnit==="hours"?"selected":""}>Hours</option>
                                <option value="days" ${o.delayUnit==="days"?"selected":""}>Days</option>
                                <option value="weeks" ${o.delayUnit==="weeks"?"selected":""}>Weeks</option>
                            </select>
                        </div>
                    </div>
                </div>
            `;n.insertAdjacentHTML("beforeend",l)}),document.getElementById("sequenceModal").classList.add("active"),document.body.style.overflow="hidden"}function H(){const t=document.getElementById("trackingTableBody");t.innerHTML=k.map(n=>`
            <tr>
                <td>${n.recipient}</td>
                <td>${n.subject}</td>
                <td>${n.type}</td>
                <td>${n.sent}</td>
                <td>${n.opened||"-"}</td>
                <td>${n.replied||"-"}</td>
                <td>${n.clicked?"Yes":"No"}</td>
                <td><span class="status-badge ${n.status}">${n.status.charAt(0).toUpperCase()+n.status.slice(1)}</span></td>
            </tr>
        `).join("");const e=document.getElementById("trackingCards");e.innerHTML=k.map(n=>`
            <div class="tracking-card">
                <div class="tracking-card-header">
                    <div class="tracking-card-title">${n.subject}</div>
                    <span class="status-badge ${n.status}">${n.status.charAt(0).toUpperCase()+n.status.slice(1)}</span>
                </div>
                <div class="tracking-card-details">
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Recipient</span>
                        <span class="tracking-card-value">${n.recipient}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Type</span>
                        <span class="tracking-card-value">${n.type}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Sent</span>
                        <span class="tracking-card-value">${n.sent}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Opened</span>
                        <span class="tracking-card-value">${n.opened||"-"}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Replied</span>
                        <span class="tracking-card-value">${n.replied||"-"}</span>
                    </div>
                    <div class="tracking-card-detail">
                        <span class="tracking-card-label">Clicked</span>
                        <span class="tracking-card-value">${n.clicked?"Yes":"No"}</span>
                    </div>
                </div>
            </div>
        `).join("")}function j(){const t=i.slice(0,5),e=document.getElementById("topTemplatesList");e.innerHTML=t.map((o,c)=>`
            <div class="top-template-item">
                <div class="top-template-name">${o.name}</div>
                <div class="top-template-stats">
                    <span>Opens: 68%</span>
                    <span>Replies: 24%</span>
                </div>
            </div>
        `).join("");const n=document.getElementById("sequencePerformanceList");n.innerHTML=p.map(o=>`
            <div class="sequence-performance-item">
                <div class="top-template-name">${o.name}</div>
                <div class="top-template-stats">
                    <span>Sent: ${o.sent}</span>
                    <span>Opened: ${o.opened}</span>
                    <span>Replied: ${o.replied}</span>
                </div>
            </div>
        `).join("")}document.getElementById("templateModal").addEventListener("click",function(t){t.target===this&&v()}),document.getElementById("sequenceModal").addEventListener("click",function(t){t.target===this&&f()});let a=[];function d(){const t=document.getElementById("connectedAccountsGrid");if(a.length===0){t.innerHTML='<div style="color: var(--text-muted); font-size: 0.875rem; grid-column: 1 / -1;">No email accounts connected yet. Connect an account below to start tracking emails.</div>';return}t.innerHTML=a.map(e=>`
            <div class="account-card">
                <div class="account-icon ${e.type}">
                    ${e.type==="google"?`
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                    `:`
                        <svg viewBox="0 0 24 24" fill="currentColor">
                            <path d="M7.5 7.5h9v9h-9z" fill="#0078D4"/>
                            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 18c-4.41 0-8-3.59-8-8s3.59-8 8-8 8 3.59 8 8-3.59 8-8 8z" fill="#0078D4"/>
                        </svg>
                    `}
                </div>
                <div class="account-info">
                    <div class="account-email">${e.email}</div>
                    <div class="account-status">
                        <span class="status-dot"></span>
                        <span>Connected • ${e.type==="google"?"Gmail":"Outlook"}</span>
                    </div>
                </div>
                <div class="account-actions">
                    <button class="account-action-btn" onclick="testConnection('${e.id}')" title="Test Connection">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/>
                        </svg>
                    </button>
                    <button class="account-action-btn danger" onclick="disconnectAccount('${e.id}')" title="Disconnect">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <line x1="18" y1="6" x2="6" y2="18"/>
                            <line x1="6" y1="6" x2="18" y2="18"/>
                        </svg>
                    </button>
                </div>
            </div>
        `).join("")}function y(){if(confirm("This will redirect you to Google to authorize email access. Continue?")){const t={id:"google_"+Date.now(),type:"google",email:"user@gmail.com",connectedAt:new Date().toISOString()};a.push(t),d(),r(),alert("Google account connected successfully!")}}function g(){if(confirm("This will redirect you to Microsoft to authorize email access. Continue?")){const t={id:"outlook_"+Date.now(),type:"outlook",email:"user@outlook.com",connectedAt:new Date().toISOString()};a.push(t),d(),r(),alert("Outlook account connected successfully!")}}function C(t){confirm("Are you sure you want to disconnect this email account? Email tracking will stop for this account.")&&(a=a.filter(e=>e.id!==t),d(),r(),alert("Account disconnected successfully."))}function M(t){const e=a.find(n=>n.id===t);e&&alert(`Testing connection to ${e.email}...

Connection successful!`)}function r(){const t=a.some(c=>c.type==="google"),e=a.some(c=>c.type==="outlook"),n=document.getElementById("connectGoogleBtn"),o=document.getElementById("connectOutlookBtn");t?(n.classList.add("connected"),n.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Connected',n.onclick=()=>alert("Google account is already connected")):(n.classList.remove("connected"),n.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>Connect Google',n.onclick=y),e?(o.classList.add("connected"),o.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>Connected',o.onclick=()=>alert("Outlook account is already connected")):(o.classList.remove("connected"),o.innerHTML='<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 16v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/></svg>Connect Outlook',o.onclick=g)}m(),d(),r(),typeof S=="function"&&(window.addSequenceStep=S),typeof f=="function"&&(window.closeSequenceModal=f),typeof v=="function"&&(window.closeTemplateModal=v),typeof y=="function"&&(window.connectGoogleAccount=y),typeof g=="function"&&(window.connectOutlookAccount=g),typeof $=="function"&&(window.deleteTemplate=$),typeof C=="function"&&(window.disconnectAccount=C),typeof I=="function"&&(window.editSequence=I),typeof h=="function"&&(window.editTemplate=h),typeof getElementById=="function"&&(window.getElementById=getElementById),typeof E=="function"&&(window.openSequenceModal=E),typeof w=="function"&&(window.openTemplateModal=w),typeof B=="function"&&(window.previewTemplate=B),typeof q=="function"&&(window.removeSequenceStep=q),typeof stopPropagation=="function"&&(window.stopPropagation=stopPropagation),typeof b=="function"&&(window.switchTab=b),typeof M=="function"&&(window.testConnection=M),typeof T=="function"&&(window.useTemplate=T)})();
