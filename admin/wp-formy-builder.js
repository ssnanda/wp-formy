function initWPFormyBuilder() {
    const dropzone = document.getElementById('wpf-dropzone');
    if (!dropzone) return;

    const canvas = document.getElementById('wpf-canvas-fields');
    const emptyState = document.querySelector('.wpf-empty-state');
    const fieldSettingsPanel = document.getElementById('wpf-panel-field');
    
    // This acts as our single source of truth (State)
    let formSchema = {
        title: 'Untitled Form',
        fields: [],
        settings: {
            submit_text: 'Submit',
            notifications_enabled: true
        }
    };

    let activeFieldId = null;
    let formId = null;

    // If the server provided initial form data, hydrate state
    if ( window.wpFormyInitialData ) {
        formId = window.wpFormyInitialData.form_id || null;
        formSchema.title = window.wpFormyInitialData.title || formSchema.title;
        formSchema.fields = window.wpFormyInitialData.schema || formSchema.fields;

        const titleInput = document.getElementById('wpf-form-title');
        if (titleInput) {
            titleInput.value = formSchema.title;
        }

        if (formSchema.fields.length) {
            activeFieldId = formSchema.fields[0].id;
        }
    }

    // Render initial fields (if any)
    renderCanvas();
    if (activeFieldId) {
        const initialField = formSchema.fields.find(f => f.id === activeFieldId);
        if (initialField) {
            openFieldSettings(initialField);
        }
    }

    // 1. Initialize Draggable Items in Left Sidebar
    // Make field buttons draggable and also support click-to-add
    document.querySelectorAll('.wpf-field-btn').forEach(btn => {
        btn.setAttribute('draggable', 'true');
        btn.addEventListener('dragstart', e => {
            e.dataTransfer.effectAllowed = 'copy';
            const payload = JSON.stringify({
                source: 'library',
                type: btn.dataset.type,
                label: btn.dataset.label
            });
            e.dataTransfer.setData('application/json', payload);
            e.dataTransfer.setData('text/plain', payload);
        });

        btn.addEventListener('click', () => {
            addField(btn.dataset.type, btn.dataset.label);
        });
    });

    // 2. Setup Dropzone behavior on Canvas
    let dragCounter = 0;

    function handleDragEnter(e) {
        e.preventDefault();
        dragCounter++;
        dropzone.classList.add('drag-over');
    }

    function handleDragOver(e) {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'copy';
    }

    function handleDragLeave(e) {
        dragCounter--;
        if (dragCounter <= 0) {
            dragCounter = 0;
            dropzone.classList.remove('drag-over');
        }
    }

    function handleDrop(e) {
        e.preventDefault();
        dragCounter = 0;
        dropzone.classList.remove('drag-over');

        const payload = e.dataTransfer.getData('application/json') || e.dataTransfer.getData('text/plain');
        console.log('Dropped payload:', payload);

        if (!payload) {
            return;
        }

        try {
            const data = JSON.parse(payload);
            if (data.source === 'library') {
                addField(data.type, data.label);
            }
        } catch (err) {
            console.log('Drop payload not valid JSON', err);
        }
    }

    [dropzone, canvas].forEach(el => {
        el.addEventListener('dragenter', handleDragEnter);
        el.addEventListener('dragover', handleDragOver);
        el.addEventListener('dragleave', handleDragLeave);
        el.addEventListener('drop', handleDrop);
    });

    // 3. Add Field to State and Render
    function addField(type, defaultLabel) {
        const fieldId = 'field_' + Math.random().toString(36).substr(2, 9);
        const newField = {
            id: fieldId,
            type: type,
            label: defaultLabel,
            placeholder: '',
            required: false,
            css_class: ''
        };
        
        formSchema.fields.push(newField);
        activeFieldId = fieldId;
        
        renderCanvas();
        openFieldSettings(newField);
    }

    // 4. Render Canvas based on State
    function renderCanvas() {
        // Clear current rendered fields
        document.querySelectorAll('.wpf-canvas-field').forEach(el => el.remove());

        if (formSchema.fields.length > 0) {
            emptyState.style.display = 'none';
        } else {
            emptyState.style.display = 'block';
            activeFieldId = null;
            fieldSettingsPanel.innerHTML = '<p style="color: #646970; font-size: 13px;">Select a field in the canvas to edit its settings.</p>';
        }

        formSchema.fields.forEach((field) => {
            const fieldEl = document.createElement('div');
            fieldEl.className = `wpf-canvas-field ${field.id === activeFieldId ? 'active' : ''}`;
            fieldEl.dataset.id = field.id;
            
            fieldEl.innerHTML = `
                <div class="wpf-field-preview">
                    <label style="display:block; font-weight:600; margin-bottom:6px; font-size:13px;">
                        ${field.label} ${field.required ? '<span style="color:#d63638;">*</span>' : ''}
                    </label>
                    <input type="text" placeholder="${field.placeholder || ''}" disabled style="width:100%; border:1px solid #dcdde1; padding:8px; border-radius:4px; background:#f6f7f7;">
                </div>
                <div class="wpf-field-actions">
                    <button class="wpf-action-btn duplicate" data-id="${field.id}" title="Duplicate">⎘</button>
                    <button class="wpf-action-btn delete" data-id="${field.id}" title="Delete">✕</button>
                </div>
            `;

            // Canvas interactions
            fieldEl.addEventListener('click', (e) => {
                e.stopPropagation();
                
                if (e.target.classList.contains('delete')) {
                    formSchema.fields = formSchema.fields.filter(f => f.id !== field.id);
                    renderCanvas();
                } else if (e.target.classList.contains('duplicate')) {
                    const clonedField = JSON.parse(JSON.stringify(field));
                    clonedField.id = 'field_' + Math.random().toString(36).substr(2, 9);
                    const index = formSchema.fields.findIndex(f => f.id === field.id);
                    formSchema.fields.splice(index + 1, 0, clonedField);
                    activeFieldId = clonedField.id;
                    renderCanvas();
                    openFieldSettings(clonedField);
                } else {
                    activeFieldId = field.id;
                    renderCanvas();
                    openFieldSettings(field);
                }
            });

            canvas.appendChild(fieldEl);
        });
    }

    // 5. Dynamic Field Settings Panel
    function openFieldSettings(field) {
        document.querySelector('.wpf-tab[data-target="field"]').click();

        fieldSettingsPanel.innerHTML = `
            <div class="wpf-setting-row"><label>Field Label</label>
                <input type="text" class="wpf-live-input" data-key="label" value="${field.label}">
            </div>
            <div class="wpf-setting-row"><label>Placeholder text</label>
                <input type="text" class="wpf-live-input" data-key="placeholder" value="${field.placeholder || ''}">
            </div>
            <div class="wpf-setting-row"><label>
                <input type="checkbox" class="wpf-live-checkbox" data-key="required" ${field.required ? 'checked' : ''}> Required Field</label>
            </div>
        `;

        // Two-way data binding
        fieldSettingsPanel.querySelectorAll('.wpf-live-input, .wpf-live-checkbox').forEach(input => {
            input.addEventListener('input', (e) => {
                field[e.target.dataset.key] = e.target.type === 'checkbox' ? e.target.checked : e.target.value;
                renderCanvas();
            });
        });
    }

    // 6. Sidebar Tabs Logic
    document.querySelectorAll('.wpf-tab').forEach(tab => {
        tab.addEventListener('click', (e) => {
            document.querySelectorAll('.wpf-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.wpf-settings-panel').forEach(p => p.classList.remove('active'));
            e.target.classList.add('active');
            document.getElementById('wpf-panel-' + e.target.dataset.target).classList.add('active');
        });
    });

    // 7. Save form
    const saveBtn = document.getElementById('wpf-save-form-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', (e) => {
            e.preventDefault();
            saveForm();
        });
    }

    function saveForm() {
        const titleInput = document.getElementById('wpf-form-title');
        const title = titleInput ? titleInput.value.trim() : '';

        if ( ! title ) {
            window.alert('Please enter a form title.');
            return;
        }

        const payload = {
            action: 'wpf_save_form',
            nonce: (window.wpFormyBuilder && window.wpFormyBuilder.nonce_save) ? window.wpFormyBuilder.nonce_save : '',
            form_id: formId,
            title: title,
            schema: JSON.stringify(formSchema),
            status: 'published'
        };

        const formData = new FormData();
        Object.keys(payload).forEach(key => formData.append(key, payload[key]));

        fetch((window.wpFormyBuilder && window.wpFormyBuilder.ajaxurl) ? window.wpFormyBuilder.ajaxurl : ajaxurl, {
            method: 'POST',
            body: formData,
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                formId = data.data.form_id;
                window.alert('Form saved successfully.');
            } else {
                window.alert(data.data || 'Unable to save form.');
            }
        })
        .catch(() => {
            window.alert('Unable to save form.');
        });
    }
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initWPFormyBuilder);
} else {
    initWPFormyBuilder();
}