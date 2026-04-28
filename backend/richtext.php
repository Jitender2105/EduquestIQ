<?php
declare(strict_types=1);
?>
<script src="https://cdn.tiny.cloud/1/no-api-key/tinymce/6/tinymce.min.js" referrerpolicy="origin"></script>
<script>
(function () {
    function initEditor(textarea) {
        if (!window.tinymce || textarea.dataset.editorReady === '1') {
            return;
        }
        tinymce.init({
            target: textarea,
            menubar: false,
            plugins: 'lists link code table',
            toolbar: 'undo redo | bold italic underline | bullist numlist | link table | code',
            height: 220,
            branding: false,
            setup: function (editor) {
                editor.on('change keyup undo redo init', function () {
                    editor.save();
                });
            }
        });
        textarea.dataset.editorReady = '1';
    }

    function initAll() {
        document.querySelectorAll('textarea.eq-richtext, textarea[data-richtext]').forEach(initEditor);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initAll);
    } else {
        initAll();
    }
})();
</script>
