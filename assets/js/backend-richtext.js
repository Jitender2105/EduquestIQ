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
        if (typeof Quill !== 'function') {
            textarea.dataset.quillReady = '0';
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
