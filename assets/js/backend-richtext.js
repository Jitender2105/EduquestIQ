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

    const quillCssUrl = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css';
    const quillScriptUrl = 'https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js';
    let quillLoadPromise = null;

    function ensureHtml(textarea) {
        return String(textarea.value || textarea.getAttribute('value') || '');
    }

    function ensureQuillCss() {
        const existing = document.querySelector('link[href*="quill"][href*="quill.snow.css"]');
        if (existing) {
            return;
        }
        const link = document.createElement('link');
        link.rel = 'stylesheet';
        link.href = quillCssUrl;
        document.head.appendChild(link);
    }

    function ensureQuill() {
        if (typeof window.Quill === 'function') {
            return Promise.resolve(window.Quill);
        }
        if (quillLoadPromise) {
            return quillLoadPromise;
        }

        quillLoadPromise = new Promise(function (resolve, reject) {
            const script = document.createElement('script');
            script.src = quillScriptUrl;
            script.onload = function () {
                if (typeof window.Quill === 'function') {
                    resolve(window.Quill);
                } else {
                    reject(new Error('Quill loaded without a global constructor.'));
                }
            };
            script.onerror = reject;
            document.head.appendChild(script);
        });

        return quillLoadPromise;
    }

    function syncTextarea(textarea, quill) {
        textarea.value = quill.root.innerHTML;
    }

    function initField(textarea) {
        if (!textarea || textarea.dataset.quillReady === '1') {
            return;
        }
        if (typeof window.Quill !== 'function') {
            return;
        }

        textarea.dataset.quillReady = '1';

        const editor = document.createElement('div');
        editor.className = 'eq-quill-editor';
        editor.innerHTML = ensureHtml(textarea);

        const wrapper = document.createElement('div');
        wrapper.className = 'eq-quill-wrap mb-2';
        textarea.parentNode.insertBefore(wrapper, textarea);
        wrapper.appendChild(editor);
        textarea.style.display = 'none';
        wrapper.insertAdjacentElement('afterend', textarea);

        const quill = new window.Quill(editor, {
            theme: 'snow',
            modules: { toolbar: toolbarOptions }
        });

        quill.on('text-change', function () {
            syncTextarea(textarea, quill);
        });

        textarea._eqRichTextSync = function () {
            syncTextarea(textarea, quill);
        };
        textarea._eqRichTextSync();
    }

    function init(root) {
        ensureQuillCss();
        ensureQuill()
            .then(function () {
                (root || document).querySelectorAll('textarea[data-richtext]').forEach(initField);
            })
            .catch(function (error) {
                console.warn('EduquestIQ backend: Quill editor failed to load.', error);
            });
    }

    function initAddedRichText(node) {
        if (!node || node.nodeType !== 1) {
            return;
        }
        if (node.matches && node.matches('textarea[data-richtext]')) {
            init(node.parentNode || document);
            return;
        }
        if (node.querySelectorAll && node.querySelector('textarea[data-richtext]')) {
            init(node);
        }
    }

    function sync(root) {
        (root || document).querySelectorAll('textarea[data-richtext]').forEach(function (textarea) {
            if (typeof textarea._eqRichTextSync === 'function') {
                textarea._eqRichTextSync();
            }
        });
    }

    window.EQRichText = {
        init: init,
        initField: initField,
        sync: sync
    };

    document.addEventListener('submit', function (event) {
        sync(event.target || document);
    }, true);

    if (typeof MutationObserver === 'function') {
        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(initAddedRichText);
            });
        });

        if (document.documentElement) {
            observer.observe(document.documentElement, { childList: true, subtree: true });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', function () {
            init(document);
        });
    } else {
        init(document);
    }

    window.addEventListener('load', function () {
        init(document);
        sync(document);
    });
})();
