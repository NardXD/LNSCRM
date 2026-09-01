(function () {
    function readConfig() {
        const configEl = document.getElementById('email-template-config');
        if (!configEl) {
            return null;
        }

        try {
            return JSON.parse(configEl.textContent || '{}');
        } catch (error) {
            console.error('Could not parse email template config.', error);
            return null;
        }
    }

    function parseJsonResponse(response) {
        const contentType = response.headers.get('content-type') || '';
        if (!contentType.includes('application/json')) {
            return null;
        }

        return response.json();
    }

    function initEmailTemplatePage() {
        const root = document.getElementById('email-template-page');
        if (!root) {
            return;
        }

        const config = readConfig() || {};
        const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const apiUrl = config.apiUrl || '';
        const storeUrl = config.storeUrl || apiUrl;
        const resetUrl = config.resetUrl || '';
        const previewContext = config.previewContext || {};
        const subjectInput = document.getElementById('template-subject');
        const bodyInput = document.getElementById('template-body');
        const alertEl = document.getElementById('template-alert');
        const previewSubjectEl = document.getElementById('preview-subject');
        const previewBodyFrame = document.getElementById('preview-body-frame');
        const form = document.getElementById('emailTemplateForm');

        if (!subjectInput || !bodyInput || !alertEl || !form || !apiUrl || !storeUrl) {
            console.error('Email template page is missing required elements or API URLs.');
            return;
        }

        let previewTimer = null;
        const placeholderOpen = '{' + '{';
        const placeholderClose = '}' + '}';

        function renderTemplate(template, context) {
            let result = template || '';
            Object.keys(context).forEach(function (key) {
                result = result.split(placeholderOpen + key + placeholderClose).join(context[key] ?? '');
            });

            return result;
        }

        function updatePreview() {
            if (!previewSubjectEl || !previewBodyFrame) {
                return;
            }

            const subject = renderTemplate(subjectInput.value, previewContext);
            const body = renderTemplate(bodyInput.value, previewContext);

            previewSubjectEl.textContent = subject.trim() !== '' ? subject : '—';

            const frameDoc = previewBodyFrame.contentDocument || previewBodyFrame.contentWindow?.document;
            if (!frameDoc) {
                return;
            }

            frameDoc.open();
            frameDoc.write(body);
            frameDoc.close();
        }

        function schedulePreviewUpdate() {
            clearTimeout(previewTimer);
            previewTimer = setTimeout(updatePreview, 200);
        }

        function showAlert(message, type) {
            alertEl.textContent = message;
            alertEl.className = 'qb-flash qb-inline-alert ' + type;
            alertEl.style.display = 'block';
        }

        function hideAlert() {
            alertEl.style.display = 'none';
        }

        async function loadTemplate() {
            const response = await fetch(apiUrl, {
                headers: { 'Accept': 'application/json' },
                credentials: 'same-origin',
            });
            const data = await parseJsonResponse(response);

            if (!response.ok || !data) {
                throw new Error('Could not load template.');
            }

            subjectInput.value = data.template?.subject || '';
            bodyInput.value = data.template?.body || '';
            updatePreview();
        }

        subjectInput.addEventListener('input', schedulePreviewUpdate);
        bodyInput.addEventListener('input', schedulePreviewUpdate);

        form.addEventListener('submit', async function (e) {
            e.preventDefault();
            hideAlert();

            const saveBtn = document.getElementById('save-template-btn');
            saveBtn.disabled = true;

            try {
                const response = await fetch(storeUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                    body: JSON.stringify({
                        subject: subjectInput.value,
                        body: bodyInput.value,
                    }),
                });

                const data = await parseJsonResponse(response);

                if (!data) {
                    showAlert('Server error (' + response.status + '). Please refresh and try again.', 'error');
                    return;
                }

                if (!response.ok) {
                    const msg = data.errors
                        ? Object.values(data.errors).flat().join(' ')
                        : (data.message || 'Failed to save template.');
                    showAlert(msg, 'error');
                    return;
                }

                if (data.template) {
                    subjectInput.value = data.template.subject || subjectInput.value;
                    bodyInput.value = data.template.body || bodyInput.value;
                    updatePreview();
                }

                showAlert(data.message || 'Template saved.', 'success');
            } catch (err) {
                showAlert('Error saving template. Please try again.', 'error');
            } finally {
                saveBtn.disabled = false;
            }
        });

        document.getElementById('reset-template-btn')?.addEventListener('click', async function () {
            if (!resetUrl) {
                return;
            }

            if (!confirm('Reset the email template to the default subject and body?')) {
                return;
            }

            hideAlert();
            this.disabled = true;

            try {
                const response = await fetch(resetUrl, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': csrf,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    credentials: 'same-origin',
                });

                const data = await parseJsonResponse(response);

                if (!data) {
                    showAlert('Server error (' + response.status + '). Please refresh and try again.', 'error');
                    return;
                }

                if (!response.ok) {
                    showAlert(data.message || 'Failed to reset template.', 'error');
                    return;
                }

                subjectInput.value = data.template?.subject || '';
                bodyInput.value = data.template?.body || '';
                updatePreview();
                showAlert(data.message || 'Template reset.', 'success');
            } catch (err) {
                showAlert('Error resetting template. Please try again.', 'error');
            } finally {
                this.disabled = false;
            }
        });

        document.querySelectorAll('.qb-placeholder-btn').forEach(function (btn) {
            btn.addEventListener('click', function () {
                const token = btn.dataset.placeholder || '';
                const target = document.activeElement === subjectInput ? subjectInput : bodyInput;
                const start = target.selectionStart ?? target.value.length;
                const end = target.selectionEnd ?? target.value.length;
                target.value = target.value.slice(0, start) + token + target.value.slice(end);
                target.focus();
                const caret = start + token.length;
                target.setSelectionRange(caret, caret);
                schedulePreviewUpdate();
            });
        });

        loadTemplate().catch(function () {
            showAlert('Could not load the email template.', 'error');
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initEmailTemplatePage);
    } else {
        initEmailTemplatePage();
    }
})();
