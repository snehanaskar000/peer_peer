<?php
// includes/footer.php
$base = getBaseUrl();
?>
    </div> <!-- End container -->

    <footer class="mt-auto py-4 border-top border-border" style="background-color: var(--bg-card);">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 text-center text-md-start mb-3 mb-md-0">
                    <span class="text-secondary">&copy; <?php echo date('Y'); ?> </span>
                    <span class="brand-font text-gradient">BookBridge</span>
                    <span class="text-secondary">. All rights reserved.</span>
                </div>
                <div class="col-md-6 text-center text-md-end">
                    <a href="<?php echo $base; ?>index.php" class="text-secondary text-decoration-none me-3 hover-accent-color">Explore Books</a>
                    <a href="mailto:support@bookbridge.example" class="text-secondary text-decoration-none me-3 hover-accent-color"><i class="bi bi-envelope-fill me-1"></i>Contact</a>
                    <a href="https://github.com" target="_blank" class="text-secondary text-decoration-none hover-accent-color"><i class="bi bi-github me-1"></i>GitHub</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
    <!-- Custom Main JS -->
    <script src="<?php echo $base; ?>assets/js/main.js"></script>
</body>
</html>
