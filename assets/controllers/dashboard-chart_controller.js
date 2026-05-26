import { Controller } from '@hotwired/stimulus';
import Chart from 'chart.js';

export default class extends Controller {
    static targets = ['canvas']
    static values = {
        config: Object
    }

    connect() {
        if (!this.hasCanvasTarget || !this.hasConfigValue) {
            return
        }

        const datasets = (this.configValue.datasets || []).map((dataset) => ({
            label: dataset.label,
            data: dataset.data || [],
            borderColor: dataset.color || '#3b7ddd',
            backgroundColor: this.backgroundColor(dataset.color || '#3b7ddd'),
            fill: this.configValue.type === 'line' ? false : true,
            tension: 0.25
        }))

        this.chart = new Chart(this.canvasTarget.getContext('2d'), {
            type: this.configValue.type || 'line',
            data: {
                labels: this.configValue.labels || [],
                datasets
            },
            options: {
                maintainAspectRatio: false,
                legend: {
                    display: datasets.length > 1
                },
                scales: {
                    xAxes: [{
                        gridLines: {
                            display: false
                        }
                    }],
                    yAxes: [{
                        ticks: {
                            beginAtZero: true
                        }
                    }]
                }
            }
        })
    }

    disconnect() {
        if (this.chart) {
            this.chart.destroy()
        }
    }

    backgroundColor(color) {
        if (color.charAt(0) === '#') {
            const red = parseInt(color.substring(1, 3), 16)
            const green = parseInt(color.substring(3, 5), 16)
            const blue = parseInt(color.substring(5, 7), 16)

            return `rgba(${red}, ${green}, ${blue}, 0.18)`
        }

        return color
    }
}
