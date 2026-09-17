// assets/js/main.js

document.addEventListener('DOMContentLoaded', function() {
    // Bootstrap tooltips initialization
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto-dismiss alerts after 5 seconds
    setTimeout(function() {
        var alerts = document.querySelectorAll('.alert-dismissible');
        alerts.forEach(function(alert) {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        });
    }, 5000);

    // Hide preloader once content is ready
    var preloader = document.getElementById('preloader');
    if (preloader) {
        preloader.classList.add('preloader-hidden');
        setTimeout(function() {
            if (preloader.parentNode) {
                preloader.parentNode.removeChild(preloader);
            }
        }, 700);
    }

    // Back to top button behavior
    var backToTop = document.getElementById('backToTop');
    if (backToTop) {
        var toggleBackToTop = function() {
            if (window.scrollY > 450) {
                backToTop.classList.remove('d-none');
            } else {
                backToTop.classList.add('d-none');
            }
        };
        toggleBackToTop();
        window.addEventListener('scroll', toggleBackToTop);

        backToTop.addEventListener('click', function() {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    // Initial notifications poll and interval
    pollNotifications();
    setInterval(pollNotifications, 10000); // Poll every 10 seconds
});

/**
 * Polls the notifications API and updates the UI badges.
 */
function pollNotifications() {
    // Check if the badges exist in the DOM (indicating user is logged in)
    const msgBadge = document.getElementById('navbar-msg-badge');
    const reqBadge = document.getElementById('navbar-req-badge');

    if (!msgBadge && !reqBadge) return; // Not logged in or elements missing

    // Get the base path
    let baseUrl = '';
    const scripts = document.getElementsByTagName('script');
    for (let i = 0; i < scripts.length; i++) {
        const src = scripts[i].getAttribute('src');
        if (src && src.includes('assets/js/main.js')) {
            baseUrl = src.replace('assets/js/main.js', '');
            break;
        }
    }

    fetch(baseUrl + 'api/notifications.php')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update messages badge
                if (msgBadge) {
                    if (data.unread_messages > 0) {
                        msgBadge.textContent = data.unread_messages;
                        msgBadge.classList.remove('d-none');
                    } else {
                        msgBadge.classList.add('d-none');
                    }
                }

                // Update requests badge
                if (reqBadge) {
                    if (data.pending_requests > 0) {
                        reqBadge.textContent = data.pending_requests;
                        reqBadge.classList.remove('d-none');
                    } else {
                        reqBadge.classList.add('d-none');
                    }
                }
            }
        })
        .catch(err => console.error('Error polling notifications:', err));
}

/**
 * Helper to display image preview when uploading.
 * 
 * @param {HTMLInputElement} input 
 * @param {string} previewElementId 
 */
function previewImage(input, previewElementId) {
    const preview = document.getElementById(previewElementId);
    if (!preview) return;

    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
        };
        reader.readAsDataURL(input.files[0]);
    } else {
        preview.src = '';
        preview.classList.add('d-none');
    }
}
