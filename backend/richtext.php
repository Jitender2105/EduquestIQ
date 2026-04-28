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
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
<script>
(function () {
    const toolbarOptions = [
        ['bold', 'italic', 'underline', 'strike'],
        ['blockquote', 'code-block'],
        ['link', 'image', 'video'],
        [{ header: 1 }, { header: 2 }],
        [{ list: 'ordered' }, { list: 'bullet' }],
        [{ script: 'sub' }, { script: 'super' }],
        [{ indent: '-1' }, { indent: '+1' }],
        [{ direction: 'rtl' }],
        [{ size: ['small', false, 'large', 'huge'] }],
        [{ header: [1, 2, 3, 4, 5, 6, false] }],
        [{ color: [] }, { background: [] }],
        [{ font: [] }],
        [{ align: [] }],
        ['clean']
    ];

    function ensureHtml(textarea) {
        return String(textarea.value || textarea.getAttribute('value') || '');
    }

    function initField(textarea) {
        if (!textarea || textarea.dataset.quillReady === '1') {
            return;
        }
        textarea.dataset.quillReady = '1';

        const wrapper = document.createElement('div');
        wrapper.className = 'eq-quill-wrap mb-2';

        const toolbar = document.createElement('div');
        toolbar.className = 'eq-quill-toolbar';

        const editor = document.createElement('div');
        editor.className = 'eq-quill-editor';

        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(toolbar);
        wrapper.appendChild(editor);
        textarea.style.display = 'none';
        wrapper.insertAdjacentElement('afterend', textarea);

        const quill = new Quill(editor, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions }
        });

        const initialValue = ensureHtml(textarea);
        if (initialValue) {
            quill.clipboard.dangerouslyPasteHTML(initialValue);
        } else {
            quill.setText('');
        }

        quill.on('text-change', function () {
            textarea.value = quill.root.innerHTML;
        });

        textarea.value = quill.root.innerHTML;
    }

    function init(root) {
        (root || document).querySelectorAll('textarea[data-richtext]').forEach(initField);
    }

    window.EQRichText = {
        init: init,
        initField: initField
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }
})();
</script>
