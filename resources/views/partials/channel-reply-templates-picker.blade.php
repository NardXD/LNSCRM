@php($p = $prefix ?? 'channel')
<div class="ch-tpl-picker" id="{{ $p }}TemplatePicker" hidden>
    <input type="search" class="ch-tpl-picker-search" id="{{ $p }}TemplatePickerSearch" placeholder="Search templates…" autocomplete="off">
    <div class="ch-tpl-picker-list" id="{{ $p }}TemplatePickerList"></div>
</div>
