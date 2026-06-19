(function (window, document, $) {
    'use strict';

    if (!$ || window.__globalMediaPickerLoaded) {
        return;
    }

    window.__globalMediaPickerLoaded = true;

    const configuredBasePath = window.MediaPickerConfig?.basePath
        || document.querySelector('meta[name="media-picker-base-path"]')?.getAttribute('content')
        || '/admin/media-management';
    const basePath = String(configuredBasePath).replace(/\/$/, '');
    const browserUrl = basePath + '/browser';
    const uploadUrl = basePath + '/browser/upload';

    let activeInput = null;
    let selectedItems = new Map();
    let mediaItemsById = new Map();
    let searchTimer = null;
    let browserRequest = null;
    let state = {
        page: 1,
        search: '',
        type: 'all',
        collection: 'all',
        lastPage: 1,
        multiple: false
    };


    /*
     * The Media Picker is loaded globally through AdminLTE. SweetAlert2 keeps a
     * reusable hidden <input type="file" class="swal2-file"> in the DOM for
     * every alert/toast. Without an exclusion guard, that internal input gets
     * enhanced and the "Browse Media" button appears inside all alerts.
     *
     * Keep this list limited to notification libraries and the picker itself;
     * normal Bootstrap/AJAX form modals must still receive Media Picker support.
     */
    const excludedContainerSelector = [
        '#globalMediaPickerModal',
        '.swal2-container',
        '.swal2-popup',
        '.swal2-toast',
        '.toast-container',
        '.toasts-top-right',
        '.toasts-top-left',
        '.toasts-bottom-right',
        '.toasts-bottom-left',
        '.iziToast-wrapper',
        '.noty_layout',
        '.alertify-notifier',
        '[data-media-picker-ignore="1"]'
    ].join(',');

    function isExcludedFileInput(input) {
        if (!(input instanceof HTMLInputElement)) {
            return true;
        }

        if (input.id === 'swal2-file'
            || input.classList.contains('swal2-file')
            || input.closest(excludedContainerSelector)) {
            return true;
        }

        return false;
    }

    function cleanupExcludedEnhancements(root = document) {
        const scope = root instanceof Element || root instanceof Document ? root : document;

        scope.querySelectorAll('.media-picker-actions').forEach(function (actionBox) {
            const input = actionBox.previousElementSibling;

            if (input instanceof HTMLInputElement && isExcludedFileInput(input)) {
                actionBox.remove();
                delete input.dataset.mediaPickerEnhanced;
            }
        });

        scope.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (isExcludedFileInput(input)) {
                delete input.dataset.mediaPickerEnhanced;
            }
        });
    }

    function csrfToken() {
        return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content')
            || document.querySelector('input[name="_token"]')?.value
            || '';
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function notify(type, message) {
        if (window.Swal) {
            Swal.fire({
                icon: type,
                title: type === 'success' ? 'Success' : (type === 'warning' ? 'Warning' : 'Error'),
                text: message
            });
            return;
        }

        window.alert(message);
    }

    function confirmAction(title, text) {
        if (window.Swal) {
            return Swal.fire({
                title: title,
                text: text,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Continue',
                confirmButtonColor: '#dc3545'
            }).then(result => Boolean(result.isConfirmed || result.value));
        }

        return Promise.resolve(window.confirm(text));
    }

    function ensureModal() {
        if (document.getElementById('globalMediaPickerModal')) {
            return;
        }

        const modal = `
            <div class="modal fade" id="globalMediaPickerModal" tabindex="-1" role="dialog" aria-hidden="true">
                <div class="modal-dialog modal-xl modal-dialog-centered" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <div>
                                <h5 class="modal-title font-weight-bold mb-0">
                                    <i class="fas fa-photo-video text-primary mr-2"></i> Media Library
                                </h5>
                                <small class="text-muted">Upload, search, select and reuse existing project media.</small>
                            </div>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>

                        <div class="modal-body">
                            <label class="media-picker-upload-zone d-block mb-3" id="mediaPickerDropZone">
                                <input type="file" id="mediaPickerUploadInput" multiple data-media-picker="off">
                                <i class="fas fa-cloud-upload-alt fa-2x text-primary mb-2"></i>
                                <div class="font-weight-bold">Drop files here or click to upload</div>
                                <small class="text-muted">Maximum 10 files, 10MB per file.</small>
                            </label>

                            <div class="media-picker-toolbar mb-3">
                                <div>
                                    <label class="small font-weight-bold text-muted mb-1">Search</label>
                                    <input type="text" id="mediaPickerSearch" class="form-control" placeholder="Search file, owner or collection...">
                                </div>
                                <div>
                                    <label class="small font-weight-bold text-muted mb-1">Collection</label>
                                    <select id="mediaPickerCollection" class="custom-select">
                                        <option value="all">All Collections</option>
                                    </select>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary" id="mediaPickerRefresh">
                                        <i class="fas fa-sync-alt mr-1"></i> Refresh
                                    </button>
                                </div>
                            </div>

                            <div class="media-picker-grid" id="mediaPickerGrid"></div>

                            <div class="d-flex justify-content-between align-items-center flex-wrap mt-3">
                                <div class="text-muted small" id="mediaPickerInfo">Showing 0 files</div>
                                <div class="btn-group media-picker-pagination" id="mediaPickerPagination"></div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <span class="mr-auto small text-muted" id="mediaPickerSelectedCount">0 selected</span>
                            <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                            <button type="button" class="btn btn-primary" id="mediaPickerUseSelected" disabled>
                                <i class="fas fa-check mr-1"></i> Use Selected
                            </button>
                        </div>
                    </div>
                </div>
            </div>`;

        document.body.insertAdjacentHTML('beforeend', modal);
        bindModalEvents();
    }

    function inferType(input) {
        const explicit = String(input.dataset.mediaPickerType || '').trim().toLowerCase();
        if (['image', 'video', 'document', 'all'].includes(explicit)) {
            return explicit;
        }

        const accept = String(input.getAttribute('accept') || '').trim().toLowerCase();
        const name = String(input.getAttribute('name') || '').trim().toLowerCase();

        const tokens = accept
            .split(',')
            .map(token => token.trim())
            .filter(Boolean);

        const imageExtensions = ['.jpg', '.jpeg', '.png', '.webp', '.gif', '.svg', '.bmp', '.avif'];
        const videoExtensions = ['.mp4', '.webm', '.mov', '.avi', '.mkv', '.m4v'];
        const documentExtensions = ['.pdf', '.doc', '.docx', '.xls', '.xlsx', '.csv', '.txt', '.zip'];

        const hasImage = tokens.some(token => token.startsWith('image/') || imageExtensions.includes(token));
        const hasVideo = tokens.some(token => token.startsWith('video/') || videoExtensions.includes(token));
        const hasDocument = tokens.some(token =>
            token.startsWith('application/')
            || token.startsWith('text/')
            || documentExtensions.includes(token)
        );

        const detectedGroups = [hasImage, hasVideo, hasDocument].filter(Boolean).length;

        // Mixed upload inputs (for example the Media Management replace field)
        // must show every compatible existing file instead of being forced into
        // the document filter merely because the input name is "file".
        if (detectedGroups > 1 || accept === '*/*') {
            return 'all';
        }

        if (hasImage) return 'image';
        if (hasVideo) return 'video';
        if (hasDocument) return 'document';

        // Use the field name only as a final semantic fallback. A generic name
        // such as "file" must remain "all".
        if (/(image|photo|logo|banner|gallery|avatar|favicon|thumbnail|icon)/.test(name)) {
            return 'image';
        }

        if (/video/.test(name)) {
            return 'video';
        }

        if (/(document|attachment|pdf|csv|excel|spreadsheet)/.test(name)) {
            return 'document';
        }

        return 'all';
    }

    function enhanceInput(input) {
        if (!(input instanceof HTMLInputElement)
            || input.type !== 'file'
            || input.dataset.mediaPickerEnhanced === '1'
            || input.dataset.mediaPicker === 'off'
            || input.disabled
            || isExcludedFileInput(input)) {
            return;
        }

        input.dataset.mediaPickerEnhanced = '1';

        const actionBox = document.createElement('div');
        actionBox.className = 'media-picker-actions';
        actionBox.innerHTML = `
            <button type="button" class="btn btn-sm btn-outline-primary js-open-media-picker">
                <i class="fas fa-images mr-1"></i> Browse Media
            </button>
            <span class="media-picker-selected-label"></span>`;

        input.insertAdjacentElement('afterend', actionBox);

        actionBox.querySelector('.js-open-media-picker').addEventListener('click', function () {
            openPicker(input);
        });

        input.addEventListener('change', function () {
            updateInputLabel(input);
        });

        updateInputLabel(input);
    }

    function enhanceFileInputs(root) {
        const scope = root instanceof Element || root instanceof Document ? root : document;

        cleanupExcludedEnhancements(scope);

        scope.querySelectorAll('input[type="file"]').forEach(function (input) {
            if (!isExcludedFileInput(input)) {
                enhanceInput(input);
            }
        });
    }

    function updateInputLabel(input) {
        const label = input.nextElementSibling?.classList.contains('media-picker-actions')
            ? input.nextElementSibling.querySelector('.media-picker-selected-label')
            : null;

        if (!label) return;

        const names = Array.from(input.files || []).map(file => file.name);
        label.textContent = names.length ? names.join(', ') : 'No reusable media selected';
        label.title = names.join(', ');
    }

    function openPicker(input) {
        ensureModal();

        activeInput = input;
        selectedItems.clear();
        mediaItemsById.clear();
        state.page = 1;
        state.search = '';
        state.collection = 'all';
        state.type = inferType(input);
        state.multiple = Boolean(input.multiple);

        $('#mediaPickerSearch').val('');
        $('#mediaPickerSelectedCount').text('0 selected');
        $('#mediaPickerUseSelected').prop('disabled', true);
        $('#mediaPickerUploadInput').attr('accept', input.getAttribute('accept') || '');
        $('#globalMediaPickerModal').modal('show');
        loadMedia();
    }

    function loadMedia(page = state.page) {
        state.page = page;

        if (browserRequest) {
            browserRequest.abort();
        }

        $('#mediaPickerGrid').html(`
            <div class="media-picker-loading">
                <i class="fas fa-spinner fa-spin fa-2x text-primary mb-2"></i>
                <span>Loading media...</span>
            </div>`);

        browserRequest = $.ajax({
            url: browserUrl,
            method: 'GET',
            dataType: 'json',
            cache: false,
            data: {
                page: state.page,
                per_page: 20,
                search: state.search,
                type: state.type,
                collection: state.collection
            },
            success: function (response) {
                if (!response.status) {
                    notify('error', response.message || 'Could not load media library.');
                    return;
                }

                renderCollections(response.collections || []);
                renderMedia(response.items || []);
                renderPagination(response.pagination || {});
            },
            error: function (xhr, status) {
                if (status === 'abort') return;

                let message = xhr.responseJSON?.message || 'Could not load media library.';

                if (xhr.status === 404) {
                    message = 'Media browser route is missing. Add GET /admin/media-management/browser and clear the Laravel route cache.';
                } else if (xhr.status === 403) {
                    message = 'You are not authorized to access the media library.';
                } else if (xhr.status === 419) {
                    message = 'Your session has expired. Reload the page and try again.';
                }

                $('#mediaPickerGrid').html(
                    '<div class="media-picker-empty">' +
                    '<i class="fas fa-exclamation-triangle fa-2x text-danger mb-2"></i>' +
                    '<span>' + escapeHtml(message) + '</span>' +
                    '</div>'
                );
                notify('error', message);
            },
            complete: function () {
                browserRequest = null;
            }
        });
    }

    function renderCollections(collections) {
        const select = $('#mediaPickerCollection');
        const current = state.collection;
        let html = '<option value="all">All Collections</option>';

        collections.forEach(function (collection) {
            html += `<option value="${escapeHtml(collection.value)}">${escapeHtml(collection.label)}</option>`;
        });

        select.html(html).val(current);
        if (select.val() === null) {
            state.collection = 'all';
            select.val('all');
        }
    }

    function previewHtml(item) {
        if (!item.exists) {
            return '<div class="media-picker-file-icon"><i class="fas fa-unlink"></i></div>';
        }

        if (String(item.mime_type).startsWith('image/')) {
            return `<img src="${escapeHtml(item.url)}" alt="${escapeHtml(item.name)}" loading="lazy" onerror="this.parentNode.innerHTML='<div class=&quot;media-picker-file-icon&quot;><i class=&quot;fas fa-image&quot;></i></div>'">`;
        }

        if (String(item.mime_type).startsWith('video/')) {
            return `<video muted preload="metadata"><source src="${escapeHtml(item.url)}" type="${escapeHtml(item.mime_type)}"></video>`;
        }

        return '<div class="media-picker-file-icon"><i class="fas fa-file-alt"></i></div>';
    }

    function renderMedia(items) {
        mediaItemsById.clear();
        const grid = $('#mediaPickerGrid');

        if (!items.length) {
            grid.html('<div class="media-picker-empty"><i class="far fa-images fa-3x mb-2"></i><strong>No media found</strong><span class="small mt-1">Try another search/filter or upload a new file.</span></div>');
            return;
        }

        let html = '';

        items.forEach(function (item) {
            mediaItemsById.set(Number(item.id), item);
            const selected = selectedItems.has(Number(item.id));
            html += `
                <div class="media-picker-card ${selected ? 'is-selected' : ''} ${item.exists ? '' : 'is-missing'}" data-media-id="${Number(item.id)}">
                    <span class="media-picker-select-mark"><i class="fas fa-check"></i></span>
                    <div class="media-picker-card-actions">
                        <button type="button" class="btn btn-xs btn-danger js-media-picker-delete" data-id="${Number(item.id)}" title="Move to trash">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="media-picker-preview">${previewHtml(item)}</div>
                    <div class="media-picker-card-body">
                        <span class="media-picker-name" title="${escapeHtml(item.file_name)}">${escapeHtml(item.name || item.file_name)}</span>
                        <div class="media-picker-meta">
                            <div>${escapeHtml(item.collection_label)}</div>
                            <div>${escapeHtml(item.owner_type)}: ${escapeHtml(item.owner)}</div>
                            <div>${escapeHtml(item.size)}${item.exists ? '' : ' · Missing file'}</div>
                        </div>
                    </div>
                </div>`;
        });

        grid.html(html);
    }

    function renderPagination(pagination) {
        state.lastPage = Number(pagination.last_page || 1);
        const current = Number(pagination.current_page || 1);
        const last = state.lastPage;

        $('#mediaPickerInfo').text(
            pagination.total
                ? `Showing ${pagination.from || 0} to ${pagination.to || 0} of ${pagination.total} files`
                : 'Showing 0 files'
        );

        let html = `
            <button type="button" class="btn btn-sm btn-light border js-media-picker-page" data-page="${Math.max(1, current - 1)}" ${current <= 1 ? 'disabled' : ''}>
                <i class="fas fa-chevron-left"></i>
            </button>`;

        const start = Math.max(1, current - 2);
        const end = Math.min(last, current + 2);

        for (let page = start; page <= end; page++) {
            html += `<button type="button" class="btn btn-sm ${page === current ? 'btn-primary' : 'btn-light border'} js-media-picker-page" data-page="${page}">${page}</button>`;
        }

        html += `
            <button type="button" class="btn btn-sm btn-light border js-media-picker-page" data-page="${Math.min(last, current + 1)}" ${current >= last ? 'disabled' : ''}>
                <i class="fas fa-chevron-right"></i>
            </button>`;

        $('#mediaPickerPagination').html(html);
    }

    function toggleSelection(id) {
        const item = mediaItemsById.get(id);
        if (!item || !item.exists) {
            notify('warning', 'This media database row exists, but its physical file is missing.');
            return;
        }

        if (!state.multiple) {
            selectedItems.clear();
            selectedItems.set(id, item);
            $('.media-picker-card').removeClass('is-selected');
            $(`.media-picker-card[data-media-id="${id}"]`).addClass('is-selected');
            useSelectedMedia();
            return;
        }

        if (selectedItems.has(id)) {
            selectedItems.delete(id);
            $(`.media-picker-card[data-media-id="${id}"]`).removeClass('is-selected');
        } else {
            selectedItems.set(id, item);
            $(`.media-picker-card[data-media-id="${id}"]`).addClass('is-selected');
        }

        updateSelectedState();
    }

    function updateSelectedState() {
        const count = selectedItems.size;
        $('#mediaPickerSelectedCount').text(`${count} selected`);
        $('#mediaPickerUseSelected').prop('disabled', count === 0);
    }

    async function itemToFile(item) {
        const response = await fetch(item.download_url, {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        });

        if (!response.ok) {
            throw new Error(`Could not read ${item.file_name}.`);
        }

        const blob = await response.blob();
        return new File([blob], item.file_name, {
            type: item.mime_type || blob.type || 'application/octet-stream',
            lastModified: Date.now()
        });
    }

    async function useSelectedMedia() {
        if (!activeInput || !selectedItems.size) return;

        const button = $('#mediaPickerUseSelected');
        const oldHtml = button.html();
        button.prop('disabled', true).html('<i class="fas fa-spinner fa-spin mr-1"></i> Preparing...');

        try {
            const dataTransfer = new DataTransfer();

            if (activeInput.multiple) {
                Array.from(activeInput.files || []).forEach(file => dataTransfer.items.add(file));
            }

            for (const item of selectedItems.values()) {
                const file = await itemToFile(item);
                const duplicate = Array.from(dataTransfer.files).some(existing => existing.name === file.name && existing.size === file.size);
                if (!duplicate) dataTransfer.items.add(file);
            }

            activeInput.files = dataTransfer.files;
            activeInput.dispatchEvent(new Event('input', { bubbles: true }));
            activeInput.dispatchEvent(new Event('change', { bubbles: true }));
            updateInputLabel(activeInput);

            const autoApply = activeInput.dataset.mediaPickerAutoApply === '1';

            $('#globalMediaPickerModal').modal('hide');

            if (autoApply) {
                notify('success', 'Media selected. Applying the change now...');
            } else {
                notify('success', `${selectedItems.size} media file(s) selected. Save/update the form to apply the change.`);
            }
        } catch (error) {
            notify('error', error.message || 'Selected media could not be prepared.');
        } finally {
            button.prop('disabled', selectedItems.size === 0).html(oldHtml);
        }
    }

    function uploadFiles(files) {
        if (!files || !files.length) return;

        const token = csrfToken();
        if (!token) {
            notify('error', 'CSRF token not found. Reload the page and try again.');
            return;
        }

        const formData = new FormData();
        Array.from(files).slice(0, 10).forEach(file => formData.append('files[]', file));

        $.ajax({
            url: uploadUrl,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            headers: { 'X-CSRF-TOKEN': token },
            beforeSend: function () {
                $('#mediaPickerDropZone').addClass('is-dragging').find('.font-weight-bold').text('Uploading...');
            },
            success: function (response) {
                notify('success', response.message || 'Media uploaded successfully.');
                state.page = 1;
                loadMedia(1);
            },
            error: function (xhr) {
                const errors = xhr.responseJSON?.errors;
                const firstError = errors ? Object.values(errors).flat()[0] : null;
                notify('error', firstError || xhr.responseJSON?.message || 'Media upload failed.');
            },
            complete: function () {
                $('#mediaPickerDropZone').removeClass('is-dragging').find('.font-weight-bold').text('Drop files here or click to upload');
                $('#mediaPickerUploadInput').val('');
            }
        });
    }

    function deleteMedia(id) {
        const item = mediaItemsById.get(id);
        if (!item) return;

        confirmAction('Move media to trash?', 'The file will be removed from the active media browser. Existing saved sections may still reference it until replaced.').then(function (confirmed) {
            if (!confirmed) return;

            const token = csrfToken();
            $.ajax({
                url: item.delete_url,
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': token },
                data: { _method: 'DELETE' },
                success: function (response) {
                    selectedItems.delete(id);
                    updateSelectedState();
                    notify('success', response.message || 'Media moved to trash.');
                    loadMedia(state.page);
                },
                error: function (xhr) {
                    notify('error', xhr.responseJSON?.message || 'Media could not be moved to trash.');
                }
            });
        });
    }

    function bindModalEvents() {
        $(document).on('click', '.media-picker-card', function (event) {
            if ($(event.target).closest('.js-media-picker-delete').length) return;
            toggleSelection(Number($(this).data('media-id')));
        });

        $(document).on('click', '.js-media-picker-delete', function (event) {
            event.preventDefault();
            event.stopPropagation();
            deleteMedia(Number($(this).data('id')));
        });

        $('#mediaPickerUseSelected').on('click', useSelectedMedia);
        $('#mediaPickerRefresh').on('click', function () { loadMedia(state.page); });

        $('#mediaPickerSearch').on('input', function () {
            state.search = $(this).val().trim();
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function () { loadMedia(1); }, 350);
        });

        $('#mediaPickerCollection').on('change', function () {
            state.collection = $(this).val() || 'all';
            loadMedia(1);
        });

        $(document).on('click', '.js-media-picker-page', function () {
            if ($(this).prop('disabled')) return;
            loadMedia(Number($(this).data('page')));
        });

        $('#mediaPickerUploadInput').on('change', function () {
            uploadFiles(this.files);
        });

        const dropZone = document.getElementById('mediaPickerDropZone');
        ['dragenter', 'dragover'].forEach(type => dropZone.addEventListener(type, function (event) {
            event.preventDefault();
            dropZone.classList.add('is-dragging');
        }));
        ['dragleave', 'drop'].forEach(type => dropZone.addEventListener(type, function (event) {
            event.preventDefault();
            dropZone.classList.remove('is-dragging');
        }));
        dropZone.addEventListener('drop', function (event) {
            uploadFiles(event.dataTransfer.files);
        });

        $('#globalMediaPickerModal').on('shown.bs.modal', function () {
            document.body.classList.add('media-picker-modal-open');
            $('.modal-backdrop').last().css('z-index', 1070);
        });

        $('#globalMediaPickerModal').on('hidden.bs.modal', function () {
            document.body.classList.remove('media-picker-modal-open');
            activeInput = null;
            selectedItems.clear();
            mediaItemsById.clear();
            if (browserRequest) browserRequest.abort();
        });
    }

    $(function () {
        ensureModal();
        cleanupExcludedEnhancements(document);
        enhanceFileInputs(document);

        const observer = new MutationObserver(function (mutations) {
            mutations.forEach(function (mutation) {
                mutation.addedNodes.forEach(function (node) {
                    if (node.nodeType !== Node.ELEMENT_NODE) {
                        return;
                    }

                    cleanupExcludedEnhancements(node);

                    if (!node.matches?.(excludedContainerSelector)
                        && !node.closest?.(excludedContainerSelector)) {
                        enhanceFileInputs(node);
                    }
                });
            });
        });

        observer.observe(document.body, { childList: true, subtree: true });
    });
})(window, document, window.jQuery);
