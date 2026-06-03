import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['prototype', 'entries', 'row', 'emptyState']

    connect() {
        this.nextIndex = this.rowTargets.length
        this.rowTargets.forEach((row) => this.initializeRow(row))
        this.toggleEmptyState()
    }

    addEntry() {
        const template = this.prototypeTarget.innerHTML.replace(/__name__/g, this.nextIndex++)
        const wrapper = document.createElement('div')
        wrapper.innerHTML = template.trim()
        const row = wrapper.firstElementChild

        this.entriesTarget.appendChild(row)
        this.initializeRow(row)
        this.toggleEmptyState()
    }

    removeEntry(event) {
        event.preventDefault()
        event.currentTarget.closest('[data-product-relations-target~="row"]').remove()
        this.toggleEmptyState()
    }

    toggleEmptyState() {
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('d-none', this.rowTargets.length > 0)
        }
    }

    initializeRow(row) {
        row.querySelectorAll('select.select2').forEach((select) => this.initializeSelect2(select))
    }

    initializeSelect2(select) {
        if (!window.$ || !window.$.fn || !window.$.fn.select2 || window.$(select).hasClass('select2-hidden-accessible')) {
            return
        }

        const isRequired = select.hasAttribute('required')
        const isMultiple = select.hasAttribute('multiple')

        window.$(select)
            .select2({
                class: 'form-control',
                width: '100%',
                allowClear: !isRequired || isMultiple,
                closeOnSelect: !isMultiple,
            })
            .on('select2:select select2:clear', function () {
                select.dispatchEvent(new Event('change', {bubbles: true}))
            })
    }
}
