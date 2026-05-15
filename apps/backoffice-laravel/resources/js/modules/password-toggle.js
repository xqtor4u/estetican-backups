/**
 * Password Visibility Toggle Module
 * Handles toggling input type between 'password' and 'text'
 */
document.addEventListener('DOMContentLoaded', () => {
    const togglers = document.querySelectorAll('[data-password-toggle]');
    
    togglers.forEach(toggler => {
        toggler.addEventListener('click', (e) => {
            e.preventDefault();
            const targetId = toggler.getAttribute('data-password-toggle');
            const input = document.getElementById(targetId);
            const icon = toggler.querySelector('svg') || toggler;

            if (input) {
                if (input.type === 'password') {
                    input.type = 'text';
                    toggler.classList.add('is-visible');
                    // Change icon if using a specific SVG pattern (simple toggle for now)
                    toggler.title = 'Ocultar contraseña';
                } else {
                    input.type = 'password';
                    toggler.classList.remove('is-visible');
                    toggler.title = 'Mostrar contraseña';
                }
            }
        });
    });
});
