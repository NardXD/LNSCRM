@extends('layouts.app')

@section('title', 'Change Password')

@section('content')
    <div class="page-header">
        <h1 class="page-title">Change Password</h1>
        <p class="page-subtitle">Update your account password to keep your account secure</p>
    </div>

    <div class="change-password-container">
        <div class="password-card">
            <div class="password-card-header">
                <div class="password-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                </div>
                <div class="password-header-text">
                    <h2>Update Your Password</h2>
                    <p>Ensure your account is using a strong password to stay secure</p>
                </div>
            </div>

            <form id="changePasswordForm" class="password-form">
                @csrf
                <div class="form-group">
                    <label class="form-label" for="current_password">Current Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="current_password" 
                            name="current_password" 
                            class="form-input" 
                            placeholder="Enter your current password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('current_password')">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="eye-off-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">New Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password" 
                            name="password" 
                            class="form-input" 
                            placeholder="Enter your new password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password')">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="eye-off-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <div class="password-requirements">
                        <p class="requirements-title">Password must contain:</p>
                        <ul class="requirements-list">
                            <li id="req-length" class="requirement">
                                <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>At least 8 characters</span>
                            </li>
                            <li id="req-uppercase" class="requirement">
                                <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>One uppercase letter</span>
                            </li>
                            <li id="req-lowercase" class="requirement">
                                <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>One lowercase letter</span>
                            </li>
                            <li id="req-number" class="requirement">
                                <svg class="check-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <polyline points="20 6 9 17 4 12"/>
                                </svg>
                                <span>One number</span>
                            </li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password_confirmation">Confirm New Password</label>
                    <div class="password-input-wrapper">
                        <input 
                            type="password" 
                            id="password_confirmation" 
                            name="password_confirmation" 
                            class="form-input" 
                            placeholder="Confirm your new password"
                            required
                        >
                        <button type="button" class="password-toggle" onclick="togglePassword('password_confirmation')">
                            <svg class="eye-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                                <circle cx="12" cy="12" r="3"/>
                            </svg>
                            <svg class="eye-off-icon" style="display: none;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/>
                                <line x1="1" y1="1" x2="23" y2="23"/>
                            </svg>
                        </button>
                    </div>
                    <p id="password-match-error" class="form-error" style="display: none;">Passwords do not match</p>
                </div>

                <div class="form-actions">
                    <button type="submit" class="btn-primary" id="submitBtn">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"/>
                        </svg>
                        <span>Update Password</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- Security Tips Card -->
        <div class="tips-card">
            <div class="tips-header">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                </svg>
                <h3>Security Tips</h3>
            </div>
            <ul class="tips-list">
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Use a unique password that you don't use elsewhere</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Avoid using personal information like birthdays</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Consider using a password manager</span>
                </li>
                <li>
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                    <span>Change your password regularly</span>
                </li>
            </ul>
        </div>
    </div>

    <!-- Success Modal -->
    <div class="modal" id="successModal">
        <div class="modal-content success-modal">
            <div class="modal-icon success">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
            </div>
            <h2>Password Updated!</h2>
            <p>Your password has been changed successfully.</p>
            <button class="btn-primary" onclick="closeSuccessModal()">Continue</button>
        </div>
    </div>
@endsection

