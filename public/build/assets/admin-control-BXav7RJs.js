(function(){const me=[{id:1,name:"Basic",price:29,period:"month",features:["5 Users","10GB Storage","Email Support"],active:!0,featured:!1},{id:2,name:"Professional",price:79,period:"month",features:["20 Users","100GB Storage","Priority Support","API Access"],active:!0,featured:!0},{id:3,name:"Enterprise",price:199,period:"month",features:["Unlimited Users","1TB Storage","24/7 Support","API Access","Custom Integrations"],active:!0,featured:!1}],a=[{id:1,company:"Acme Corporation",plan:"Professional",status:"active",cycle:"Monthly",amount:79,nextBilling:"2026-02-01"},{id:2,company:"TechStart Inc",plan:"Enterprise",status:"active",cycle:"Yearly",amount:2388,nextBilling:"2027-01-01"},{id:3,company:"BrandCo",plan:"Basic",status:"trial",cycle:"Trial",amount:0,nextBilling:"2026-01-20"},{id:4,company:"ShopNow",plan:"Professional",status:"active",cycle:"Monthly",amount:79,nextBilling:"2026-02-01"},{id:5,company:"CloudTech",plan:"Basic",status:"expired",cycle:"Monthly",amount:29,nextBilling:"-"}],d=[{id:"dashboard",name:"Dashboard",description:"Main dashboard and overview",route:"dashboard"},{id:"time-tracking",name:"Time Tracking",description:"Track employee time and attendance",route:"time-tracking"},{id:"user-management",name:"User Management",description:"Manage users and permissions",route:"user-management"},{id:"employee-monitoring",name:"Employee Monitoring",description:"Monitor employee activity",route:"employee-monitoring"},{id:"phone-system",name:"Phone System",description:"VoIP phone system integration",route:"phone-system"},{id:"payroll",name:"Payroll",description:"Automated payroll processing",route:"payroll"},{id:"project-management",name:"Project Management",description:"Project tracking and management",route:"project-management"},{id:"messaging",name:"Messaging",description:"Internal messaging system",route:"messaging"},{id:"billing",name:"Billing & Payments",description:"Invoice and payment management",route:"billing"},{id:"client-management",name:"Client Management",description:"CRM and client database",route:"client-management"},{id:"tickets",name:"Tickets & Helpdesk",description:"Support ticket system",route:"tickets"},{id:"knowledge-base",name:"Knowledge Base",description:"Documentation and knowledge base",route:"knowledge-base"},{id:"integrations",name:"Integrations",description:"Third-party integrations",route:"integrations"},{id:"quotation-builder",name:"Quotation Builder",description:"Create and manage quotations",route:"quotation-builder"},{id:"calendar",name:"Calendar",description:"Calendar and scheduling",route:"calendar"},{id:"email-tracking",name:"Email Tracking",description:"Track email opens and clicks",route:"email-tracking"},{id:"openai",name:"AI Assistant",description:"OpenAI integration",route:"openai"}],r={1:["dashboard","time-tracking","user-management","employee-monitoring","project-management","billing","client-management"],2:d.map(t=>t.id),3:["dashboard","time-tracking","user-management","employee-monitoring"],4:["dashboard","time-tracking","user-management","project-management","billing","client-management","messaging"],5:["dashboard","billing"]};let u=null;const pe=[{id:1,company:"Acme Corporation",amount:79,date:"2026-01-01",status:"completed",method:"Credit Card"},{id:2,company:"TechStart Inc",amount:2388,date:"2026-01-01",status:"completed",method:"Bank Transfer"},{id:3,company:"ShopNow",amount:79,date:"2025-12-28",status:"completed",method:"Credit Card"},{id:4,company:"BrandCo",amount:0,date:"2025-12-25",status:"trial",method:"Trial"}],ge=[{id:1,name:"Acme Corporation"},{id:2,name:"TechStart Inc"},{id:3,name:"BrandCo"},{id:4,name:"ShopNow"},{id:5,name:"CloudTech"}],ye=[{id:1,name:"Time Tracking",description:"Track employee time and attendance",enabled:!0},{id:2,name:"User Management",description:"Manage users and permissions",enabled:!0},{id:3,name:"Phone System",description:"VoIP phone system integration",enabled:!1},{id:4,name:"Payroll",description:"Automated payroll processing",enabled:!0},{id:5,name:"Project Management",description:"Project tracking and management",enabled:!0},{id:6,name:"Messaging",description:"Internal messaging system",enabled:!0},{id:7,name:"Billing",description:"Invoice and payment management",enabled:!0},{id:8,name:"Client Management",description:"CRM and client database",enabled:!0},{id:9,name:"Tickets",description:"Support ticket system",enabled:!1},{id:10,name:"Knowledge Base",description:"Documentation and knowledge base",enabled:!0},{id:11,name:"Integrations",description:"Third-party integrations",enabled:!1},{id:12,name:"AI Assistant",description:"OpenAI integration",enabled:!1}],fe=[{id:1,name:"Super Admin",users:2,permissions:"All"},{id:2,name:"Admin",users:5,permissions:"Most"},{id:3,name:"Manager",users:12,permissions:"Limited"},{id:4,name:"Employee",users:45,permissions:"Basic"}],ve=[{id:1,text:'Company "Acme Corporation" accessed Time Tracking feature',time:"2 hours ago",type:"feature"},{id:2,text:'User "john.doe@acme.com" logged in',time:"3 hours ago",type:"login"},{id:3,text:'Permissions updated for "TechStart Inc"',time:"5 hours ago",type:"permission"},{id:4,text:'Company "BrandCo" accessed Billing feature',time:"1 day ago",type:"feature"},{id:5,text:'User "admin@techstart.com" logged in',time:"1 day ago",type:"login"}],he=[{id:1,name:"Maintenance Mode",description:"Enable maintenance mode for system updates",enabled:!1},{id:2,name:"Email Notifications",description:"Send email notifications for system events",enabled:!0},{id:3,name:"Two-Factor Authentication",description:"Require 2FA for all admin accounts",enabled:!0},{id:4,name:"API Rate Limiting",description:"Enable rate limiting for API requests",enabled:!0},{id:5,name:"Auto Backup",description:"Automatically backup database daily",enabled:!0}],be=[{id:1,name:"John Doe",email:"john@admin.com",role:"Super Admin",company:"System",status:"active"},{id:2,name:"Jane Smith",email:"jane@admin.com",role:"Admin",company:"System",status:"active"},{id:3,name:"Bob Johnson",email:"bob@acme.com",role:"Manager",company:"Acme Corporation",status:"active"},{id:4,name:"Alice Brown",email:"alice@techstart.com",role:"Admin",company:"TechStart Inc",status:"active"}];function we(){const t=document.getElementById("plansGrid");t.innerHTML=me.map(e=>`
            <div class="plan-card ${e.featured?"featured":""}">
                <div class="plan-header">
                    <h4 class="plan-name">${e.name}</h4>
                    ${e.featured?'<span class="plan-badge">Popular</span>':""}
                </div>
                <div class="plan-price">
                    $${e.price}<span>/${e.period}</span>
                </div>
                <ul class="plan-features">
                    ${e.features.map(n=>`<li><svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="20 6 9 17 4 12"/></svg>${n}</li>`).join("")}
                </ul>
                <div class="plan-actions">
                    <button class="btn-sm btn-secondary" onclick="editPlan(${e.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deletePlan(${e.id})">Delete</button>
                </div>
            </div>
        `).join("")}function $(){const t=document.getElementById("billingTableBody");t.innerHTML=a.map(e=>`
            <tr>
                <td><strong>${e.company}</strong></td>
                <td>${e.plan}</td>
                <td><span class="status-badge ${e.status}">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span></td>
                <td>${e.cycle}</td>
                <td>$${e.amount.toLocaleString()}</td>
                <td>${e.nextBilling}</td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="manageBilling(${e.id})">Manage</button>
                </td>
            </tr>
        `).join("")}function Me(){const t=document.getElementById("paymentsList");t.innerHTML=pe.map(e=>`
            <div class="payment-item">
                <div class="payment-info">
                    <div class="payment-company">${e.company}</div>
                    <p class="payment-details">${e.date} • ${e.method}</p>
                </div>
                <div class="payment-amount">$${e.amount.toLocaleString()}</div>
                <span class="status-badge ${e.status}">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span>
            </div>
        `).join("")}function Se(){const t=document.getElementById("companySelector");t.innerHTML='<option value="">Select a company...</option>'+ge.map(e=>`<option value="${e.id}">${e.name}</option>`).join("")}function $e(){const t=document.getElementById("featuresGrid");t.innerHTML=ye.map(e=>`
            <div class="feature-card">
                <div class="feature-info">
                    <div class="feature-name">${e.name}</div>
                    <p class="feature-desc">${e.description}</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" ${e.enabled?"checked":""} onchange="toggleFeature(${e.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join("")}function B(){const t=document.getElementById("rolesList");t.innerHTML=fe.map(e=>`
            <div class="role-card">
                <div class="role-info">
                    <div class="role-name">${e.name}</div>
                    <p class="role-users">${e.users} users • ${e.permissions} permissions</p>
                </div>
                <div>
                    <button class="btn-sm btn-secondary" onclick="editRole(${e.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deleteRole(${e.id})">Delete</button>
                </div>
            </div>
        `).join("")}function A(){const t=document.getElementById("accessLogs");t.innerHTML=ve.map(e=>`
            <div class="log-item">
                <div class="log-text">${e.text}</div>
                <p class="log-meta">${e.time}</p>
            </div>
        `).join("")}function Be(){const t=document.getElementById("systemSettings");t.innerHTML=he.map(e=>`
            <div class="setting-item">
                <div class="setting-info">
                    <div class="setting-name">${e.name}</div>
                    <p class="setting-desc">${e.description}</p>
                </div>
                <label class="toggle-switch">
                    <input type="checkbox" ${e.enabled?"checked":""} onchange="toggleSetting(${e.id}, this.checked)">
                    <span class="toggle-slider"></span>
                </label>
            </div>
        `).join("")}function Ae(){const t=document.getElementById("usersTableBody");t.innerHTML=be.map(e=>`
            <tr>
                <td><strong>${e.name}</strong></td>
                <td>${e.email}</td>
                <td>${e.role}</td>
                <td>${e.company}</td>
                <td><span class="status-badge active">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span></td>
                <td>
                    <button class="btn-sm btn-secondary" onclick="editUser(${e.id})">Edit</button>
                    <button class="btn-sm btn-secondary" onclick="deleteUser(${e.id})">Delete</button>
                </td>
            </tr>
        `).join("")}function C(){const t=document.getElementById("healthMetrics");t.innerHTML=`
            <div class="health-metric good">
                <div class="health-metric-value">99.9%</div>
                <p class="health-metric-label">Uptime</p>
            </div>
            <div class="health-metric good">
                <div class="health-metric-value">245ms</div>
                <p class="health-metric-label">Avg Response</p>
            </div>
            <div class="health-metric warning">
                <div class="health-metric-value">78%</div>
                <p class="health-metric-label">Storage Used</p>
            </div>
            <div class="health-metric good">
                <div class="health-metric-value">1,247</div>
                <p class="health-metric-label">Active Users</p>
            </div>
        `}function E(){document.getElementById("companySelector").value?(document.getElementById("featureAccessSection").style.display="block",document.getElementById("rolePermissionsSection").style.display="block",$e(),B()):(document.getElementById("featureAccessSection").style.display="none",document.getElementById("rolePermissionsSection").style.display="none")}function I(t,e){console.log(`Feature ${t} ${e?"enabled":"disabled"}`)}function k(t,e){console.log(`Setting ${t} ${e?"enabled":"disabled"}`)}function T(){alert("Access settings saved successfully!")}function L(){alert("System settings saved successfully!")}function y(){document.getElementById("planModal").classList.add("active")}function f(){document.getElementById("planModal").classList.remove("active")}function P(){alert("Plan saved successfully!"),f()}function j(t){console.log("Edit plan:",t),y()}function x(t){confirm("Are you sure you want to delete this plan?")&&console.log("Delete plan:",t)}function R(t){u=t;const e=a.find(o=>o.id===t);if(!e)return;document.getElementById("companyModuleName").textContent=e.company;const n=r[t]||[];Ce(n),g(),document.getElementById("companyModuleModal").classList.add("active")}function Ce(t=[]){const e=document.getElementById("modulesGrid");e.innerHTML=d.map(n=>`
                <div class="module-card ${t.includes(n.id)?"selected":""}" data-module-id="${n.id}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${n.name}</div>
                        <p class="module-desc">${n.description}</p>
                    </div>
                </div>
            `).join("")}function U(t){t.classList.toggle("selected"),g()}function H(){document.querySelectorAll(".module-card").forEach(e=>e.classList.add("selected")),g()}function F(){document.querySelectorAll(".module-card").forEach(e=>e.classList.remove("selected")),g()}function g(){const t=document.querySelectorAll(".module-card.selected").length,e=d.length;document.getElementById("moduleCount").textContent=`${t} of ${e} modules selected`}function D(){if(!u)return;const t=document.querySelectorAll(".module-card.selected"),e=Array.from(t).map(i=>i.getAttribute("data-module-id")).filter(Boolean);r[u]=e;const n=a.find(i=>i.id===u),o=e.map(i=>{const s=d.find(l=>l.id===i);return s?s.name:i}).join(", ");alert(`Module access updated successfully for ${n?.company}!

Selected modules: ${e.length}

Modules: ${o}`),v()}function v(){document.getElementById("companyModuleModal").classList.remove("active"),u=null}function q(){alert("Role creation modal would open here")}function G(t){console.log("Edit role:",t)}function N(t){confirm("Are you sure you want to delete this role?")&&console.log("Delete role:",t)}function J(){alert("User creation modal would open here")}function K(t){console.log("Edit user:",t)}function V(t){confirm("Are you sure you want to delete this user?")&&console.log("Delete user:",t)}function O(){C(),alert("System health refreshed!")}const z=[{id:1,company:"Acme Corporation",subject:"Cannot access Time Tracking module",status:"open",priority:"high",created:"2 hours ago",type:"module-access"},{id:2,company:"BrandCo",subject:"Billing module showing error",status:"in-progress",priority:"medium",created:"5 hours ago",type:"technical"},{id:3,company:"ShopNow",subject:"Need temporary access to Project Management",status:"open",priority:"low",created:"1 day ago",type:"access-request"},{id:4,company:"CloudTech",subject:"User Management permissions issue",status:"resolved",priority:"high",created:"2 days ago",type:"permissions"}],m=[{id:1,type:"emergency",company:"Acme Corporation",action:"Granted emergency full access",admin:"John Doe",time:"2 hours ago",duration:"24 hours"},{id:2,type:"grant",company:"BrandCo",action:"Granted access to Phone System module",admin:"Jane Smith",time:"5 hours ago",notes:"Support request for testing"},{id:3,type:"bypass",company:"ShopNow",action:"Bypassed module restrictions for troubleshooting",admin:"John Doe",time:"1 day ago",notes:"Troubleshooting billing issue"},{id:4,type:"grant",company:"CloudTech",action:"Granted access to AI Assistant module",admin:"Jane Smith",time:"2 days ago",notes:"Trial period extension"}],c=[{id:1,company:"Acme Corporation",type:"Emergency Access",started:"2 hours ago",expires:"22 hours remaining",admin:"John Doe"},{id:2,company:"BrandCo",type:"Module Grant",started:"5 hours ago",expires:"19 hours remaining",admin:"Jane Smith"}];let p=null;function Q(){document.getElementById("companyModuleReviewModal").classList.add("active")}function h(){document.getElementById("companyModuleReviewModal").classList.remove("active"),p=null}function Y(){const t=document.getElementById("emergencyCompanySelect");t.innerHTML='<option value="">Choose a company...</option>'+a.map(e=>`<option value="${e.id}">${e.company}</option>`).join(""),document.getElementById("emergencyAccessModal").classList.add("active")}function b(){document.getElementById("emergencyAccessModal").classList.remove("active"),document.getElementById("emergencyAccessForm").reset()}function W(){const t=document.getElementById("emergencyCompanySelect").value,e=document.getElementById("emergencyDuration").value,n=document.getElementById("emergencyReason").value,o=document.getElementById("emergencyNotify").checked;if(!t||!n){alert("Please fill in all required fields");return}const i=a.find(l=>l.id===parseInt(t)),s=e==="1"?"1 Hour":e==="4"?"4 Hours":e==="24"?"24 Hours":"7 Days";m.unshift({id:m.length+1,type:"emergency",company:i.company,action:`Granted emergency full access for ${s}`,admin:"Current Admin",time:"Just now",duration:s,reason:n}),c.push({id:c.length+1,company:i.company,type:"Emergency Access",started:"Just now",expires:`${s} remaining`,admin:"Current Admin"}),alert(`Emergency access granted to ${i.company} for ${s}.
Reason: ${n}
${o?"Company admin has been notified.":""}`),b(),S()}function X(){de(),document.getElementById("supportTicketsModal").classList.add("active")}function Z(){document.getElementById("supportTicketsModal").classList.remove("active")}function _(){M(),document.getElementById("supportActionsLog").scrollIntoView({behavior:"smooth"})}function ee(){document.querySelectorAll("#reviewModulesGrid .module-card").forEach(e=>e.classList.add("selected")),w()}function te(){document.querySelectorAll("#reviewModulesGrid .module-card").forEach(e=>e.classList.remove("selected")),w()}function w(){const t=document.querySelectorAll("#reviewModulesGrid .module-card.selected").length,e=d.length;document.getElementById("reviewModuleCount").textContent=`${t} of ${e} modules selected`}function ne(){if(!p){alert("Please select a company first");return}const t=document.querySelectorAll("#reviewModulesGrid .module-card.selected"),e=Array.from(t).map(i=>i.getAttribute("data-module-id")).filter(Boolean),n=document.getElementById("supportNotes").value;r[p]=e;const o=a.find(i=>i.id===p);m.unshift({id:m.length+1,type:"grant",company:o.company,action:`Modified module access (${e.length} modules)`,admin:"Current Admin",time:"Just now",notes:n||"No notes provided"}),alert(`Module access updated for ${o.company}!

Selected modules: ${e.length}

This action has been logged.`),h(),S()}function oe(){const t=document.getElementById("supportCompaniesTableBody");t.innerHTML=a.map(e=>{const n=r[e.id]||[],o=n.map(s=>{const l=d.find(Ie=>Ie.id===s);return l?l.name:s}).slice(0,3).join(", "),i=n.length>3?` +${n.length-3} more`:"";return`
                <tr>
                    <td><strong>${e.company}</strong></td>
                    <td>${e.plan}</td>
                    <td><span class="status-badge ${e.status}">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span></td>
                    <td>${o}${i}</td>
                    <td>2 days ago</td>
                    <td>
                        <button class="btn-sm btn-secondary" onclick="reviewCompanyModules(${e.id})">Review</button>
                        <button class="btn-sm btn-secondary" onclick="manageBilling(${e.id})">Manage</button>
                    </td>
                </tr>
            `}).join("")}function ie(t){p=t;const e=a.find(o=>o.id===t);document.getElementById("reviewCompanyName").textContent=e.company;const n=r[t]||[];Ee(n),w(),document.getElementById("companyModuleReviewModal").classList.add("active")}function Ee(t=[]){const e=document.getElementById("reviewModulesGrid");e.innerHTML=d.map(n=>`
                <div class="module-card ${t.includes(n.id)?"selected":""}" data-module-id="${n.id}" onclick="toggleModule(this)">
                    <div class="module-checkbox"></div>
                    <div class="module-info">
                        <div class="module-name">${n.name}</div>
                        <p class="module-desc">${n.description}</p>
                    </div>
                </div>
            `).join("")}function se(){const t=document.getElementById("supportSessionsList");if(document.getElementById("activeSessionsCount").textContent=`${c.length} active`,c.length===0){t.innerHTML='<p style="text-align: center; color: var(--text-secondary); padding: 2rem;">No active support sessions</p>';return}t.innerHTML=c.map(e=>`
            <div class="support-session-card">
                <div class="support-session-info">
                    <div class="support-session-company">${e.company}</div>
                    <p class="support-session-details">${e.type} • Started ${e.started} • Expires in ${e.expires}</p>
                </div>
                <div class="support-session-time">By ${e.admin}</div>
                <div class="support-session-actions">
                    <button class="btn-sm btn-secondary" onclick="endSupportSession(${e.id})">End Session</button>
                </div>
            </div>
        `).join("")}function ae(t){if(confirm("Are you sure you want to end this support session?")){const e=c.findIndex(n=>n.id===t);e>-1&&(c.splice(e,1),se())}}function M(){const t=document.getElementById("supportActionsLog");t.innerHTML=m.map(e=>{const n=e.type==="emergency"?"emergency":e.type==="grant"?"grant":"bypass",o=e.type==="emergency"?'<path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/><path d="M12 8v4"/><path d="M12 16h.01"/>':e.type==="grant"?'<polyline points="20 6 9 17 4 12"/>':'<path d="M18 6L6 18M6 6l12 12"/>';return`
                <div class="support-action-log-item">
                    <div class="support-action-log-icon ${n}">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            ${o}
                        </svg>
                    </div>
                    <div class="support-action-log-content">
                        <div class="support-action-log-text">
                            <strong>${e.company}</strong>: ${e.action}
                        </div>
                        <p class="support-action-log-meta">
                            ${e.admin} • ${e.time}${e.notes?` • ${e.notes}`:""}
                        </p>
                    </div>
                </div>
            `}).join("")}function de(){const t=document.getElementById("supportTicketsList");t.innerHTML=z.map(e=>`
            <div class="support-ticket-card" onclick="viewSupportTicket(${e.id})">
                <div class="support-ticket-header">
                    <span class="support-ticket-id">#TKT-${e.id.toString().padStart(4,"0")}</span>
                    <span class="support-ticket-status ${e.status}">${e.status.replace("-"," ").split(" ").map(n=>n.charAt(0).toUpperCase()+n.slice(1)).join(" ")}</span>
                </div>
                <div class="support-ticket-subject">${e.subject}</div>
                <p class="support-ticket-meta">${e.company} • ${e.created} • Priority: ${e.priority}</p>
            </div>
        `).join("")}function ce(t){const e=z.find(n=>n.id===t);e&&alert(`Support Ticket #TKT-${e.id.toString().padStart(4,"0")}

Company: ${e.company}
Subject: ${e.subject}
Status: ${e.status}
Priority: ${e.priority}
Created: ${e.created}`)}function le(){document.getElementById("supportCompanySearch").value.toLowerCase(),document.getElementById("supportModuleFilter").value,oe()}function re(){document.getElementById("supportActionFilter").value,M()}function ue(){document.getElementById("ticketStatusFilter").value,document.getElementById("ticketSearch").value.toLowerCase(),de()}function S(){oe(),se(),M();const t=document.getElementById("supportModuleFilter");t.innerHTML='<option value="all">All Modules</option>'+d.map(e=>`<option value="${e.id}">${e.name}</option>`).join("")}document.getElementById("billingFilter")?.addEventListener("change",function(){console.log("Filter billing by:",this.value),$()}),document.getElementById("logFilter")?.addEventListener("change",function(){console.log("Filter logs by:",this.value),A()}),document.addEventListener("DOMContentLoaded",function(){we(),$(),Me(),Se(),B(),A(),Be(),Ae(),C(),S()}),typeof v=="function"&&(window.closeCompanyModuleModal=v),typeof h=="function"&&(window.closeCompanyModuleReviewModal=h),typeof b=="function"&&(window.closeEmergencyAccessModal=b),typeof f=="function"&&(window.closePlanModal=f),typeof Z=="function"&&(window.closeSupportTicketsModal=Z),typeof x=="function"&&(window.deletePlan=x),typeof N=="function"&&(window.deleteRole=N),typeof V=="function"&&(window.deleteUser=V),typeof F=="function"&&(window.deselectAllModules=F),typeof j=="function"&&(window.editPlan=j),typeof G=="function"&&(window.editRole=G),typeof K=="function"&&(window.editUser=K),typeof ae=="function"&&(window.endSupportSession=ae),typeof re=="function"&&(window.filterSupportActions=re),typeof le=="function"&&(window.filterSupportCompanies=le),typeof ue=="function"&&(window.filterTickets=ue),typeof ee=="function"&&(window.grantAllModulesForSupport=ee),typeof W=="function"&&(window.grantEmergencyAccess=W),typeof E=="function"&&(window.loadCompanyAccess=E),typeof R=="function"&&(window.manageBilling=R),typeof _=="function"&&(window.openBypassLog=_),typeof Q=="function"&&(window.openCompanyModuleReview=Q),typeof Y=="function"&&(window.openEmergencyAccess=Y),typeof y=="function"&&(window.openPlanModal=y),typeof q=="function"&&(window.openRoleModal=q),typeof X=="function"&&(window.openSupportTickets=X),typeof J=="function"&&(window.openUserModal=J),typeof O=="function"&&(window.refreshSystemHealth=O),typeof ie=="function"&&(window.reviewCompanyModules=ie),typeof te=="function"&&(window.revokeAllModulesForSupport=te),typeof T=="function"&&(window.saveAccessSettings=T),typeof D=="function"&&(window.saveCompanyModules=D),typeof P=="function"&&(window.savePlan=P),typeof ne=="function"&&(window.saveSupportModuleChanges=ne),typeof L=="function"&&(window.saveSystemSettings=L),typeof H=="function"&&(window.selectAllModules=H),typeof I=="function"&&(window.toggleFeature=I),typeof U=="function"&&(window.toggleModule=U),typeof k=="function"&&(window.toggleSetting=k),typeof ce=="function"&&(window.viewSupportTicket=ce)})();
