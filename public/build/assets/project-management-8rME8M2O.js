(function(){const U=window.__projectManagementConfig||{},O=U.currentUserId;U.currentUserName;const b=U.userPermissions||[];b.includes("create_project_management"),b.includes("create_task_management");const ue=b.includes("edit_project_management");b.includes("delete_project_management");const K=b.includes("edit_project_management"),W=b.includes("delete_project_management");function me(n){return n.replace(/-([a-z])/g,e=>e[1].toUpperCase())}document.querySelectorAll(".tab-btn").forEach(n=>{n.addEventListener("click",function(){const e=this.dataset.tab,t=me(e);document.querySelectorAll(".tab-btn").forEach(a=>a.classList.remove("active")),this.classList.add("active"),document.querySelectorAll(".tab-content").forEach(a=>{a.classList.remove("active")});const o=document.getElementById(t+"Tab");o&&o.classList.add("active")})});let m=[],w=[],d={current_page:1,last_page:1,per_page:10,total:0},H=[],v={current_page:1,last_page:1,per_page:10,total:0},k=[],P={total:0,active:0,completed:0,on_hold:0},T={},I="all";const u="/api/project-management",X="/api/client-management";let G=null,y=-1;function B(n,e,t){const o=document.getElementById(n),a=document.getElementById(e),s=document.getElementById(t);!o||!a||!s||(o.addEventListener("input",function(){const i=this.value.trim();if(!i){s.value="",p(a);return}clearTimeout(G),G=setTimeout(()=>{pe(i,a,o,s)},300)}),o.addEventListener("keydown",function(i){const c=a.querySelectorAll(".client-autocomplete-item");i.key==="ArrowDown"?(i.preventDefault(),y=Math.min(y+1,c.length-1),J(c)):i.key==="ArrowUp"?(i.preventDefault(),y=Math.max(y-1,-1),J(c)):i.key==="Enter"?(i.preventDefault(),y>=0&&c[y]?c[y].click():Y(o,s,a)):i.key==="Escape"&&p(a)}),document.addEventListener("click",function(i){!o.contains(i.target)&&!a.contains(i.target)&&p(a)}),o.addEventListener("blur",function(){setTimeout(()=>{a.contains(document.activeElement)||Y(o,s,a)},200)}))}async function pe(n,e,t,o){try{const s=await(await fetch(`${X}/clients/search?q=${encodeURIComponent(n)}`)).json();s.success&&s.data.length>0?ge(s.data,e,t,o):p(e)}catch(a){console.error("Error searching clients:",a),p(e)}}function ge(n,e,t,o){y=-1,e.innerHTML=n.map((a,s)=>`
            <div class="client-autocomplete-item" data-client-id="${a.id}" data-client-name="${a.name}" data-index="${s}">
                <div class="client-autocomplete-item-name">${a.name}</div>
            </div>
        `).join(""),e.querySelectorAll(".client-autocomplete-item").forEach(a=>{a.addEventListener("click",function(){const s=this.getAttribute("data-client-id"),i=this.getAttribute("data-client-name");t.value=i,o.value=s,p(e)})}),e.classList.add("active")}function J(n){n.forEach((e,t)=>{t===y?(e.classList.add("selected"),e.scrollIntoView({block:"nearest"})):e.classList.remove("selected")})}function p(n){n.classList.remove("active"),y=-1}async function Y(n,e,t){const o=n.value.trim();if(!o){e.value="",p(t);return}if(!e.value)try{const s=await(await fetch(`${X}/clients/search?q=${encodeURIComponent(o)}`)).json();if(s.success&&s.data.length>0){const i=s.data.find(c=>c.name.toLowerCase()===o.toLowerCase());i?(e.value=i.id,n.value=i.name,p(t)):(n.value="",e.value="",p(t),alert("No matching client found. Please select a client from the suggestions."),n.focus())}else n.value="",e.value="",p(t),alert("No matching client found. Please select a client from the suggestions."),n.focus()}catch(a){console.error("Error validating client:",a),n.value="",e.value="",p(t)}}async function Q(n="all"){try{const t=await(await fetch(`${u}/projects?status=${n}`)).json();return t.success?(m=t.data,t.data):[]}catch(e){return console.error("Error fetching projects:",e),[]}}async function Z(){try{const e=await(await fetch(`${u}/projects/stats`)).json();return e.success?(P=e.data,ye(),e.data):null}catch(n){return console.error("Error fetching project stats:",n),null}}async function $(n="all",e="all",t=1){try{const a=await(await fetch(`${u}/tasks?project_id=${n}&status=${e}&per_page=${d.per_page||10}&page=${t}`)).json();return a.success?(w=a.data,a.pagination?d=a.pagination:d={current_page:1,last_page:1,per_page:10,total:a.data?a.data.length:0,from:a.data?1:0,to:a.data?a.data.length:0},await ee(),a.data):[]}catch(o){return console.error("Error fetching tasks:",o),[]}}async function ee(){try{const e=await(await fetch(`${u}/time-tracking/active-record`)).json();e.success&&e.data?(T={},e.data.forEach(t=>{t.task_id&&(T[t.task_id]=t)})):T={}}catch(n){console.error("Error fetching active time tracking:",n),T={}}}async function C(n="all",e=null,t=1){try{let o=`${u}/time-tracking?project_id=${n}&per_page=${v.per_page||10}&page=${t}`;e&&(o+=`&date=${e}`);const s=await(await fetch(o)).json();return s.success?(H=s.data,s.pagination?v=s.pagination:v={current_page:1,last_page:1,per_page:10,total:s.data?s.data.length:0,from:s.data?1:0,to:s.data?s.data.length:0},s.data):[]}catch(o){return console.error("Error fetching time tracking:",o),[]}}async function fe(){try{const e=await(await fetch(`${u}/time-tracking/summary`)).json();return e.success?(he(e.data),e.data):null}catch(n){return console.error("Error fetching time tracking summary:",n),null}}async function ve(){try{const e=await(await fetch(`${u}/users`)).json();return e.success?(k=e.data,M(),e.data):[]}catch(n){return console.error("Error fetching users:",n),[]}}function ye(){const n=document.getElementById("statTotalProjects"),e=document.getElementById("statActiveProjects"),t=document.getElementById("statCompletedProjects"),o=document.getElementById("statOnHoldProjects");n&&(n.textContent=P.total||0),e&&(e.textContent=P.active||0),t&&(t.textContent=P.completed||0),o&&(o.textContent=P.on_hold||0)}function he(n){const e=document.querySelector(".time-summary-grid .summary-value");e&&n.today!==void 0&&(e.textContent=n.today);const t=document.querySelectorAll(".time-summary-grid .summary-value")[1];t&&n.this_week!==void 0&&(t.textContent=n.this_week);const o=document.querySelectorAll(".time-summary-grid .summary-value")[2];o&&n.this_month!==void 0&&(o.textContent=n.this_month)}function te(){const n=document.getElementById("taskAssignedTo"),e=document.getElementById("taskProject");if(!n||!e)return;const t=e.value,o=n.value;if(!t){n.innerHTML='<option value="">Unassigned</option>';return}const a=m.find(c=>c.id===parseInt(t));if(!a||!a.team_members){n.innerHTML='<option value="">Unassigned</option>';return}const s=a.team_members.map(c=>c.id),i=k.filter(c=>s.includes(c.id));n.innerHTML='<option value="">Unassigned</option>'+i.map(c=>`<option value="${c.id}">${c.name}</option>`).join(""),o&&i.some(c=>c.id===parseInt(o))?n.value=o:n.value=""}function V(){const n=document.getElementById("editTaskAssignedTo"),e=document.getElementById("editTaskProject");if(!n||!e)return;const t=e.value,o=n.value;if(!t){n.innerHTML='<option value="">Unassigned</option>';return}const a=m.find(c=>c.id===parseInt(t));if(!a||!a.team_members){n.innerHTML='<option value="">Unassigned</option>';return}const s=a.team_members.map(c=>c.id),i=k.filter(c=>s.includes(c.id));n.innerHTML='<option value="">Unassigned</option>'+i.map(c=>`<option value="${c.id}">${c.name}</option>`).join(""),o&&i.some(c=>c.id===parseInt(o))?n.value=o:n.value=""}function M(){const n=document.getElementById("projectTeam");n&&k.length>0&&(n.innerHTML=k.map(t=>`<option value="${t.id}">${t.name} (${t.initials})</option>`).join("")),te();const e=document.getElementById("taskProject");if(e&&m.length>0){const t=e.value,o=m.filter(a=>a.status!=="completed");e.innerHTML='<option value="">Select project</option>'+o.map(a=>`<option value="${a.id}">${a.title}</option>`).join(""),t&&(e.value=t)}}function ne(){const n=document.getElementById("projectsGrid");n.innerHTML=m.map(e=>`
            <div class="project-card">
                <div class="project-card-header">
                    <div>
                        <h3 class="project-title">${e.title}</h3>
                        <p class="project-client">${e.client}</p>
                    </div>
                    <span class="project-status-badge ${e.status}">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span>
                </div>
                <div class="project-meta">
                    <div class="project-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M9 11l3 3L22 4"/>
                            <path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7"/>
                        </svg>
                        ${e.completed}/${e.tasks} tasks
                    </div>
                    <div class="project-meta-item">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                            <polyline points="12 6 12 12 16 14"/>
                        </svg>
                        ${e.deadline}
                    </div>
                </div>
                <div class="project-progress-section">
                    <div class="project-progress-header">
                        <span class="project-progress-label">Progress</span>
                        <span class="project-progress-value">${e.progress}%</span>
                    </div>
                    <div class="project-progress-bar">
                        <div class="project-progress-fill" style="width: ${e.progress}%"></div>
                    </div>
                </div>
                <div class="project-team">
                    <span class="team-label">Team:</span>
                    ${e.team.map(t=>`<div class="team-avatar">${t}</div>`).join("")}
                </div>
                ${ue?`
                <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                    <button class="btn-secondary" onclick="openEditProjectModal(${e.id})" style="flex: 1; font-size: 0.875rem;">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                        Edit
                    </button>
                </div>
                `:""}
            </div>
        `).join("")}function E(){const n=document.getElementById("tasksTableBody"),e=document.getElementById("tasksCards");ke(),window.innerWidth>768?n.innerHTML=w.map(t=>{const o=t.assignedTo&&t.assignedTo.id===O,a=o&&t.status!=="done"&&t.progress<100,s=T[t.id]!==void 0;return`
                <tr>
                    <td>
                        <div style="font-weight: 500; color: var(--text-primary);">${t.title}</div>
                    </td>
                    <td>${t.project}</td>
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar">${t.assignedTo?t.assignedTo.initials:"--"}</div>
                            <span class="employee-name">${t.assignedTo?t.assignedTo.name:"Unassigned"}</span>
                        </div>
                    </td>
                    <td><span class="priority-badge ${t.priority}">${t.priority.charAt(0).toUpperCase()+t.priority.slice(1)}</span></td>
                    <td>${t.deadline||"--"}</td>
                    <td><span class="status-badge ${t.status}">${t.status.replace("-"," ").split(" ").map(i=>i.charAt(0).toUpperCase()+i.slice(1)).join(" ")}</span></td>
                    <td>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            ${s&&o?`
                            <span style="display: inline-block; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; animation: pulse 2s infinite;" title="Time tracking active"></span>
                            `:""}
                            <div class="progress-bar-inline">
                                <div class="progress-fill-inline" style="width: ${t.progress}%"></div>
                            </div>
                            <span style="font-size: 0.75rem; color: var(--text-secondary);">${t.progress}%</span>
                        </div>
                    </td>
                    <td>
                        <div class="table-actions">
                            ${s&&o?`
                            <button class="icon-btn" title="Stop Task" onclick="openStopTaskModal(${t.id})" style="color: #dc2626;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <rect x="9" y="9" width="6" height="6"/>
                                </svg>
                            </button>
                            `:a?`
                            <button class="icon-btn" title="Start Task" onclick="startTaskTimeTracking(${t.id})" style="color: var(--accent);">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <circle cx="12" cy="12" r="10"/>
                                    <polygon points="10 8 16 12 10 16 10 8"/>
                                </svg>
                            </button>
                            `:""}
                            ${K||o?`
                            <button class="icon-btn" title="Edit" onclick="openEditTaskModal(${t.id})">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                            `:""}
                            ${W?`
                            <button class="icon-btn" title="Delete" onclick="deleteTask(${t.id})" style="color: #dc2626;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                </svg>
                            </button>
                            `:""}
                        </div>
                    </td>
                </tr>
            `}).join(""):e.innerHTML=w.map(t=>{const o=t.assignedTo&&t.assignedTo.id===O,a=o&&t.status!=="done"&&t.progress<100,s=T[t.id]!==void 0;return`
                <div class="task-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title" style="display: flex; align-items: center; gap: 0.5rem;">
                                ${s&&o?`
                                <span style="display: inline-block; width: 8px; height: 8px; background: #dc2626; border-radius: 50%; animation: pulse 2s infinite;" title="Time tracking active"></span>
                                `:""}
                                ${t.title}
                            </div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${t.project}</div>
                        </div>
                        <span class="status-badge ${t.status}">${t.status.replace("-"," ").split(" ").map(i=>i.charAt(0).toUpperCase()+i.slice(1)).join(" ")}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Assigned To</span>
                            <span class="card-value">${t.assignedTo?t.assignedTo.name:"Unassigned"}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Priority</span>
                            <span class="card-value"><span class="priority-badge ${t.priority}">${t.priority.charAt(0).toUpperCase()+t.priority.slice(1)}</span></span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Deadline</span>
                            <span class="card-value">${t.deadline||"--"}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Progress</span>
                            <span class="card-value">${t.progress}%</span>
                        </div>
                    </div>
                    <div style="display: flex; gap: 0.5rem; margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border);">
                        ${s&&o?`
                        <button class="btn-secondary" onclick="openStopTaskModal(${t.id})" style="flex: 1; font-size: 0.875rem; color: #dc2626;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <circle cx="12" cy="12" r="10"/>
                                <rect x="9" y="9" width="6" height="6"/>
                            </svg>
                            Stop Task
                        </button>
                        `:a?`
                        <button class="btn-secondary" onclick="startTaskTimeTracking(${t.id})" style="flex: 1; font-size: 0.875rem;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <circle cx="12" cy="12" r="10"/>
                                <polygon points="10 8 16 12 10 16 10 8"/>
                            </svg>
                            Start Task
                        </button>
                        `:""}
                        ${K||o?`
                        <button class="btn-secondary" onclick="openEditTaskModal(${t.id})" style="flex: 1; font-size: 0.875rem;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                            Edit
                        </button>
                        `:""}
                        ${W?`
                        <button class="btn-secondary" onclick="deleteTask(${t.id})" style="flex: 1; font-size: 0.875rem; color: #dc2626;">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                                <polyline points="3 6 5 6 21 6"/>
                                <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            </svg>
                            Delete
                        </button>
                        `:""}
                </div>
                </div>
            `}).join("")}function ke(){const n=document.getElementById("tasksPaginationInfo"),e=document.getElementById("tasksPaginationNumbers"),t=document.getElementById("tasksPrevBtn"),o=document.getElementById("tasksNextBtn");if(!n||!e||!t||!o)return;const{current_page:a=1,last_page:s=1,from:i=0,to:c=0,total:h=0}=d,z=i||0,R=c||0,L=h||0;n.textContent=L>0?`Showing ${z} to ${R} of ${L} tasks`:"No results found",t.disabled=a===1,o.disabled=a===s||s===0;let r="";if(s>1){let l=Math.max(1,a-Math.floor(2.5)),g=Math.min(s,l+5-1);g-l<4&&(l=Math.max(1,g-5+1)),l>1&&(r+='<button class="pagination-number" data-page="1">1</button>',l>2&&(r+='<span class="pagination-number ellipsis">...</span>'));for(let f=l;f<=g;f++)r+=`<button class="pagination-number ${f===a?"active":""}" data-page="${f}">${f}</button>`;g<s&&(g<s-1&&(r+='<span class="pagination-number ellipsis">...</span>'),r+=`<button class="pagination-number" data-page="${s}">${s}</button>`)}e.innerHTML=r,e.querySelectorAll(".pagination-number:not(.ellipsis)").forEach(j=>{j.addEventListener("click",()=>{N(parseInt(j.dataset.page))})})}async function N(n){if(n<1||n>(d.last_page||1))return;const e=document.getElementById("taskProjectFilter")?.value||"all";await $(e,I,n),E()}function x(){const n=document.getElementById("timeTrackingTableBody"),e=document.getElementById("timeTrackingCards");Te(),window.innerWidth>768?n.innerHTML=H.map(t=>`
                <tr>
                    <td>${t.date}</td>
                    <td>${t.project}</td>
                    <td>${t.task}</td>
                    <td>
                        <div class="employee-cell">
                            <div class="employee-avatar">${t.employee.initials}</div>
                            <span class="employee-name">${t.employee.name}</span>
                        </div>
                    </td>
                    <td>${t.hours}</td>
                    <td>${t.description}</td>
                    <td>
                        <div class="table-actions">
                            <button class="icon-btn" title="Edit">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                    <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                                </svg>
                            </button>
                        </div>
                    </td>
                </tr>
            `).join(""):e.innerHTML=H.map(t=>`
                <div class="time-tracking-card">
                    <div class="card-header">
                        <div>
                            <div class="card-title">${t.task}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${t.project}</div>
                        </div>
                        <span style="font-weight: 600; color: var(--accent);">${t.hours}</span>
                    </div>
                    <div class="card-details">
                        <div class="card-detail">
                            <span class="card-label">Date</span>
                            <span class="card-value">${t.date}</span>
                        </div>
                        <div class="card-detail">
                            <span class="card-label">Employee</span>
                            <span class="card-value">${t.employee.name}</span>
                        </div>
                        <div class="card-detail" style="grid-column: 1 / -1;">
                            <span class="card-label">Description</span>
                            <span class="card-value">${t.description}</span>
                        </div>
                    </div>
                </div>
            `).join("")}function Te(){const n=document.getElementById("timeTrackingPaginationInfo"),e=document.getElementById("timeTrackingPaginationNumbers"),t=document.getElementById("timeTrackingPrevBtn"),o=document.getElementById("timeTrackingNextBtn");if(!n||!e||!t||!o)return;const{current_page:a=1,last_page:s=1,from:i=0,to:c=0,total:h=0}=v,z=i||0,R=c||0,L=h||0;n.textContent=L>0?`Showing ${z} to ${R} of ${L} entries`:"No results found",t.disabled=a===1,o.disabled=a===s||s===0;let r="";if(s>1){let l=Math.max(1,a-Math.floor(2.5)),g=Math.min(s,l+5-1);g-l<4&&(l=Math.max(1,g-5+1)),l>1&&(r+='<button class="pagination-number" data-page="1">1</button>',l>2&&(r+='<span class="pagination-number ellipsis">...</span>'));for(let f=l;f<=g;f++)r+=`<button class="pagination-number ${f===a?"active":""}" data-page="${f}">${f}</button>`;g<s&&(g<s-1&&(r+='<span class="pagination-number ellipsis">...</span>'),r+=`<button class="pagination-number" data-page="${s}">${s}</button>`)}e.innerHTML=r,e.querySelectorAll(".pagination-number:not(.ellipsis)").forEach(j=>{j.addEventListener("click",()=>{q(parseInt(j.dataset.page))})})}async function q(n){if(n<1||n>(v.last_page||1))return;const e=document.getElementById("timeProjectFilter")?.value||"all",t=document.getElementById("timeDateFilter")?.value||null;await C(e,t,n),x()}function Ee(){["taskProjectFilter","timeProjectFilter"].forEach(o=>{const a=document.getElementById(o);if(a){const s=a.value;a.innerHTML='<option value="all">All Projects</option>',m.forEach(i=>{const c=document.createElement("option");c.value=i.id,c.textContent=i.title,a.appendChild(c)}),s&&s!=="all"&&Array.from(a.options).some(i=>i.value===s)&&(a.value=s)}}),M();const e=document.getElementById("editTaskProject");if(e&&m.length>0){const o=e.value,a=m.filter(s=>s.status==="completed"?o&&s.id===parseInt(o):!0);e.innerHTML='<option value="">Select project</option>'+a.map(s=>`<option value="${s.id}">${s.title}</option>`).join(""),o&&(e.value=o)}V();const t=document.getElementById("editProjectTeam");if(t&&k.length>0){const o=Array.from(t.selectedOptions).map(a=>a.value);t.innerHTML=k.map(a=>`<option value="${a.id}">${a.name} (${a.initials})</option>`).join(""),Array.from(t.options).forEach(a=>{a.selected=o.includes(a.value)})}}function ae(){document.getElementById("projectModal").classList.add("active"),document.body.style.overflow="hidden",setTimeout(typeof B=="function"?()=>{B("projectClient","projectClientDropdown","projectClientId"),document.getElementById("projectTitle").focus()}:()=>{document.getElementById("projectTitle").focus()},100)}function _(){document.getElementById("projectModal").classList.remove("active"),document.body.style.overflow="",document.getElementById("projectForm").reset(),document.getElementById("projectClientId").value="";const e=document.getElementById("projectClientDropdown");e&&e.classList.remove("active")}document.getElementById("projectModal").addEventListener("click",function(n){n.target===this&&_()}),document.addEventListener("keydown",function(n){if(n.key==="Escape"){const e=document.getElementById("projectModal");e&&e.classList.contains("active")&&_();const t=document.getElementById("editProjectModal");t&&t.classList.contains("active")&&S()}});async function oe(n){if(!m.find(t=>t.id===n)){alert("Project not found");return}try{const o=await(await fetch(`${u}/projects/${n}`)).json();if(!o.success){alert("Error fetching project details");return}const a=o.data;if(document.getElementById("editProjectModal").classList.add("active"),document.body.style.overflow="hidden",M(),document.getElementById("editProjectId").value=a.id,document.getElementById("editProjectTitle").value=a.title,document.getElementById("editProjectClient").value=a.client_name||a.client||"",document.getElementById("editProjectClientId").value=a.client_id||"",document.getElementById("editProjectStatus").value=a.status,document.getElementById("editProjectDescription").value=a.description||"",a.deadline){const h=new Date(a.deadline).toISOString().split("T")[0];document.getElementById("editProjectDeadline").value=h}const i=document.getElementById("editProjectTeam");i&&a.team&&Array.isArray(a.team)&&Array.from(i.options).forEach(c=>{c.selected=a.team.includes(parseInt(c.value))}),setTimeout(typeof B=="function"?()=>{B("editProjectClient","editProjectClientDropdown","editProjectClientId"),document.getElementById("editProjectTitle").focus()}:()=>{document.getElementById("editProjectTitle").focus()},100)}catch(t){console.error("Error fetching project details:",t),alert("Error loading project details. Please try again.")}}function S(){document.getElementById("editProjectModal").classList.remove("active"),document.body.style.overflow="",document.getElementById("editProjectForm").reset(),document.getElementById("editProjectClientId").value="";const e=document.getElementById("editProjectClientDropdown");e&&e.classList.remove("active")}document.getElementById("editProjectModal")?.addEventListener("click",function(n){n.target===this&&S()});function se(){document.getElementById("taskModal").classList.add("active"),document.body.style.overflow="hidden",M(),setTimeout(()=>{const e=document.getElementById("taskProject");e&&m.length>0?e.focus():document.getElementById("taskTitle").focus()},100)}function A(){document.getElementById("taskModal").classList.remove("active"),document.body.style.overflow="",document.getElementById("taskForm").reset()}document.getElementById("taskModal")?.addEventListener("click",function(n){n.target===this&&A()}),document.addEventListener("keydown",function(n){if(n.key==="Escape"){const e=document.getElementById("taskModal");e&&e.classList.contains("active")&&A();const t=document.getElementById("editTaskModal");t&&t.classList.contains("active")&&D();const o=document.getElementById("stopTaskModal");o&&o.classList.contains("active")&&F()}});async function ie(n){const e=w.find(o=>o.id===n);if(!e){alert("Task not found");return}document.getElementById("editTaskModal").classList.add("active"),document.body.style.overflow="hidden",M(),document.getElementById("editTaskId").value=e.id,document.getElementById("editTaskTitle").value=e.title,document.getElementById("editTaskProject").value=e.project_id,document.getElementById("editTaskPriority").value=e.priority,document.getElementById("editTaskStatus").value=e.status,document.getElementById("editTaskDescription").value=e.description||"",document.getElementById("editTaskProgress").value=e.progress||0,document.getElementById("editTaskDeadline").value=e.deadline_raw||"",V(),e.assignedTo&&e.assignedTo.id?document.getElementById("editTaskAssignedTo").value=e.assignedTo.id:document.getElementById("editTaskAssignedTo").value="",setTimeout(()=>{document.getElementById("editTaskTitle").focus()},100)}function D(){document.getElementById("editTaskModal").classList.remove("active"),document.body.style.overflow="",document.getElementById("editTaskForm").reset()}document.getElementById("editTaskModal")?.addEventListener("click",function(n){n.target===this&&D()});async function ce(n){if(confirm("Are you sure you want to delete this task? This action cannot be undone."))try{const t=await(await fetch(`${u}/tasks/${n}`,{method:"DELETE",headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""}})).json();if(t.success){const o=document.getElementById("taskProjectFilter")?.value||"all";await $(o,I,d.current_page),await Q(),await Z(),E(),ne(),alert("Task deleted successfully!")}else alert("Error deleting task: "+(t.message||"Unknown error"))}catch(e){console.error("Error deleting task:",e),alert("Error deleting task. Please try again.")}}async function re(n){const e=w.find(o=>o.id===n);if(!e){alert("Task not found");return}if(!e.project_id){alert("Task must be associated with a project");return}const t=new Date;t.toISOString().split("T")[0],t.toTimeString().split(" ")[0];try{const a=await(await fetch(`${u}/time-tracking/active-record`)).json();if(a.success&&a.data&&a.data.length>0){if(a.data.find(h=>h.task_id===n)){alert("You already have an active time tracking session for this task. Please stop it first.");return}if(a.data.length>0&&!confirm("You already have an active time tracking session for another task. Do you want to stop it and start tracking this task?"))return}const i=await(await fetch(`${u}/time-tracking/start`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({project_id:e.project_id,task_id:n,description:`Working on task: ${e.title}`})})).json();if(i.success){if(e.status==="todo")try{await fetch(`${u}/tasks/${n}`,{method:"PUT",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||""},body:JSON.stringify({status:"in-progress"})})}catch(h){console.error("Error updating task status:",h)}alert("Time tracking started for this task!"),await ee();const c=document.getElementById("taskProjectFilter")?.value||"all";await $(c,I,d.current_page),E()}else alert("Error starting time tracking: "+(i.message||"Unknown error"))}catch(o){console.error("Error starting time tracking:",o),alert("Error starting time tracking. Please try again.")}}async function le(n){const e=w.find(a=>a.id===n);if(!e){alert("Task not found");return}const t=T[n];if(!t){alert("No active time tracking found for this task");return}document.getElementById("stopTaskModal").classList.add("active"),document.body.style.overflow="hidden",document.getElementById("stopTaskId").value=e.id,document.getElementById("stopTimeTrackingId").value=t.id,document.getElementById("stopTaskTitle").textContent=e.title,document.getElementById("stopTaskProject").textContent=e.project,document.getElementById("stopTaskCurrentProgress").textContent=e.progress||0,document.getElementById("stopTaskProgress").value=e.progress||0,document.getElementById("stopTaskNotes").value="",setTimeout(()=>{document.getElementById("stopTaskNotes").focus()},100)}function F(){document.getElementById("stopTaskModal").classList.remove("active"),document.body.style.overflow="",document.getElementById("stopTaskForm").reset()}document.getElementById("stopTaskModal")?.addEventListener("click",function(n){n.target===this&&F()});function de(){alert("Exporting time tracking data...")}window.addEventListener("resize",()=>{E(),x()});async function je(){await Promise.all([Q(),Z(),$(),C("all",null,1),fe(),ve()]),Ee(),ne(),E(),x()}je(),document.querySelectorAll(".status-tab-btn").forEach(n=>{n.addEventListener("click",async function(){document.querySelectorAll(".status-tab-btn").forEach(t=>t.classList.remove("active")),this.classList.add("active"),I=this.getAttribute("data-status");const e=document.getElementById("taskProjectFilter")?.value||"all";await $(e,I,1),E()})}),document.getElementById("taskProjectFilter")?.addEventListener("change",async function(){const n=this.value;await $(n,I,1),E()}),document.getElementById("timeProjectFilter")?.addEventListener("change",async function(){const n=this.value,e=document.getElementById("timeDateFilter")?.value||null;await C(n,e,1),x()}),document.getElementById("timeDateFilter")?.addEventListener("change",async function(){const n=this.value,e=document.getElementById("timeProjectFilter")?.value||"all";await C(e,n,1),x()}),document.getElementById("timeTrackingPrevBtn")?.addEventListener("click",()=>{v.current_page>1&&q(v.current_page-1)}),document.getElementById("timeTrackingNextBtn")?.addEventListener("click",()=>{v.current_page<v.last_page&&q(v.current_page+1)}),document.getElementById("taskProject")?.addEventListener("change",function(){te()}),document.getElementById("editTaskProject")?.addEventListener("change",function(){V()}),document.getElementById("tasksPrevBtn")?.addEventListener("click",()=>{d.current_page>1&&N(d.current_page-1)}),document.getElementById("tasksNextBtn")?.addEventListener("click",()=>{d.current_page<d.last_page&&N(d.current_page+1)}),document.addEventListener("DOMContentLoaded",function(){B("projectClient","projectClientDropdown","projectClientId"),B("editProjectClient","editProjectClientDropdown","editProjectClientId");const n=new URLSearchParams(window.location.search),e=n.get("tab"),t=n.get("project");if(e==="tasks"){const o=document.querySelector('.tab-btn[data-tab="tasks"]');o&&o.click(),t&&setTimeout(()=>{const a=document.getElementById("taskProjectFilter");a&&(a.value=t,a.dispatchEvent(new Event("change")))},500)}}),typeof S=="function"&&(window.closeEditProjectModal=S),typeof D=="function"&&(window.closeEditTaskModal=D),typeof _=="function"&&(window.closeProjectModal=_),typeof F=="function"&&(window.closeStopTaskModal=F),typeof A=="function"&&(window.closeTaskModal=A),typeof ce=="function"&&(window.deleteTask=ce),typeof de=="function"&&(window.exportTimeTracking=de),typeof oe=="function"&&(window.openEditProjectModal=oe),typeof ie=="function"&&(window.openEditTaskModal=ie),typeof ae=="function"&&(window.openProjectModal=ae),typeof le=="function"&&(window.openStopTaskModal=le),typeof se=="function"&&(window.openTaskModal=se),typeof re=="function"&&(window.startTaskTimeTracking=re)})();
