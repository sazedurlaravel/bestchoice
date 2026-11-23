import $ from 'jquery';
import Popper from 'popper.js';

window.$ = window.jQuery = $;
window.Popper = Popper;

await import('bootstrap');
await import('admin-lte');

const closeSidebarOnInitialLoad = () => {
    document.body.classList.add('sidebar-collapse');
    document.body.classList.remove('sidebar-open');

    if (window.innerWidth <= 992) {
        document.body.classList.add('sidebar-closed');
    }
};

window.addEventListener('load', closeSidebarOnInitialLoad, { once: true });

document.addEventListener('click', (event) => {
    const removeButton = event.target.closest('[data-remove-main-image]');
    if (!removeButton) return;

    const urlInput = document.querySelector('[data-main-image-url]');
    const uploadInput = document.querySelector('[data-main-image-upload]');
    const preview = document.querySelector('[data-main-image-preview]');

    if (removeButton.dataset.removeMainImage === 'current') {
        if (urlInput) urlInput.value = '';
        const form = removeButton.closest('form');
        if (form && !form.querySelector('input[name="remove_main_image"]')) {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'remove_main_image';
            input.value = '1';
            form.append(input);
        }
        removeButton.closest('[data-main-image-current]')?.remove();
    } else {
        if (urlInput) urlInput.value = '';
        if (uploadInput) uploadInput.value = '';
        if (preview) preview.style.display = 'none';
    }
});

document.querySelectorAll('[data-rich-text-editor]').forEach((editor) => {
    const input = editor.querySelector('[data-editor-input]');
    const value = editor.querySelector('[data-editor-value]');
    if (!input || !value) return;

    input.innerHTML = value.value;

    const syncValue = () => {
        value.value = input.innerHTML;
    };

    input.addEventListener('input', syncValue);
    editor.querySelectorAll('[data-editor-command]').forEach((button) => {
        button.addEventListener('mousedown', (event) => event.preventDefault());
        button.addEventListener('click', () => {
            input.focus();
            document.execCommand(button.dataset.editorCommand, false, button.dataset.editorValue ?? null);
            syncValue();
        });
    });

    editor.closest('form')?.addEventListener('submit', syncValue);
});

document.querySelectorAll('.gallery-image-inputs').forEach((list) => {
    list.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-gallery-image]');
        if (removeButton) removeButton.closest('.gallery-image-row')?.remove();
    });
});

document.querySelectorAll('[data-remove-existing-gallery]').forEach((button) => {
    button.addEventListener('click', () => {
        const container = button.closest('.gallery-existing')?.nextElementSibling;
        if (!container) return;

        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'remove_gallery_image_ids[]';
        input.value = button.dataset.removeExistingGallery;
        container.append(input);
        button.closest('figure')?.remove();
    });
});

document.querySelectorAll('[data-gallery-upload]').forEach((input) => {
    const preview = document.getElementById('gallery-upload-preview');
    if (!preview) return;

    let selectedFiles = [];

    const renderPreviews = () => {
        preview.querySelectorAll('img[data-preview-url]').forEach((image) => URL.revokeObjectURL(image.dataset.previewUrl));
        preview.replaceChildren();

        selectedFiles.forEach((file, index) => {
            const imageUrl = URL.createObjectURL(file);
            const figure = document.createElement('figure');
            figure.style.cssText = 'position:relative;margin:0;text-align:center;width:90px';

            const image = document.createElement('img');
            image.src = imageUrl;
            image.dataset.previewUrl = imageUrl;
            image.alt = file.name;
            image.style.cssText = 'width:90px;height:90px;object-fit:cover;border-radius:8px;border:1px solid #d1d5db';

            const removeButton = document.createElement('button');
            removeButton.type = 'button';
            removeButton.className = 'btn secondary';
            removeButton.setAttribute('aria-label', 'ছবি সরান');
            removeButton.title = 'ছবি সরান';
            removeButton.dataset.removeUploadIndex = String(index);
            removeButton.style.cssText = 'position:absolute;top:-8px;right:-8px;width:24px;height:24px;padding:0;border-radius:50%;display:flex;align-items:center;justify-content:center;background:#fff;color:#b42318;border:1px solid #b42318';
            removeButton.innerHTML = '<i class="fa-solid fa-xmark" aria-hidden="true"></i><span class="sr-only">ছবি সরান</span>';

            const caption = document.createElement('figcaption');
            caption.className = 'muted';
            caption.style.cssText = 'display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;margin-top:4px';
            caption.textContent = file.name;

            figure.append(image, removeButton, caption);
            preview.append(figure);
        });

        preview.style.display = selectedFiles.length ? 'flex' : 'none';
    };

    input.addEventListener('change', () => {
        selectedFiles = Array.from(input.files || []).slice(0, 20);
        renderPreviews();
    });

    preview.addEventListener('click', (event) => {
        const removeButton = event.target.closest('[data-remove-upload-index]');
        if (!removeButton) return;

        selectedFiles.splice(Number(removeButton.dataset.removeUploadIndex), 1);
        const transfer = new DataTransfer();
        selectedFiles.forEach((file) => transfer.items.add(file));
        input.files = transfer.files;
        renderPreviews();
    });
});
