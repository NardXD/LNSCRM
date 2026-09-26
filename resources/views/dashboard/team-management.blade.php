@extends('layouts.app')

@section('title', 'Team Management')

@push('styles')
    @vite(['resources/css/pages/team-management.css'])
@endpush

@push('scripts')
<script>
    window.__teamManagementConfig = {
        userId: @json(auth()->id()),
        canEdit: @json((bool) (auth()->user()?->hasPermission('edit_team_management') || auth()->user()?->isAdmin())),
        canDelete: @json((bool) (auth()->user()?->hasPermission('delete_team_management') || auth()->user()?->isAdmin())),
    };
</script>
    @vite(['resources/js/pages/team-management.js'])
@endpush

@section('content')
<div class="page-header">
    <h1 class="page-title">Team Management</h1>
    <p class="page-subtitle">Manage teams, members, monitor time tracking and project progress</p>
</div>

<div class="team-container">
    <!-- Main View -->
    <div id="teamsListView">
        <!-- Tabs Navigation -->
        <div class="management-tabs">
            <button class="tab-btn active" data-tab="teams">All Teams</button>
            <button class="tab-btn" data-tab="my-team">My Teams</button>
        </div>

        <!-- Teams Tab -->
        <div class="tab-content active" id="teamsTab">
            <div class="content-section">
                <div class="section-header">
                    <h2 class="section-title">All Teams</h2>
                    <div class="section-actions">
                        <div class="search-box">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <circle cx="11" cy="11" r="8"/>
                                <path d="M21 21l-4.35-4.35"/>
                            </svg>
                            <input type="text" id="teamSearch" placeholder="Search teams..." onkeyup="filterTeams()">
                        </div>
                        @if(auth()->user()?->hasPermission('create_team_management') || auth()->user()?->isAdmin())
                        <button class="btn-primary" onclick="openCreateTeamModal()">
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                <line x1="12" y1="5" x2="12" y2="19"/>
                                <line x1="5" y1="12" x2="19" y2="12"/>
                            </svg>
                            Create Team
                        </button>
                        @endif
                    </div>
                </div>

                <div class="teams-grid" id="teamsGrid">
                    <!-- Teams will be loaded here -->
                </div>
            </div>
        </div>

        <!-- My Team Tab -->
        <div class="tab-content" id="myTeamTab">
            <div class="content-section" id="myTeamsContainer">
                <!-- User's teams will be loaded here -->
            </div>
        </div>
    </div>

    <!-- Team Details View -->
    <div id="teamDetailsView" style="display: none;">
        <div class="team-details">
            <div class="team-details-header">
                <button class="back-btn" onclick="showTeamsList()">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M19 12H5M12 19l-7-7 7-7"/>
                    </svg>
                    Back to Teams
                </button>
                <div class="team-actions" id="teamDetailActions">
                    <!-- Actions will be added dynamically -->
                </div>
            </div>

            <div class="management-tabs">
                <button class="tab-btn active" data-detail-tab="overview">Overview</button>
                <button class="tab-btn" data-detail-tab="members">Members</button>
                <button class="tab-btn" data-detail-tab="time-tracking">Time Tracking</button>
                <button class="tab-btn" data-detail-tab="recordings">Recordings</button>
                <button class="tab-btn" data-detail-tab="tasks">Tasks</button>
            </div>

            <div id="teamDetailContent" class="content-section" style="margin-top: 1.5rem;">
                <!-- Detail content will be loaded here -->
            </div>
        </div>
    </div>
</div>

<!-- Create/Edit Team Modal -->
<div class="modal" id="teamModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title" id="teamModalTitle">Create Team</h3>
            <button class="modal-close" onclick="closeTeamModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <form id="teamForm">
                <input type="hidden" id="teamId" name="team_id">
                
                <div class="form-group">
                    <label class="form-label" for="teamName">Team Name *</label>
                    <input type="text" class="form-input" id="teamName" name="name" required>
                </div>

                <div class="form-group">
                    <label class="form-label" for="teamDescription">Description</label>
                    <textarea class="form-textarea" id="teamDescription" name="description" rows="3"></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="teamLeader">Team Leader</label>
                        <select class="form-select" id="teamLeader" name="leader_id">
                            <option value="">Select Leader</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Team Color</label>
                        <div class="color-picker-group">
                            <input type="color" class="color-picker" id="teamColor" name="color" value="#5f61e6">
                            <div class="color-preview" id="colorPreview" style="background: #5f61e6;"></div>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Team Members</label>
                    <div class="user-selection" id="memberSelection">
                        <!-- Users will be loaded here -->
                    </div>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeTeamModal()">Cancel</button>
            <button class="btn-primary" onclick="saveTeam()">
                <span id="saveTeamText">Create Team</span>
            </button>
        </div>
    </div>
</div>

<!-- Add Members Modal -->
<div class="modal" id="addMembersModal">
    <div class="modal-content">
        <div class="modal-header">
            <h3 class="modal-title">Add Team Members</h3>
            <button class="modal-close" onclick="closeAddMembersModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body">
            <input type="hidden" id="addMembersTeamId">
            <div class="form-group">
                <label class="form-label">Select Members to Add</label>
                <div class="user-selection" id="availableMembersSelection">
                    <!-- Available users will be loaded here -->
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Role</label>
                <select class="form-select" id="newMemberRole">
                    <option value="member">Member</option>
                    <option value="co-leader">Co-Leader</option>
                </select>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn-secondary" onclick="closeAddMembersModal()">Cancel</button>
            <button class="btn-primary" onclick="addSelectedMembers()">Add Members</button>
        </div>
    </div>
</div>

<!-- Video Player Modal -->
<div class="modal" id="videoModal">
    <div class="modal-content" style="max-width: 1200px;">
        <div class="modal-header">
            <h3 class="modal-title" id="videoModalTitle">Recording</h3>
            <button class="modal-close" onclick="closeVideoModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="recordingVideoContainer">
            <video id="recordingVideo" controls style="width: 100%; border-radius: 8px;">
                Your browser does not support video playback.
            </video>
        </div>
    </div>
</div>

<!-- Task Time Tracking Modal -->
<div class="modal" id="taskTimeTrackingModal">
    <div class="modal-content" style="max-width: 900px;">
        <div class="modal-header">
            <h3 class="modal-title" id="taskTimeTrackingTitle">Task Time Tracking</h3>
            <button class="modal-close" onclick="closeTaskTimeTrackingModal()">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="18" y1="6" x2="6" y2="18"/>
                    <line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </button>
        </div>
        <div class="modal-body" id="taskTimeTrackingContent">
            <div class="loading-container"><div class="loading-spinner"></div></div>
        </div>
    </div>
</div>
@endsection
