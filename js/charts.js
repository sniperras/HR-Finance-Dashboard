// Chart rendering functions
class ChartRenderer {
    constructor() {
        this.colors = {
            'BMT': '#00ADB5',
            'LMT': '#4ECDC4',
            'CMT': '#45B7D1',
            'EMT': '#96CEB4',
            'AEP': '#FFEAA7',
            'MSM': '#DDA0DD',
            'QA': '#98D8C8',
            'MRO HR': '#F7B05E',
            'MD/DIV.': '#E67E22'
        };
    }

    renderProgressRing(elementId, percentage) {
        const element = document.getElementById(elementId);
        if (!element) return;

        const radius = 70;
        const circumference = 2 * Math.PI * radius;
        const offset = circumference - (percentage / 100) * circumference;

        element.innerHTML = `
            <svg width="150" height="150">
                <circle cx="75" cy="75" r="${radius}" stroke="#393E46" stroke-width="8" fill="none"/>
                <circle cx="75" cy="75" r="${radius}" stroke="#00ADB5" stroke-width="8" fill="none"
                        stroke-dasharray="${circumference}" stroke-dashoffset="${offset}"
                        style="transform: rotate(-90deg); transform-origin: 50% 50%; transition: stroke-dashoffset 0.5s;"/>
                <text x="75" y="85" text-anchor="middle" fill="#EEEEEE" font-size="24" font-weight="bold">${percentage}%</text>
            </svg>
        `;
    }

    renderBarChart(elementId, data, title) {
        const element = document.getElementById(elementId);
        if (!element) return;

        let html = `<h4>${title}</h4><div class="department-bars">`;
        
        for (const [dept, value] of Object.entries(data)) {
            const color = this.colors[dept] || '#00ADB5';
            html += `
                <div class="department-bar tooltip" data-tooltip="${dept}: ${value}%">
                    <span class="department-name">${dept}</span>
                    <div class="bar-container">
                        <div class="bar-fill" style="width: ${value}%; background: ${color}">
                            <span class="bar-value">${value}%</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        html += `</div>`;
        element.innerHTML = html;
    }

    updateAllCharts(data) {
        // Update overall metrics with progress rings
        if (data.overall_metrics) {
            for (const [metric, value] of Object.entries(data.overall_metrics)) {
                const elementId = `ring-${metric.replace(/\s+/g, '-').toLowerCase()}`;
                this.renderProgressRing(elementId, value);
            }
        }

        // Update department breakdowns
        if (data.department_data) {
            for (const [metric, departments] of Object.entries(data.department_data)) {
                const elementId = `bars-${metric.replace(/\s+/g, '-').toLowerCase()}`;
                this.renderBarChart(elementId, departments, metric);
            }
        }
    }
}

// Data fetching and management
class DataManager {
    constructor() {
        this.currentMonth = new Date().toISOString().slice(0, 7);
        this.chartRenderer = new ChartRenderer();
    }

    async loadMonthlyData(month) {
        try {
            const response = await fetch(`api/get_monthly_data.php?month=${month}`);
            const data = await response.json();
            
            if (data.success) {
                this.currentMonth = month;
                this.chartRenderer.updateAllCharts(data);
                return data;
            } else {
                throw new Error(data.message || 'Failed to load data');
            }
        } catch (error) {
            console.error('Error loading data:', error);
            this.showError('Failed to load dashboard data. Please try again.');
            return null;
        }
    }

    async navigateMonth(direction) {
        const date = new Date(this.currentMonth + '-01');
        if (direction === 'prev') {
            date.setMonth(date.getMonth() - 1);
        } else if (direction === 'next') {
            date.setMonth(date.getMonth() + 1);
        }
        
        const newMonth = date.toISOString().slice(0, 7);
        await this.loadMonthlyData(newMonth);
        
        // Update month display
        const monthDisplay = document.getElementById('current-month');
        if (monthDisplay) {
            monthDisplay.textContent = date.toLocaleDateString('en-US', { 
                year: 'numeric', 
                month: 'long' 
            });
        }
    }

    showError(message) {
        const errorDiv = document.getElementById('error-message');
        if (errorDiv) {
            errorDiv.textContent = message;
            errorDiv.style.display = 'block';
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
        } else {
            alert(message);
        }
    }
}

// Initialize dashboard
document.addEventListener('DOMContentLoaded', () => {
    const dataManager = new DataManager();
    
    // Load current month data
    dataManager.loadMonthlyData(dataManager.currentMonth);
    
    // Setup month navigation
    const prevBtn = document.getElementById('prev-month');
    const nextBtn = document.getElementById('next-month');
    
    if (prevBtn) {
        prevBtn.addEventListener('click', () => dataManager.navigateMonth('prev'));
    }
    if (nextBtn) {
        nextBtn.addEventListener('click', () => dataManager.navigateMonth('next'));
    }
});