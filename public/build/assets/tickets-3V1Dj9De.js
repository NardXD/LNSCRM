(function(){const h=(window.__ticketsConfig||{}).apiBase||"/api/tickets",k=document.querySelector('meta[name="csrf-token"]')?.getAttribute("content")||"";let w=[],l=1,E={last_page:1,total:0};const $=10;let M="open",P="all",B="",A="all",f=null,j=null;function N(){return w}async function v(){try{const e=new URLSearchParams({page:l,per_page:$,status:M,priority:A});P==="assigned-to-me"&&e.set("assigned_to_me","1"),B&&e.set("search",B);const t=await(await fetch(`${h}?${e}`)).json();if(!t.success)throw new Error(t.message||"Failed to load tickets");w=t.data,E=t.pagination,t.stats&&K(t.stats),x()}catch(e){console.error(e),w=[],x()}}function K(e){const s=(t,a)=>{const i=document.getElementById(t);i&&(i.textContent=a??0)};s("tabCountOpen",e.open),s("tabCountInProgress",e.in_progress),s("tabCountPending",e.pending),s("tabCountResolved",e.resolved),s("tabCountClosed",e.closed)}function Y(){const e=document.getElementById("ticketsTableBody"),s=N();if(!s.length){e.innerHTML='<tr><td colspan="9" class="empty-state">No tickets found.</td></tr>';return}e.innerHTML=s.map(t=>`
            <tr class="is-clickable" onclick="openTicketModal(${t.id})">
                <td><strong>${t.ticketId}</strong></td>
                <td>${t.subject}</td>
                <td>${t.client}</td>
                <td>
                    <div class="employee-cell">
                        <div class="employee-avatar">${t.assignedTo.initials}</div>
                        <span>${t.assignedTo.name}</span>
                    </div>
                </td>
                <td><span class="priority-badge ${t.priority}">${t.priority.charAt(0).toUpperCase()+t.priority.slice(1)}</span></td>
                <td><span class="status-badge ${t.status}">${t.status.replace("-"," ").split(" ").map(a=>a.charAt(0).toUpperCase()+a.slice(1)).join(" ")}</span></td>
                <td><span class="sla-badge ${t.sla}">${t.sla.charAt(0).toUpperCase()+t.sla.slice(1)}</span></td>
                <td>${t.created}</td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openTicketModal(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                        </button>
                    </div>
                </td>
            </tr>
        `).join("")}function W(){const e=document.getElementById("ticketsCards"),s=N();if(!s.length){e.innerHTML='<div class="empty-state">No tickets found.</div>';return}e.innerHTML=s.map(t=>`
            <div class="ticket-card" onclick="openTicketModal(${t.id})">
                <div class="card-header">
                    <div>
                        <div class="card-title">${t.ticketId}</div>
                        <div style="font-size: 0.75rem; color: var(--text-muted); margin-top: 0.25rem;">${t.subject}</div>
                    </div>
                    <span class="status-badge ${t.status}">${t.status.replace("-"," ").split(" ").map(a=>a.charAt(0).toUpperCase()+a.slice(1)).join(" ")}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail">
                        <span class="card-label">Client</span>
                        <span class="card-value">${t.client}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Assigned To</span>
                        <span class="card-value">${t.assignedTo.name}</span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">Priority</span>
                        <span class="card-value"><span class="priority-badge ${t.priority}">${t.priority.charAt(0).toUpperCase()+t.priority.slice(1)}</span></span>
                    </div>
                    <div class="card-detail">
                        <span class="card-label">SLA</span>
                        <span class="card-value"><span class="sla-badge ${t.sla}">${t.sla.charAt(0).toUpperCase()+t.sla.slice(1)}</span></span>
                    </div>
                </div>
            </div>
        `).join("")}function X(){const e=E.last_page||1,s=E.total||0,t=document.getElementById("paginationInfo"),a=document.getElementById("paginationNumbers"),i=document.getElementById("prevBtn"),d=document.getElementById("nextBtn"),o=s?(l-1)*$+1:0,c=Math.min(l*$,s);t.textContent=s?`Showing ${o}–${c} of ${s}`:"Showing 0 of 0",i.disabled=l===1,d.disabled=l>=e;let r="";const p=5;let m=Math.max(1,l-Math.floor(p/2)),y=Math.min(e,m+p-1);y-m<p-1&&(m=Math.max(1,y-p+1)),m>1&&(r+='<button class="pagination-number" data-page="1">1</button>',m>2&&(r+='<span class="pagination-number ellipsis">...</span>'));for(let u=m;u<=y;u++)r+=`<button class="pagination-number ${u===l?"active":""}" data-page="${u}">${u}</button>`;y<e&&(y<e-1&&(r+='<span class="pagination-number ellipsis">...</span>'),r+=`<button class="pagination-number" data-page="${e}">${e}</button>`),a.innerHTML=r,a.querySelectorAll(".pagination-number:not(.ellipsis)").forEach(u=>{u.addEventListener("click",()=>{l=parseInt(u.dataset.page),v()})})}function x(){window.innerWidth<=768?W():Y(),X()}document.getElementById("nextBtn").addEventListener("click",()=>{l<E.last_page&&(l++,v())}),document.getElementById("prevBtn").addEventListener("click",()=>{l>1&&(l--,v())}),document.getElementById("ticketSearch").addEventListener("input",()=>{clearTimeout(j),j=setTimeout(()=>{B=document.getElementById("ticketSearch").value.trim(),l=1,v()},300)}),document.getElementById("priorityFilter").addEventListener("change",function(){A=this.value,l=1,v()}),document.querySelectorAll(".view-submenu-tab").forEach(e=>{e.addEventListener("click",()=>{document.querySelectorAll(".view-submenu-tab").forEach(s=>s.classList.remove("active")),e.classList.add("active"),P=e.dataset.view,l=1,v()})}),document.querySelectorAll(".status-tab").forEach(e=>{e.addEventListener("click",()=>{document.querySelectorAll(".status-tab").forEach(s=>s.classList.remove("active")),e.classList.add("active"),M=e.dataset.status,l=1,v()})});async function U(e){f=e;const s=w.find(t=>t.id===e);s&&_(s),document.getElementById("ticketModal").classList.add("active"),document.body.style.overflow="hidden";try{const a=await(await fetch(`${h}/${e}`)).json();a.success&&a.data&&_(a.data)}catch(t){console.error(t)}}function _(e){const s=n=>(n||"").replace("-"," ").split(" ").map(g=>(g||"").charAt(0).toUpperCase()+(g||"").slice(1)).join(" "),t=n=>document.getElementById(n),a=(n,g)=>{const V=t(n);V&&g(V)};a("modalTicketId",n=>n.textContent="#"+(e.ticketId||e.ticket_number||"")),a("modalTicketSubject",n=>n.textContent=e.subject||""),a("modalTicketClient",n=>n.textContent="Client: "+(e.client||"")),a("modalTicketDate",n=>n.textContent="Created: "+(e.created_at||e.created||"")),a("modalDescription",n=>n.textContent=e.description||""),a("modalStatus",n=>n.value=e.status||"open"),a("sidebarPriority",n=>{n.textContent=s(e.priority),n.className=`priority-badge ${e.priority||"medium"}`}),a("sidebarStatus",n=>{n.textContent=s(e.status),n.className=`status-badge ${e.status||"open"}`}),a("sidebarCategory",n=>n.textContent=e.category?s(e.category):"—");const i=t("ticketAttachmentSection"),d=t("ticketAttachmentImg");e.image_url&&i&&d?(i.style.display="block",d.src=e.image_url):i&&(i.style.display="none");const o=e.assignedTo||{name:"Unassigned",initials:"—"};a("sidebarAssignedTo",n=>n.innerHTML=`<div class="employee-cell"><div class="employee-avatar">${o.initials||"—"}</div><span>${o.name||"Unassigned"}</span></div>`);const c=t("commentsList");c&&(e.comments?c.innerHTML=e.comments.map(n=>`
                <div class="comment-item">
                    <div class="comment-avatar">${n.initials||n.author?.slice(0,2).toUpperCase()||"—"}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">${n.author||"User"}</span>
                            <span class="comment-time">${n.time||""}</span>
                        </div>
                        <div class="comment-text">${n.text||n.content||""}</div>
                    </div>
                </div>
            `).join(""):c.innerHTML="");const r=e.sla_tracking||{},p=r.response||{status:e.sla||"compliant",text:"—"},m=r.resolution||{status:e.sla||"compliant",text:"—"},y=n=>n==="compliant"?100:n==="warning"?65:30,u=n=>n==="warning"?"At Risk":(n||"compliant").charAt(0).toUpperCase()+(n||"").slice(1);a("slaResponseStatus",n=>{n.textContent=u(p.status),n.className=`sla-status ${p.status||"compliant"}`}),a("slaResponseText",n=>n.textContent=p.text||"—"),a("slaResponseFill",n=>{n.style.width=y(p.status)+"%",n.className=`sla-fill ${p.status||"compliant"}`}),a("slaResolutionStatus",n=>{n.textContent=u(m.status),n.className=`sla-status ${m.status||"compliant"}`}),a("slaResolutionText",n=>n.textContent=m.text||"—"),a("slaResolutionFill",n=>{n.style.width=y(m.status)+"%",n.className=`sla-fill ${m.status||"compliant"}`});const q=t("activityList");if(q){const n=e.activities||[];q.innerHTML=n.length?n.map(g=>`
                <div class="activity-item">
                    <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"/>
                    </svg>
                    <div class="activity-text">${g.text||""}</div>
                    <div class="activity-time">${g.time||""}</div>
                </div>
            `).join(""):'<div class="activity-empty">No activity yet</div>'}const C=["resolved","closed"].includes(e.status||""),D=t("modalStatus"),J=t("commentTextarea"),G=t("addCommentBtn"),z=t("commentInputSection");D&&(D.disabled=C),J&&(J.disabled=C),G&&(G.disabled=C),z&&z.classList.toggle("ticket-readonly",C)}function b(){document.getElementById("ticketModal").classList.remove("active"),document.body.style.overflow=""}function L(e){const s=e?.querySelector("img")||document.getElementById("ticketAttachmentImg"),t=document.getElementById("imagePopupOverlay"),a=document.getElementById("imagePopupImg");s?.src&&t&&a&&(a.src=s.src,t.classList.add("visible"),document.body.style.overflow="hidden",document.addEventListener("keydown",R))}function S(){const e=document.getElementById("imagePopupOverlay");e&&(e.classList.remove("visible"),document.body.style.overflow="",document.removeEventListener("keydown",R))}function R(e){e.key==="Escape"&&S()}document.getElementById("ticketAttachmentClickable")?.addEventListener("keydown",function(e){(e.key==="Enter"||e.key===" ")&&(e.preventDefault(),L(this))}),document.getElementById("ticketModal").addEventListener("click",function(e){e.target===this&&b()}),document.getElementById("modalStatus").addEventListener("change",async function(){if(!(!f||this.disabled))try{if((await(await fetch(`${h}/${f}`,{method:"PUT",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":k},body:JSON.stringify({status:this.value})})).json()).success){const t=w.find(a=>a.id===f);t&&(t.status=this.value),document.getElementById("sidebarStatus").textContent=this.value.replace("-"," ").split(" ").map(a=>a.charAt(0).toUpperCase()+a.slice(1)).join(" "),document.getElementById("sidebarStatus").className=`status-badge ${this.value}`}}catch(e){console.error(e)}}),document.addEventListener("keydown",function(e){e.key==="Escape"&&(b(),I())});function I(){document.getElementById("newTicketModal").classList.remove("active"),document.body.style.overflow=document.getElementById("ticketModal").classList.contains("active")?"hidden":""}document.getElementById("newTicketModal").addEventListener("click",function(e){e.target===this&&I()});async function F(){const e=document.getElementById("commentTextarea"),s=e.value.trim();if(!(!s||!f))try{const a=await(await fetch(`${h}/${f}/comments`,{method:"POST",headers:{"Content-Type":"application/json","X-CSRF-TOKEN":k,Accept:"application/json"},body:JSON.stringify({content:s})})).json();if(a.success&&a.data){const i=a.data,d=document.getElementById("commentsList"),o=document.createElement("div");o.className="comment-item",o.innerHTML=`
                    <div class="comment-avatar">${i.initials||"ME"}</div>
                    <div class="comment-content">
                        <div class="comment-header">
                            <span class="comment-author">${i.author||"You"}</span>
                            <span class="comment-time">${i.created_at||"Just now"}</span>
                        </div>
                        <div class="comment-text">${i.content}</div>
                    </div>
                `,d.appendChild(o),e.value="";const c=document.getElementById("activityList");if(c&&!c.querySelector(".activity-empty")){const r=document.createElement("div");r.className="activity-item",r.innerHTML=`
                        <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="12" cy="12" r="10"/>
                        </svg>
                        <div class="activity-text">${i.author||"You"} commented</div>
                        <div class="activity-time">${i.created_at||"Just now"}</div>
                    `,c.insertBefore(r,c.firstChild)}else c&&(c.innerHTML=`
                        <div class="activity-item">
                            <svg class="activity-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="12" cy="12" r="10"/>
                            </svg>
                            <div class="activity-text">${i.author||"You"} commented</div>
                            <div class="activity-time">${i.created_at||"Just now"}</div>
                        </div>
                    `)}}catch(t){console.error(t)}}async function H(){document.getElementById("newTicketModal").classList.add("active"),document.body.style.overflow="hidden";const e=document.getElementById("newTicketClient"),s=document.getElementById("newTicketAssignedTo");e.innerHTML='<option value="">Select client</option>',s.innerHTML='<option value="">Unassigned</option>';try{const a=await(await fetch(`${h}/form-data`)).json();if(a.success&&a.data){const{clients:i,employees:d}=a.data;if(i&&i.length>0)i.forEach(o=>{const c=document.createElement("option");c.value=o.id,c.textContent=o.name,e.appendChild(c)});else{const o=document.createElement("option");o.value="",o.textContent="No clients yet",o.disabled=!0,e.appendChild(o)}if(d&&d.length>0)d.forEach(o=>{const c=document.createElement("option");c.value=o.id,c.textContent=o.name,s.appendChild(c)});else{const o=document.createElement("option");o.value="",o.textContent="No employees yet",o.disabled=!0,s.appendChild(o)}}}catch(t){console.error(t)}document.getElementById("newTicketSubject").value="",document.getElementById("newTicketDescription").value="",e.value="",s.value="",document.getElementById("newTicketPriority").value="medium",document.getElementById("newTicketCategory").value="",T()}function O(e){const s=document.getElementById("imageUploadPlaceholder"),t=document.getElementById("imagePreview"),a=document.getElementById("imagePreviewImg"),i=e.files&&e.files[0];if(i){if(!i.type.startsWith("image/")){alert("Please select an image file (PNG, JPG, GIF)."),e.value="";return}if(i.size>5*1024*1024){alert("Image must be under 5MB."),e.value="";return}const d=new FileReader;d.onload=function(o){a.src=o.target.result,s.style.display="none",t.style.display="flex"},d.readAsDataURL(i)}else T()}function T(){const e=document.getElementById("newTicketImage"),s=document.getElementById("imageUploadPlaceholder"),t=document.getElementById("imagePreview");e.value="",s.style.display="flex",t.style.display="none";const a=document.getElementById("imagePreviewImg");a&&(a.src="")}window.addEventListener("resize",x),v(),typeof F=="function"&&(window.addComment=F),typeof S=="function"&&(window.closeImagePopup=S),typeof I=="function"&&(window.closeNewTicketModal=I),typeof b=="function"&&(window.closeTicketModal=b),typeof H=="function"&&(window.createTicket=H),typeof getElementById=="function"&&(window.getElementById=getElementById),typeof L=="function"&&(window.openImagePopup=L),typeof U=="function"&&(window.openTicketModal=U),typeof O=="function"&&(window.previewTicketImage=O),typeof T=="function"&&(window.removeTicketImage=T),typeof stopPropagation=="function"&&(window.stopPropagation=stopPropagation)})();
