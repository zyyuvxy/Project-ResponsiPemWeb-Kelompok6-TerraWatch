/**
 * TerraWatch Application JavaScript Helper
 * Observe, Report, Protect - SDGs 15 Life on Land
 */

document.addEventListener('DOMContentLoaded', function () {
    // Enable Bootstrap tooltips if any
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Auto dismiss standard alerts after 5 seconds
    var alerts = document.querySelectorAll('.alert:not(.alert-permanent)');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });
});

/**
 * SweetAlert2 confirmation dialog for deleting items
 */
function confirmAction(url, message, title) {
    title = title || 'Apakah Anda Yakin?';
    message = message || 'Tindakan ini tidak dapat dibatalkan.';

    if (typeof Swal !== 'undefined') {
        Swal.fire({
            title: title,
            text: message,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#C17B58',
            cancelButtonColor: '#34483B',
            confirmButtonText: 'Ya, Lanjutkan!',
            cancelButtonText: 'Batal'
        }).then(function (result) {
            if (result.isConfirmed) {
                window.location.href = url;
            }
        });
    } else {
        if (confirm(title + '\n' + message)) {
            window.location.href = url;
        }
    }
}

/**
 * Preview single image upload
 */
function previewSingleImage(input, previewElementId) {
    if (input.files && input.files[0]) {
        var reader = new FileReader();
        reader.onload = function (e) {
            var previewEl = document.getElementById(previewElementId);
            if (previewEl) {
                previewEl.src = e.target.result;
                previewEl.style.display = 'block';
            }
        };
        reader.readAsDataURL(input.files[0]);
    }
}
