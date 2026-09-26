(function(){const ue=window.__clientManagementConfig||{};let B=[],f={},m=1;const A=10;let v=1,w=0;const d="/api/client-management";async function _(n=1,t="",e="all",o="all"){try{const a=new URLSearchParams({page:n,per_page:A});t&&a.append("search",t),e!=="all"&&a.append("status",e),o!=="all"&&a.append("industry",o);const l=await(await fetch(`${d}/clients?${a}`)).json();l.success?(B=l.data.map(c=>({...c,contactPerson:c.contact_person,initials:$(c.name)})),w=l.pagination.total,v=l.pagination.last_page,m=l.pagination.current_page,D()):(console.error("Failed to fetch clients:",l.message),alert("Failed to load clients: "+(l.message||"Unknown error")))}catch(a){console.error("Error fetching clients:",a),alert("Error loading clients. Please refresh the page.")}}async function pe(){try{const t=await(await fetch(`${d}/stats`)).json();t.success&&(f=t.data,ye())}catch(n){console.error("Error fetching stats:",n)}}function ye(){document.querySelector(".stat-card:nth-child(1) .stat-value").textContent=f.total_clients||0,document.querySelector(".stat-card:nth-child(2) .stat-value").textContent=f.active_clients||0,document.querySelector(".stat-card:nth-child(2) .stat-change").textContent=`${f.active_percentage||0}% of total`,document.querySelector(".stat-card:nth-child(3) .stat-value").textContent=f.new_this_month||0,document.querySelector(".stat-card:nth-child(3) .stat-change").textContent=`${f.growth_percentage||0}% growth`;const n=f.total_revenue||0,t=n>=1e6?`$${(n/1e6).toFixed(1)}M`:`$${Math.round(n).toLocaleString()}`;document.querySelector(".stat-card:nth-child(4) .stat-value").textContent=t}function $(n){const t=n.trim().split(" ");return t.length>=2?(t[0][0]+t[t.length-1][0]).toUpperCase():n.substring(0,2).toUpperCase()}function ve(){const n=document.getElementById("clientsTableBody"),t=B;n.innerHTML=t.map(e=>`
            <tr onclick="openClientModal(${e.id})">
                <td onclick="event.stopPropagation()"><input type="checkbox" class="table-checkbox" data-id="${e.id}"></td>
                <td>
                    <div class="client-cell">
                        <div class="client-avatar">${e.initials}</div>
                        <div class="client-info">
                            <div class="client-name">${e.name}</div>
                        </div>
                    </div>
                </td>
                <td>${e.contactPerson}</td>
                <td>${e.email}</td>
                <td>${e.phone}</td>
                <td>${e.industry}</td>
                <td><span class="status-badge ${e.status}">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span></td>
                <td><strong>$${e.revenue.toLocaleString()}</strong></td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openClientModal(${e.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                        <button class="icon-btn" title="Edit" onclick="event.stopPropagation(); editClientById(${e.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                                <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join("")}function fe(){const n=document.getElementById("clientsCards"),t=B;n.innerHTML=t.map(e=>`
            <div class="client-card" onclick="openClientModal(${e.id})">
                <div class="card-header">
                    <div class="card-main">
                        <input type="checkbox" class="table-checkbox" data-id="${e.id}" onclick="event.stopPropagation()">
                        <div class="client-avatar">${e.initials}</div>
                        <div>
                            <div class="client-name">${e.name}</div>
                            <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${e.contactPerson}</div>
                        </div>
                    </div>
                    <span class="status-badge ${e.status}">${e.status.charAt(0).toUpperCase()+e.status.slice(1)}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <span class="card-label">Email</span>
                        <span class="card-value">${e.email}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Phone</span>
                        <span class="card-value">${e.phone}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Industry</span>
                        <span class="card-value">${e.industry}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Revenue</span>
                        <span class="card-value">$${e.revenue.toLocaleString()}</span>
                    </div>
                </div>
            </div>
        `).join("")}function ge(){const n=document.getElementById("paginationInfo"),t=document.getElementById("paginationNumbers"),e=document.getElementById("prevBtn"),o=document.getElementById("nextBtn"),a=w>0?(m-1)*A+1:0,s=Math.min(m*A,w);n.textContent=w>0?`Showing ${a} to ${s} of ${w} results`:"No results found",e.disabled=m===1,o.disabled=m===v;let l="";const c=5;let p=Math.max(1,m-Math.floor(c/2)),y=Math.min(v,p+c-1);y-p<c-1&&(p=Math.max(1,y-c+1)),p>1&&(l+='<button class="pagination-number" data-page="1">1</button>',p>2&&(l+='<span class="pagination-number ellipsis">...</span>'));for(let i=p;i<=y;i++)l+=`<button class="pagination-number ${i===m?"active":""}" data-page="${i}">${i}</button>`;y<v&&(y<v-1&&(l+='<span class="pagination-number ellipsis">...</span>'),l+=`<button class="pagination-number" data-page="${v}">${v}</button>`),t.innerHTML=l,t.querySelectorAll(".pagination-number:not(.ellipsis)").forEach(i=>{i.addEventListener("click",()=>{g(parseInt(i.dataset.page))})})}function D(){window.innerWidth<=768?fe():ve(),ge()}document.getElementById("prevBtn").addEventListener("click",()=>{m>1&&g(m-1)}),document.getElementById("nextBtn").addEventListener("click",()=>{m<v&&g(m+1)});let R;document.getElementById("clientSearch")?.addEventListener("input",function(){clearTimeout(R),R=setTimeout(()=>{g(1)},500)}),document.getElementById("statusFilter")?.addEventListener("change",function(){O(this.value),g(1)}),document.getElementById("industryFilter")?.addEventListener("change",function(){g(1)});function O(n){document.querySelectorAll(".client-status-tab").forEach(e=>{e.classList.toggle("active",e.getAttribute("data-status")===n)});const t=document.getElementById("statusFilter");t&&(t.value=n)}document.querySelectorAll(".client-status-tab").forEach(n=>{n.addEventListener("click",function(){const t=this.getAttribute("data-status");O(t),g(1)})});function g(n=1){const t=document.getElementById("clientSearch")?.value||"",e=document.getElementById("statusFilter")?.value||"all",o=document.getElementById("industryFilter")?.value||"all";_(n,t,e,o)}document.getElementById("selectAllClients")?.addEventListener("change",function(){document.querySelectorAll(".table-checkbox:not(#selectAllClients)").forEach(t=>t.checked=this.checked)});async function V(n){r=n;try{const e=await(await fetch(`${d}/clients/${n}`)).json();if(!e.success){alert("Failed to load client data: "+(e.message||"Unknown error"));return}const o=e.data;document.getElementById("modalClientInitials").textContent=$(o.name),document.getElementById("modalClientName").textContent=o.name,document.getElementById("modalClientIndustry").textContent=o.industry||"N/A",document.getElementById("detailCompanyName").textContent=o.name||"N/A",document.getElementById("detailContactPerson").textContent=o.contact_person||"N/A",document.getElementById("detailEmail").textContent=o.email||"N/A",document.getElementById("detailPhone").textContent=o.phone||"N/A",document.getElementById("detailIndustry").textContent=o.industry||"N/A",document.getElementById("detailStatus").innerHTML=`<span class="status-badge ${o.status}">${o.status.charAt(0).toUpperCase()+o.status.slice(1)}</span>`;const a=document.getElementById("detailWebsite");if(o.website){let l=o.website;l.match(/^https?:\/\//i)||(l="https://"+l),a.innerHTML=`<a href="${l}" target="_blank" rel="noopener noreferrer">${o.website}</a>`}else a.textContent="N/A";document.getElementById("detailAddress").textContent=o.address||"N/A",document.getElementById("detailRevenue").textContent=`$${(parseFloat(o.revenue)||0).toLocaleString()}`,o.contacts&&Array.isArray(o.contacts)?z(o.contacts):(console.warn("Contacts data is missing or not an array:",o.contacts),z([])),o.employees&&Array.isArray(o.employees)?x(o.employees):x([]),o.projects&&Array.isArray(o.projects)?K(o.projects):K([]),o.notes&&Array.isArray(o.notes)&&o.notes.length>0?$e(o.notes):await M(n),await le(n);const s=document.getElementById("clientModal");s.querySelectorAll(".modal-tab").forEach((l,c)=>{c===0?l.classList.add("active"):l.classList.remove("active")}),s.querySelectorAll(".modal-tab-content").forEach((l,c)=>{c===0?l.classList.add("active"):l.classList.remove("active")}),s.classList.add("active"),document.body.style.overflow="hidden"}catch(t){console.error("Error loading client:",t),alert("Error loading client data. Please try again.")}}function b(){document.getElementById("clientModal").classList.remove("active"),document.body.style.overflow=""}document.getElementById("clientModal").addEventListener("click",function(n){n.target===this&&b()}),document.addEventListener("keydown",function(n){n.key==="Escape"&&(document.getElementById("addPortalUserModal").classList.contains("active")?S():document.getElementById("addProjectModal").classList.contains("active")?L():document.getElementById("addEmployeeModal").classList.contains("active")?C():document.getElementById("newClientModal").classList.contains("active")?P():b())}),document.querySelectorAll("#clientModal .modal-tab").forEach(n=>{n.addEventListener("click",function(){const t=this.dataset.tab,e=this.closest(".client-modal");e.querySelectorAll(".modal-tab").forEach(o=>o.classList.remove("active")),this.classList.add("active"),e.querySelectorAll(".modal-tab-content").forEach(o=>{o.classList.remove("active")}),e.querySelector(`#${t}Tab`).classList.add("active"),t==="notes"&&r&&M(r)})}),document.addEventListener("input",function(n){if(n.target.id==="notesTextarea"){const t=n.target.value.length,e=document.getElementById("noteCharCount");e&&(e.textContent=`${t} / 5000 characters`,t>4500?e.style.color="#ef4444":e.style.color="var(--text-muted)")}}),document.addEventListener("keydown",function(n){n.target.id==="notesTextarea"&&n.key==="Enter"&&!n.shiftKey&&(n.preventDefault(),T())});function z(n){const t=document.getElementById("contactsList");if(!t){console.error("contactsList element not found");return}if(!n||n.length===0){t.innerHTML=`
                <div class="empty-state">
                    <p>No contacts added yet.</p>
                </div>
            `;return}t.innerHTML=n.map(e=>`
                <div class="contact-item">
                    <div class="contact-avatar">${F(e.name)}</div>
                    <div class="contact-info">
                        <div class="contact-name">${e.name||"N/A"}</div>
                        ${e.role?`<div class="contact-role">${e.role}</div>`:""}
                        ${e.email?`<div class="contact-email">${e.email}</div>`:""}
                        ${e.phone?`<div class="contact-phone">${e.phone}</div>`:""}
                    </div>
                </div>
            `).join("")}function K(n){const t=document.getElementById("projectsListContainer"),e=document.getElementById("projectsEmptyState");if(t){if(t.querySelectorAll(".project-item").forEach(o=>o.remove()),!n||n.length===0){e.style.display="block";return}e.style.display="none",n.forEach(o=>{const a=document.createElement("div");a.className="project-item",a.setAttribute("data-project-id",o.id),a.addEventListener("click",function(){he(o.id)}),a.innerHTML=`
                <div class="project-header">
                    <div class="project-name">${o.title||"Untitled Project"}</div>
                    <span class="project-status-badge ${o.status}">${o.status.charAt(0).toUpperCase()+o.status.slice(1).replace("-"," ")}</span>
                </div>
                ${o.description?`<div class="project-description">${o.description}</div>`:""}
                <div class="project-meta">
                    <span>Deadline: ${o.deadline||"N/A"}</span>
                    ${o.progress!==void 0?`<span>Progress: ${o.progress}%</span>`:""}
                    ${o.tasks!==void 0?`<span>Tasks: ${o.completed||0}/${o.tasks||0}</span>`:""}
                </div>
                ${o.progress!==void 0?`
                    <div class="project-progress">
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${o.progress}%"></div>
                        </div>
                    </div>
                `:""}
            `,t.appendChild(a)})}}function L(){document.getElementById("addProjectModal").classList.remove("active"),document.getElementById("newProjectForm").reset()}document.getElementById("addProjectModal")?.addEventListener("click",function(n){n.target===this&&L()});function he(n){const t=new URL(ue.projectManagementUrl||"/project-management",window.location.origin);t.searchParams.set("tab","tasks"),t.searchParams.set("project",n),window.location.href=t.toString()}let r=null,U=[],X=[];function x(n){const t=document.getElementById("employeesListContainer"),e=document.getElementById("employeesEmptyState");if(t){if(t.querySelectorAll(".employee-item").forEach(o=>o.remove()),!n||n.length===0){e.style.display="block";return}e.style.display="none",n.forEach(o=>{const a=$(o.name),s=document.createElement("div");s.className="employee-item";const l=o.photo?`<img src="${o.photo}" alt="${o.name}" class="employee-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                   <div class="employee-avatar-fallback" style="display: none;">${a}</div>`:`<div class="employee-avatar-fallback">${a}</div>`,c=o.department?typeof o.department=="string"?o.department:o.department.name||o.department:null;s.innerHTML=`
                <div class="employee-avatar">
                    ${l}
                </div>
                <div class="employee-info">
                    <div class="employee-name">${o.name||"N/A"}</div>
                    ${o.email?`<div class="employee-email">${o.email}</div>`:""}
                    ${c?`<div class="employee-department">${c}</div>`:""}
                </div>
                <div class="employee-actions">
                    <button type="button" class="icon-btn" onclick="removeEmployeeFromClient(${o.id})" title="Remove employee">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            `,t.appendChild(s)})}}async function W(){if(r)try{const n=await fetch(`${d}/clients/${r}/available-employees`);if(!n.ok){const e=await n.text();console.error("HTTP Error:",n.status,e),alert(`Error loading employees: ${n.status===404?"Client not found.":n.status===403?"Access denied.":"Server error. Please try again."}`);return}const t=await n.json();if(t.success){const e=t.data.all_employees||[];X=t.data.assigned_ids||[],U=e.filter(o=>!X.includes(o.id)),Ee(U),document.getElementById("addEmployeeModal").classList.add("active"),document.getElementById("employeeSearchInput").value=""}else alert("Failed to load employees: "+(t.message||"Unknown error"))}catch(n){console.error("Error loading employees:",n),alert("Error loading employees. Please try again.")}}function C(){document.getElementById("addEmployeeModal").classList.remove("active"),U=[]}document.getElementById("addEmployeeModal")?.addEventListener("click",function(n){n.target===this&&C()});function Ee(n,t=""){const e=document.getElementById("employeesSelectList");if(!e)return;let o=n;if(t){const a=t.toLowerCase();o=n.filter(s=>s.name.toLowerCase().includes(a)||s.email&&s.email.toLowerCase().includes(a)||s.department&&s.department.toLowerCase().includes(a))}if(o.length===0){e.innerHTML='<div class="empty-state"><p>No employees found.</p></div>';return}e.innerHTML=o.map(a=>{const s=$(a.name),l=a.photo?`<img src="${a.photo}" alt="${a.name}" class="employee-select-avatar-img" onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                   <div class="employee-select-avatar-fallback" style="display: none;">${s}</div>`:`<div class="employee-select-avatar-fallback">${s}</div>`;return`
                <div class="employee-select-item" onclick="toggleEmployeeSelection(${a.id})">
                    <input type="checkbox" id="emp_${a.id}" value="${a.id}" onclick="event.stopPropagation()">
                    <div class="employee-select-avatar">
                        ${l}
                    </div>
                    <div class="employee-select-info">
                        <div class="employee-select-name">${a.name||"N/A"}</div>
                        ${a.email?`<div class="employee-select-email">${a.email}</div>`:""}
                    </div>
                </div>
            `}).join("")}function G(n){const t=document.getElementById(`emp_${n}`);t&&(t.checked=!t.checked)}async function J(){if(r)try{const n=await fetch(`${d}/clients/${r}`);if(!n.ok){console.error("HTTP Error reloading employee list:",n.status);return}const t=await n.json();if(t.success){const e=t.data;e.employees&&Array.isArray(e.employees)?x(e.employees):x([])}}catch(n){console.error("Error reloading employee list:",n)}}function we(){const n=document.getElementById("clientModal");n&&(n.querySelectorAll(".modal-tab").forEach(t=>{t.dataset.tab==="employees"?t.classList.add("active"):t.classList.remove("active")}),n.querySelectorAll(".modal-tab-content").forEach(t=>{t.id==="employeesTab"?t.classList.add("active"):t.classList.remove("active")}))}async function Q(){if(!r)return;const n=document.querySelectorAll('#employeesSelectList input[type="checkbox"]:checked:not(:disabled)'),t=Array.from(n).map(e=>parseInt(e.value));if(t.length===0){alert("Please select at least one employee to add.");return}try{const o=await(await fetch(`${d}/clients/${r}/assign-employees`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.content||""},body:JSON.stringify({employee_ids:t})})).json();o.success?(C(),await J(),we()):alert("Failed to assign employees: "+(o.message||"Unknown error"))}catch(e){console.error("Error assigning employees:",e),alert("Error assigning employees. Please try again.")}}async function Y(n){if(!r){alert("Client ID not found.");return}if(confirm("Are you sure you want to remove this employee from the client?"))try{const e=await(await fetch(`${d}/clients/${r}/remove-employee`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.content||""},body:JSON.stringify({user_id:n})})).json();e.success?await J():alert("Failed to remove employee: "+(e.message||"Unknown error"))}catch(t){console.error("Error removing employee:",t),alert("Error removing employee. Please try again.")}}function $e(n){const t=document.getElementById("notesList");if(t){if(!n||n.length===0){t.innerHTML=`
                <div class="empty-state">
                    <p>No notes added yet. Add a note below.</p>
                </div>
            `;return}t.innerHTML=n.map(e=>{const o=e.user?e.user.name:e.author||"Unknown",a=e.created_at?new Date(e.created_at).toLocaleDateString("en-US",{month:"short",day:"numeric",year:"numeric"}):"",s=e.time_ago||a;return`
                <div class="note-item" data-note-id="${e.id}">
                    <div class="note-text">${k(e.note)}</div>
                    <div class="note-meta">
                        <span>${k(o)}</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>${s}</span>
                            <button type="button" class="icon-btn" onclick="deleteNote(${e.id}, ${r})" title="Delete note" style="width: 24px; height: 24px; padding: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `}).join("")}}async function M(n){if(n)try{const e=await(await fetch(`${d}/clients/${n}/notes`)).json(),o=document.getElementById("notesList");if(!o)return;if(!e.success||!e.data||e.data.length===0){o.innerHTML=`
                    <div class="empty-state">
                        <p>No notes added yet. Add a note below.</p>
                    </div>
                `;return}o.innerHTML=e.data.map(a=>`
                <div class="note-item" data-note-id="${a.id}">
                    <div class="note-text">${k(a.note)}</div>
                    <div class="note-meta">
                        <span>${k(a.author)}</span>
                        <div style="display: flex; align-items: center; gap: 0.5rem;">
                            <span>${a.time_ago}</span>
                            <button type="button" class="icon-btn" onclick="deleteNote(${a.id}, ${n})" title="Delete note" style="width: 24px; height: 24px; padding: 0;">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 14px; height: 14px;">
                                    <polyline points="3 6 5 6 21 6"/>
                                    <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                                    <line x1="10" y1="11" x2="10" y2="17"/>
                                    <line x1="14" y1="11" x2="14" y2="17"/>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            `).join("")}catch(t){console.error("Error fetching notes:",t);const e=document.getElementById("notesList");e&&(e.innerHTML=`
                    <div class="empty-state">
                        <p>Error loading notes. Please try again.</p>
                    </div>
                `)}}async function T(){if(!r){alert("Client ID not found.");return}const n=document.getElementById("notesTextarea"),t=n.value.trim();if(!t){alert("Please enter a note."),n.focus();return}if(t.length>5e3){alert("Note is too long. Maximum 5000 characters allowed."),n.focus();return}const e=document.querySelector("#notesTab button.btn-primary"),o=e?e.innerHTML:"Add Note";e&&(e.disabled=!0,e.innerHTML="Adding...");try{const s=await(await fetch(`${d}/clients/${r}/notes`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.content||""},body:JSON.stringify({note:t})})).json();s.success?(n.value="",await M(r)):alert("Error adding note: "+(s.message||"Unknown error"))}catch(a){console.error("Error adding note:",a),alert("Error adding note. Please try again.")}finally{e&&(e.disabled=!1,e.innerHTML=o)}}async function Z(n,t){if(confirm("Are you sure you want to delete this note?"))try{const o=await(await fetch(`${d}/clients/${t}/notes/${n}`,{method:"DELETE",headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]')?.content||""}})).json();o.success?await M(t):alert("Error deleting note: "+(o.message||"Unknown error"))}catch(e){console.error("Error deleting note:",e),alert("Error deleting note. Please try again.")}}function k(n){const t=document.createElement("div");return t.textContent=n,t.innerHTML}let N=null,u=[];function ee(){N=null,u=[],document.getElementById("newClientModalTitle").textContent="New Client",document.getElementById("submitBtnText").textContent="Create Client",document.getElementById("newClientForm").reset(),document.getElementById("clientStatus").value="active",document.querySelectorAll("#newClientModal .modal-tab").forEach((n,t)=>{t===0?n.classList.add("active"):n.classList.remove("active")}),document.querySelectorAll("#newClientModal .modal-tab-content").forEach((n,t)=>{t===0?n.classList.add("active"):n.classList.remove("active")}),I(),E(),te()}function te(){document.getElementById("newClientModal").classList.add("active"),document.body.style.overflow="hidden"}function P(){document.getElementById("newClientModal").classList.remove("active"),document.body.style.overflow="",N=null,u=[],document.getElementById("newClientForm").reset(),I(),E()}document.querySelectorAll("#newClientModal .modal-tab").forEach(n=>{n.addEventListener("click",function(){const t=this.dataset.tab;document.querySelectorAll("#newClientModal .modal-tab").forEach(e=>e.classList.remove("active")),this.classList.add("active"),document.querySelectorAll("#newClientModal .modal-tab-content").forEach(e=>{e.classList.remove("active")}),document.getElementById(t+"Tab").classList.add("active")})});function F(n){const t=n.trim().split(" ");return t.length>=2?(t[0][0]+t[t.length-1][0]).toUpperCase():n.substring(0,2).toUpperCase()}function E(){const n=u.length,t=document.getElementById("contactsCountText");t&&(t.textContent=n===1?"1 contact added":`${n} contacts added`)}function q(){document.getElementById("contactName").value="",document.getElementById("contactRole").value="",document.getElementById("contactEmailInput").value="",document.getElementById("contactPhoneInput").value="",document.getElementById("contactName").focus()}function ne(n=!1){const t=document.getElementById("contactName").value.trim(),e=document.getElementById("contactRole").value.trim(),o=document.getElementById("contactEmailInput").value.trim(),a=document.getElementById("contactPhoneInput").value.trim();if(!t){alert("Please enter a contact name"),document.getElementById("contactName").focus();return}const s={id:Date.now(),name:t,role:e||"",email:o||"",phone:a||"",initials:F(t)};u.push(s),I(),E(),n?(document.getElementById("contactName").value="",document.getElementById("contactName").focus()):q()}function oe(n){confirm("Are you sure you want to remove this contact?")&&(u=u.filter(t=>t.id!==n),I(),E())}function I(){const n=document.getElementById("contactsListContainer"),t=document.getElementById("contactsEmptyState");if(u.length===0){t.style.display="block",n.querySelectorAll(".contact-item-form").forEach(e=>e.remove()),E();return}t.style.display="none",n.querySelectorAll(".contact-item-form").forEach(e=>e.remove()),u.forEach(e=>{const o=document.createElement("div");o.className="contact-item-form",o.innerHTML=`
                <div class="contact-avatar">${e.initials}</div>
                <div class="contact-info">
                    <div class="contact-name">${e.name}</div>
                    ${e.role?`<div class="contact-role">${e.role}</div>`:""}
                    ${e.email?`<div class="contact-email">${e.email}</div>`:""}
                    ${e.phone?`<div class="contact-phone">${e.phone}</div>`:""}
                </div>
                <div class="contact-actions">
                    <button type="button" class="icon-btn" onclick="removeContactFromList(${e.id})" title="Remove contact">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                            <line x1="10" y1="11" x2="10" y2="17"/>
                            <line x1="14" y1="11" x2="14" y2="17"/>
                        </svg>
                    </button>
                </div>
            `,n.insertBefore(o,t.nextSibling)})}document.getElementById("newClientModal")?.addEventListener("click",function(n){n.target===this&&P()});async function j(n){try{const e=await(await fetch(`${d}/clients/${n}`)).json();if(!e.success){alert("Failed to load client data: "+(e.message||"Unknown error"));return}const o=e.data;document.getElementById("clientModal").classList.contains("active")&&b(),N=o.id,document.getElementById("newClientModalTitle").textContent="Edit Client",document.getElementById("submitBtnText").textContent="Update Client",document.getElementById("clientName").value=o.name||"",document.getElementById("contactPerson").value=o.contact_person||"",document.getElementById("contactEmail").value=o.email||"",document.getElementById("contactPhone").value=o.phone||"",document.getElementById("clientIndustry").value=o.industry||"",document.getElementById("clientStatus").value=o.status||"active",document.getElementById("clientWebsite").value=o.website||"",document.getElementById("clientRevenue").value=o.revenue||0,document.getElementById("clientAddress").value=o.address||"",u=(o.contacts||[]).map(a=>({id:a.id,name:a.name,role:a.role||"",email:a.email||"",phone:a.phone||"",initials:F(a.name)})),I(),E(),document.querySelectorAll("#newClientModal .modal-tab").forEach((a,s)=>{s===0?a.classList.add("active"):a.classList.remove("active")}),document.querySelectorAll("#newClientModal .modal-tab-content").forEach((a,s)=>{s===0?a.classList.add("active"):a.classList.remove("active")}),te()}catch(t){console.error("Error loading client:",t),alert("Error loading client data. Please try again.")}}function ae(){if(!document.getElementById("clientModal").classList.contains("active"))return;const t=document.getElementById("modalClientName").textContent,e=B.find(o=>o.name===t);e&&j(e.id)}async function se(){try{const n=document.getElementById("statusFilter")?.value||"all",t=document.getElementById("industryFilter")?.value||"all",e=new URLSearchParams;n!=="all"&&e.append("status",n),t!=="all"&&e.append("industry",t);const a=await(await fetch(`${d}/export?${e}`)).json();if(a.success){const l=[["Name","Contact Person","Email","Phone","Industry","Status","Website","Revenue","Address"].join(","),...a.data.map(i=>[`"${i.name}"`,`"${i.contact_person}"`,`"${i.email}"`,`"${i.phone||""}"`,`"${i.industry||""}"`,`"${i.status}"`,`"${i.website||""}"`,i.revenue||0,`"${(i.address||"").replace(/"/g,'""')}"`].join(","))].join(`
`),c=new Blob([l],{type:"text/csv"}),p=window.URL.createObjectURL(c),y=document.createElement("a");y.href=p,y.download=`clients_export_${new Date().toISOString().split("T")[0]}.csv`,y.click(),window.URL.revokeObjectURL(p)}else alert("Failed to export clients: "+(a.message||"Unknown error"))}catch(n){console.error("Error exporting clients:",n),alert("Error exporting clients. Please try again.")}}window.addEventListener("resize",D);let h=[];async function le(n){try{const e=await(await fetch(`${d}/clients/${n}/users`)).json();e.success?(h=e.data||[],H()):(console.error("Failed to load portal users:",e.message),h=[],H())}catch(t){console.error("Error loading portal users:",t),h=[],H()}}function H(){const n=document.getElementById("portalUsersListContainer"),t=document.getElementById("portalUsersEmptyState");if(n){if(n.querySelectorAll(".portal-user-item").forEach(e=>e.remove()),!h||h.length===0){t&&(t.style.display="block");return}t&&(t.style.display="none"),h.forEach(e=>{const o=$(e.name),a=document.createElement("div");a.className="portal-user-item",a.innerHTML=`
                <div class="portal-user-avatar">${o}</div>
                <div class="portal-user-info">
                    <div class="portal-user-name">${e.name}</div>
                    <div class="portal-user-email">${e.email}</div>
                    <div class="portal-user-meta">
                        ${e.position?`<span class="portal-user-position">${e.position}</span>`:""}
                        ${e.phone?`<span>${e.phone}</span>`:""}
                        <span class="portal-user-status ${e.status}">${e.status}</span>
                    </div>
                </div>
                <div class="portal-user-actions">
                    <button class="btn-secondary btn-small" onclick="editPortalUser(${e.id})" title="Edit">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/>
                            <path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>
                        </svg>
                    </button>
                    <button class="btn-danger btn-small" onclick="deletePortalUser(${e.id})" title="Delete">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="width: 16px; height: 16px;">
                            <polyline points="3 6 5 6 21 6"/>
                            <path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                        </svg>
                    </button>
                </div>
            `,n.appendChild(a)})}}function ie(){document.getElementById("portalUserModalTitle").textContent="Add Portal User",document.getElementById("submitPortalUserBtnText").textContent="Create Portal User",document.getElementById("portalUserForm").reset(),document.getElementById("portalUserId").value="",document.getElementById("portalUserPassword").required=!0,document.getElementById("passwordRequired").style.display="inline",document.getElementById("passwordHint").textContent="Minimum 8 characters",document.getElementById("portalUserStatusGroup").style.display="none",document.getElementById("addPortalUserModal").classList.add("active"),document.body.style.overflow="hidden"}function S(){document.getElementById("addPortalUserModal").classList.remove("active"),document.body.style.overflow="hidden"}function re(n){const t=h.find(e=>e.id===n);t&&(document.getElementById("portalUserModalTitle").textContent="Edit Portal User",document.getElementById("submitPortalUserBtnText").textContent="Update Portal User",document.getElementById("portalUserId").value=t.id,document.getElementById("portalUserName").value=t.name,document.getElementById("portalUserEmail").value=t.email,document.getElementById("portalUserPhone").value=t.phone||"",document.getElementById("portalUserPosition").value=t.position||"",document.getElementById("portalUserPassword").value="",document.getElementById("portalUserPassword").required=!1,document.getElementById("passwordRequired").style.display="none",document.getElementById("passwordHint").textContent="Leave blank to keep current password",document.getElementById("portalUserStatus").value=t.status,document.getElementById("portalUserStatusGroup").style.display="block",document.getElementById("addPortalUserModal").classList.add("active"),document.body.style.overflow="hidden")}async function ce(n){if(confirm("Are you sure you want to delete this portal user? They will no longer be able to access the client portal."))try{const e=await(await fetch(`${d}/clients/${r}/users/${n}`,{method:"DELETE",headers:{"X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content,Accept:"application/json"}})).json();e.success?(await le(r),alert("Portal user deleted successfully!")):alert("Error: "+(e.message||"Failed to delete portal user"))}catch(t){console.error("Error deleting portal user:",t),alert("Error deleting portal user. Please try again.")}}function de(){const n=document.getElementById("portalLoginUrl").textContent;navigator.clipboard.writeText(n).then(()=>{const t=event.target.closest(".btn-icon"),e=t.title;t.title="Copied!",t.style.color="#10b981",setTimeout(()=>{t.title=e,t.style.color=""},2e3)}).catch(t=>{console.error("Failed to copy:",t);const e=document.createElement("textarea");e.value=n,document.body.appendChild(e),e.select(),document.execCommand("copy"),document.body.removeChild(e),alert("URL copied to clipboard!")})}function me(){const n=document.getElementById("portalUserPassword"),t=document.getElementById("portalPasswordEye");n.type==="password"?(n.type="text",t.innerHTML=`
                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                <line x1="1" y1="1" x2="23" y2="23"/>
            `):(n.type="password",t.innerHTML=`
                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                <circle cx="12" cy="12" r="3"/>
            `)}document.getElementById("addPortalUserModal").addEventListener("click",function(n){n.target===this&&S()}),document.addEventListener("DOMContentLoaded",async function(){await Promise.all([_(1),pe()])}),typeof ne=="function"&&(window.addContactToList=ne),typeof T=="function"&&(window.addNote=T),typeof Q=="function"&&(window.assignSelectedEmployees=Q),typeof q=="function"&&(window.clearContactForm=q),typeof C=="function"&&(window.closeAddEmployeeModal=C),typeof S=="function"&&(window.closeAddPortalUserModal=S),typeof L=="function"&&(window.closeAddProjectModal=L),typeof b=="function"&&(window.closeClientModal=b),typeof P=="function"&&(window.closeNewClientModal=P),typeof de=="function"&&(window.copyPortalUrl=de),typeof ee=="function"&&(window.createClient=ee),typeof Z=="function"&&(window.deleteNote=Z),typeof ce=="function"&&(window.deletePortalUser=ce),typeof ae=="function"&&(window.editClient=ae),typeof j=="function"&&(window.editClientById=j),typeof re=="function"&&(window.editPortalUser=re),typeof se=="function"&&(window.exportClients=se),typeof W=="function"&&(window.openAddEmployeeModal=W),typeof ie=="function"&&(window.openAddPortalUserModal=ie),typeof V=="function"&&(window.openClientModal=V),typeof oe=="function"&&(window.removeContactFromList=oe),typeof Y=="function"&&(window.removeEmployeeFromClient=Y),typeof stopPropagation=="function"&&(window.stopPropagation=stopPropagation),typeof G=="function"&&(window.toggleEmployeeSelection=G),typeof me=="function"&&(window.togglePortalUserPassword=me)})();
