@extends('layouts.app')

@section('title', 'Knowledge Base')

@push('styles')
    @include('partials.leads-page-base-styles')
    @vite(['resources/css/pages/knowledge-base.css'])
@endpush

@push('scripts')
<script>
    window.__knowledgeBaseConfig = {
        canCreate: @json($canCreateKnowledgeBase ?? true),
        canEdit: @json($canEditKnowledgeBase ?? true),
        canDelete: @json($canDeleteKnowledgeBase ?? true),
        baseUrl: @json(\Illuminate\Support\Str::replaceLast('/bootstrap', '', route('api.knowledge-base.bootstrap'))),
        bootstrapUrl: @json(route('api.knowledge-base.bootstrap')),
    };
</script>
    @vite(['resources/js/pages/knowledge-base.js'])
@endpush

@section('content')
    <div class="ld-page-wrapper">
    <div class="ld-page">
    <div class="ld-top">
        <div class="ld-top-main">
            <h1 class="ld-title">Knowledge Base</h1>
            <p class="ld-subtitle">Articles organized by category.</p>
        </div>
    </div>

    <div class="kb-shell">
        <aside class="kb-pane kb-sidebar" aria-label="Categories">
            <div class="kb-pane-header">
                <span class="kb-pane-title">Categories</span>
                @if($canCreateKnowledgeBase ?? true)
                <button type="button" class="kb-icon-btn" data-action="new-category" title="New category" aria-label="New category">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                </button>
                @endif
            </div>
            <nav class="kb-nav">
                <button type="button" class="kb-nav-item active" data-select="all">
                    <span class="kb-nav-label">All articles</span>
                    <span class="kb-count" data-count="all"></span>
                </button>
                <button type="button" class="kb-nav-item" data-select="uncategorized">
                    <span class="kb-nav-label">Uncategorized</span>
                    <span class="kb-count" data-count="uncategorized"></span>
                </button>
                <div class="kb-tree" id="kbTree" role="tree"></div>
            </nav>
        </aside>

        <section class="kb-pane kb-list-pane" aria-label="Articles">
            <div class="kb-pane-header">
                <span class="kb-pane-title" id="kbListTitle">All articles</span>
                @if($canCreateKnowledgeBase ?? true)
                <button type="button" class="btn btn-primary btn-sm" data-action="new-article">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    New article
                </button>
                @endif
            </div>
            <div class="kb-status-tabs" role="tablist">
                <button type="button" class="kb-status-tab active" data-status="all">All</button>
                <button type="button" class="kb-status-tab" data-status="published">Published</button>
                <button type="button" class="kb-status-tab" data-status="draft">Draft</button>
                <button type="button" class="kb-status-tab" data-status="archived">Archived</button>
            </div>
            <div class="kb-article-list" id="kbArticleList" aria-busy="true">
                @include('partials.skeleton-cards', ['count' => 6])
            </div>
        </section>

        <section class="kb-pane kb-detail-pane" aria-label="Article">
            <div class="kb-empty" id="kbEmpty">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"><path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/></svg>
                <p>Select an article to read it.</p>
            </div>

            <article class="kb-reader" id="kbReader" hidden>
                <div class="kb-reader-toolbar">
                    <span class="article-badge" id="kbReaderStatus"></span>
                    <div class="kb-reader-actions">
                        @if($canEditKnowledgeBase ?? true)
                        <button type="button" class="btn btn-secondary btn-sm" data-action="edit-article">Edit</button>
                        @endif
                        @if($canDeleteKnowledgeBase ?? true)
                        <button type="button" class="btn btn-secondary btn-sm kb-danger" data-action="delete-article">Delete</button>
                        @endif
                    </div>
                </div>
                <div class="kb-reader-breadcrumb" id="kbReaderBreadcrumb"></div>
                <h2 class="kb-reader-title" id="kbReaderTitle"></h2>
                <div class="kb-reader-meta" id="kbReaderMeta"></div>
                <div class="content-body kb-reader-body" id="kbReaderBody"></div>
            </article>

            <form class="kb-editor" id="kbEditor" hidden novalidate>
                <div class="kb-editor-toolbar">
                    <span class="kb-pane-title" id="kbEditorHeading">New article</span>
                    <div class="kb-reader-actions">
                        <button type="button" class="btn btn-secondary btn-sm" data-action="cancel-edit">Cancel</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-save="draft">Save draft</button>
                        <button type="button" class="btn btn-secondary btn-sm" data-save="archived">Archive</button>
                        <button type="button" class="btn btn-primary btn-sm" data-save="published">Publish</button>
                    </div>
                </div>
                <input type="text" id="kbEditorTitle" class="kb-editor-title" placeholder="Article title" maxlength="255" required>
                <div class="kb-editor-field">
                    <label for="kbEditorCategory" class="form-label">Category</label>
                    <select id="kbEditorCategory" class="form-input"></select>
                </div>
                <div class="rich-editor kb-editor-rich">
                    <div class="rich-editor-toolbar" data-editor="kbEditorContent">
                        <button type="button" class="rich-editor-btn" data-cmd="bold" title="Bold"><b>B</b></button>
                        <button type="button" class="rich-editor-btn" data-cmd="italic" title="Italic"><i>I</i></button>
                        <button type="button" class="rich-editor-btn" data-cmd="underline" title="Underline"><u>U</u></button>
                        <button type="button" class="rich-editor-btn" data-cmd="strikeThrough" title="Strikethrough"><s>S</s></button>
                        <span class="rich-editor-sep"></span>
                        <select class="rich-editor-select" data-editor="kbEditorContent" title="Text style">
                            <option value="p">Paragraph</option>
                            <option value="h2">Heading 2</option>
                            <option value="h3">Heading 3</option>
                        </select>
                        <span class="rich-editor-sep"></span>
                        <button type="button" class="rich-editor-btn" data-cmd="insertUnorderedList" title="Bullet list">• List</button>
                        <button type="button" class="rich-editor-btn" data-cmd="insertOrderedList" title="Numbered list">1. List</button>
                        <button type="button" class="rich-editor-btn" data-cmd="formatBlock" data-value="blockquote" title="Quote">" Quote</button>
                        <span class="rich-editor-sep"></span>
                        <button type="button" class="rich-editor-btn" data-cmd="createLink" title="Insert link">Link</button>
                        <button type="button" class="rich-editor-btn" data-cmd="insertImage" title="Insert image by URL">Image</button>
                        <button type="button" class="rich-editor-btn" data-cmd="removeFormat" title="Clear formatting">Clear</button>
                    </div>
                    <div id="kbEditorContent" class="rich-editor-content kb-editor-content content-body" contenteditable="true" role="textbox" aria-multiline="true" aria-label="Article content" data-placeholder="Write your article…"></div>
                </div>
            </form>
        </section>
    </div>

    <div class="knowledge-modal" id="kbCategoryModal">
        <div class="knowledge-modal-content knowledge-modal-form">
            <button type="button" class="modal-close" data-action="close-category-modal" aria-label="Close">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
            <div class="modal-header">
                <h2 class="modal-title" id="kbCategoryModalTitle">New category</h2>
            </div>
            <form id="kbCategoryForm" class="modal-form">
                <div class="form-group">
                    <label for="kbCategoryName" class="form-label">Name <span class="required">*</span></label>
                    <input type="text" id="kbCategoryName" class="form-input" required maxlength="100" placeholder="e.g. Getting started">
                </div>
                <div class="form-group">
                    <label for="kbCategoryParent" class="form-label">Parent category</label>
                    <select id="kbCategoryParent" class="form-input"></select>
                </div>
                <div class="modal-form-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-action="close-category-modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="kbCategorySubmit">Save</button>
                </div>
            </form>
        </div>
    </div>
    </div>
    </div>
@endsection
