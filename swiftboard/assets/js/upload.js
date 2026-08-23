/**
 * SwiftBoard — Image Upload avec conversion AVIF + modération
 *
 * Upload une image → conversion AVIF côté serveur → insertion dans le textarea
 * Les images sont en attente de modération admin avant d'être visibles.
 */
(function() {
    'use strict';

    const uploadedImages = [];

    function initUpload(zoneId, fileId, previewId, textareaId) {
        const fileInput = document.getElementById(fileId);
        const preview = document.getElementById(previewId);
        const zone = document.getElementById(zoneId);
        const textarea = document.getElementById(textareaId);

        if (!fileInput || !preview || !zone) {
            return;
        }

        // File selection
        fileInput.addEventListener('change', function(e) {
            const files = e.target.files;
            for (let i = 0; i < files.length; i++) {
                uploadFile(files[i], preview, textarea);
            }
            fileInput.value = ''; // Reset for re-upload
        });

        // Drag & drop
        zone.addEventListener('dragover', function(e) {
            e.preventDefault();
            zone.classList.add('dragover');
        });
        zone.addEventListener('dragleave', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
        });
        zone.addEventListener('drop', function(e) {
            e.preventDefault();
            zone.classList.remove('dragover');
            const files = e.dataTransfer.files;
            for (let i = 0; i < files.length; i++) {
                if (files[i].type.startsWith('image/')) {
                    uploadFile(files[i], preview, textarea);
                }
            }
        });
    }

    function uploadFile(file, preview, textarea) {
        // Validate size
        if (file.size > 10 * 1024 * 1024) {
            addPreviewItem(preview, null, 'error', 'Fichier trop volumineux (max 10 Mo)');
            return;
        }

        // Validate type
        const allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (allowed.indexOf(file.type) === -1) {
            addPreviewItem(preview, null, 'error', 'Format non supporté');
            return;
        }

        // Create preview item with progress
        const itemId = 'upload-' + Date.now() + '-' + Math.random().toString(36).substr(2, 5);
        const item = document.createElement('div');
        item.className = 'swiftboard-preview-item';
        item.id = itemId;

        // Thumbnail preview
        const reader = new FileReader();
        reader.onload = function(e) {
            item.innerHTML = '<img src="' + e.target.result + '" alt="preview">' +
                '<div class="swiftboard-upload-progress"><div class="swiftboard-upload-progress-bar" style="width: 0%"></div></div>' +
                '<div class="swiftboard-preview-status pending">⏳ Upload...</div>';
        };
        reader.readAsDataURL(file);
        preview.appendChild(item);

        // Upload via REST API
        const formData = new FormData();
        formData.append('image', file);

        const xhr = new XMLHttpRequest();
        // Fix: normalize URL to avoid double slash if restUrl ends with '/'
        const baseUrl = swiftboardUpload.restUrl.replace(/\/+$/, '');
        xhr.open('POST', baseUrl + '/swiftboard/v1/upload');

        // Add nonce
        xhr.setRequestHeader('X-WP-Nonce', swiftboardUpload.nonce);

        xhr.upload.onprogress = function(e) {
            if (e.lengthComputable) {
                const percent = Math.round((e.loaded / e.total) * 100);
                const bar = item.querySelector('.swiftboard-upload-progress-bar');
                if (bar) {
                    bar.style.width = percent + '%';
                }
            }
        };

        xhr.onload = function() {
            const bar = item.querySelector('.swiftboard-upload-progress-bar');
            if (bar) {
                bar.style.width = '100%';
            }
            // v4.6.2 : statusEl déclaré une seule fois en haut du scope (fix no-redeclare)
            const statusEl = item.querySelector('.swiftboard-preview-status');

            if (xhr.status === 200) {
                const response = JSON.parse(xhr.responseText);

                if (response.success) {
                    // Update preview with actual AVIF image
                    const img = item.querySelector('img');
                    if (img) {
                        img.src = response.image_url;
                    }

                    if (statusEl) {
                        statusEl.className = 'swiftboard-preview-status success';
                        statusEl.textContent = '✅ ' + response.message;
                    }

                    // Insert image tag into textarea
                    if (textarea) {
                        const imgTag = '\n<img src="' + response.image_url + '" alt="image" loading="lazy">\n';
                        const currentPos = textarea.selectionStart;
                        const textBefore = textarea.value.substring(0, currentPos);
                        const textAfter = textarea.value.substring(currentPos);
                        textarea.value = textBefore + imgTag + textAfter;
                        textarea.selectionStart = textarea.selectionEnd = currentPos + imgTag.length;
                        textarea.focus();
                    }

                    // Add remove button
                    const removeBtn = document.createElement('button');
                    removeBtn.className = 'swiftboard-preview-remove';
                    removeBtn.textContent = '×';
                    removeBtn.setAttribute('aria-label', 'Supprimer');
                    removeBtn.onclick = function() {
                        item.remove();
                    };
                    item.appendChild(removeBtn);

                    uploadedImages.push({
                        id: response.image_id,
                        url: response.image_url,
                    });

                } else {
                    if (statusEl) {
                        statusEl.className = 'swiftboard-preview-status error';
                        statusEl.textContent = '❌ Erreur';
                    }
                }
            } else {
                if (statusEl) {
                    statusEl.className = 'swiftboard-preview-status error';
                    try {
                        const err = JSON.parse(xhr.responseText);
                        statusEl.textContent = '❌ ' + (err.message || 'Erreur');
                    } catch(e) {
                        statusEl.textContent = '❌ Erreur serveur';
                    }
                }
            }

            // Hide progress bar after 1s
            setTimeout(function() {
                const progress = item.querySelector('.swiftboard-upload-progress');
                if (progress) {
                    progress.style.display = 'none';
                }
            }, 1000);
        };

        xhr.onerror = function() {
            // v4.6.2 : statusEl déjà déclaré dans le scope parent (uploadFile)
            const errStatusEl = item.querySelector('.swiftboard-preview-status');
            if (errStatusEl) {
                errStatusEl.className = 'swiftboard-preview-status error';
                errStatusEl.textContent = '❌ Erreur réseau';
            }
        };

        xhr.send(formData);
    }

    function addPreviewItem(preview, src, status, message) {
        const item = document.createElement('div');
        item.className = 'swiftboard-preview-item';
        item.innerHTML = (src ? '<img src="' + src + '" alt="">' : '<div style="width:100px;height:100px;background:#fee2e2;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:2rem;">❌</div>') +
            '<div class="swiftboard-preview-status ' + status + '">' + message + '</div>';
        preview.appendChild(item);
    }

    // Initialize when DOM is ready
    function init() {
        // Topic form
        initUpload('swiftboard-upload-topic', 'swiftboard-file-topic', 'swiftboard-preview-topic', 'bbp_topic_content');

        // Reply form
        initUpload('swiftboard-upload-reply', 'swiftboard-file-reply', 'swiftboard-preview-reply', 'bbp_reply_content');
    }

    // EXI-QUAL-06 : la configuration arrive par des attributs data-* sur
    // #sb-upload-config, et non plus par wp_localize_script() qui emettait un
    // <script> inline bloque par la CSP en enforce.
    if (typeof swiftboardUpload === 'undefined') {
        const cfgEl = document.getElementById('sb-upload-config');
        window.swiftboardUpload = cfgEl ? {
            restUrl: cfgEl.getAttribute('data-rest-url') || '/wp-json',
            nonce: cfgEl.getAttribute('data-nonce') || ''
        } : {
            restUrl: (typeof wpApiSettings !== 'undefined' && wpApiSettings.root) ? wpApiSettings.root : '/wp-json',
            nonce: (typeof wpApiSettings !== 'undefined' && wpApiSettings.nonce) ? wpApiSettings.nonce : ''
        };
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();

