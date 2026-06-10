import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['prototype', 'entries', 'row', 'emptyState', 'head', 'warning', 'inheritedList', 'inheritedEmpty']
    static values = {
        optionsUrl: String,
        parentSelectId: String,
        editable: Boolean,
        duplicateMessage: String,
        inheritedConflictMessage: String,
        requiredLabel: String,
        filterLabel: String,
        sourceLabel: String,
    }

    connect() {
        this.nextIndex = this.hasRowTarget ? this.rowTargets.length : 0
        this.parentSelect = document.getElementById(this.parentSelectIdValue)
        this.parentChangeHandler = () => this.loadOptions()
        this.submitHandler = (event) => this.preventConflictingSubmit(event)
        this.available = []
        this.inherited = []

        if (this.parentSelect) {
            this.parentSelect.addEventListener('change', this.parentChangeHandler)
        }
        this.element.addEventListener('submit', this.submitHandler)
        this.loadOptions()
    }

    disconnect() {
        if (this.parentSelect) {
            this.parentSelect.removeEventListener('change', this.parentChangeHandler)
        }
        this.element.removeEventListener('submit', this.submitHandler)
    }

    addEntry(event) {
        event.preventDefault()
        const wrapper = document.createElement('div')
        wrapper.innerHTML = this.prototypeTarget.innerHTML.replace(/__name__/g, this.nextIndex++).trim()
        const row = wrapper.firstElementChild

        this.entriesTarget.appendChild(row)
        this.updateRowSelect(row)
        this.initializeSelect2(row.querySelector('select'))
        this.updateWarnings()
        this.toggleEmptyState()
    }

    removeEntry(event) {
        event.preventDefault()
        event.currentTarget.closest('[data-category-parameters-target~="row"]').remove()
        this.refreshRows()
    }

    parameterChanged() {
        this.refreshRows()
    }

    loadOptions() {
        const url = new URL(this.optionsUrlValue, window.location.origin)
        const parentId = this.parentSelect ? this.parentSelect.value : ''
        if (parentId) {
            url.searchParams.set('parent_id', parentId)
        }

        fetch(url.toString(), {headers: {'Accept': 'application/json'}})
            .then((response) => response.ok ? response.json() : {available: [], inherited: []})
            .then((data) => {
                this.available = data.available || []
                this.inherited = data.inherited || []
                this.renderInherited()
                this.refreshRows()
            })
            .catch(() => {
                this.available = []
                this.inherited = []
                this.renderInherited()
                this.refreshRows()
            })
    }

    refreshRows() {
        if (!this.editableValue || !this.hasRowTarget) {
            return
        }

        this.rowTargets.forEach((row) => this.updateRowSelect(row))
        this.rowTargets.forEach((row) => this.initializeSelect2(row.querySelector('select')))
        this.updateWarnings()
        this.toggleEmptyState()
    }

    updateRowSelect(row) {
        const select = row.querySelector('select')
        if (!select) {
            return
        }

        const currentValue = select.value
        const currentLabel = select.selectedOptions.length ? select.selectedOptions[0].textContent : currentValue
        const selectedElsewhere = new Set(this.rowTargets
            .map((candidate) => candidate.querySelector('select'))
            .filter((candidate) => candidate && candidate !== select && candidate.value)
            .map((candidate) => candidate.value))

        this.destroySelect2(select)
        select.innerHTML = ''
        select.appendChild(this.buildOption('', '', false))

        this.available.forEach((parameter) => {
            const value = String(parameter.id)
            if (!selectedElsewhere.has(value) || value === currentValue) {
                select.appendChild(this.buildOption(value, parameter.name, value === currentValue))
            }
        })

        if (currentValue && !Array.from(select.options).some((option) => option.value === currentValue)) {
            select.appendChild(this.buildOption(currentValue, currentLabel, true))
        }
        select.value = currentValue
    }

    updateWarnings() {
        const inheritedIds = new Set(this.inherited.map((parameter) => String(parameter.id)))
        const counts = {}

        this.rowTargets.forEach((row) => {
            const value = row.querySelector('select')?.value
            if (value) {
                counts[value] = (counts[value] || 0) + 1
            }
        })

        this.rowTargets.forEach((row) => {
            const select = row.querySelector('select')
            const warning = row.querySelector('[data-category-parameters-target~="warning"]')
            const messages = []

            if (select?.value && inheritedIds.has(select.value)) {
                messages.push(this.inheritedConflictMessageValue)
            }
            if (select?.value && counts[select.value] > 1) {
                messages.push(this.duplicateMessageValue)
            }

            select?.classList.toggle('is-invalid', messages.length > 0)
            if (warning) {
                warning.textContent = messages.join(' ')
                warning.classList.toggle('d-none', messages.length === 0)
            }
        })
    }

    preventConflictingSubmit(event) {
        if (!this.editableValue) {
            return
        }

        this.updateWarnings()
        const conflict = this.warningTargets.find((warning) => !warning.classList.contains('d-none'))
        if (conflict) {
            event.preventDefault()
            conflict.scrollIntoView({behavior: 'smooth', block: 'center'})
        }
    }

    renderInherited() {
        if (!this.hasInheritedListTarget || !this.hasInheritedEmptyTarget) {
            return
        }

        this.inheritedListTarget.innerHTML = ''
        this.inherited.forEach((parameter) => {
            const row = document.createElement('div')
            row.className = 'category-parameter-readonly'

            const main = document.createElement('div')
            const name = document.createElement('strong')
            name.textContent = parameter.name
            const source = document.createElement('div')
            source.className = 'text-muted small'
            source.textContent = `${this.sourceLabelValue}: ${parameter.sourceCategory}`
            main.append(name, source)

            const badges = document.createElement('div')
            badges.className = 'd-flex flex-wrap gap-1'
            if (parameter.isRequired) {
                badges.appendChild(this.buildBadge(this.requiredLabelValue, 'bg-warning text-dark'))
            }
            if (parameter.isFilter) {
                badges.appendChild(this.buildBadge(this.filterLabelValue, 'bg-info'))
            }

            row.append(main, badges)
            this.inheritedListTarget.appendChild(row)
        })

        const hasInherited = this.inherited.length > 0
        this.inheritedListTarget.classList.toggle('d-none', !hasInherited)
        this.inheritedEmptyTarget.classList.toggle('d-none', hasInherited)
    }

    toggleEmptyState() {
        const hasRows = this.hasRowTarget && this.rowTargets.length > 0
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('d-none', hasRows)
        }
        if (this.hasHeadTarget) {
            this.headTarget.classList.toggle('d-md-grid', hasRows)
        }
    }

    buildOption(value, label, selected) {
        const option = document.createElement('option')
        option.value = value
        option.textContent = label
        option.selected = selected
        return option
    }

    buildBadge(label, classes) {
        const badge = document.createElement('span')
        badge.className = `badge ${classes}`
        badge.textContent = label
        return badge
    }

    initializeSelect2(select) {
        if (!select || !window.$ || !window.$.fn?.select2 || window.$(select).hasClass('select2-hidden-accessible')) {
            return
        }

        window.$(select).select2({
            class: 'form-control',
            width: '100%',
            allowClear: true,
        }).on('select2:select select2:clear', () => {
            select.dispatchEvent(new Event('change', {bubbles: true}))
        })
    }

    destroySelect2(select) {
        if (window.$?.fn?.select2 && window.$(select).hasClass('select2-hidden-accessible')) {
            window.$(select).select2('destroy')
        }
    }
}
