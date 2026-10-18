// ===== CHARTS.JS - GRAPHIQUES ET VISUALISATIONS =====

class ChartsManager {
    constructor() {
        this.charts = new Map();
        this.defaultOptions = {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        usePointStyle: true,
                        font: {
                            family: "'Segoe UI', system-ui, sans-serif",
                            size: 12
                        }
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(255, 255, 255, 0.95)',
                    titleColor: '#2c3e50',
                    bodyColor: '#2c3e50',
                    borderColor: '#e0e0e0',
                    borderWidth: 1,
                    cornerRadius: 8,
                    displayColors: true,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            if (label) {
                                label += ': ';
                            }
                            if (context.parsed.y !== undefined) {
                                label += context.parsed.y.toLocaleString();
                            } else {
                                label += context.parsed.toLocaleString();
                            }
                            return label;
                        }
                    }
                }
            },
            animation: {
                duration: 1000,
                easing: 'easeOutQuart'
            },
            elements: {
                line: {
                    tension: 0.4
                }
            }
        };
        this.init();
    }

    init() {
        this.initializeCharts();
        this.initializeResizeHandler();
    }

    // Initialisation de tous les graphiques sur la page
    initializeCharts() {
        // Graphique de présence globale
        this.initAttendanceChart();
        
        // Graphique de répartition des présences
        this.initAttendanceDistributionChart();
        
        // Graphique d'évolution mensuelle
        this.initMonthlyEvolutionChart();
        
        // Graphique des cours populaires
        this.initPopularCoursesChart();
        
        // Graphique de performance des étudiants
        this.initStudentPerformanceChart();
        
        // Graphique de comparaison par séance
        this.initSessionComparisonChart();
        
        // Graphique radar des compétences (exemple)
        this.initRadarChart();
    }

    // Graphique de présence globale
    initAttendanceChart() {
        const ctx = document.getElementById('attendanceChart');
        if (!ctx) return;

        const data = this.getAttendanceData();
        
        this.charts.set('attendance', new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: data.labels,
                datasets: [{
                    data: data.values,
                    backgroundColor: [
                        '#27ae60', // Présent
                        '#e74c3c', // Absent
                        '#f39c12', // Retard
                        '#3498db'  // Excusé
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff',
                    hoverOffset: 15
                }]
            },
            options: {
                ...this.defaultOptions,
                cutout: '60%',
                plugins: {
                    ...this.defaultOptions.plugins,
                    title: {
                        display: true,
                        text: 'Répartition des Présences',
                        font: {
                            size: 16,
                            weight: 'bold'
                        },
                        padding: 20
                    }
                }
            }
        }));
    }

    // Graphique de répartition des présences
    initAttendanceDistributionChart() {
        const ctx = document.getElementById('attendanceDistributionChart');
        if (!ctx) return;

        const data = this.getDistributionData();
        
        this.charts.set('distribution', new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Taux de présence (%)',
                    data: data.values,
                    backgroundColor: this.generateGradient(ctx, '#3498db', '#2980b9'),
                    borderColor: '#2980b9',
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                ...this.defaultOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        }));
    }

    // Graphique d'évolution mensuelle
    initMonthlyEvolutionChart() {
        const ctx = document.getElementById('monthlyEvolutionChart');
        if (!ctx) return;

        const data = this.getMonthlyData();
        
        this.charts.set('monthly', new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: 'Taux de présence',
                    data: data.values,
                    backgroundColor: this.generateGradient(ctx, 'rgba(52, 152, 219, 0.1)', 'rgba(52, 152, 219, 0)'),
                    borderColor: '#3498db',
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                ...this.defaultOptions,
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            }
                        }
                    },
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    }
                },
                plugins: {
                    ...this.defaultOptions.plugins,
                    title: {
                        display: true,
                        text: 'Évolution Mensuelle',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                }
            }
        }));
    }

    // Graphique des cours populaires
    initPopularCoursesChart() {
        const ctx = document.getElementById('popularCoursesChart');
        if (!ctx) return;

        const data = this.getPopularCoursesData();
        
        this.charts.set('popularCourses', new Chart(ctx, {
            type: 'horizontalBar',
            data: {
                labels: data.labels,
                datasets: [{
                    label: "Nombre d'étudiants",
                    data: data.values,
                    backgroundColor: this.generateGradient(ctx, '#9b59b6', '#8e44ad'),
                    borderColor: '#8e44ad',
                    borderWidth: 2,
                    borderRadius: 6
                }]
            },
            options: {
                ...this.defaultOptions,
                indexAxis: 'y',
                scales: {
                    x: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        }
                    },
                    y: {
                        grid: {
                            display: false
                        }
                    }
                },
                plugins: {
                    ...this.defaultOptions.plugins,
                    title: {
                        display: true,
                        text: 'Cours les Plus Populaires',
                        font: {
                            size: 16,
                            weight: 'bold'
                        }
                    }
                }
            }
        }));
    }

    // Graphique de performance des étudiants
    initStudentPerformanceChart() {
        const ctx = document.getElementById('studentPerformanceChart');
        if (!ctx) return;

        const data = this.getStudentPerformanceData();
        
        this.charts.set('performance', new Chart(ctx, {
            type: 'scatter',
            data: {
                datasets: [{
                    label: 'Étudiants',
                    data: data.points,
                    backgroundColor: this.generatePerformanceColors(data.points),
                    borderColor: '#ffffff',
                    borderWidth: 2,
                    pointRadius: 8,
                    pointHoverRadius: 10
                }]
            },
            options: {
                ...this.defaultOptions,
                scales: {
                    y: {
                        title: {
                            display: true,
                            text: 'Taux de Présence (%)'
                        },
                        min: 0,
                        max: 100
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Nombre de Séances'
                        },
                        type: 'linear',
                        position: 'bottom'
                    }
                },
                plugins: {
                    ...this.defaultOptions.plugins,
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return `Étudiant: ${context.raw.student}, Présence: ${context.raw.y}%, Séances: ${context.raw.x}`;
                            }
                        }
                    }
                }
            }
        }));
    }

    // Graphique de comparaison par séance
    initSessionComparisonChart() {
        const ctx = document.getElementById('sessionComparisonChart');
        if (!ctx) return;

        const data = this.getSessionComparisonData();
        
        this.charts.set('sessionComparison', new Chart(ctx, {
            type: 'radar',
            data: {
                labels: data.labels,
                datasets: data.datasets
            },
            options: {
                ...this.defaultOptions,
                scales: {
                    r: {
                        angleLines: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        pointLabels: {
                            font: {
                                size: 12
                            }
                        },
                        min: 0,
                        max: 100,
                        ticks: {
                            callback: function(value) {
                                return value + '%';
                            },
                            stepSize: 20
                        }
                    }
                },
                elements: {
                    line: {
                        borderWidth: 3
                    }
                }
            }
        }));
    }

    // Graphique radar (exemple avancé)
    initRadarChart() {
        const ctx = document.getElementById('radarChart');
        if (!ctx) return;

        const data = this.getRadarData();
        
        this.charts.set('radar', new Chart(ctx, {
            type: 'radar',
            data: {
                labels: ['Présence', 'Ponctualité', 'Participation', 'Progression', 'Assiduité'],
                datasets: [{
                    label: 'Performance Moyenne',
                    data: [85, 78, 92, 88, 95],
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: '#3498db',
                    pointBackgroundColor: '#3498db',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#3498db'
                }]
            },
            options: {
                ...this.defaultOptions,
                scales: {
                    r: {
                        angleLines: {
                            display: true
                        },
                        suggestedMin: 0,
                        suggestedMax: 100
                    }
                }
            }
        }));
    }

    // Gestion du redimensionnement
    initializeResizeHandler() {
        let resizeTimeout;
        window.addEventListener('resize', () => {
            clearTimeout(resizeTimeout);
            resizeTimeout = setTimeout(() => {
                this.charts.forEach(chart => {
                    chart.resize();
                });
            }, 250);
        });
    }

    // Méthodes utilitaires pour les données
    getAttendanceData() {
        // Données simulées - à remplacer par des données réelles
        return {
            labels: ['Présents', 'Absents', 'Retards', 'Excusés'],
            values: [65, 15, 12, 8]
        };
    }

    getDistributionData() {
        return {
            labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam'],
            values: [85, 92, 78, 88, 95, 45]
        };
    }

    getMonthlyData() {
        return {
            labels: ['Jan', 'Fév', 'Mar', 'Avr', 'Mai', 'Juin'],
            values: [75, 82, 78, 85, 90, 88]
        };
    }

    getPopularCoursesData() {
        return {
            labels: ['DEV101', 'BDD201', 'WEB301', 'MATH101', 'PHYS201'],
            values: [45, 38, 32, 28, 25]
        };
    }

    getStudentPerformanceData() {
        return {
            points: [
                { x: 20, y: 95, student: 'Alice' },
                { x: 18, y: 88, student: 'Bob' },
                { x: 22, y: 92, student: 'Charlie' },
                { x: 15, y: 78, student: 'Diana' },
                { x: 25, y: 85, student: 'Eve' }
            ]
        };
    }

    getSessionComparisonData() {
        return {
            labels: ['Séance 1', 'Séance 2', 'Séance 3', 'Séance 4', 'Séance 5'],
            datasets: [
                {
                    label: 'Groupe A',
                    data: [85, 78, 90, 82, 88],
                    backgroundColor: 'rgba(52, 152, 219, 0.2)',
                    borderColor: '#3498db',
                    pointBackgroundColor: '#3498db'
                },
                {
                    label: 'Groupe B',
                    data: [78, 85, 82, 90, 85],
                    backgroundColor: 'rgba(46, 204, 113, 0.2)',
                    borderColor: '#2ecc71',
                    pointBackgroundColor: '#2ecc71'
                }
            ]
        };
    }

    getRadarData() {
        // Données pour le graphique radar
        return {
            labels: ['Compétence 1', 'Compétence 2', 'Compétence 3', 'Compétence 4', 'Compétence 5'],
            datasets: [
                {
                    label: 'Moyenne Classe',
                    data: [75, 80, 85, 78, 82],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)'
                }
            ]
        };
    }

    // Génération de dégradés
    generateGradient(ctx, color1, color2) {
        const gradient = ctx.createLinearGradient(0, 0, 0, 400);
        gradient.addColorStop(0, color1);
        gradient.addColorStop(1, color2);
        return gradient;
    }

    // Génération de couleurs pour la performance
    generatePerformanceColors(points) {
        return points.map(point => {
            if (point.y >= 90) return '#27ae60'; // Excellent - Vert
            if (point.y >= 80) return '#f39c12'; // Bon - Orange
            if (point.y >= 70) return '#e67e22'; // Moyen - Orange foncé
            return '#e74c3c'; // À améliorer - Rouge
        });
    }

    // Mise à jour dynamique des données
    updateChart(chartId, newData) {
        const chart = this.charts.get(chartId);
        if (chart) {
            chart.data = newData;
            chart.update('active');
        }
    }

    // Animation de mise à jour
    animateChartUpdate(chartId, newData) {
        const chart = this.charts.get(chartId);
        if (chart) {
            chart.data = newData;
            chart.update({
                duration: 800,
                easing: 'easeOutQuart'
            });
        }
    }

    // Export des graphiques
    exportChart(chartId, format = 'png') {
        const chart = this.charts.get(chartId);
        if (chart) {
            const link = document.createElement('a');
            link.download = `graphique-${chartId}.${format}`;
            link.href = chart.toBase64Image();
            link.click();
        }
    }

    // Destruction propre des graphiques
    destroyChart(chartId) {
        const chart = this.charts.get(chartId);
        if (chart) {
            chart.destroy();
            this.charts.delete(chartId);
        }
    }

    // Destruction de tous les graphiques
    destroyAllCharts() {
        this.charts.forEach((chart, chartId) => {
            chart.destroy();
        });
        this.charts.clear();
    }

    // Méthodes statistiques avancées
    calculateStatistics(data) {
        const values = data.map(d => d.value);
        
        return {
            mean: this.calculateMean(values),
            median: this.calculateMedian(values),
            mode: this.calculateMode(values),
            standardDeviation: this.calculateStandardDeviation(values),
            variance: this.calculateVariance(values)
        };
    }

    calculateMean(values) {
        return values.reduce((a, b) => a + b, 0) / values.length;
    }

    calculateMedian(values) {
        const sorted = [...values].sort((a, b) => a - b);
        const middle = Math.floor(sorted.length / 2);
        
        if (sorted.length % 2 === 0) {
            return (sorted[middle - 1] + sorted[middle]) / 2;
        }
        
        return sorted[middle];
    }

    calculateMode(values) {
        const frequency = {};
        let maxFreq = 0;
        let modes = [];
        
        values.forEach(value => {
            frequency[value] = (frequency[value] || 0) + 1;
            
            if (frequency[value] > maxFreq) {
                maxFreq = frequency[value];
                modes = [value];
            } else if (frequency[value] === maxFreq) {
                modes.push(value);
            }
        });
        
        return modes.length === values.length ? [] : modes;
    }

    calculateVariance(values) {
        const mean = this.calculateMean(values);
        const squaredDifferences = values.map(value => Math.pow(value - mean, 2));
        return this.calculateMean(squaredDifferences);
    }

    calculateStandardDeviation(values) {
        return Math.sqrt(this.calculateVariance(values));
    }

    // Génération de données aléatoires pour la démo
    generateRandomData(count, min, max) {
        return Array.from({ length: count }, () => 
            Math.floor(Math.random() * (max - min + 1)) + min
        );
    }

    // Filtrage des données
    filterData(data, condition) {
        return data.filter(condition);
    }

    // Regroupement des données
    groupData(data, key) {
        return data.reduce((groups, item) => {
            const group = item[key];
            groups[group] = groups[group] || [];
            groups[group].push(item);
            return groups;
        }, {});
    }
}

// Plugin Chart.js personnalisé pour les annotations
const annotationPlugin = {
    id: 'customAnnotations',
    afterDraw: (chart) => {
        const ctx = chart.ctx;
        ctx.save();
        
        // Exemple d'annotation simple
        if (chart.config.type === 'line') {
            const dataset = chart.data.datasets[0];
            const maxValue = Math.max(...dataset.data);
            const maxIndex = dataset.data.indexOf(maxValue);
            
            if (maxValue > 80) {
                const point = chart.getDatasetMeta(0).data[maxIndex];
                
                ctx.fillStyle = '#27ae60';
                ctx.font = 'bold 12px Segoe UI';
                ctx.textAlign = 'center';
                ctx.fillText('📈 Pic de présence', point.x, point.y - 15);
            }
        }
        
        ctx.restore();
    }
};

// Enregistrement du plugin
Chart.register(annotationPlugin);

// Initialisation du gestionnaire de graphiques
document.addEventListener('DOMContentLoaded', () => {
    window.chartsManager = new ChartsManager();
});

// Export pour utilisation dans d'autres modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ChartsManager;
}