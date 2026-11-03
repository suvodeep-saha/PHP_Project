<?php
/**
 * Global Footer Component
 * Online Assignment Submission System
 */
?>
<footer class="bg-white border-top mt-auto py-4">
    <div class="container text-center text-md-start">
        <div class="row align-items-center">
            <div class="col-md-6 mb-3 mb-md-0">
                <div class="d-flex align-items-center justify-content-center justify-content-md-start">
                    <i class="bi bi-mortarboard-fill text-primary fs-4 me-2"></i>
                    <span class="fw-bold text-dark"><?php echo APP_NAME; ?></span>
                </div>
                <p class="text-muted small mb-0 mt-1">
                    Centralized Academic Assignment & Evaluation Management System.
                </p>
            </div>
            <div class="col-md-6 text-center text-md-end">
                <div class="small text-muted mb-1">
                    Powered by <strong>Core PHP 8</strong> &bull; <strong>MySQL</strong> &bull; <strong>Bootstrap 5</strong>
                </div>
                <div class="small text-secondary">
                    &copy; <?php echo date('Y'); ?> <?php echo APP_NAME; ?>. Built for Academic Excellence.
                </div>
            </div>
        </div>
    </div>
</footer>

<!-- Bootstrap 5.3.3 Bundle JS with Popper -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Custom Portal Script -->
<script src="<?php echo BASE_URL; ?>/assets/js/main.js"></script>

</body>
</html>

