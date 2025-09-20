/**
 * Press Releases Manager - JavaScript
 * Handles accordion functionality and AJAX URL loading
 */

(function($) {
    'use strict';

    // Initialize when document is ready
    $(document).ready(function() {
        initPressReleasesManager();
    });

    function initPressReleasesManager() {
        // Accordion toggle functionality
        $('.press-releases-container').on('click', '.accordion-header', function(e) {
            e.preventDefault();

            const $header = $(this);
            const $item = $header.closest('.press-release-item');
            const $content = $item.find('.accordion-content');
            const $icon = $header.find('.toggle-icon');
            const releaseId = $item.data('release-id');

            // Toggle accordion
            if ($content.hasClass('active')) {
                // Close accordion
                $content.slideUp(300, function() {
                    $content.removeClass('active');
                });
                $header.removeClass('active');
                $icon.removeClass('rotated');
            } else {
                // Close other open accordions (optional - remove for multiple open)
                $('.accordion-content.active').each(function() {
                    const $otherContent = $(this);
                    const $otherHeader = $otherContent.prev('.accordion-header');
                    const $otherIcon = $otherHeader.find('.toggle-icon');

                    $otherContent.slideUp(300, function() {
                        $otherContent.removeClass('active');
                    });
                    $otherHeader.removeClass('active');
                    $otherIcon.removeClass('rotated');
                });

                // Open current accordion
                $content.addClass('active').slideDown(300);
                $header.addClass('active');
                $icon.addClass('rotated');

                // Load URLs if not already loaded
                if (!$content.hasClass('urls-loaded')) {
                    loadPressReleaseUrls(releaseId, $content);
                }
            }
        });

        // Copy URL functionality
        $('.press-releases-container').on('click', '.copy-url-btn', function(e) {
            e.stopPropagation();
            const url = $(this).data('url');
            copyToClipboard(url, $(this));
        });

        // Copy all URLs functionality
        $('.press-releases-container').on('click', '.copy-all-btn', function(e) {
            e.stopPropagation();
            const releaseId = $(this).data('release-id');
            copyAllUrls(releaseId, $(this));
        });
    }

    function loadPressReleaseUrls(releaseId, $content) {
        const $loadingSpinner = $content.find('.loading-spinner');
        const $urlsContent = $content.find('.urls-content');

        // Show loading state
        $loadingSpinner.show();
        $urlsContent.empty();

        // AJAX request to load URLs
        $.ajax({
            url: press_releases_ajax.ajax_url,
            type: 'POST',
            data: {
                action: 'load_press_release_urls',
                release_id: releaseId,
                nonce: press_releases_ajax.nonce
            },
            success: function(response) {
                $loadingSpinner.hide();
                $urlsContent.html(response);
                $content.addClass('urls-loaded');

                // Add fade-in animation
                $urlsContent.find('.press-release-urls').hide().fadeIn(400);
            },
            error: function(xhr, status, error) {
                $loadingSpinner.hide();
                $urlsContent.html(
                    '<div class="error-message">' +
                    '<p style="color: #dc3545; text-align: center; padding: 20px;">' +
                    'Error loading URLs. Please try again.' +
                    '</p></div>'
                );
                console.error('AJAX Error:', error);
            },
            timeout: 15000 // 15 second timeout
        });
    }

    function copyToClipboard(text, $button) {
        // Modern clipboard API
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(function() {
                showCopySuccess($button);
            }).catch(function() {
                fallbackCopyToClipboard(text, $button);
            });
        } else {
            // Fallback for older browsers
            fallbackCopyToClipboard(text, $button);
        }
    }

    function fallbackCopyToClipboard(text, $button) {
        // Create temporary textarea element
        const textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-9999px';
        textArea.style.top = '-9999px';

        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();

        try {
            const successful = document.execCommand('copy');
            if (successful) {
                showCopySuccess($button);
            } else {
                showCopyError($button);
            }
        } catch (err) {
            showCopyError($button);
            console.error('Copy failed:', err);
        }

        document.body.removeChild(textArea);
    }

    function copyAllUrls(releaseId, $button) {
        const $urlsList = $button.closest('.urls-container').find('.urls-list');
        const urls = [];

        $urlsList.find('.url-link').each(function() {
            urls.push($(this).text().trim());
        });

        if (urls.length === 0) {
            showCopyError($button, 'No URLs found to copy');
            return;
        }

        const urlsText = urls.join('\n');
        copyToClipboard(urlsText, $button);
    }

    function showCopySuccess($button, message = 'Copied!') {
        const originalText = $button.text();
        $button.addClass('copied')
               .text(message)
               .prop('disabled', true);

        setTimeout(function() {
            $button.removeClass('copied')
                   .text(originalText)
                   .prop('disabled', false);
        }, 2000);
    }

    function showCopyError($button, message = 'Copy failed') {
        const originalText = $button.text();
        $button.css('background-color', '#dc3545')
               .text(message)
               .prop('disabled', true);

        setTimeout(function() {
            $button.css('background-color', '')
                   .text(originalText)
                   .prop('disabled', false);
        }, 2000);
    }

    // Search functionality (optional enhancement)
    function addSearchFunctionality() {
        const searchHtml = `
            <div class="press-releases-search" style="margin-bottom: 20px;">
                <input type="text" id="press-releases-search-input"
                       placeholder="Search press releases..."
                       style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 4px;">
            </div>
        `;

        $('.press-releases-container').prepend(searchHtml);

        $('#press-releases-search-input').on('input', function() {
            const searchTerm = $(this).val().toLowerCase();

            $('.press-release-item').each(function() {
                const $item = $(this);
                const title = $item.find('.release-title').text().toLowerCase();
                const description = $item.find('.release-description').text().toLowerCase();

                if (title.includes(searchTerm) || description.includes(searchTerm) || searchTerm === '') {
                    $item.fadeIn(200);
                } else {
                    $item.fadeOut(200);
                }
            });
        });
    }

    // Keyboard navigation
    function addKeyboardNavigation() {
        $(document).on('keydown', function(e) {
            if (e.target.tagName.toLowerCase() === 'input' ||
                e.target.tagName.toLowerCase() === 'textarea') {
                return;
            }

            const $activeHeader = $('.accordion-header.active');

            switch(e.key) {
                case 'ArrowUp':
                    e.preventDefault();
                    navigateToAccordion($activeHeader, 'prev');
                    break;
                case 'ArrowDown':
                    e.preventDefault();
                    navigateToAccordion($activeHeader, 'next');
                    break;
                case 'Escape':
                    e.preventDefault();
                    $activeHeader.click(); // Close active accordion
                    break;
            }
        });
    }

    function navigateToAccordion($current, direction) {
        let $target;

        if ($current.length === 0) {
            $target = $('.accordion-header').first();
        } else {
            const $currentItem = $current.closest('.press-release-item');
            $target = direction === 'next' ?
                $currentItem.next('.press-release-item').find('.accordion-header') :
                $currentItem.prev('.press-release-item').find('.accordion-header');

            if ($target.length === 0) {
                $target = direction === 'next' ?
                    $('.accordion-header').first() :
                    $('.accordion-header').last();
            }
        }

        if ($target.length > 0) {
            $target.click();
            $target[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }

    // Performance optimization: Lazy load images in URL descriptions
    function lazyLoadImages() {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy-load');
                    observer.unobserve(img);
                }
            });
        });

        $('.press-releases-container img[data-src]').each(function() {
            imageObserver.observe(this);
        });
    }

    // Initialize optional features
    // Uncomment the features you want to enable:

    // addSearchFunctionality();
    // addKeyboardNavigation();
    // lazyLoadImages();

})(jQuery);