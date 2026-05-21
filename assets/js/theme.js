(function() {
    const themeKey = 'nexuspanel_theme';
    const savedTheme = localStorage.getItem(themeKey) || 'dark'; // Default to dark as it looks more premium
    
    // Apply theme immediately to prevent flicker
    document.documentElement.setAttribute('data-theme', savedTheme);

    window.toggleTheme = function() {
        const currentTheme = localStorage.getItem(themeKey) || 'dark';
        const newTheme = currentTheme === 'dark' ? 'palette' : 'dark';
        
        document.documentElement.setAttribute('data-theme', newTheme);
        if (document.body) document.body.setAttribute('data-theme', newTheme);
        localStorage.setItem(themeKey, newTheme);
        
        // Update icons if they exist
        updateThemeIcons(newTheme);
    };

    function updateThemeIcons(theme) {
        const icons = document.querySelectorAll('.theme-toggle-icon');
        icons.forEach(icon => {
            if (theme === 'dark') {
                icon.classList.remove('fa-moon');
                icon.classList.add('fa-sun');
            } else {
                icon.classList.remove('fa-sun');
                icon.classList.add('fa-moon');
            }
        });
    }

    // Initialize icons on load
    document.addEventListener('DOMContentLoaded', () => {
        const theme = localStorage.getItem(themeKey) || 'dark';
        if (document.body) document.body.setAttribute('data-theme', theme);
        updateThemeIcons(theme);
    });

    // Listen for changes from other tabs to sync immediately
    window.addEventListener('storage', (e) => {
        if (e.key === themeKey) {
            const newTheme = e.newValue || 'dark';
            document.documentElement.setAttribute('data-theme', newTheme);
            if (document.body) document.body.setAttribute('data-theme', newTheme);
            updateThemeIcons(newTheme);
        }
    });
})();
