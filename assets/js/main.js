/**
 * Custom Portal Frontend Scripts
 * Online Assignment Submission System
 */

document.addEventListener('DOMContentLoaded', function () {
    // 1. Auto-dismiss flash alerts after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 6000);
    });

    // 2. Generic Confirmation Modals/Prompts for dangerous actions
    const deleteButtons = document.querySelectorAll('.btn-confirm-delete');
    deleteButtons.forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            const itemName = btn.getAttribute('data-item-name') || 'this item';
            if (!confirm(`Are you sure you want to delete ${itemName}? This action cannot be undone.`)) {
                e.preventDefault();
            }
        });
    });

    // 3. Client-side File Upload Validation (Pre-flight check)
    const fileInputs = document.querySelectorAll('input[type="file"][data-max-size]');
    fileInputs.forEach(function (input) {
        input.addEventListener('change', function () {
            const file = this.files[0];
            if (!file) return;

            const maxBytes = parseInt(this.getAttribute('data-max-size'), 10) || (10 * 1024 * 1024);
            const maxMB = (maxBytes / (1024 * 1024)).toFixed(0);

            // Size check
            if (file.size > maxBytes) {
                alert(`File is too large! Maximum allowed file size is ${maxMB} MB. Selected file: ${(file.size / (1024 * 1024)).toFixed(2)} MB.`);
                this.value = '';
                return;
            }

            // Extension check
            const allowedAttr = this.getAttribute('data-allowed-ext');
            if (allowedAttr) {
                const allowedExts = allowedAttr.toLowerCase().split(',').map(s => s.trim().replace('.', ''));
                const fileExt = file.name.split('.').pop().toLowerCase();
                if (!allowedExts.includes(fileExt)) {
                    alert(`Invalid file format! Allowed file types: ${allowedExts.join(', ')}.`);
                    this.value = '';
                    return;
                }
            }
        });
    });

    // 4. Live Table Filter / Search Input
    const tableSearchInputs = document.querySelectorAll('[data-table-filter]');
    tableSearchInputs.forEach(function (input) {
        const tableSelector = input.getAttribute('data-table-filter');
        const targetTable = document.querySelector(tableSelector);
        if (!targetTable) return;

        input.addEventListener('keyup', function () {
            const term = this.value.toLowerCase().trim();
            const rows = targetTable.querySelectorAll('tbody tr');

            rows.forEach(function (row) {
                const text = row.textContent.toLowerCase();
                if (text.includes(term)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    });
});

