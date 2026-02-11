<?php
// footer.php
?>
</main>

<?php if (isLoggedIn()): ?>
<footer class="main-footer">
    <div class="footer-container">
        <div>
            &copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?> -
            <span style="color: var(--primary); font-weight: 500;">Version 2.0</span>
        </div>
        <div class="footer-status">
            <div class="status-item online">
                <i class="fas fa-circle"></i>
                <span>Système en ligne</span>
            </div>
            <div class="status-item connected">
                <i class="fas fa-database"></i>
                <span>Base connectée</span>
            </div>
            <div class="status-item">
                <i class="fas fa-clock"></i>
                <span><?php echo date('d/m/Y H:i'); ?></span>
            </div>
        </div>
    </div>
</footer>
<?php endif; ?>

<script>
// Notifications
const notificationBtn = document.getElementById('notificationBtn');
if (notificationBtn) {
    notificationBtn.addEventListener('click', function() {
        // Simuler l'ouverture des notifications
        alert('Fonctionnalité notifications - À implémenter');
    });
}

// Mettre à jour l'heure en temps réel
function updateTime() {
    const now = new Date();
    const timeElements = document.querySelectorAll('.footer-status .status-item:last-child span');
    timeElements.forEach(el => {
        el.textContent = now.toLocaleDateString('fr-FR') + ' ' + now.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });
    });
}

// Mettre à jour l'heure toutes les minutes
setInterval(updateTime, 60000);

// Animation au chargement
document.addEventListener('DOMContentLoaded', function() {
    // Fade in effect
    document.body.style.opacity = '0';
    document.body.style.transition = 'opacity 0.3s ease';
    setTimeout(() => {
        document.body.style.opacity = '1';
    }, 100);

    // Animer les cartes d'action
    const actionCards = document.querySelectorAll('.action-card');
    actionCards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.animation = 'fadeInUp 0.5s ease forwards';
    });
});

// Ajouter les styles d'animation
const style = document.createElement('style');
style.textContent = `
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
document.head.appendChild(style);

// Gestion responsive du menu
function handleResponsive() {
    const navLinks = document.querySelectorAll('.nav-link span:not(.nav-icon)');
    if (window.innerWidth <= 768) {
        navLinks.forEach(span => {
            span.style.display = 'none';
        });
    } else {
        navLinks.forEach(span => {
            span.style.display = 'inline';
        });
    }
}

window.addEventListener('resize', handleResponsive);
handleResponsive(); // Appeler au chargement
</script>
</body>

</html>