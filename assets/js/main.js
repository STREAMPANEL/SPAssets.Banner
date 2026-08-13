document.addEventListener('DOMContentLoaded', () => {
    const tabs = document.querySelectorAll('.tab-btn');
    const grids = document.querySelectorAll('.grid-section');
    const formatFilter = document.getElementById('format-filter');
    const copyBtns = document.querySelectorAll('.btn-copy');
    const lightbox = document.getElementById('lightbox');
    const lightboxImg = document.getElementById('lightbox-img');
    const lightboxCaption = document.getElementById('lightbox-caption');

    // Tab switching
    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            grids.forEach(g => g.classList.add('hidden'));

            tab.classList.add('active');
            const targetGrid = document.getElementById('sp-grid-' + tab.dataset.size) || document.getElementById('grid-' + tab.dataset.size);
            if (targetGrid) {
                targetGrid.classList.remove('hidden');
            }
            if (formatFilter) {
                filterImages(formatFilter.value);
            }
        });
    });

    // Format filter
    if (formatFilter) {
        formatFilter.addEventListener('change', (e) => {
            filterImages(e.target.value);
        });
    }

    function filterImages(format) {
        const activeGrid = document.querySelector('.grid-section:not(.hidden)');
        if (!activeGrid) return;

        const cards = activeGrid.querySelectorAll('.card');
        let visibleCount = 0;

        cards.forEach(card => {
            const ext = card.dataset.ext;
            if (format === 'all' || ext === format) {
                card.classList.remove('hidden');
                visibleCount++;
            } else {
                card.classList.add('hidden');
            }
        });

        // Show/hide empty state safely using DOM methods
        let emptyState = activeGrid.querySelector('.empty-state');
        if (visibleCount === 0) {
            if (!emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                
                const svgNS = 'http://www.w3.org/2000/svg';
                const svg = document.createElementNS(svgNS, 'svg');
                svg.setAttribute('width', '40');
                svg.setAttribute('height', '40');
                svg.setAttribute('fill', 'none');
                svg.setAttribute('stroke', 'currentColor');
                svg.setAttribute('stroke-width', '2');
                svg.setAttribute('viewBox', '0 0 24 24');

                const path = document.createElementNS(svgNS, 'path');
                path.setAttribute('stroke-linecap', 'round');
                path.setAttribute('stroke-linejoin', 'round');
                path.setAttribute('d', 'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z');
                
                svg.appendChild(path);
                emptyState.appendChild(svg);

                const p = document.createElement('p');
                p.textContent = activeGrid.dataset.noImagesMsg || 'No images found.';
                emptyState.appendChild(p);

                activeGrid.appendChild(emptyState);
            } else {
                emptyState.classList.remove('hidden');
            }
        } else if (emptyState) {
            emptyState.classList.add('hidden');
        }
    }

    // Clipboard Copy
    copyBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const code = btn.getAttribute('data-code');
            if (!code) return;

            navigator.clipboard.writeText(code).then(() => {
                const textSpan = btn.querySelector('span');
                const originalText = textSpan ? textSpan.textContent : '';
                const copiedText = btn.getAttribute('data-copied-text') || 'Copied!';
                
                btn.classList.add('copied');
                if (textSpan) {
                    textSpan.textContent = copiedText;
                }

                setTimeout(() => {
                    btn.classList.remove('copied');
                    if (textSpan) {
                        textSpan.textContent = originalText;
                    }
                }, 2000);
            }).catch(err => {
                console.error('Failed to copy text: ', err);
            });
        });
    });

    // Unobtrusive Event Delegation for Lightbox
    document.addEventListener('click', (e) => {
        const img = e.target.closest('.image-container img');
        if (img) {
            const sizeName = img.getAttribute('data-size') || '';
            showLightbox(img.src, sizeName);
        }
    });

    // Lightbox modal logic
    function showLightbox(src, sizeName) {
        if (!lightbox || !lightboxImg) return;
        lightboxImg.src = src;
        if (lightboxCaption) {
            lightboxCaption.textContent = sizeName;
        }
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function hideLightbox() {
        if (!lightbox) return;
        lightbox.classList.remove('active');
        document.body.style.overflow = '';
        setTimeout(() => {
            if (lightboxImg) lightboxImg.src = '';
        }, 300);
    }

    if (lightbox) {
        lightbox.addEventListener('click', (e) => {
            if (e.target === lightbox || e.target.closest('#lightbox-close')) {
                hideLightbox();
            }
        });
    }

    // Keyboard support (Escape to close lightbox)
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox && lightbox.classList.contains('active')) {
            hideLightbox();
        }
    });

    // Initial run
    if (formatFilter) {
        filterImages(formatFilter.value);
    }
});

