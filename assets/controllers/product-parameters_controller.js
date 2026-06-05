import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['prototype', 'entries', 'row', 'emptyState', 'selectCategoryHint', 'parameterName', 'warning', 'head', 'requiredBadge', 'removeButton', 'parameterNameDisplay']
    static values = {
        optionsUrl: String,
        categorySelectId: String,
        duplicateMessage: String,
        incompatibleMessage: String,
        requiredLabel: String,
        requiredLockedMessage: String,
    }

    connect() {
        this.nextIndex = this.rowTargets.length
        this.categorySelect = document.getElementById(this.categorySelectIdValue)
        this.categoryChangeHandler = () => this.loadCategoryParameters()
        this.parameters = []

        if (this.categorySelect) {
            this.categorySelect.addEventListener('change', this.categoryChangeHandler)
        }

        // select2 is mounted once via reconcileRows() -> refreshRowOptions() below,
        // after the (deduped) option set is built. Enhancing here as well would cause a
        // visible enhance -> destroy -> rebuild flash on load.
        this.loadCategoryParameters()
    }

    disconnect() {
        if (this.categorySelect) {
            this.categorySelect.removeEventListener('change', this.categoryChangeHandler)
        }
    }

    categoryChanged() {
        this.loadCategoryParameters()
    }

    parameterChanged() {
        this.refreshRowOptions()
        this.updateWarnings()
    }

    addEntry(event = null, parameter = null) {
        if (event) {
            event.preventDefault()
        }

        const template = this.prototypeTarget.innerHTML.replace(/__name__/g, this.nextIndex++)
        const wrapper = document.createElement('div')
        wrapper.innerHTML = template.trim()
        const row = wrapper.firstElementChild

        this.entriesTarget.appendChild(row)
        this.updateRowOptions(row, parameter ? String(parameter.id) : '')
        this.refreshRowOptions()
        this.updateWarnings()
        this.toggleEmptyState()
    }

    removeEntry(event) {
        event.preventDefault()
        event.currentTarget.closest('[data-product-parameters-target~="row"]').remove()
        this.refreshRowOptions()
        this.updateWarnings()
        this.toggleEmptyState()
    }

    loadCategoryParameters() {
        const categoryId = this.categorySelect ? this.categorySelect.value : ''
        this.toggleCategoryHint(categoryId)

        if (!categoryId || !this.hasOptionsUrlValue) {
            this.parameters = []
            this.reconcileRows()
            return
        }

        const url = new URL(this.optionsUrlValue, window.location.origin)
        url.searchParams.set('category_id', categoryId)

        fetch(url.toString(), {headers: {'Accept': 'application/json'}})
            .then((response) => response.ok ? response.json() : {parameters: []})
            .then((data) => {
                this.parameters = data.parameters || []
                this.reconcileRows()
            })
            .catch(() => {
                this.parameters = []
                this.reconcileRows()
            })
    }

    reconcileRows() {
        // When a category is loaded, drop rows whose selected parameter is no longer
        // available for that category. Guarded on a non-empty parameter set so a failed
        // fetch or a parameter-less category never removes every row.
        if (this.parameters.length > 0) {
            const allowedIds = new Set(this.parameters.map((parameter) => String(parameter.id)))

            this.rowTargets.forEach((row) => {
                const select = row.querySelector('[data-product-parameters-target~="parameterName"]')
                if (select && select.value && !allowedIds.has(select.value)) {
                    row.remove()
                }
            })
        }

        // Rebuild options AND (re)initialize select2 on existing rows. Using
        // refreshRowOptions (not bare updateRowOptions) prevents leaving rows as native
        // selects after the async category fetch.
        this.refreshRowOptions()

        const selectedIds = new Set(this.parameterNameTargets
            .map((select) => select.value)
            .filter((value) => value !== ''))

        this.parameters.forEach((parameter) => {
            if (!selectedIds.has(String(parameter.id))) {
                this.addEntry(null, parameter)
                selectedIds.add(String(parameter.id))
            }
        })

        this.updateWarnings()
        this.toggleEmptyState()
    }

    updateRowOptions(row, selectedValue = null) {
        const select = row.querySelector('[data-product-parameters-target~="parameterName"]')
        if (!select) {
            return
        }

        const currentValue = selectedValue || select.value
        const currentOption = select.selectedOptions.length > 0 ? select.selectedOptions[0] : null
        const currentLabel = currentOption ? currentOption.textContent : ''
        const allowedIds = new Set(this.parameters.map((parameter) => String(parameter.id)))
        const selectedIds = this.selectedParameterIds(select)
        const placeholder = select.querySelector('option[value=""]')?.textContent || ''

        this.destroySelect2(select)
        select.innerHTML = ''
        select.appendChild(this.buildOption('', placeholder, false))

        this.parameters.forEach((parameter) => {
            if (selectedIds.has(String(parameter.id)) && String(parameter.id) !== currentValue) {
                return
            }

            select.appendChild(this.buildOption(String(parameter.id), parameter.name, String(parameter.id) === currentValue))
        })

        if (currentValue && !allowedIds.has(currentValue)) {
            select.appendChild(this.buildOption(currentValue, currentLabel || currentValue, true))
        }

        select.value = currentValue
        select.disabled = this.parameters.length === 0 && !currentValue
    }

    refreshRowOptions() {
        const requiredIds = this.requiredParameterIds()

        this.rowTargets.forEach((row) => {
            this.updateRowOptions(row)

            const select = row.querySelector('[data-product-parameters-target~="parameterName"]')
            const isRequired = Boolean(select && select.value && requiredIds.has(select.value))

            this.presentRow(row, select, isRequired)
        })
    }

    // Required parameters cannot be changed: show the name as a read-only text field
    // (the real select stays in the DOM and keeps submitting its value) instead of an
    // editable select2. Optional parameters keep the editable select2.
    presentRow(row, select, isRequired) {
        if (!select) {
            return
        }

        const display = row.querySelector('[data-product-parameters-target~="parameterNameDisplay"]')

        if (isRequired) {
            this.destroySelect2(select)
            select.classList.add('d-none')

            if (display) {
                display.value = this.parameterNameById(select.value)
                display.classList.remove('d-none')
            }

            return
        }

        select.classList.remove('d-none')

        if (display) {
            display.value = ''
            display.classList.add('d-none')
        }

        this.initializeSelect2(select)
    }

    requiredParameterIds() {
        return new Set(this.parameters
            .filter((parameter) => parameter.isRequired)
            .map((parameter) => String(parameter.id)))
    }

    parameterNameById(id) {
        const parameter = this.parameters.find((parameter) => String(parameter.id) === String(id))

        return parameter ? parameter.name : ''
    }

    selectedParameterIds(exceptSelect = null) {
        return new Set(this.parameterNameTargets
            .filter((select) => select !== exceptSelect)
            .map((select) => select.value)
            .filter((value) => value !== ''))
    }

    updateWarnings() {
        const allowedIds = new Set(this.parameters.map((parameter) => String(parameter.id)))
        const selectedCounts = {}

        this.parameterNameTargets.forEach((select) => {
            if (select.value) {
                selectedCounts[select.value] = (selectedCounts[select.value] || 0) + 1
            }
        })

        const requiredIds = new Set(this.parameters
            .filter((parameter) => parameter.isRequired)
            .map((parameter) => String(parameter.id)))

        this.rowTargets.forEach((row) => {
            const select = row.querySelector('[data-product-parameters-target~="parameterName"]')
            const warning = row.querySelector('[data-product-parameters-target~="warning"]')
            const messages = []

            if (select && select.value && !allowedIds.has(select.value)) {
                messages.push(this.incompatibleMessageValue)
            }

            if (select && select.value && selectedCounts[select.value] > 1) {
                messages.push(this.duplicateMessageValue)
            }

            if (warning) {
                warning.textContent = messages.join(' ')
                warning.classList.toggle('d-none', messages.length === 0)
            }

            if (select) {
                select.classList.toggle('is-invalid', messages.length > 0)
            }

            this.updateRequiredState(row, select && requiredIds.has(select.value))
        })
    }

    updateRequiredState(row, isRequired) {
        const badge = row.querySelector('[data-product-parameters-target~="requiredBadge"]')
        const removeButton = row.querySelector('[data-product-parameters-target~="removeButton"]')

        if (badge) {
            badge.textContent = this.hasRequiredLabelValue ? this.requiredLabelValue : ''
            badge.classList.toggle('d-none', !isRequired || !this.hasRequiredLabelValue)
        }

        if (removeButton) {
            const locked = Boolean(isRequired)

            removeButton.disabled = locked
            removeButton.classList.toggle('btn-outline-danger', !locked)
            removeButton.classList.toggle('btn-outline-secondary', locked)

            const icon = removeButton.querySelector('i')
            if (icon) {
                icon.classList.toggle('fa-trash', !locked)
                icon.classList.toggle('fa-lock', locked)
            }

            if (locked && this.hasRequiredLockedMessageValue) {
                removeButton.setAttribute('title', this.requiredLockedMessageValue)
            } else {
                removeButton.setAttribute('title', this.removeTitle(removeButton))
            }
        }
    }

    removeTitle(removeButton) {
        return removeButton.getAttribute('aria-label') || ''
    }

    toggleEmptyState() {
        const hasRows = this.rowTargets.length > 0

        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('d-none', hasRows)
        }

        if (this.hasHeadTarget) {
            this.headTarget.classList.toggle('d-md-grid', hasRows)
        }
    }

    toggleCategoryHint(categoryId) {
        if (this.hasSelectCategoryHintTarget) {
            this.selectCategoryHintTarget.classList.toggle('d-none', Boolean(categoryId))
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

    destroySelect2(select) {
        if (!window.$ || !window.$.fn || !window.$.fn.select2 || !window.$(select).hasClass('select2-hidden-accessible')) {
            return
        }

        window.$(select).select2('destroy')
    }

    buildOption(value, label, selected) {
        const option = document.createElement('option')
        option.value = value
        option.textContent = label
        option.selected = selected

        return option
    }
}
