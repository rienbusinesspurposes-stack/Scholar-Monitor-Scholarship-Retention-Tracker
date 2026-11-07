</section>


            <footer class="main-footer">
                <p>© <span data-year></span> MCC BALUQ Student Scholarship Monitoring System</p>
            </footer>

        </main>

    </div>


    <!-- Logout modal: "Yes, Log Out" submits the form below, which logout.php needs (POST + CSRF) -->
    <div class="modal-overlay" id="logout-modal" role="dialog" aria-modal="true"
         aria-labelledby="logout-title" aria-hidden="true">
        <div class="modal-box">

            <button type="button" class="modal-close" id="close-logout-modal" aria-label="Close">&times;</button>

            <div class="logout-modal-icon" aria-hidden="true">➜]</div>

            <h2 id="logout-title">Log out?</h2>
            <p>Are you sure you want to log out of your BALUQ account?</p>

            <div class="logout-modal-actions">
                <button type="button" class="cancel-button" id="cancel-logout">Cancel</button>
                <button type="button" class="confirm-logout-button" id="confirm-logout">Yes, Log Out</button>
            </div>

        </div>
    </div>

    <form id="logout-form" method="post" action="../logout.php" hidden><?= csrf_field() ?></form>

    <!-- JavaScript -->
    <script src="../assets/js/nav.js"></script>
    <script src="../assets/js/dashboard.js"></script>

</body>

</html>