@push('styles')
<style>
    .change-password-container {
        display: grid;
        grid-template-columns: 1fr 350px;
        gap: 1.5rem;
        max-width: 1000px;
    }

    .password-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 2rem;
    }

    .password-card-header {
        display: flex;
        align-items: flex-start;
        gap: 1rem;
        margin-bottom: 2rem;
        padding-bottom: 1.5rem;
        border-bottom: 1px solid var(--border);
    }

    .password-icon {
        width: 48px;
        height: 48px;
        background: var(--accent-light);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    .password-icon svg {
        width: 24px;
        height: 24px;
        color: var(--accent);
    }

    .password-header-text h2 {
        font-size: 1.25rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.25rem 0;
    }

    .password-header-text p {
        font-size: 0.875rem;
        color: var(--text-secondary);
        margin: 0;
    }

    .password-form {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    .form-group {
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
    }

    .form-label {
        font-size: 0.875rem;
        font-weight: 500;
        color: var(--text-primary);
    }

    .password-input-wrapper {
        position: relative;
        display: flex;
        align-items: center;
    }

    .form-input {
        width: 100%;
        padding: 0.75rem 3rem 0.75rem 1rem;
        border: 1px solid var(--border);
        border-radius: 8px;
        font-size: 0.9375rem;
        background: var(--bg-primary);
        color: var(--text-primary);
        transition: all 0.15s;
    }

    .form-input:focus {
        outline: none;
        border-color: var(--accent);
        box-shadow: 0 0 0 3px rgba(95, 97, 230, 0.1);
    }

    .form-input.error {
        border-color: #ef4444;
    }

    .password-toggle {
        position: absolute;
        right: 0.75rem;
        background: none;
        border: none;
        padding: 0.25rem;
        cursor: pointer;
        color: var(--text-muted);
        transition: color 0.15s;
    }

    .password-toggle:hover {
        color: var(--text-primary);
    }

    .password-toggle svg {
        width: 20px;
        height: 20px;
    }

    .password-requirements {
        background: var(--bg-primary);
        border-radius: 8px;
        padding: 1rem;
        margin-top: 0.5rem;
    }

    .requirements-title {
        font-size: 0.8125rem;
        font-weight: 500;
        color: var(--text-secondary);
        margin: 0 0 0.75rem 0;
    }

    .requirements-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 0.5rem;
    }

    .requirement {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.8125rem;
        color: var(--text-muted);
        transition: color 0.15s;
    }

    .requirement.met {
        color: #10b981;
    }

    .requirement .check-icon {
        width: 16px;
        height: 16px;
        opacity: 0.4;
        transition: opacity 0.15s;
    }

    .requirement.met .check-icon {
        opacity: 1;
        color: #10b981;
    }

    .form-error {
        font-size: 0.8125rem;
        color: #ef4444;
        margin-top: 0.25rem;
    }

    .form-actions {
        margin-top: 1rem;
        padding-top: 1.5rem;
        border-top: 1px solid var(--border);
    }

    .btn-primary {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.5rem;
        padding: 0.75rem 1.5rem;
        background: var(--accent);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.9375rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.15s;
    }

    .btn-primary:hover {
        background: var(--accent-hover);
    }

    .btn-primary:disabled {
        opacity: 0.6;
        cursor: not-allowed;
    }

    .btn-primary svg {
        width: 18px;
        height: 18px;
    }

    /* Tips Card */
    .tips-card {
        background: var(--bg-card);
        border: 1px solid var(--border);
        border-radius: 12px;
        padding: 1.5rem;
        height: fit-content;
    }

    .tips-header {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        margin-bottom: 1rem;
        padding-bottom: 1rem;
        border-bottom: 1px solid var(--border);
    }

    .tips-header svg {
        width: 24px;
        height: 24px;
        color: var(--accent);
    }

    .tips-header h3 {
        font-size: 1rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0;
    }

    .tips-list {
        list-style: none;
        padding: 0;
        margin: 0;
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
    }

    .tips-list li {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        font-size: 0.875rem;
        color: var(--text-secondary);
        line-height: 1.5;
    }

    .tips-list li svg {
        width: 16px;
        height: 16px;
        color: #10b981;
        flex-shrink: 0;
        margin-top: 2px;
    }

    /* Success Modal */
    .modal {
        display: none;
        position: fixed;
        inset: 0;
        background: rgba(0, 0, 0, 0.75);
        z-index: 2000;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }

    .modal.active {
        display: flex;
    }

    .modal-content {
        background: var(--bg-card);
        border-radius: 16px;
        padding: 2rem;
        text-align: center;
        max-width: 400px;
        width: 100%;
    }

    .modal-icon {
        width: 64px;
        height: 64px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        margin: 0 auto 1.5rem;
    }

    .modal-icon.success {
        background: #d1fae5;
    }

    .modal-icon.success svg {
        width: 32px;
        height: 32px;
        color: #10b981;
    }

    .modal-content h2 {
        font-size: 1.5rem;
        font-weight: 600;
        color: var(--text-primary);
        margin: 0 0 0.5rem 0;
    }

    .modal-content p {
        font-size: 0.9375rem;
        color: var(--text-secondary);
        margin: 0 0 1.5rem 0;
    }

    /* Responsive */
    @media (max-width: 900px) {
        .change-password-container {
            grid-template-columns: 1fr;
        }

        .tips-card {
            order: -1;
        }
    }

    @media (max-width: 600px) {
        .password-card {
            padding: 1.5rem;
        }

        .password-card-header {
            flex-direction: column;
            text-align: center;
        }

        .password-icon {
            margin: 0 auto;
        }

        .requirements-list {
            grid-template-columns: 1fr;
        }
    }
</style>
@endpush

@push('scripts')
<script>
    // Toggle password visibility
    function togglePassword(inputId) {
        const input = document.getElementById(inputId);
        const wrapper = input.closest('.password-input-wrapper');
        const eyeIcon = wrapper.querySelector('.eye-icon');
        const eyeOffIcon = wrapper.querySelector('.eye-off-icon');

        if (input.type === 'password') {
            input.type = 'text';
            eyeIcon.style.display = 'none';
            eyeOffIcon.style.display = 'block';
        } else {
            input.type = 'password';
            eyeIcon.style.display = 'block';
            eyeOffIcon.style.display = 'none';
        }
    }

    // Password validation
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('password_confirmation');
    const matchError = document.getElementById('password-match-error');

    const requirements = {
        length: document.getElementById('req-length'),
        uppercase: document.getElementById('req-uppercase'),
        lowercase: document.getElementById('req-lowercase'),
        number: document.getElementById('req-number')
    };

    passwordInput.addEventListener('input', function() {
        const value = this.value;

        // Check length
        if (value.length >= 8) {
            requirements.length.classList.add('met');
        } else {
            requirements.length.classList.remove('met');
        }

        // Check uppercase
        if (/[A-Z]/.test(value)) {
            requirements.uppercase.classList.add('met');
        } else {
            requirements.uppercase.classList.remove('met');
        }

        // Check lowercase
        if (/[a-z]/.test(value)) {
            requirements.lowercase.classList.add('met');
        } else {
            requirements.lowercase.classList.remove('met');
        }

        // Check number
        if (/[0-9]/.test(value)) {
            requirements.number.classList.add('met');
        } else {
            requirements.number.classList.remove('met');
        }

        // Check password match
        checkPasswordMatch();
    });

    confirmInput.addEventListener('input', checkPasswordMatch);

    function checkPasswordMatch() {
        if (confirmInput.value && passwordInput.value !== confirmInput.value) {
            matchError.style.display = 'block';
            confirmInput.classList.add('error');
        } else {
            matchError.style.display = 'none';
            confirmInput.classList.remove('error');
        }
    }

    // Form submission
    document.getElementById('changePasswordForm').addEventListener('submit', async function(e) {
        e.preventDefault();

        const submitBtn = document.getElementById('submitBtn');
        const originalText = submitBtn.innerHTML;

        // Validate password match
        if (passwordInput.value !== confirmInput.value) {
            matchError.style.display = 'block';
            confirmInput.classList.add('error');
            confirmInput.focus();
            return;
        }

        // Disable button and show loading
        submitBtn.disabled = true;
        submitBtn.innerHTML = `
            <svg class="spinner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10" stroke-dasharray="30 60"/>
            </svg>
            <span>Updating...</span>
        `;

        try {
            const response = await fetch('{{ route("api.change-password.update") }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    current_password: document.getElementById('current_password').value,
                    password: passwordInput.value,
                    password_confirmation: confirmInput.value
                })
            });

            const data = await response.json();

            if (response.ok && data.success) {
                // Show success modal
                document.getElementById('successModal').classList.add('active');
                
                // Clear form
                this.reset();
                Object.values(requirements).forEach(req => req.classList.remove('met'));
            } else {
                // Show error
                alert(data.message || 'An error occurred. Please try again.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('An error occurred. Please try again.');
        } finally {
            submitBtn.disabled = false;
            submitBtn.innerHTML = originalText;
        }
    });

    function closeSuccessModal() {
        document.getElementById('successModal').classList.remove('active');
    }

    // Close modal on outside click
    document.getElementById('successModal').addEventListener('click', function(e) {
        if (e.target === this) {
            closeSuccessModal();
        }
    });

    // Add spinner animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes spin {
            from { transform: rotate(0deg); }
            to { transform: rotate(360deg); }
        }
        .spinner {
            animation: spin 1s linear infinite;
        }
    `;
    document.head.appendChild(style);
</script>
@endpush
