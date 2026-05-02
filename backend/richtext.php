<?php
declare(strict_types=1);
?>
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
    .eq-quill-wrap {
        border: 1px solid rgba(47, 59, 120, 0.14);
        border-radius: 16px;
        overflow: hidden;
        background: #fff;
    }
    .eq-quill-toolbar {
        border: 0;
        border-bottom: 1px solid rgba(47, 59, 120, 0.10);
        background: #f8faff;
    }
    .eq-quill-editor {
        min-height: 180px;
        background: #fff;
    }
    .eq-quill-editor .ql-editor {
        min-height: 180px;
        font-size: 0.98rem;
        line-height: 1.55;
    }
    .eq-quill-editor .ql-editor.ql-blank::before {
        color: #94a0bc;
        font-style: normal;
    }
</style>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js" defer></script>
<script src="<?php echo htmlspecialchars(url_for('assets/js/backend-richtext.js')); ?>" defer></script>
