<div class="impersonation-banner" role="status">
    <div class="impersonation-banner-content">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" width="18" height="18" aria-hidden="true">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
        </svg>
        <span>You are viewing this company as an admin.</span>
    </div>
    <form action="{{ route('leave-impersonation') }}" method="POST" class="impersonation-banner-form">
        @csrf
        <button type="submit" class="impersonation-banner-btn">Return to Admin Panel</button>
    </form>
</div>

<style>
    .impersonation-banner {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
        flex-wrap: wrap;
        padding: 0.75rem 1.5rem;
        background: #fffbeb;
        border-bottom: 1px solid #f59e0b;
        color: #92400e;
        font-size: 0.875rem;
    }

    .impersonation-banner-content {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .impersonation-banner-form {
        margin: 0;
    }

    .impersonation-banner-btn {
        border: 1px solid #d97706;
        background: #ffffff;
        color: #92400e;
        border-radius: 6px;
        padding: 0.375rem 0.75rem;
        font-size: 0.8125rem;
        font-weight: 600;
        cursor: pointer;
        transition: background 0.15s ease;
    }

    .impersonation-banner-btn:hover {
        background: #fef3c7;
    }
</style>
