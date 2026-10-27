"use strict";

function initializeArticleImages() {
    const form = document.querySelector('[data-article-form]');
    const editor = form?.querySelector('[data-editor]');
    const dialog = form?.querySelector('[data-body-image-dialog]');
    const openButton = form?.querySelector('[data-open-body-image]');
    if (!form || !editor || !dialog || !openButton) return;

    const find = selector => dialog.querySelector(selector);
    const fileInput = find('[data-body-image-file]');
    const library = find('[data-body-image-library]');
    const search = find('[data-body-image-search]');
    const altInput = find('[data-body-image-alt]');
    const captionInput = find('[data-body-image-caption]');
    const preview = find('[data-body-image-preview]');
    const previewImage = preview?.querySelector('img');
    const feedback = find('[data-body-image-status]');
    const insertButton = find('[data-body-image-insert]');
    if (!fileInput || !library || !altInput || !captionInput || !feedback || !insertButton) return;

    const uploadEnabled = form.dataset.imageUploadEnabled === 'true';
    const imageTypes = new Set(['image/jpeg', 'image/png', 'image/webp', 'image/gif']);
    const inlineElements = new Set(['A', 'ABBR', 'B', 'BR', 'CODE', 'EM', 'I', 'S', 'SMALL', 'SPAN', 'STRONG', 'SUB', 'SUP', 'U']);
    let savedRange, insertionRange, objectUrl, uploadController;
    let uploading = false, operation = 0;

    const validRange = range => range && editor.contains(range.startContainer) && editor.contains(range.endContainer);
    const rememberSelection = () => {
        if (dialog.open) return;
        const selection = window.getSelection();
        if (selection?.rangeCount && validRange(selection.getRangeAt(0))) savedRange = selection.getRangeAt(0).cloneRange();
    };
    const endRange = () => {
        const range = document.createRange();
        range.selectNodeContents(editor);
        range.collapse(false);
        return range;
    };
    const restoreSelection = range => {
        const target = validRange(range) ? range : endRange();
        editor.focus();
        const selection = window.getSelection();
        selection?.removeAllRanges();
        selection?.addRange(target);
        savedRange = target.cloneRange();
    };
    const showMessage = (message, error = false) => {
        feedback.textContent = message;
        feedback.classList.toggle('field-error', error);
    };
    const clearPreview = () => {
        if (objectUrl) URL.revokeObjectURL(objectUrl);
        objectUrl = undefined;
        if (preview) preview.hidden = true;
        previewImage?.removeAttribute('src');
    };
    const imageUrl = value => {
        const input = String(value).trim();
        const result = new URL(input, location.href);
        if (!['https:', 'http:'].includes(result.protocol)) throw new Error('படத்தின் இணைப்பு செல்லுபடியாகவில்லை. வேறு படத்தைத் தேர்ந்தெடுக்கவும்.');
        // Local media must remain portable when a draft moves to production.
        // Backslashes can act like slashes in URLs, so exclude both forms of a
        // protocol-relative URL before preserving a root-relative path.
        if (/^\/(?![\\/])/.test(input) && result.origin === location.origin) return result.pathname + result.search + result.hash;
        return result.href;
    };
    const showPreview = value => {
        if (!preview || !previewImage) return;
        previewImage.src = value;
        previewImage.alt = altInput.value.trim();
        preview.hidden = false;
    };
    const validateFile = file => {
        if (!imageTypes.has(file.type)) throw new Error('JPG, PNG, WebP அல்லது GIF படத்தைத் தேர்ந்தெடுக்கவும்.');
        if (file.size > 8 * 1024 * 1024) throw new Error('படத்தின் அளவு 8 MB-க்குள் இருக்க வேண்டும்.');
        if (!file.size) throw new Error('இந்தப் படம் காலியாக உள்ளது. வேறு படத்தைத் தேர்ந்தெடுக்கவும்.');
    };
    const setBusy = busy => {
        uploading = busy;
        dialog.setAttribute('aria-busy', String(busy));
        insertButton.disabled = busy;
        fileInput.disabled = busy || !uploadEnabled;
        library.disabled = busy;
        if (search) search.disabled = busy;
        altInput.disabled = busy;
        captionInput.disabled = busy;
    };
    const resetPicker = () => {
        fileInput.value = '';
        library.value = '';
        if (search) search.value = '';
        for (const option of library.options) option.hidden = false;
        altInput.value = '';
        captionInput.value = '';
        clearPreview();
        showMessage('');
        setBusy(false);
    };

    document.addEventListener('selectionchange', rememberSelection);
    editor.addEventListener('input', rememberSelection);
    openButton.addEventListener('pointerdown', event => {
        if (event.button !== 0) return;
        rememberSelection();
        // Keep the writing position while the toolbar receives the click.
        event.preventDefault();
    });
    openButton.addEventListener('click', () => {
        if (dialog.open) return;
        rememberSelection();
        insertionRange = validRange(savedRange) ? savedRange.cloneRange() : endRange();
        resetPicker();
        dialog.showModal();
    });
    dialog.querySelectorAll('[data-close-body-image]').forEach(button => button.addEventListener('click', () => dialog.close()));
    dialog.addEventListener('close', () => {
        operation++;
        uploadController?.abort();
        uploadController = undefined;
        resetPicker();
        restoreSelection(insertionRange);
    });
    dialog.addEventListener('keydown', event => {
        // Text fields live inside the article form; Enter must not publish it.
        if (event.key === 'Enter' && event.target instanceof HTMLInputElement && !['file', 'button', 'submit'].includes(event.target.type)) event.preventDefault();
    });
    form.addEventListener('submit', event => {
        if (!uploading) return;
        event.preventDefault();
        event.stopImmediatePropagation();
        showMessage('படம் பதிவேறுகிறது. முடியும் வரை காத்திருக்கவும்.');
    }, true);

    fileInput.addEventListener('change', () => {
        clearPreview();
        const file = fileInput.files?.[0];
        if (!file) {
            library.disabled = false;
            showMessage('');
            return;
        }
        library.value = '';
        library.disabled = false;
        try {
            if (!uploadEnabled) throw new Error('இப்போது புதிய படத்தைப் பதிவேற்ற முடியவில்லை. பட நூலகத்தைப் பயன்படுத்தவும்.');
            validateFile(file);
            objectUrl = URL.createObjectURL(file);
            showPreview(objectUrl);
            showMessage('பட விளக்கத்தை உள்ளிட்டு “படத்தைச் சேர்” என்பதைத் தேர்ந்தெடுக்கவும்.');
        } catch (error) { showMessage(error.message, true); }
    });
    library.addEventListener('change', () => {
        clearPreview();
        fileInput.value = '';
        library.disabled = false;
        const option = library.selectedOptions[0];
        if (!option?.value) { showMessage(''); return; }
        altInput.value = option.dataset.alt || '';
        try {
            showPreview(imageUrl(option.value));
            showMessage('பட விளக்கத்தைச் சரிபார்த்து கட்டுரையில் சேர்க்கவும்.');
        } catch (error) { showMessage(error.message, true); }
    });
    search?.addEventListener('input', () => {
        const term = search.value.trim().toLocaleLowerCase();
        Array.from(library.options).forEach((option, index) => {
            option.hidden = index > 0 && !option.selected && !option.textContent.toLocaleLowerCase().includes(term);
        });
    });
    altInput.addEventListener('input', () => { if (previewImage) previewImage.alt = altInput.value.trim(); });

    const hasContent = node => !!node.textContent.trim() || !!node.querySelector?.('img,video,audio,iframe,hr,table');
    const isInline = node => node.nodeType === Node.TEXT_NODE || (node.nodeType === Node.ELEMENT_NODE && inlineElements.has(node.tagName));
    const makeParagraph = () => {
        const paragraph = document.createElement('p');
        paragraph.append(document.createElement('br'));
        return paragraph;
    };
    function insertImage(media, alt, caption) {
        const src = imageUrl(media.url);
        const figure = document.createElement('figure');
        const image = document.createElement('img');
        image.src = src;
        image.alt = alt;
        image.loading = 'lazy';
        image.decoding = 'async';
        const width = Number(media.width), height = Number(media.height);
        if (Number.isInteger(width) && width > 0 && Number.isInteger(height) && height > 0) {
            image.width = width;
            image.height = height;
        }
        figure.append(image);
        if (caption) {
            const label = document.createElement('figcaption');
            label.textContent = caption;
            figure.append(label);
        }

        const range = validRange(insertionRange) ? insertionRange.cloneRange() : endRange();
        // A highlighted passage is preserved; the image follows the selection.
        range.collapse(false);
        const node = range.startContainer.nodeType === Node.ELEMENT_NODE ? range.startContainer : range.startContainer.parentElement;
        const existingFigure = node?.closest('figure');
        if (existingFigure && editor.contains(existingFigure)) {
            range.setStartAfter(existingFigure);
            range.collapse(true);
        }

        let reference;
        let trailing = document.createDocumentFragment();
        if (range.startContainer === editor) {
            reference = editor.childNodes[range.startOffset] || null;
        } else {
            let block = range.startContainer;
            while (block.parentNode !== editor) block = block.parentNode;
            reference = block.nextSibling;
            // Extract only the remainder of this top-level block. Range clones its
            // inline ancestors, preserving formatting on both sides of the caret.
            const remainder = document.createRange();
            remainder.setStart(range.startContainer, range.startOffset);
            remainder.setEndAfter(block);
            trailing = remainder.extractContents();
            if (!hasContent(block)) block.remove();
            else if (isInline(block)) {
                const paragraph = document.createElement('p');
                block.replaceWith(paragraph);
                paragraph.append(block);
            }
            for (const child of Array.from(trailing.childNodes)) {
                if (!hasContent(child)) child.remove();
            }
        }

        // Keep the figure a direct editor child, never inside a paragraph,
        // heading, list, quote, or another figure.
        editor.insertBefore(figure, reference);
        let paragraph;
        if (trailing.firstChild?.nodeName === 'P') {
            paragraph = trailing.firstChild;
        } else if (trailing.firstChild && isInline(trailing.firstChild)) {
            paragraph = document.createElement('p');
            while (trailing.firstChild && isInline(trailing.firstChild)) paragraph.append(trailing.firstChild);
            trailing.prepend(paragraph);
        } else if (!trailing.childNodes.length && reference?.nodeName === 'P') {
            paragraph = reference;
        } else {
            paragraph = makeParagraph();
            trailing.prepend(paragraph);
        }
        editor.insertBefore(trailing, reference);
        const after = document.createRange();
        after.selectNodeContents(paragraph);
        after.collapse(true);
        insertionRange = after;
        savedRange = after.cloneRange();
        editor.dispatchEvent(new Event('input', {bubbles: true}));
        dialog.close('inserted');
    }

    insertButton.addEventListener('click', async () => {
        if (uploading) return;
        const alt = altInput.value.trim();
        const caption = captionInput.value.trim();
        if (!alt || Array.from(alt).length > 255) {
            showMessage('255 எழுத்துகளுக்குள் படத்திற்கான விளக்கத்தை உள்ளிடவும்.', true);
            altInput.focus();
            return;
        }
        if (Array.from(caption).length > 500) {
            showMessage('படத்தின் கீழ் காட்டும் குறிப்பை 500 எழுத்துகளுக்குள் எழுதவும்.', true);
            captionInput.focus();
            return;
        }
        const file = fileInput.files?.[0];
        const option = library.selectedOptions[0];
        if (!file && !option?.value) {
            showMessage('ஒரு படத்தைப் பதிவேற்றவும் அல்லது நூலகத்திலிருந்து தேர்ந்தெடுக்கவும்.', true);
            (uploadEnabled ? fileInput : library).focus();
            return;
        }
        const currentOperation = ++operation;
        let timeout;
        try {
            let media;
            if (file) {
                if (!uploadEnabled) throw new Error('புதிய படத்தைப் பதிவேற்ற முடியவில்லை. பட நூலகத்தைப் பயன்படுத்தவும்.');
                validateFile(file);
                const endpoint = new URL(form.dataset.imageUploadUrl, location.href);
                if (endpoint.origin !== location.origin) throw new Error('படத்தைப் பதிவேற்ற முடியவில்லை. பக்கத்தைப் புதுப்பித்து மீண்டும் முயலவும்.');
                const data = new FormData();
                data.set('_csrf', form.querySelector('[name="_csrf"]').value);
                data.set('image', file);
                data.set('alt_text', alt);
                uploadController = new AbortController();
                const controller = uploadController;
                timeout = setTimeout(() => controller.abort(), 60000);
                setBusy(true);
                showMessage('படம் பதிவேறுகிறது…');
                const response = await fetch(endpoint.href, {
                    method: 'POST', body: data, credentials: 'same-origin',
                    headers: {Accept: 'application/json'}, signal: controller.signal
                });
                const result = await response.json().catch(() => ({}));
                if (!response.ok) throw new Error(result.error || 'படத்தைப் பதிவேற்ற முடியவில்லை. மீண்டும் முயலவும்.');
                if (!result.url) throw new Error('படத்தின் இணைப்பு கிடைக்கவில்லை. மீண்டும் முயலவும்.');
                media = result;
            } else {
                media = {url: option.value, width: option.dataset.width, height: option.dataset.height};
            }
            if (currentOperation !== operation || !dialog.open) return;
            if (file && !Array.from(library.options).some(item => item.value === media.url)) {
                const uploaded = document.createElement('option');
                uploaded.value = imageUrl(media.url);
                uploaded.textContent = media.original_name || file.name;
                uploaded.dataset.id = String(media.id || '');
                uploaded.dataset.alt = alt;
                uploaded.dataset.width = String(media.width || '');
                uploaded.dataset.height = String(media.height || '');
                library.append(uploaded);
            }
            insertImage(media, alt, caption);
        } catch (error) {
            if (currentOperation !== operation || !dialog.open) return;
            showMessage(error.name === 'AbortError' ? 'படம் பதிவேறுவதற்கு நேரம் ஆகிறது. மீண்டும் முயலவும்.' : error.message, true);
        } finally {
            clearTimeout(timeout);
            if (currentOperation === operation) {
                uploadController = undefined;
                setBusy(false);
            }
        }
    });
}

initializeArticleImages();
