        </main>
    </div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            document.querySelectorAll('[data-open-pw]').forEach(function (el) {
                el.addEventListener('click', function () {
                    var modalEl = document.getElementById('pw-modal');
                    if (!modalEl || typeof bootstrap === 'undefined') {
                        return;
                    }
                    bootstrap.Modal.getOrCreateInstance(modalEl).show();
                });
            });

            document.querySelectorAll('form').forEach(function (form) {
                form.addEventListener('invalid', function () {
                    form.classList.add('was-validated');
                }, true);

                form.addEventListener('submit', function (event) {
                    if (event.defaultPrevented) {
                        return;
                    }
                    var btn = form.querySelector('button[type="submit"]');
                    if (!btn) {
                        return;
                    }
                    btn.classList.add('btn-loading');
                    btn.setAttribute('aria-busy', 'true');
                });
            });
        });
    </script>
</body>
</html>
