(function(){let f=[],c=null,b=[],m={page:1,perPage:10,total:0,lastPage:1,search:""},p={page:1,perPage:10,total:0,lastPage:1};document.addEventListener("DOMContentLoaded",function(){w(),Q(),W(),Y();const e=document.getElementById("videoModal");e&&e.addEventListener("click",function(n){n.target===this&&h()}),document.addEventListener("keydown",function(n){if(n.key==="Escape"){const o=document.getElementById("videoModal"),i=document.getElementById("taskTimeTrackingModal");o&&o.classList.contains("active")?h():i&&i.classList.contains("active")&&y()}});const t=document.getElementById("taskTimeTrackingModal");t&&t.addEventListener("click",function(n){n.target===this&&y()})});function W(){document.querySelectorAll(".tab-btn[data-tab]").forEach(e=>{e.addEventListener("click",function(){const t=this.dataset.tab;document.querySelectorAll(".tab-btn[data-tab]").forEach(i=>i.classList.remove("active")),this.classList.add("active"),document.querySelectorAll(".tab-content").forEach(i=>i.classList.remove("active"));const n=t.replace(/-([a-z])/g,i=>i[1].toUpperCase())+"Tab",o=document.getElementById(n);o&&o.classList.add("active"),t==="my-team"&&J()})}),document.querySelectorAll(".tab-btn[data-detail-tab]").forEach(e=>{e.addEventListener("click",function(){const t=this.dataset.detailTab;document.querySelectorAll(".tab-btn[data-detail-tab]").forEach(n=>n.classList.remove("active")),this.classList.add("active"),I(t)})})}function Y(){const e=document.getElementById("teamColor"),t=document.getElementById("colorPreview");e.addEventListener("input",function(){t.style.background=this.value})}async function w(){try{const t=await(await fetch("/api/team-management/teams",{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();t.success&&(f=t.data,G())}catch(e){console.error("Error loading teams:",e)}}async function J(){const e=document.getElementById("myTeamsContainer");e.innerHTML='<div class="loading-spinner"></div>';try{const n=await(await fetch("/api/team-management/teams",{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();if(n.success){const i=(window.__teamManagementConfig||{}).userId,a=n.data.filter(s=>s.leader&&s.leader.id===i?!0:s.members.some(r=>r.id===i));a.length===0?e.innerHTML=`
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>No Teams Found</h3>
                            <p>You are not a member of any team yet.</p>
                        </div>
                    `:e.innerHTML=`<div class="teams-grid">${a.map(C).join("")}</div>`}}catch(t){console.error("Error loading my teams:",t),e.innerHTML='<p class="text-center text-muted">Error loading teams</p>'}}async function Q(){try{const t=await(await fetch("/api/team-management/users",{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();t.success&&(b=t.data,Z())}catch(e){console.error("Error loading users:",e)}}function Z(){const e=document.getElementById("teamLeader");e.innerHTML='<option value="">Select Leader</option>',b.forEach(t=>{e.innerHTML+=`<option value="${t.id}">${t.name}</option>`})}function x(e=null,t=[]){const n=document.getElementById("memberSelection");n.innerHTML="",b.forEach(o=>{if(o.id==e)return;const i=t.includes(o.id);n.innerHTML+=`
                <label class="user-option ${i?"selected":""}">
                    <input type="checkbox" name="member_ids[]" value="${o.id}" ${i?"checked":""} onchange="this.parentElement.classList.toggle('selected')">
                    <div class="leader-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                        ${o.photo?`<img src="${o.photo}" alt="${o.name}">`:o.initials}
                    </div>
                    <div class="member-info">
                        <div class="member-name">${o.name}</div>
                        <div class="member-email">${o.email}</div>
                    </div>
                </label>
            `})}function G(){const e=document.getElementById("teamsGrid");if(f.length===0){e.innerHTML=`
                <div class="empty-state" style="grid-column: 1 / -1;">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                        <circle cx="9" cy="7" r="4"/>
                        <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                        <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                    </svg>
                    <h3>No Teams Yet</h3>
                    <p>Create your first team to start organizing your workforce.</p>
                </div>
            `;return}e.innerHTML=f.map(C).join("")}function C(e){const t=e.members.slice(0,4),n=e.members.length-4;return`
            <div class="team-card" style="--team-color: ${e.color};" onclick="viewTeam(${e.id})">
                <div class="team-header">
                    <div class="team-info">
                        <h3 class="team-name">${e.name}</h3>
                    </div>
                    <div class="team-actions" onclick="event.stopPropagation()">
                        ${CFG.canEdit?`
                        <button class="icon-btn" title="Edit" onclick="editTeam(${e.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                        `:""}
                        ${CFG.canDelete?`
                        <button class="icon-btn" title="Delete" onclick="deleteTeam(${e.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                        </button>
                        `:""}
                    </div>
                </div>

                <p class="team-description">${e.description||"No description"}</p>

                ${e.leader?`
                <div class="team-leader">
                    <div class="leader-avatar">
                        ${e.leader.photo?`<img src="${e.leader.photo}" alt="${e.leader.name}">`:e.leader.initials}
                    </div>
                    <div class="leader-info">
                        <div class="leader-label">Team Leader</div>
                        <div class="leader-name">${e.leader.name}</div>
                    </div>
                </div>
                `:""}

                <div class="team-members-preview">
                    <div class="members-avatars">
                        ${t.map(o=>`
                            <div class="member-avatar" title="${o.name}">
                                ${o.photo?`<img src="${o.photo}" alt="${o.name}">`:o.initials}
                            </div>
                        `).join("")}
                        ${n>0?`<div class="member-avatar more">+${n}</div>`:""}
                    </div>
                    <span class="members-count">${e.members_count} member${e.members_count!==1?"s":""}</span>
                </div>

                <div class="team-stats">
                    <div class="stat-item">
                        <div class="stat-value">${e.members_count+(e.leader?1:0)}</div>
                        <div class="stat-label">Total</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${e.projects_count}</div>
                        <div class="stat-label">Projects</div>
                    </div>
                    <div class="stat-item">
                        <div class="stat-value">${e.is_active?"●":"○"}</div>
                        <div class="stat-label">${e.is_active?"Active":"Inactive"}</div>
                    </div>
                </div>
            </div>
        `}async function L(e){try{const n=await(await fetch(`/api/team-management/teams/${e}`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();n.success&&(c=n.data,ee())}catch(t){console.error("Error loading team:",t)}}function ee(){document.getElementById("teamsListView").style.display="none",document.getElementById("teamDetailsView").style.display="block",document.querySelectorAll(".tab-btn[data-detail-tab]").forEach(e=>e.classList.remove("active")),document.querySelector('.tab-btn[data-detail-tab="overview"]').classList.add("active"),I("overview")}function B(){document.getElementById("teamDetailsView").style.display="none",document.getElementById("teamsListView").style.display="block",c=null}async function I(e){const t=document.getElementById("teamDetailContent");switch(e){case"overview":te(t);break;case"members":ae(t);break;case"time-tracking":oe(t);break;case"recordings":se(t);break;case"tasks":de(t);break}}async function te(e){try{const n=await(await fetch(`/api/team-management/teams/${c.id}/stats`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json(),o=n.success?n.data:{total_members:0,today_attendance:0,active_projects:0,completed_tasks_this_week:0};e.innerHTML=`
                <div class="section-header">
                    <h2 class="section-title">${c.name}</h2>
                </div>
                <p style="color: var(--text-secondary); margin-bottom: 2rem;">${c.description||"No description provided."}</p>

                <!-- Stats Summary Grid -->
                <div class="summary-grid">
                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Team Members</span>
                            <div class="summary-icon">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                    <circle cx="9" cy="7" r="4"/>
                                    <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                    <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${o.total_members}</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Present Today</span>
                            <div class="summary-icon success">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 11 12 14 22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${o.today_attendance}</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Active Projects</span>
                            <div class="summary-icon info">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M22 19a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5l2 3h9a2 2 0 0 1 2 2z"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${o.active_projects}</div>
                    </div>

                    <div class="summary-card">
                        <div class="summary-header">
                            <span class="summary-label">Tasks This Week</span>
                            <div class="summary-icon purple">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="9 11 12 14 22 4"/>
                                    <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                </svg>
                            </div>
                        </div>
                        <div class="summary-value">${o.completed_tasks_this_week}</div>
                    </div>
                </div>

                ${c.leader?`
                <div class="section-header" style="margin-top: 2rem;">
                    <h2 class="section-title">Team Leader</h2>
                </div>
                <div class="leader-card">
                    <div class="leader-avatar">
                        ${c.leader.photo?`<img src="${c.leader.photo}" alt="${c.leader.name}">`:c.leader.initials}
                    </div>
                    <div class="leader-info">
                        <div class="leader-name">${c.leader.name}</div>
                        <div style="font-size: 0.875rem; color: var(--text-secondary);">${c.leader.email}</div>
                    </div>
                </div>
                `:""}
            `}catch(t){console.error("Error loading overview:",t),e.innerHTML='<p class="text-center text-muted">Error loading overview</p>'}}async function ae(e){m={page:1,perPage:10,total:0,lastPage:1,search:""},e.innerHTML=`
            <div class="section-header">
                <h2 class="section-title">Team Members</h2>
                <div class="section-actions">
                    <div class="search-box">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="8"/>
                            <path d="M21 21l-4.35-4.35"/>
                        </svg>
                        <input type="text" id="memberSearch" placeholder="Search members..." onkeyup="searchMembers(this.value)">
                    </div>
                    <select class="filter-select" id="membersPerPage" onchange="changeMembersPerPage(this.value)">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                    ${CFG.canEdit?`
                    <button class="btn-primary" onclick="openAddMembersModal()">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M16 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                            <circle cx="8.5" cy="7" r="4"/>
                            <line x1="20" y1="8" x2="20" y2="14"/>
                            <line x1="23" y1="11" x2="17" y2="11"/>
                        </svg>
                        Add Members
                    </button>
                    `:""}
                </div>
            </div>
            <div id="membersContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `,await u()}async function u(){const e=document.getElementById("membersContent");if(!e){console.error("membersContent container not found");return}try{const t=m.search?`&search=${encodeURIComponent(m.search)}`:"",n=`/api/team-management/teams/${c.id}/members?page=${m.page}&per_page=${m.perPage}${t}`;console.log("Fetching members from:",n);const i=await(await fetch(n,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();console.log("Members API response:",i),i.success&&(i.pagination&&(m.total=i.pagination.total,m.lastPage=i.pagination.last_page,m.page=i.pagination.current_page,console.log("Updated pagination:",m)),i.data.length>0?e.innerHTML=`
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Member</th>
                                        <th>Email</th>
                                        <th>Role</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${i.data.map(a=>`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="member-avatar-sm">
                                                        ${a.photo?`<img src="${a.photo}" alt="${a.name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`:a.initials}
                                                    </div>
                                                    <span>${a.name}</span>
                                                </div>
                                            </td>
                                            <td>${a.email}</td>
                                            <td><span class="status-badge ${a.role}">${a.role==="leader"?"Team Leader":a.role==="co-leader"?"Co-Leader":"Member"}</span></td>
                                            <td>
                                                ${a.role!=="leader"?`
                                                <div class="team-actions" style="justify-content: flex-start;">
                                                    ${CFG.canEdit?`
                                                    <button class="icon-btn" title="${a.role==="member"?"Set as Co-Leader":"Set as Member"}" onclick="changeMemberRole(${a.id}, '${a.role}')">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                                        </svg>
                                                    </button>
                                                    <button class="icon-btn" title="Remove" onclick="removeMember(${a.id})">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                            <polyline points="3 6 5 6 21 6"/>
                                                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                                        </svg>
                                                    </button>
                                                    `:""}
                                                </div>
                                                `:'<span style="color: var(--text-muted);">—</span>'}
                                            </td>
                                        </tr>
                                    `).join("")}
                                </tbody>
                            </table>
                        </div>
                        ${ne()}
                    `:e.innerHTML=`
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/>
                                <circle cx="9" cy="7" r="4"/>
                                <path d="M23 21v-2a4 4 0 0 0-3-3.87"/>
                                <path d="M16 3.13a4 4 0 0 1 0 7.75"/>
                            </svg>
                            <h3>No Members Found</h3>
                            <p>${m.search?"No members found matching your search.":"No members in this team yet."}</p>
                        </div>
                    `)}catch(t){console.error("Error loading members:",t),e.innerHTML='<p class="text-center text-muted">Error loading members</p>'}}function ne(){const{page:e,perPage:t,total:n,lastPage:o}=m;if(n===0)return"";const i=(e-1)*t+1,a=Math.min(e*t,n),s=o>1;let r=`
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing ${i} to ${a} of ${n} members
                </div>
                <div class="pagination-controls">
        `;if(s){r+=`
                <button class="pagination-btn" onclick="goToMembersPage(${e-1})" ${e===1?"disabled":""}>Previous</button>
                <div class="pagination-numbers">
            `;const l=5;let v=Math.max(1,e-Math.floor(l/2)),d=Math.min(o,v+l-1);d-v<l-1&&(v=Math.max(1,d-l+1));for(let g=v;g<=d;g++)r+=`<button class="pagination-btn ${g===e?"active":""}" onclick="goToMembersPage(${g})">${g}</button>`;r+=`
                </div>
                <button class="pagination-btn" onclick="goToMembersPage(${e+1})" ${e===o?"disabled":""}>Next</button>
            `}return r+=`
                </div>
            </div>
        `,r}function P(e){m.page=e,u()}function S(e){m.perPage=parseInt(e),m.page=1,u()}function _(){const e=new Date,t=e.getDay(),n=t===0?-6:1-t,o=new Date(e);o.setDate(e.getDate()+n);const i=new Date(o);return i.setDate(o.getDate()+6),{start:o.toISOString().split("T")[0],end:i.toISOString().split("T")[0]}}async function oe(e){const t=_(),n=t.start,o=t.end;p={page:1,perPage:10,total:0,lastPage:1},e.innerHTML=`
            <div class="section-header">
                <h2 class="section-title">Time Tracking</h2>
                <div class="section-actions">
                    <div class="date-range-filter">
                        <input type="date" class="date-input" id="ttStartDate" value="${n}" onchange="goToTimeTrackingPage(1)">
                        <span class="date-range-separator">to</span>
                        <input type="date" class="date-input" id="ttEndDate" value="${o}" onchange="goToTimeTrackingPage(1)">
                    </div>
                    <select class="filter-select" id="ttPerPage" onchange="changeTimeTrackingPerPage(this.value)">
                        <option value="10">10 per page</option>
                        <option value="25">25 per page</option>
                        <option value="50">50 per page</option>
                    </select>
                </div>
            </div>
            <div id="timeTrackingContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `,await T()}async function T(){const e=document.getElementById("ttStartDate").value,t=document.getElementById("ttEndDate").value,n=document.getElementById("timeTrackingContent");if(!n){console.error("timeTrackingContent container not found");return}try{const o=`/api/team-management/teams/${c.id}/time-tracking?start_date=${e}&end_date=${t}&page=${p.page}&per_page=${p.perPage}`;console.log("Fetching time tracking from:",o);const a=await(await fetch(o,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();console.log("Time tracking API response:",a),a.success&&(a.pagination&&(p.total=a.pagination.total,p.lastPage=a.pagination.last_page,p.page=a.pagination.current_page,console.log("Updated time tracking pagination:",p)),a.data.records.length>0?n.innerHTML=`
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Time In</th>
                                        <th>Time Out</th>
                                        <th>Hours Worked</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${a.data.records.map(s=>`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="member-avatar-sm">${s.user_initials}</div>
                                                    <span>${s.user_name}</span>
                                                </div>
                                            </td>
                                            <td>${s.date}</td>
                                            <td>${s.time_in||"--"}</td>
                                            <td>${s.time_out||"--"}</td>
                                            <td><strong>${s.hours_worked}</strong></td>
                                            <td><span class="status-badge ${s.status}">${s.status}</span></td>
                                        </tr>
                                    `).join("")}
                                </tbody>
                            </table>
                        </div>
                        ${ie()}
                    `:n.innerHTML=`
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <h3>No Records Found</h3>
                            <p>No time tracking records found for this period.</p>
                        </div>
                    `)}catch(o){console.error("Error loading time tracking:",o),n.innerHTML='<p class="text-center text-muted">Error loading time tracking data</p>'}}function ie(){const{page:e,perPage:t,total:n,lastPage:o}=p;if(n===0)return"";const i=(e-1)*t+1,a=Math.min(e*t,n),s=o>1;let r=`
            <div class="table-pagination">
                <div class="pagination-info">
                    Showing ${i} to ${a} of ${n} records
                </div>
                <div class="pagination-controls">
        `;if(s){r+=`
                <button class="pagination-btn" onclick="goToTimeTrackingPage(${e-1})" ${e===1?"disabled":""}>Previous</button>
                <div class="pagination-numbers">
            `;const l=5;let v=Math.max(1,e-Math.floor(l/2)),d=Math.min(o,v+l-1);d-v<l-1&&(v=Math.max(1,d-l+1));for(let g=v;g<=d;g++)r+=`<button class="pagination-btn ${g===e?"active":""}" onclick="goToTimeTrackingPage(${g})">${g}</button>`;r+=`
                </div>
                <button class="pagination-btn" onclick="goToTimeTrackingPage(${e+1})" ${e===o?"disabled":""}>Next</button>
            `}return r+=`
                </div>
            </div>
        `,r}function j(e){p.page=e,T()}function H(e){p.perPage=parseInt(e),p.page=1,T()}let $={};async function se(e){const t=_(),n=t.start,o=t.end;e.innerHTML=`
            <div class="section-header">
                <h2 class="section-title">Screen Recordings</h2>
                <div class="section-actions">
                    <div class="date-range-filter">
                        <input type="date" class="date-input" id="recStartDate" value="${n}" onchange="refreshRecordings()">
                        <span class="date-range-separator">to</span>
                        <input type="date" class="date-input" id="recEndDate" value="${o}" onchange="refreshRecordings()">
                    </div>
                </div>
            </div>
            <div id="recordingsContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `,await k()}async function k(){const e=document.getElementById("recStartDate").value,t=document.getElementById("recEndDate").value,n=document.getElementById("recordingsContent");try{const i=await(await fetch(`/api/team-management/teams/${c.id}/recordings?start_date=${e}&end_date=${t}`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();i.success&&($={},i.data.forEach(a=>{$[a.user_id]=a}),i.data.length>0?(n.innerHTML=`
                        <div class="recordings-grouped-grid">
                            ${i.data.map(a=>`
                                <div class="employee-monitor-card">
                                    <div class="monitor-card-header">
                                        <div class="employee-info">
                                            <div class="employee-avatar">
                                                ${a.user_photo?`<img src="${a.user_photo}" alt="${a.user_name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`:a.user_initials}
                                            </div>
                                            <div>
                                                <div class="employee-name">${a.user_name}</div>
                                                <div class="employee-meta">
                                                    <span>${a.user_email}</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="monitor-stats">
                                            <div class="stat-item">
                                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                    <polygon points="23 7 16 12 23 17 23 7"/>
                                                    <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                                                </svg>
                                                <span>${a.total_videos} Videos</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="monitor-content active">
                                        <div class="media-grid" id="videos-${a.user_id}">
                                            <div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">Loading videos...</div>
                                        </div>
                                        ${a.total_videos>2?`
                                        <div class="view-more">
                                            <button class="btn-secondary" onclick="viewAllTeamVideos(${a.user_id})">View All ${a.total_videos} Videos</button>
                                        </div>
                                        `:""}
                                    </div>
                                </div>
                            `).join("")}
                        </div>
                    `,setTimeout(()=>{i.data.forEach(a=>{re(a.user_id,a.recordings)})},100)):n.innerHTML=`
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <polygon points="23 7 16 12 23 17 23 7"/>
                                <rect x="1" y="5" width="15" height="14" rx="2" ry="2"/>
                            </svg>
                            <h3>No Recordings Found</h3>
                            <p>No recordings found for this period.</p>
                        </div>
                    `)}catch(o){console.error("Error loading recordings:",o),n.innerHTML='<p class="text-center text-muted">Error loading recordings</p>'}}function re(e,t){const n=document.getElementById(`videos-${e}`);if(!n)return;if(!t||t.length===0){n.innerHTML='<div style="grid-column: 1 / -1; text-align: center; padding: 2rem; color: var(--text-muted);">No videos available</div>';return}const o=t.slice(0,2);n.innerHTML=o.map(a=>{const s=(a.url||"").replace(/'/g,"\\'"),r=(a.date_full||"").replace(/'/g,"\\'");return`
                <div class="media-item video-item" onclick="openTeamMediaViewer('video', ${a.id}, '${s}', '${r}', ${e})" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                    <div class="media-thumbnail">
                        <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video">
                        <video class="video-preview" muted loop preload="metadata">
                            <source src="${s}" type="video/webm">
                            <source src="${s}" type="video/mp4">
                        </video>
                        <div class="media-overlay">
                            <div class="media-overlay-content">
                                <div class="play-button">
                                    <svg viewBox="0 0 24 24" fill="white">
                                        <polygon points="9 5 9 19 19 12"/>
                                    </svg>
                                </div>
                            </div>
                        </div>
                        <div class="video-duration">${a.duration_formatted||"00:00"}</div>
                    </div>
                    <div class="media-info">
                        <div class="media-time">${a.time||"N/A"}</div>
                        <div class="media-date">${a.date||"N/A"}</div>
                    </div>
                </div>
            `}).join("");const i=n.closest(".monitor-content").querySelector(".view-more");if(i)if(t.length>2){i.style.display="block";const a=i.querySelector("button");a&&(a.textContent=`View All ${t.length} Videos`)}else i.style.display="none"}function V(e){const t=$[e];if(!t||!t.recordings||t.recordings.length===0){alert("No videos available for this team member");return}const n=document.getElementById("videoModal"),o=document.getElementById("recordingVideoContainer"),i=document.getElementById("videoModalTitle");if(!n||!o||!i){console.error("Video modal elements not found");return}i.textContent=`All Videos - ${t.user_name}`,o.innerHTML=`
            <div class="all-videos-grid">
                ${t.recordings.map(a=>{const s=(a.url||"").replace(/'/g,"\\'").replace(/"/g,"&quot;"),r=(a.date_full||"").replace(/'/g,"\\'").replace(/"/g,"&quot;");return`
                        <div class="media-item video-item" style="cursor: pointer;" onclick="openTeamVideoViewer(${a.id}, '${s}', '${r}', ${e})" onmouseenter="playVideoPreview(this)" onmouseleave="pauseVideoPreview(this)">
                            <div class="media-thumbnail">
                                <img src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='300' height='200'%3E%3Crect fill='%23e5e7eb' width='300' height='200'/%3E%3Ccircle cx='150' cy='100' r='30' fill='%235f61e6'/%3E%3Cpolygon points='140,90 140,110 160,100' fill='white'/%3E%3C/svg%3E" alt="Video" style="width: 100%; height: 150px; object-fit: cover;">
                                <video class="video-preview" muted loop preload="metadata">
                                    <source src="${s}" type="video/webm">
                                    <source src="${s}" type="video/mp4">
                                </video>
                                <div class="media-overlay">
                                    <div class="media-overlay-content">
                                        <div class="play-button">
                                            <svg viewBox="0 0 24 24" fill="white">
                                                <polygon points="9 5 9 19 19 12"/>
                                            </svg>
                                        </div>
                                    </div>
                                </div>
                                <div class="video-duration">${a.duration_formatted||"00:00"}</div>
                            </div>
                            <div class="media-info">
                                <div class="media-time">${a.time||"N/A"}</div>
                                <div class="media-date">${a.date||"N/A"}</div>
                            </div>
                        </div>
                    `}).join("")}
            </div>
        `,n.classList.add("active"),document.body.style.overflow="hidden"}function A(e,t,n,o,i=null){const a=document.getElementById("videoModal"),s=document.getElementById("recordingVideoContainer"),r=document.getElementById("videoModalTitle");if(!a||!s||!r){console.error("Video modal elements not found");return}r.textContent=o||"Video Recording",s.innerHTML=`
            <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                <source src="${n}" type="video/webm">
                <source src="${n}" type="video/mp4">
                Your browser does not support video playback.
            </video>
        `,a.classList.add("active"),document.body.style.overflow="hidden"}function N(e,t,n,o){const i=document.getElementById("videoModal"),a=document.getElementById("recordingVideoContainer"),s=document.getElementById("videoModalTitle");if(!i||!a||!s){console.error("Video modal elements not found");return}s.textContent=n||"Video Recording",a.innerHTML=`
            <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                <source src="${t}" type="video/webm">
                <source src="${t}" type="video/mp4">
                Your browser does not support video playback.
            </video>
        `,i.classList.add("active"),document.body.style.overflow="hidden"}async function D(e,t,n){const o=document.getElementById("taskTimeTrackingModal"),i=document.getElementById("taskTimeTrackingContent"),a=document.getElementById("taskTimeTrackingTitle");if(!o||!i||!a){console.error("Task time tracking modal elements not found");return}a.textContent=`Time Tracking - ${t}`,i.innerHTML='<div class="loading-container"><div class="loading-spinner"></div></div>',o.classList.add("active"),document.body.style.overflow="hidden";try{const r=await(await fetch(`/api/team-management/teams/${c.id}/tasks/${e}/time-tracking`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();if(r.success){const l=r.data.time_tracking,v=r.data.task;l.length>0?i.innerHTML=`
                        <div style="margin-bottom: 1.5rem;">
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">Task:</span>
                                <span style="color: var(--text-secondary);">${v.title}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">Project:</span>
                                <span style="color: var(--text-secondary);">${v.project_title}</span>
                            </div>
                            <div style="display: flex; align-items: center; gap: 0.5rem;">
                                <span style="font-weight: 600; color: var(--text-primary);">Progress:</span>
                                <span class="task-progress-badge">${v.progress}%</span>
                                <div class="task-progress-bar" style="flex: 1; max-width: 200px; height: 6px; margin-left: 0.5rem;">
                                    <div class="task-progress-fill" style="width: ${v.progress}%;"></div>
                                </div>
                            </div>
                        </div>
                        <div class="table-container">
                            <table class="data-table">
                                <thead>
                                    <tr>
                                        <th>Employee</th>
                                        <th>Date</th>
                                        <th>Start Time</th>
                                        <th>End Time</th>
                                        <th>Hours Worked</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    ${l.map(d=>`
                                        <tr>
                                            <td>
                                                <div style="display: flex; align-items: center; gap: 0.75rem;">
                                                    <div class="member-avatar-sm">${d.user_initials}</div>
                                                    <span>${d.user_name}</span>
                                                </div>
                                            </td>
                                            <td>${d.date}</td>
                                            <td>${d.start_time||"--"}</td>
                                            <td>${d.end_time||"--"}</td>
                                            <td><strong>${d.hours_worked}</strong></td>
                                        </tr>
                                    `).join("")}
                                </tbody>
                            </table>
                        </div>
                        ${l.length>0&&l.some(d=>d.notes)?`
                        <div style="margin-top: 1.5rem;">
                            <h4 style="font-size: 0.9375rem; font-weight: 600; color: var(--text-primary); margin-bottom: 0.75rem;">Notes</h4>
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                ${l.filter(d=>d.notes).map(d=>`
                                    <div style="padding: 0.75rem; background: var(--bg-primary); border-radius: 8px; border: 1px solid var(--border);">
                                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
                                            <span style="font-weight: 500; color: var(--text-primary);">${d.user_name}</span>
                                            <span style="font-size: 0.8125rem; color: var(--text-secondary);">${d.date}</span>
                                        </div>
                                        <p style="font-size: 0.875rem; color: var(--text-secondary); margin: 0; line-height: 1.5;">${d.notes}</p>
                                    </div>
                                `).join("")}
                            </div>
                        </div>
                        `:""}
                    `:i.innerHTML=`
                        <div class="empty-state">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                                <polyline points="12 6 12 12 16 14"/>
                            </svg>
                            <h3>No Time Tracking Records</h3>
                            <p>No time tracking records found for this task.</p>
                        </div>
                    `}else i.innerHTML=`
                    <div class="empty-state">
                        <h3>Error</h3>
                        <p>${r.message||"Failed to load time tracking records"}</p>
                    </div>
                `}catch(s){console.error("Error loading task time tracking:",s),i.innerHTML=`
                <div class="empty-state">
                    <h3>Error</h3>
                    <p>Failed to load time tracking records. Please try again.</p>
                </div>
            `}}function y(){const e=document.getElementById("taskTimeTrackingModal");e&&(e.classList.remove("active"),document.body.style.overflow="")}async function de(e){e.innerHTML=`
            <div class="section-header">
                <h2 class="section-title">Team Tasks</h2>
            </div>
            <div id="tasksContent">
                <div class="loading-container"><div class="loading-spinner"></div></div>
            </div>
        `;try{const n=await(await fetch(`/api/team-management/teams/${c.id}/tasks`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json(),o=document.getElementById("tasksContent");n.success&&n.data.length>0?o.innerHTML=`
                    <div class="recordings-grouped-grid">
                        ${n.data.map(i=>`
                            <div class="employee-monitor-card">
                                <div class="monitor-card-header">
                                    <div class="employee-info">
                                        <div class="employee-avatar">
                                            ${i.user_photo?`<img src="${i.user_photo}" alt="${i.user_name}" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">`:i.user_initials}
                                        </div>
                                        <div>
                                            <div class="employee-name">${i.user_name}</div>
                                            <div class="employee-meta">
                                                <span>${i.user_email}</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="monitor-stats">
                                        <div class="stat-item">
                                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                                <polyline points="9 11 12 14 22 4"/>
                                                <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                                            </svg>
                                            <span>${i.total_tasks} Tasks</span>
                                        </div>
                                    </div>
                                </div>
                                <div class="monitor-content active">
                                    <div class="tasks-list-container">
                                        ${i.tasks.map(a=>`
                                            <div class="task-item-card" onclick="viewTaskTimeTracking(${a.id}, '${a.title.replace(/'/g,"\\'")}', '${a.project_title.replace(/'/g,"\\'")}')" style="cursor: pointer;">
                                                <div class="task-item-header">
                                                    <div class="task-title-section">
                                                        <h4 class="task-title">${a.title}</h4>
                                                        <span class="task-project-badge">${a.project_title}</span>
                                                    </div>
                                                    <div class="task-badges">
                                                        <span class="status-badge ${a.priority}">${a.priority}</span>
                                                        <span class="task-progress-badge">${a.progress}%</span>
                                                    </div>
                                                </div>
                                                ${a.description?`
                                                <div class="task-description">
                                                    ${a.description.length>100?a.description.substring(0,100)+"...":a.description}
                                                </div>
                                                `:""}
                                                <div class="task-footer">
                                                    <div class="task-progress-section">
                                                        <div class="task-progress-bar">
                                                            <div class="task-progress-fill" style="width: ${a.progress}%;"></div>
                                                        </div>
                                                        <span class="task-progress-text">${a.progress}%</span>
                                                    </div>
                                                    ${a.deadline?`
                                                    <div class="task-deadline">
                                                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px; margin-right: 0.25rem;">
                                                            <circle cx="12" cy="12" r="10"/>
                                                            <polyline points="12 6 12 12 16 14"/>
                                                        </svg>
                                                        <span>${a.deadline}</span>
                                                    </div>
                                                    `:""}
                                                </div>
                                            </div>
                                        `).join("")}
                                    </div>
                                </div>
                            </div>
                        `).join("")}
                    </div>
                `:o.innerHTML=`
                    <div class="empty-state">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="9 11 12 14 22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>
                        </svg>
                        <h3>No Tasks Found</h3>
                        <p>No tasks assigned to team members yet.</p>
                    </div>
                `}catch(t){console.error("Error loading tasks:",t),document.getElementById("tasksContent").innerHTML='<p class="text-center text-muted">Error loading tasks</p>'}}function h(){const e=document.getElementById("videoModal"),t=document.getElementById("recordingVideoContainer"),n=document.getElementById("recordingVideo");n&&(n.pause(),n.src=""),t&&(t.innerHTML=`
                <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                    Your browser does not support video playback.
                </video>
            `),e.classList.remove("active"),document.body.style.overflow=""}function R(){document.getElementById("teamModalTitle").textContent="Create Team",document.getElementById("saveTeamText").textContent="Create Team",document.getElementById("teamId").value="",document.getElementById("teamForm").reset(),document.getElementById("teamColor").value="#5f61e6",document.getElementById("colorPreview").style.background="#5f61e6",x(),document.getElementById("teamModal").classList.add("active")}async function F(e){event.stopPropagation();try{const n=await(await fetch(`/api/team-management/teams/${e}`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();if(n.success){const o=n.data;document.getElementById("teamModalTitle").textContent="Edit Team",document.getElementById("saveTeamText").textContent="Save Changes",document.getElementById("teamId").value=o.id,document.getElementById("teamName").value=o.name,document.getElementById("teamDescription").value=o.description||"",document.getElementById("teamLeader").value=o.leader_id||"",document.getElementById("teamColor").value=o.color||"#5f61e6",document.getElementById("colorPreview").style.background=o.color||"#5f61e6";const i=o.members.map(a=>a.id);x(o.leader_id,i),document.getElementById("teamModal").classList.add("active")}}catch(t){console.error("Error loading team for edit:",t)}}function E(){document.getElementById("teamModal").classList.remove("active")}async function q(){const e=document.getElementById("teamId").value,t={name:document.getElementById("teamName").value,description:document.getElementById("teamDescription").value,leader_id:document.getElementById("teamLeader").value||null,color:document.getElementById("teamColor").value,member_ids:Array.from(document.querySelectorAll("#memberSelection input:checked")).map(i=>parseInt(i.value))},n=e?`/api/team-management/teams/${e}`:"/api/team-management/teams",o=e?"PUT":"POST";try{const a=await(await fetch(n,{method:o,headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify(t)})).json();a.success?(E(),w(),alert(a.message)):alert(a.message||"Error saving team")}catch(i){console.error("Error saving team:",i),alert("Error saving team")}}async function O(e){if(event.stopPropagation(),!!confirm("Are you sure you want to delete this team? This action cannot be undone."))try{const n=await(await fetch(`/api/team-management/teams/${e}`,{method:"DELETE",headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();n.success?(w(),alert(n.message)):alert(n.message||"Error deleting team")}catch(t){console.error("Error deleting team:",t),alert("Error deleting team")}}async function K(){document.getElementById("addMembersTeamId").value=c.id;try{const t=await(await fetch(`/api/team-management/users?exclude_team_id=${c.id}`,{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();if(t.success){const n=document.getElementById("availableMembersSelection");n.innerHTML="",t.data.length===0?n.innerHTML='<p class="text-center text-muted" style="padding: 1rem;">All users are already members of this team.</p>':t.data.forEach(o=>{n.innerHTML+=`
                            <label class="user-option">
                                <input type="checkbox" name="new_member_ids[]" value="${o.id}" onchange="this.parentElement.classList.toggle('selected')">
                                <div class="leader-avatar" style="width: 32px; height: 32px; font-size: 0.7rem;">
                                    ${o.photo?`<img src="${o.photo}" alt="${o.name}">`:o.initials}
                                </div>
                                <div class="member-info">
                                    <div class="member-name">${o.name}</div>
                                    <div class="member-email">${o.email}</div>
                                </div>
                            </label>
                        `})}}catch(e){console.error("Error loading available users:",e)}document.getElementById("addMembersModal").classList.add("active")}function M(){document.getElementById("addMembersModal").classList.remove("active")}async function X(){const e=document.getElementById("addMembersTeamId").value,t=Array.from(document.querySelectorAll("#availableMembersSelection input:checked")).map(o=>parseInt(o.value)),n=document.getElementById("newMemberRole").value;if(t.length===0){alert("Please select at least one member to add.");return}try{const i=await(await fetch(`/api/team-management/teams/${e}/members`,{method:"POST",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({member_ids:t,role:n})})).json();i.success?(M(),u(),alert(i.message)):alert(i.message||"Error adding members")}catch(o){console.error("Error adding members:",o),alert("Error adding members")}}async function z(e,t){const n=t==="member"?"co-leader":"member";if(confirm(`Change this member to ${n==="co-leader"?"Co-Leader":"Member"}?`))try{const a=await(await fetch(`/api/team-management/teams/${c.id}/members/${e}/role`,{method:"PUT",headers:{"Content-Type":"application/json",Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content},body:JSON.stringify({role:n})})).json();a.success?u():alert(a.message||"Error updating role")}catch(i){console.error("Error updating role:",i),alert("Error updating role")}}async function U(e){if(confirm("Are you sure you want to remove this member from the team?"))try{const n=await(await fetch(`/api/team-management/teams/${c.id}/members/${e}`,{method:"DELETE",headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();n.success?u():alert(n.message||"Error removing member")}catch(t){console.error("Error removing member:",t),alert("Error removing member")}}typeof X=="function"&&(window.addSelectedMembers=X),typeof z=="function"&&(window.changeMemberRole=z),typeof S=="function"&&(window.changeMembersPerPage=S),typeof H=="function"&&(window.changeTimeTrackingPerPage=H),typeof M=="function"&&(window.closeAddMembersModal=M),typeof y=="function"&&(window.closeTaskTimeTrackingModal=y),typeof E=="function"&&(window.closeTeamModal=E),typeof h=="function"&&(window.closeVideoModal=h),typeof O=="function"&&(window.deleteTeam=O),typeof F=="function"&&(window.editTeam=F),typeof P=="function"&&(window.goToMembersPage=P),typeof j=="function"&&(window.goToTimeTrackingPage=j),typeof K=="function"&&(window.openAddMembersModal=K),typeof R=="function"&&(window.openCreateTeamModal=R),typeof A=="function"&&(window.openTeamMediaViewer=A),typeof N=="function"&&(window.openTeamVideoViewer=N),typeof k=="function"&&(window.refreshRecordings=k),typeof U=="function"&&(window.removeMember=U),typeof q=="function"&&(window.saveTeam=q),typeof B=="function"&&(window.showTeamsList=B),typeof stopPropagation=="function"&&(window.stopPropagation=stopPropagation),typeof toggle=="function"&&(window.toggle=toggle),typeof V=="function"&&(window.viewAllTeamVideos=V),typeof D=="function"&&(window.viewTaskTimeTracking=D),typeof L=="function"&&(window.viewTeam=L)})();
