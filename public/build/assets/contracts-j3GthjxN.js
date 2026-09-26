(function(){const I=window.__contractsConfig||{},h=I.permissions||[],T=h.includes("create_contracts"),H=h.includes("send_contracts"),S=h.includes("delete_contracts"),s=I.contractApi||"/api/contracts",O=e=>(I.statusHistoryUrlTemplate||"/api/contracts/:id/status-history").replace(":id",e);let $=[],m=[],c=1,E=1,p=0,P=null;window.viewingContractId=null;const C={draft:"Draft",pending_signatures:"Pending",partially_signed:"Partially Signed",signed:"Signed",cancelled:"Cancelled",expired:"Expired"};function g(e){return{draft:"draft",pending_signatures:"sent",partially_signed:"partial",signed:"accepted",cancelled:"rejected",expired:"expired"}[e]||"draft"}function b(e){return C[e]||(e?e.replace(/_/g," ").replace(/\b\w/g,t=>t.toUpperCase()):"")}function R(e){return e.length?`
            <div class="status-history-list">
                ${e.map((t,n)=>`
                    <div class="status-history-item ${n===0?"current":""}">
                        <div class="status-history-timeline">
                            <div class="status-history-dot"></div>
                            ${n<e.length-1?'<div class="status-history-line"></div>':""}
                        </div>
                        <div class="status-history-content">
                            <div class="status-history-header">
                                <span class="status-badge ${g(t.status)}">${b(t.status)}</span>
                                <span class="status-history-date">${t.changed_at_formatted}</span>
                            </div>
                            ${t.previous_status?`
                                <div class="status-history-change">
                                    <span class="status-history-label">Changed from:</span>
                                    <span class="status-badge ${g(t.previous_status)}">${b(t.previous_status)}</span>
                                    <span>→</span>
                                    <span class="status-badge ${g(t.status)}">${b(t.status)}</span>
                                </div>
                            `:""}
                            <div class="status-history-user">
                                <span class="status-history-label">By:</span>
                                <span>${d(t.changed_by)}</span>
                            </div>
                            ${t.notes?`
                                <div class="status-history-notes">
                                    <span class="status-history-label">Notes:</span>
                                    <span>${d(t.notes)}</span>
                                </div>
                            `:""}
                        </div>
                    </div>
                `).join("")}
            </div>
        `:'<div style="text-align:center;padding:2rem;color:var(--text-muted);">No history recorded yet.</div>'}async function r(e,t={}){return(await fetch(e,{headers:{Accept:"application/json","Content-Type":"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content,...t.headers||{}},...t})).json()}async function v(){const e=await r(s+"/stats");e.success&&(document.getElementById("statTotal").textContent=e.data.total,document.getElementById("statPending").textContent=e.data.pending,document.getElementById("statSigned").textContent=e.data.signed,document.getElementById("statDraft").textContent=e.data.draft)}async function i(e=1){c=e;const t=document.getElementById("contractSearch").value,n=document.getElementById("statusFilter").value,a=new URLSearchParams({page:e,per_page:10});t&&a.set("search",t),n!=="all"&&a.set("status",n);const o=await r(`${s}?${a}`);o.success&&(m=o.data,E=o.pagination.last_page,p=o.pagination.total,A(),U(),z())}function A(){const e=document.getElementById("contractsTableBody");if(!m.length){e.innerHTML='<tr><td colspan="7" class="empty-cell">No contracts found.</td></tr>';return}e.innerHTML=m.map(t=>`
            <tr onclick="openViewContractModal(${t.id})">
                <td><strong>${d(t.contract_number)}</strong></td>
                <td>${d(t.title)}</td>
                <td>${d(t.lead)}</td>
                <td><span class="status-badge ${g(t.status)}">${C[t.status]||t.status}</span></td>
                <td>${t.signers_progress}</td>
                <td>${t.created_at}</td>
                <td onclick="event.stopPropagation()">
                    <div class="table-actions">
                        <button class="icon-btn" title="View" onclick="openViewContractModal(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        </button>
                        <button class="icon-btn" title="Download PDF" onclick="downloadContractPdf(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                        </button>
                        <button class="icon-btn" title="History" onclick="event.stopPropagation(); viewContractHistory(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        </button>
                        ${T&&t.status==="draft"&&t.content_type!=="storage_quote"?`
                        <button class="icon-btn" title="Edit" onclick="editContract(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                        </button>`:""}
                        ${H&&["draft","pending_signatures","partially_signed"].includes(t.status)?`
                        <button class="icon-btn" title="Send for Signature" onclick="sendContract(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                        </button>`:""}
                        ${S&&["draft","cancelled"].includes(t.status)?`
                        <button class="icon-btn icon-btn-danger" title="Delete" onclick="deleteContract(${t.id})">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/></svg>
                        </button>`:""}
                    </div>
                </td>
            </tr>
        `).join("")}function U(){const e=document.getElementById("contractsCards");if(!m.length){e.innerHTML='<div class="empty-cell">No contracts found.</div>';return}e.innerHTML=m.map(t=>`
            <div class="contract-card" onclick="openViewContractModal(${t.id})">
                <div class="card-header">
                    <div>
                        <div class="card-title">${d(t.contract_number)}</div>
                        <div style="font-size:0.75rem;color:var(--text-muted);margin-top:0.25rem;">${d(t.title)}</div>
                    </div>
                    <span class="status-badge ${g(t.status)}">${C[t.status]||t.status}</span>
                </div>
                <div class="card-details">
                    <div class="card-detail"><span class="card-label">Lead</span><span class="card-value">${d(t.lead)}</span></div>
                    <div class="card-detail"><span class="card-label">Signatures</span><span class="card-value">${t.signers_progress}</span></div>
                    <div class="card-detail"><span class="card-label">Created</span><span class="card-value">${t.created_at}</span></div>
                </div>
            </div>
        `).join("")}function z(){const e=document.getElementById("paginationInfo"),t=document.getElementById("paginationNumbers"),n=document.getElementById("prevBtn"),a=document.getElementById("nextBtn"),o=10,y=p?(c-1)*o+1:0,tt=Math.min(c*o,p);e.textContent=p?`Showing ${y} to ${tt} of ${p} results`:"Showing 0 results",n.disabled=c<=1,a.disabled=c>=E;let j="";for(let f=1;f<=E;f++)j+=`<button class="pagination-number ${f===c?"active":""}" onclick="loadContracts(${f})">${f}</button>`;t.innerHTML=j}function d(e){const t=document.createElement("div");return t.textContent=e??"",t.innerHTML}function K(e){const t=document.createElement("div");return t.innerHTML=e||"",(t.textContent||t.innerText||"").trim()}function l(){const e=document.getElementById("contractContentEditor"),t=document.getElementById("contractContent");e&&t&&(t.value=e.innerHTML)}function N(e){const t=document.getElementById("contractContentEditor"),n=document.getElementById("contractContent"),a=e||"";t&&(t.innerHTML=a),n&&(n.value=a)}function X(){N("")}document.getElementById("contractContentEditor")?.addEventListener("input",l),document.getElementById("contractContentEditor")?.addEventListener("paste",()=>setTimeout(l,0)),document.querySelector('.rich-editor-toolbar[data-editor="contractContentEditor"]')?.addEventListener("click",function(e){const t=e.target.closest(".rich-editor-btn");if(!t)return;e.preventDefault();const n=document.getElementById("contractContentEditor");if(!n)return;n.focus();const a=t.dataset.cmd,o=t.dataset.value;if(a==="createLink"){const y=prompt("Enter URL:","https://");y&&(document.execCommand("createLink",!1,y),l())}else a==="formatBlock"&&o?(document.execCommand("formatBlock",!1,o),l()):(document.execCommand(a,!1,null),l())}),document.querySelector('.rich-editor-select[data-editor="contractContentEditor"]')?.addEventListener("change",function(){const e=document.getElementById("contractContentEditor");e&&(e.focus(),document.execCommand("formatBlock",!1,this.value),l())});async function G(){const e=await r(s+"/leads");e.success&&($=e.data),document.getElementById("leadId").innerHTML='<option value="">Select lead...</option>'+$.map(t=>`<option value="${t.id}">${d(t.name)}</option>`).join("")}function w(e={}){const t=document.createElement("div");t.className="signer-row",t.innerHTML=`
            <input type="text" class="form-input signer-name" placeholder="Name" value="${d(e.name||"")}" required>
            <input type="email" class="form-input signer-email" placeholder="Email" value="${d(e.email||"")}" required>
            <select class="form-input signer-role">
                <option value="client" ${e.role==="client"?"selected":""}>Client</option>
                <option value="company" ${e.role==="company"?"selected":""}>Company</option>
                <option value="witness" ${e.role==="witness"?"selected":""}>Witness</option>
            </select>
            <input type="number" class="form-input signer-order" min="1" value="${e.signing_order||1}" title="Order">
            <button type="button" class="icon-btn icon-btn-danger" onclick="this.parentElement.remove()" title="Remove">&times;</button>
        `,document.getElementById("signersList").appendChild(t)}function J(){return[...document.querySelectorAll(".signer-row")].map((e,t)=>({name:e.querySelector(".signer-name").value.trim(),email:e.querySelector(".signer-email").value.trim(),role:e.querySelector(".signer-role").value,signing_order:parseInt(e.querySelector(".signer-order").value)||t+1}))}function D(){document.getElementById("contractModal").classList.add("active"),document.body.style.overflow="hidden"}function B(){document.getElementById("contractModal").classList.remove("active"),document.body.style.overflow=""}async function W(){document.getElementById("contractModalTitle").textContent="New Contract",document.getElementById("contractId").value="",document.getElementById("contractForm").reset(),document.getElementById("signersList").innerHTML="",X();const e=await r(s+"/next-number");e.success&&(document.getElementById("contractNumber").value=e.data.contract_number),w(),D()}async function L(e){const t=await r(`${s}/${e}`);if(!t.success)return alert(t.message);const n=t.data;document.getElementById("contractModalTitle").textContent="Edit Contract",document.getElementById("contractId").value=n.id,document.getElementById("leadId").value=n.lead_id,document.getElementById("contractNumber").value=n.contract_number,document.getElementById("contractTitle").value=n.title,document.getElementById("effectiveDate").value=n.effective_date||"",document.getElementById("expiryDate").value=n.expiry_date||"",N(n.content||""),document.getElementById("signersList").innerHTML="",n.signers.forEach(a=>w(a)),u(),D()}async function Y(){const e=document.getElementById("contractId").value,t=J();if(!t.length)return alert("Add at least one signer.");l();const n=document.getElementById("contractContent").value;if(!K(n))return alert("Contract content is required.");const a={lead_id:document.getElementById("leadId").value,title:document.getElementById("contractTitle").value,content:n,effective_date:document.getElementById("effectiveDate").value||null,expiry_date:document.getElementById("expiryDate").value||null,signers:t},o=await r(e?`${s}/${e}`:s,{method:e?"PUT":"POST",body:JSON.stringify(a)});o.success?(B(),i(c),v()):alert(o.message||"Failed to save contract.")}async function x(e){const t=await r(`${s}/${e}`);if(!t.success)return alert(t.message);const n=t.data;window.viewingContractId=n.id,document.getElementById("viewContractNumber").textContent=n.contract_number,document.getElementById("viewContractStatus").innerHTML=`<span class="status-badge ${g(n.status)}">${C[n.status]||n.status}</span>`,document.getElementById("viewContractTitle").textContent=n.title||"-",document.getElementById("viewContractClient").textContent=n.lead?.name||"-",document.getElementById("viewContractEffective").textContent=n.effective_date||"-",document.getElementById("viewContractExpiry").textContent=n.expiry_date||"-";const a=n.signers.filter(o=>o.status==="signed").length;document.getElementById("viewContractProgress").textContent=`${a}/${n.signers.length} signed`,document.getElementById("viewContractCreator").textContent=n.created_by||"-",document.getElementById("viewContractContent").innerHTML=n.content_type==="storage_quote"?n.rendered_content||'<p style="color:var(--text-muted)">No content</p>':n.content||'<p style="color:var(--text-muted)">No content</p>',document.getElementById("viewContractSigners").innerHTML=n.signers.map(o=>`
            <tr>
                <td>${d(o.name)}</td>
                <td>${d(o.email)}</td>
                <td>${d(o.role)}</td>
                <td><span class="status-badge ${o.status==="signed"?"accepted":"sent"}">${o.status}</span></td>
                <td>${o.signed_at?new Date(o.signed_at).toLocaleString():"-"}</td>
            </tr>
        `).join(""),document.getElementById("viewSendBtn").style.display=H&&["draft","pending_signatures","partially_signed"].includes(n.status)?"inline-flex":"none",document.getElementById("viewEditBtn").style.display=T&&n.status==="draft"&&n.content_type!=="storage_quote"?"inline-flex":"none",document.getElementById("viewDeleteBtn").style.display=S&&["draft","cancelled"].includes(n.status)?"inline-flex":"none",document.getElementById("viewCancelBtn").style.display=n.status!=="signed"&&n.status!=="cancelled"?"inline-flex":"none",document.getElementById("viewContractModal").style.display="flex",document.body.style.overflow="hidden"}function u(){document.getElementById("viewContractModal").style.display="none",document.body.style.overflow="",window.viewingContractId=null}function q(e){const t=e??window.viewingContractId;if(!t)return alert("No contract selected.");const a=(m.find(y=>y.id===t)?.contract_number||"contract")+".pdf",o=document.createElement("a");o.href=`${s}/${t}/pdf`,o.download=a,o.rel="noopener noreferrer",document.body.appendChild(o),o.click(),document.body.removeChild(o)}function Q(){document.getElementById("contractHistoryModal").classList.add("active"),document.body.style.overflow="hidden"}function F(){document.getElementById("contractHistoryModal").classList.remove("active"),document.getElementById("viewContractModal").style.display!=="flex"&&(document.body.style.overflow="")}async function M(e){const t=e??window.viewingContractId;if(!t)return;Q();const n=document.getElementById("contractHistoryBody");n.innerHTML='<div style="text-align:center;padding:2rem;"><div class="spinner"></div><p>Loading history...</p></div>';try{const o=await(await fetch(O(t),{headers:{Accept:"application/json","X-CSRF-TOKEN":document.querySelector('meta[name="csrf-token"]').content}})).json();o.success&&o.data?n.innerHTML=R(o.data):n.innerHTML='<div style="text-align:center;padding:2rem;color:#dc2626;">Failed to load history.</div>'}catch{n.innerHTML='<div style="text-align:center;padding:2rem;color:#dc2626;">Failed to load history.</div>'}}async function _(e){const t=e??window.viewingContractId;if(!t||!confirm("Send this contract to all pending signers via email?"))return;const n=await r(`${s}/${t}/send`,{method:"POST"});alert(n.message),n.success&&(u(),i(c),v())}async function Z(e){const t=window.viewingContractId;if(!t||!confirm("Cancel this contract?"))return;const n=await r(`${s}/${t}/cancel`,{method:"POST"});n.success?(u(),i(c),v()):alert(n.message)}async function k(e){const t=e??window.viewingContractId;if(!t||!confirm("Delete this contract permanently?"))return;const n=await r(`${s}/${t}`,{method:"DELETE"});n.success?(u(),i(c),v()):alert(n.message)}document.getElementById("newContractBtn")?.addEventListener("click",W),document.getElementById("addSignerBtn").addEventListener("click",()=>w()),document.getElementById("saveContractBtn").addEventListener("click",Y),document.getElementById("closeContractModal").addEventListener("click",B),document.getElementById("cancelContractBtn").addEventListener("click",B),document.getElementById("closeViewModal").addEventListener("click",u),document.getElementById("closeViewModalBtn").addEventListener("click",u),document.getElementById("viewSendBtn").addEventListener("click",()=>_()),document.getElementById("viewEditBtn").addEventListener("click",()=>L(window.viewingContractId)),document.getElementById("viewDeleteBtn").addEventListener("click",()=>k()),document.getElementById("viewCancelBtn").addEventListener("click",()=>Z()),document.getElementById("viewHistoryBtn").addEventListener("click",()=>M()),document.getElementById("closeContractHistoryModal").addEventListener("click",F),document.getElementById("contractHistoryModal").addEventListener("click",e=>{e.target.id==="contractHistoryModal"&&F()}),document.getElementById("contractModal").addEventListener("click",e=>{e.target.id==="contractModal"&&B()}),document.getElementById("viewContractModal").addEventListener("click",e=>{e.target.id==="viewContractModal"&&u()}),document.getElementById("contractSearch").addEventListener("input",()=>{clearTimeout(P),P=setTimeout(()=>i(1),300)}),document.getElementById("statusFilter").addEventListener("change",()=>i(1)),document.getElementById("prevBtn").addEventListener("click",()=>{c>1&&i(c-1)}),document.getElementById("nextBtn").addEventListener("click",()=>{c<E&&i(c+1)}),document.getElementById("leadId").addEventListener("change",function(){const e=$.find(n=>n.id==this.value);if(!e||document.getElementById("contractId").value)return;const t=document.getElementById("signersList");t.children.length===1&&!t.querySelector(".signer-name").value&&(t.innerHTML="",w({name:e.name,email:e.email||"",role:"client",signing_order:1}))}),G(),v(),i();const V=new URLSearchParams(window.location.search).get("open");V&&x(parseInt(V,10)),typeof k=="function"&&(window.deleteContract=k),typeof q=="function"&&(window.downloadContractPdf=q),typeof L=="function"&&(window.editContract=L),typeof i=="function"&&(window.loadContracts=i),typeof x=="function"&&(window.openViewContractModal=x),typeof remove=="function"&&(window.remove=remove),typeof _=="function"&&(window.sendContract=_),typeof stopPropagation=="function"&&(window.stopPropagation=stopPropagation),typeof M=="function"&&(window.viewContractHistory=M)})();
