"use strict";

const form = document.querySelector('[data-article-form]');
if (form) {
    const find = selector => form.querySelector(selector);
    // The server keeps this false until the site owner approves the headline translation provider.
    // Disabled mode performs only local slug formatting and never requests a translation.
    const translationEnabled = form.dataset.translationEnabled === 'true';
    const title = find('[data-title-input]');
    const english = find('[data-english-title]');
    const slug = find('[data-article-slug]');
    const mode = find('[data-slug-mode]');
    const source = find('[data-translated-source]');
    const feedback = find('[data-translation-status]');
    const preview = find('[data-url-summary]');
    const body = find('[data-editor]');
    const bodyError = find('[data-editor-error]');
    const status = find('[data-publish-status]');
    const save = find('[data-save-article-button]');
    let timer, controller, pending;
    let revision = 0;
    let composing = false, submitting = false, submittingAgain = false;
    const initialTitle = title.value.trim();
    const initialSlug = slug.value;
    const initialStatus = status.value;
    const englishSlug = value => value.normalize('NFKD').replace(/[\u0300-\u036f]/g, '').toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 260).replace(/-+$/g, '');
    const showMessage = (message, error = false) => {
        feedback.textContent = message;
        feedback.classList.toggle('field-error', error);
    };
    const stopTranslation = () => {
        clearTimeout(timer);
        revision++;
        controller?.abort();
        pending = undefined;
    };
    const translate = () => {
        stopTranslation();
        if (!translationEnabled) {
            showMessage('தானியங்கு மொழிபெயர்ப்பு இயக்கப்படவில்லை. இணைப்புக்கான ஆங்கிலத் தலைப்பை உள்ளிடலாம்.');
            preview.textContent = slug.value || 'ஆங்கிலத் தலைப்பை உள்ளிடவும்';
            return Promise.resolve(false);
        }
        const headline = title.value.trim();
        if (!headline || mode.value === 'manual') return Promise.resolve(false);
        const requestRevision = revision;
        controller = new AbortController();
        const requestController = controller;
        const timeout = setTimeout(() => requestController.abort(), 25000);
        const data = new FormData();
        data.set('_csrf', find('[name="_csrf"]').value);
        data.set('title', headline);
        data.set('id', find('[name="id"]').value);
        showMessage('ஆங்கிலத் தலைப்பும் இணைப்பும் உருவாகின்றன…');
        preview.textContent = 'இணைப்பு உருவாகிறது…';
        pending = (async () => {
            try {
                const response = await fetch(form.dataset.translateUrl, {
                    method: 'POST', body: data, credentials: 'same-origin',
                    headers: {Accept: 'application/json'}, signal: requestController.signal
                });
                const result = await response.json();
                if (requestRevision !== revision || headline !== title.value.trim() || mode.value !== 'auto') return false;
                if (!response.ok || !result.english_title || !result.slug) throw new Error(result.error || 'மொழிபெயர்ப்பு கிடைக்கவில்லை. ஆங்கிலத் தலைப்பு அல்லது இணைப்பை உள்ளிடலாம்.');
                english.value = result.english_title;
                slug.value = result.slug;
                source.value = headline;
                preview.textContent = result.slug;
                showMessage('தமிழ்த் தலைப்பிலிருந்து இணைப்பு உருவாக்கப்பட்டது.');
                return true;
            } catch (error) {
                if (requestRevision !== revision) return false;
                showMessage(error.name === 'AbortError' ? 'மொழிபெயர்ப்பிற்கு நேரம் ஆகிறது. மீண்டும் முயலவும் அல்லது இணைப்பை உள்ளிடவும்.' : error.message, true);
                preview.textContent = 'இணைப்பை உள்ளிடவும் அல்லது மீண்டும் முயலவும்';
                return false;
            } finally {
                clearTimeout(timeout);
                if (requestRevision === revision) pending = undefined;
            }
        })();
        return pending;
    };
    const changedTitle = () => {
        stopTranslation();
        english.value = '';
        source.value = '';
        if (mode.value === 'manual') {
            showMessage('நீங்கள் உள்ளிட்ட இணைப்பு பயன்படுத்தப்படும்.');
            return;
        }
        slug.value = '';
        preview.textContent = !title.value.trim() ? 'தலைப்பை எழுதுங்கள்' : translationEnabled ? 'இணைப்பு உருவாகிறது…' : 'ஆங்கிலத் தலைப்பை உள்ளிடவும்';
        if (!title.value.trim()) { showMessage(''); return; }
        if (translationEnabled && !composing) timer = setTimeout(translate, 900);
    };
    title.addEventListener('compositionstart', () => { composing = true; stopTranslation(); });
    title.addEventListener('compositionend', () => { composing = false; changedTitle(); });
    title.addEventListener('input', changedTitle);
    slug.addEventListener('input', () => {
        stopTranslation();
        mode.value = 'manual';
        preview.textContent = slug.value || 'இணைப்பை உள்ளிடவும்';
        showMessage('நீங்கள் உள்ளிட்ட இணைப்பு பயன்படுத்தப்படும்.');
    });
    english.addEventListener('input', () => {
        stopTranslation();
        mode.value = 'manual';
        source.value = title.value.trim();
        slug.value = englishSlug(english.value);
        preview.textContent = slug.value || 'ஆங்கிலத் தலைப்பை உள்ளிடவும்';
        showMessage('ஆங்கிலத் தலைப்பிலிருந்து இணைப்பு புதுப்பிக்கப்பட்டது.');
    });
    const regenerate = find('[data-regenerate-slug]');
    regenerate.hidden = !translationEnabled;
    regenerate.addEventListener('click', () => {
        mode.value = 'auto';
        slug.value = ''; english.value = ''; source.value = '';
        translate();
    });
    if (!translationEnabled) showMessage('தானியங்கு மொழிபெயர்ப்பு இயக்கப்படவில்லை. ஆங்கிலத் தலைப்பை உள்ளிடும்போது இணைப்பு தானாக மாறும்.');
    const updateBody = () => {
        const words = body.textContent.trim().split(/\s+/u).filter(Boolean).length;
        find('[data-word-count]').textContent = `${words} சொற்கள்`;
        if (words) { bodyError.hidden = true; body.removeAttribute('aria-invalid'); }
    };
    body.addEventListener('input', updateBody);
    updateBody();
    const updatePublish = () => {
        const scheduled = status.value === 'scheduled';
        find('[data-schedule-field]').hidden = !scheduled;
        find('[name="scheduled_at"]').required = scheduled;
        const labels = {draft:'வரைவைச் சேமி', published:'வெளியிடு', scheduled:'வெளியீட்டைத் திட்டமிடு', archived:'காப்பகத்தில் சேமி'};
        const notes = {draft:'வரைவு வாசகர்களுக்குத் தெரியாது.', published:'சேமித்ததும் கட்டுரை வாசகர்களுக்குத் தெரியும்.', scheduled:'தேர்ந்தெடுத்த நேரத்தில் கட்டுரை வெளியிடப்படும்.', archived:'கட்டுரை வாசகர்களுக்குக் காட்டப்படாது.'};
        save.textContent = labels[status.value];
        find('[data-publish-note]').textContent = notes[status.value];
    };
    status.addEventListener('change', updatePublish);
    updatePublish();
    const filterSubcategoryField = () => {
        find('[data-subcategory-field]').hidden = find('[data-subcategory-select]').disabled;
    };
    find('[data-category-select]').addEventListener('change', filterSubcategoryField);
    filterSubcategoryField();
    find('[data-media-filter]')?.addEventListener('input', event => {
        const query = event.target.value.trim().toLocaleLowerCase();
        Array.from(find('[data-featured-image-select]').options).forEach((option, index) => {
            option.hidden = index > 0 && !option.selected && !option.textContent.toLocaleLowerCase().includes(query);
        });
    });
    form.addEventListener('invalid', event => {
        let section = event.target.closest('details');
        while (section) { section.open = true; section = section.parentElement.closest('details'); }
    }, true);
    form.addEventListener('submit', async event => {
        if (form.querySelector('[data-body-image-dialog]')?.open) { event.preventDefault(); return; }
        if (event.defaultPrevented) return;
        if (submittingAgain) return;
        event.preventDefault();
        if (submitting) return;
        if (!body.textContent.trim() && !body.querySelector('img')) {
            bodyError.hidden = false;
            body.setAttribute('aria-invalid', 'true');
            body.focus();
            return;
        }
        submitting = true;
        save.disabled = true;
        try {
            const unchanged = title.value.trim() === initialTitle && slug.value === initialSlug && !!initialSlug;
            if (translationEnabled && mode.value === 'auto' && !unchanged && (!slug.value || source.value !== title.value.trim())) await (pending || translate());
            const needsEnglishLink = initialStatus === 'draft' || !unchanged;
            if (status.value !== 'draft' && mode.value === 'auto' && (!slug.value || (needsEnglishLink && !/^[a-z0-9]+(?:-[a-z0-9]+)*$/.test(slug.value)))) {
                find('[data-url-settings]').open = true;
                showMessage('வெளியிடுவதற்கு ஆங்கிலத் தலைப்பு அல்லது இணைப்பை உள்ளிடவும். வரைவை இப்போதே சேமிக்கலாம்.', true);
                english.focus();
                return;
            }
            find('[data-editor-input]').value = body.innerHTML;
            // A second submit must run after the first submit event has finished.
            // Browsers ignore a synchronous requestSubmit() from inside that event.
            await new Promise(resolve => setTimeout(resolve, 0));
            if (!form.reportValidity()) return;
            submittingAgain = true;
            form.requestSubmit();
        } finally {
            submitting = false;
            save.disabled = false;
        }
    });
}
