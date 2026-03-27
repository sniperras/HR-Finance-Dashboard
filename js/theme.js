// Theme Management
class ThemeManager {
    constructor() {
        this.themeKey = 'dashboard_theme';
        this.loadTheme();
        this.initToggle();
    }
    
    loadTheme() {
        const savedTheme = localStorage.getItem(this.themeKey);
        if (savedTheme === 'light') {
            document.body.classList.add('light-theme');
            this.updateToggleButton(true);
        } else {
            document.body.classList.remove('light-theme');
            this.updateToggleButton(false);
        }
    }
    
    toggleTheme() {
        if (document.body.classList.contains('light-theme')) {
            document.body.classList.remove('light-theme');
            localStorage.setItem(this.themeKey, 'dark');
            this.updateToggleButton(false);
        } else {
            document.body.classList.add('light-theme');
            localStorage.setItem(this.themeKey, 'light');
            this.updateToggleButton(true);
        }
    }
    
    updateToggleButton(isLight) {
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            toggleBtn.innerHTML = isLight ? '🌙 Dark' : '☀️ Light';
        }
    }
    
    initToggle() {
        const toggleBtn = document.getElementById('themeToggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', () => this.toggleTheme());
        }
    }
}

// Initialize theme when DOM is ready
document.addEventListener('DOMContentLoaded', () => {
    new ThemeManager();
});