(function () {
    function parseJsonAttribute(value, fallback) {
        if (!value) {
            return fallback;
        }
        try {
            return JSON.parse(value);
        } catch (error) {
            console.warn('backend-tests: failed to parse page data', error);
            return fallback;
        }
    }

    const root = document.getElementById('backend-tests-page');
    if (!root) {
        return;
    }

    const pageData = {
        bundle: parseJsonAttribute(root.dataset.bundle, { questions: [] }),
        attributes: parseJsonAttribute(root.dataset.attributes, []),
        subAttributes: parseJsonAttribute(root.dataset.subAttributes, [])
    };

    const subAttributeMap = {};
    pageData.subAttributes.forEach(function (sub) {
        const attrId = String(sub.attribute_id);
        if (!subAttributeMap[attrId]) {
            subAttributeMap[attrId] = [];
        }
        subAttributeMap[attrId].push(sub);
    });

    const state = {
        builder: null,
        initialized: false,
        questionSeed: 0
    };

    function initEditors(rootNode) {
        if (window.EQRichText) {
            try {
                window.EQRichText.init(rootNode);
            } catch (error) {
                console.warn('backend-tests: rich text init skipped', error);
            }
        }
    }

    function getBuilder() {
        if (state.builder && document.contains(state.builder)) {
            return state.builder;
        }
        state.builder = document.getElementById('questions-builder') || document.querySelector('.eq-questions-list');
        return state.builder;
    }

    function optionRowHtml(questionIndex, optionIndex, option) {
        const checked = option && option.is_correct ? 'checked' : '';
        const value = (option && option.text) ? option.text : '';
        return `
            <div class="eq-option-row" data-option-row>
                <div class="eq-option-card">
                    <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                        <strong class="small text-uppercase text-muted">Option ${optionIndex + 1}</strong>
                        <button type="button" class="btn btn-outline-danger btn-sm" data-remove-option>Remove</button>
                    </div>
                    <textarea class="form-control eq-richtext" data-richtext name="questions[${questionIndex}][options][${optionIndex}][text]">${value}</textarea>
                    <div class="form-check mt-2">
                        <input class="form-check-input" type="checkbox" id="q${questionIndex}_opt${optionIndex}_correct" name="questions[${questionIndex}][options][${optionIndex}][is_correct]" value="1" ${checked}>
                        <label class="form-check-label" for="q${questionIndex}_opt${optionIndex}_correct">is_correct option</label>
                    </div>
                </div>
            </div>`;
    }

    function createQuestionCard(question) {
        const idx = state.questionSeed++;
        const item = question || {};
        const title = item.title || '';
        const type = item.question_type || 'mcq';
        const marks = item.marks || '1';
        const attributeId = item.attribute_id || '';
        const subAttributeId = item.sub_attribute_id || '';
        const weight = item.weight || '1.00';
        const options = Array.isArray(item.options) && item.options.length ? item.options : [
            { text: '', is_correct: 1 },
            { text: '', is_correct: 0 }
        ];

        const card = document.createElement('div');
        card.className = 'eq-question-card';
        card.dataset.questionCard = '1';
        card.dataset.questionIndex = String(idx);
        card.innerHTML = `
            <div class="eq-question-toolbar">
                <h5>Question ${idx + 1}</h5>
                <button type="button" class="btn btn-outline-danger btn-sm" data-remove-question>Remove question</button>
            </div>

            <div class="row g-3">
                <div class="col-12">
                    <div class="eq-inline-label">Question title</div>
                    <textarea class="form-control eq-richtext" data-richtext name="questions[${idx}][title]">${title}</textarea>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Question type</label>
                    <select class="form-select" name="questions[${idx}][question_type]" data-question-type>
                        <option value="mcq" ${type === 'mcq' ? 'selected' : ''}>MCQ</option>
                        <option value="subjective" ${type === 'subjective' ? 'selected' : ''}>Subjective</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Marks</label>
                    <input class="form-control" type="number" min="1" name="questions[${idx}][marks]" value="${marks}">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Weight</label>
                    <input class="form-control" type="number" step="0.01" min="0" name="questions[${idx}][weight]" value="${weight}">
                </div>
                <div class="col-md-6">
                    <label class="form-label">Attribute</label>
                    <select class="form-select" name="questions[${idx}][attribute_id]" data-attribute-select>
                        <option value="">Select attribute</option>
                        ${pageData.attributes.map(function (attr) {
                            return `<option value="${attr.id}" ${String(attributeId) === String(attr.id) ? 'selected' : ''}>${attr.name}</option>`;
                        }).join('')}
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">Sub-attribute</label>
                    <select class="form-select" name="questions[${idx}][sub_attribute_id]" data-sub-attribute-select>
                        <option value="">Select sub-attribute</option>
                    </select>
                </div>
            </div>

            <div class="mt-3" data-options-wrap>
                <div class="d-flex justify-content-between align-items-center gap-2 mb-2">
                    <strong>Options</strong>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-add-option>Add option</button>
                </div>
                <div data-options-list class="d-grid gap-2"></div>
            </div>
        `;

        const optionsList = card.querySelector('[data-options-list]');
        options.forEach(function (option, optionIndex) {
            optionsList.insertAdjacentHTML('beforeend', optionRowHtml(idx, optionIndex, option));
        });

        function populateSubAttributes() {
            const attrSelect = card.querySelector('[data-attribute-select]');
            const subSelect = card.querySelector('[data-sub-attribute-select]');
            const selectedAttr = String(attrSelect.value || '');
            const selectedSub = String(subAttributeId || '');
            const items = subAttributeMap[selectedAttr] || [];
            subSelect.innerHTML = '<option value="">Select sub-attribute</option>' + items.map(function (item) {
                return `<option value="${item.id}" ${String(item.id) === selectedSub ? 'selected' : ''}>${item.name}</option>`;
            }).join('');
        }

        function syncQuestionType() {
            const typeValue = card.querySelector('[data-question-type]').value;
            card.querySelector('[data-options-wrap]').style.display = typeValue === 'mcq' ? '' : 'none';
        }

        card.querySelector('[data-question-type]').addEventListener('change', syncQuestionType);
        card.querySelector('[data-attribute-select]').addEventListener('change', populateSubAttributes);

        populateSubAttributes();
        syncQuestionType();
        return card;
    }

    function addQuestion(question) {
        const builder = getBuilder();
        if (!builder) {
            return null;
        }
        const card = createQuestionCard(question || {});
        builder.appendChild(card);
        initEditors(card);
        return card;
    }

    function renderBundle() {
        const builder = getBuilder();
        if (!builder) {
            return;
        }
        builder.innerHTML = '';
        state.questionSeed = 0;
        (pageData.bundle.questions || []).forEach(function (question) {
            addQuestion(question);
        });
        if (!builder.querySelector('[data-question-card]')) {
            addQuestion();
        }
    }

    function handleClick(event) {
        const builder = getBuilder();
        if (!builder) {
            return;
        }

        const addOptionBtn = event.target.closest('[data-add-option]');
        if (addOptionBtn) {
            const card = addOptionBtn.closest('[data-question-card]');
            if (!card) {
                return;
            }
            const optionsList = card.querySelector('[data-options-list]');
            const nextIndex = card.querySelectorAll('[data-option-row]').length;
            const questionIndex = card.dataset.questionIndex || '0';
            optionsList.insertAdjacentHTML('beforeend', optionRowHtml(questionIndex, nextIndex, { text: '', is_correct: 0 }));
            initEditors(optionsList);
            return;
        }

        const removeOptionBtn = event.target.closest('[data-remove-option]');
        if (removeOptionBtn) {
            const row = removeOptionBtn.closest('[data-option-row]');
            if (row) {
                row.remove();
            }
            return;
        }

        const removeQuestionBtn = event.target.closest('[data-remove-question]');
        if (removeQuestionBtn) {
            const card = removeQuestionBtn.closest('[data-question-card]');
            if (card) {
                card.remove();
            }
            if (!builder.querySelector('[data-question-card]')) {
                addQuestion();
            }
        }
    }

    function bindEvents() {
        if (state.initialized) {
            return;
        }
        const builder = getBuilder();
        const form = document.getElementById('test-builder-form');
        const addButtons = document.querySelectorAll('#btn-add-question-top, #btn-add-question, #btn-add-question-bottom');

        if (builder) {
            builder.addEventListener('click', handleClick);
        }
        addButtons.forEach(function (button) {
            button.addEventListener('click', function (event) {
                event.preventDefault();
                addQuestion();
                const builderNode = getBuilder();
                if (builderNode) {
                    builderNode.scrollIntoView({ behavior: 'smooth', block: 'end' });
                }
            });
        });
        if (form) {
            form.addEventListener('submit', function () {
                if (window.EQRichText) {
                    try {
                        window.EQRichText.init(document);
                    } catch (error) {
                        console.warn('backend-tests: rich text sync skipped', error);
                    }
                }
            });
        }
        state.initialized = true;
    }

    window.eqTestsAddQuestion = function () {
        const created = addQuestion();
        const builder = getBuilder();
        if (created && builder) {
            builder.scrollIntoView({ behavior: 'smooth', block: 'end' });
        }
        return false;
    };

    document.addEventListener('DOMContentLoaded', function () {
        bindEvents();
        renderBundle();
        initEditors(document);
    });
})();
