import {Controller} from '@hotwired/stimulus'

const SOURCE_LABEL_KEYS = {
    stock: ['order.source.stock', 'Stock'],
    production: ['order.source.production', 'For production'],
    preorder: ['order.source.preorder', 'Preorder'],
    service: ['order.source.service', 'Service'],
    material: ['order.source.material', 'Material'],
}

function trans(key, fallback, parameters = {}) {
    let value = window.adminTranslations && window.adminTranslations[key] ? window.adminTranslations[key] : fallback

    Object.entries(parameters).forEach(([name, replacement]) => {
        value = value.replace(`%${name}%`, replacement)
    })

    return value
}

export default class extends Controller {
    static targets = [
        'form',
        'prototype',
        'entries',
        'row',
        'emptyState',
        'summary',
        'summaryStatus',
        'summaryCount',
        'summarySubtotal',
        'summaryDiscount',
        'summaryTotal',
        'customerSelect',
        'customerPhone',
        'customerName',
        'customerLastName',
        'customerResults',
    ]

    connect() {
        this.nextIndex = this.rowTargets.length
        this.activeRow = null
        this.summaryTimeout = null
        this.summaryAbortController = null
        this.summaryRequestId = 0
        this.customerSearchTimeout = null
        this.customerSearchAbortController = null
        this.customerSearchRequestId = 0
        this.isFillingCustomer = false
        this.customerSearchElement = this.hasCustomerPhoneTarget ? this.customerPhoneTarget.closest('.order-customer-search') : null

        this.element.addEventListener('product-search:add', this.addProduct.bind(this))
        this.element.addEventListener('order-entry:changed', this.entryChanged.bind(this))
        this.element.addEventListener('order-entry:remove', this.removeEntry.bind(this))
        this.formTarget.addEventListener('change', this.changed.bind(this))
        document.addEventListener('click', this.closeCustomerResultsOnOutsideClick.bind(this))

        this.rowTargets.forEach((row) => this.initializeRow(row))
        this.relatedAbortControllers = new WeakMap()
        this.rowTargets.forEach((row) => this.loadRelatedProducts(row))
        this.updateCurrencyLabels()
        this.requestSummaryCalculation()
        this.toggleEmptyState()
    }

    customerPhoneFocused() {
        if (this.hasCustomerPhoneTarget && this.customerPhoneTarget.value.trim().length >= 5) {
            this.searchCustomers()
        }
    }

    customerChanged() {
        if (!this.isFillingCustomer) {
            this.clearSelectedCustomer()
        }

        window.clearTimeout(this.customerSearchTimeout)

        if (!this.hasCustomerPhoneTarget || this.customerPhoneTarget.value.trim().length < 5) {
            this.customerSearchRequestId += 1
            if (this.customerSearchAbortController) {
                this.customerSearchAbortController.abort()
            }
            this.setCustomerLoading(false)
            this.hideCustomerResults()
            return
        }

        this.customerSearchTimeout = window.setTimeout(() => this.searchCustomers(), 250)
    }

    customerDetailsChanged() {
        if (!this.isFillingCustomer) {
            this.clearSelectedCustomer()
        }
    }

    searchCustomers() {
        if (!this.hasCustomerPhoneTarget || !this.hasCustomerResultsTarget) {
            return
        }

        const url = this.formTarget.dataset.orderFormCustomerSearchUrl
        const phone = this.customerPhoneTarget.value.trim()

        if (!url || phone.length < 5) {
            this.hideCustomerResults()
            return
        }

        if (this.customerSearchAbortController) {
            this.customerSearchAbortController.abort()
        }

        this.customerSearchAbortController = new AbortController()
        const requestId = ++this.customerSearchRequestId
        const searchUrl = new URL(url, window.location.origin)
        searchUrl.searchParams.set('phone', phone)
        this.setCustomerLoading(true)
        this.renderCustomerLoading()

        fetch(searchUrl.toString(), {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: this.customerSearchAbortController.signal,
        })
            .then((response) => response.json())
            .then((data) => {
                if (requestId === this.customerSearchRequestId) {
                    this.renderCustomerResults(data.customers || [])
                }
            })
            .catch((error) => {
                if (requestId === this.customerSearchRequestId && error.name !== 'AbortError') {
                    this.hideCustomerResults()
                }
            })
            .finally(() => {
                if (requestId === this.customerSearchRequestId) {
                    this.setCustomerLoading(false)
                }
            })
    }

    renderCustomerResults(customers) {
        if (!this.hasCustomerResultsTarget) {
            return
        }

        this.customerResultsTarget.innerHTML = ''

        if (customers.length === 0) {
            this.hideCustomerResults()
            return
        }

        customers.forEach((customer) => {
            const button = document.createElement('button')
            button.type = 'button'
            button.className = 'order-customer-result'
            button.innerHTML = `
                <span>${this.escapeHtml(customer.fullName || '')}</span>
                <small>${this.escapeHtml(customer.phone || '')}</small>
            `
            button.addEventListener('click', () => this.selectCustomer(customer))
            this.customerResultsTarget.appendChild(button)
        })

        this.customerResultsTarget.classList.remove('d-none')
    }

    selectCustomer(customer) {
        this.isFillingCustomer = true

        if (this.hasCustomerPhoneTarget) this.customerPhoneTarget.value = customer.phone || ''
        if (this.hasCustomerNameTarget) this.customerNameTarget.value = customer.name || ''
        if (this.hasCustomerLastNameTarget) this.customerLastNameTarget.value = customer.lastName || ''
        if (this.hasCustomerSelectTarget) this.setCustomerSelect(customer)

        this.isFillingCustomer = false
        this.hideCustomerResults()
    }

    setCustomerSelect(customer) {
        let option = Array.from(this.customerSelectTarget.options).find((item) => item.value === String(customer.id))

        if (!option) {
            option = new Option(customer.fullName || customer.name || customer.id, customer.id, true, true)
            this.customerSelectTarget.appendChild(option)
        }

        option.selected = true
        this.customerSelectTarget.dispatchEvent(new Event('change', {bubbles: true}))
    }

    clearSelectedCustomer() {
        if (!this.hasCustomerSelectTarget || !this.customerSelectTarget.value) {
            return
        }

        this.customerSelectTarget.value = ''
        this.customerSelectTarget.dispatchEvent(new Event('change', {bubbles: true}))
    }

    hideCustomerResults() {
        if (this.hasCustomerResultsTarget) {
            this.customerResultsTarget.classList.add('d-none')
        }
    }

    renderCustomerLoading() {
        if (!this.hasCustomerResultsTarget) {
            return
        }

        this.customerResultsTarget.innerHTML = `<div class="order-search-loading"><span class="order-search-spinner"></span><span>${this.escapeHtml(trans('common.searching', 'Searching...'))}</span></div>`
        this.customerResultsTarget.classList.remove('d-none')
    }

    setCustomerLoading(isLoading) {
        if (this.customerSearchElement) {
            this.customerSearchElement.classList.toggle('is-loading', isLoading)
        }
    }

    closeCustomerResultsOnOutsideClick(event) {
        if (!this.hasCustomerResultsTarget || !this.hasCustomerPhoneTarget) {
            return
        }

        if (this.customerResultsTarget.contains(event.target) || this.customerPhoneTarget.contains(event.target)) {
            return
        }

        this.hideCustomerResults()
    }

    changed(event) {
        if (event.target && event.target.name && event.target.name.endsWith('[currency]')) {
            this.updateCurrencyLabels()
            this.requestSummaryCalculation()
        }
    }

    addProduct(event) {
        const product = event.detail.product
        const row = this.createRow()
        const productSelect = row.querySelector('[data-order-entry-target~="product"]')
        const quantityInput = row.querySelector('[data-order-entry-target~="quantity"]')
        const unitPriceInput = row.querySelector('[data-order-entry-target~="unitPrice"]')
        const warehouseSelect = row.querySelector('[data-order-entry-target~="warehouse"]')
        const sourceSelect = row.querySelector('[data-order-entry-target~="source"]')
        const layers = Array.isArray(product.batchLayers) ? product.batchLayers : []

        this.entriesTarget.prepend(row)
        this.initializeRow(row)
        this.setProduct(productSelect, product)

        if (product.price && unitPriceInput && !this.numberValue(unitPriceInput.value)) {
            unitPriceInput.value = this.format(this.numberValue(product.price))
        }

        if (quantityInput && !this.numberValue(quantityInput.value)) {
            quantityInput.value = this.formatQuantity(1, product.unitPrecision)
        }
        this.applyQuantityPrecision(quantityInput, product.unitPrecision)

        if (product.warehouseId && warehouseSelect) {
            this.setWarehouse(warehouseSelect, product.warehouseId)
        }

        row.dataset.productId = product.id || ''
        row.dataset.batchLayers = JSON.stringify(layers)
        row.dataset.sourceType = product.sourceType || (product.warehouseId ? 'stock' : 'production')
        if (sourceSelect) {
            sourceSelect.value = row.dataset.sourceType
        }
        row.dataset.available = product.available || '0.0000'
        row.dataset.unitPrecision = product.unitPrecision ?? '4'
        this.setWarehouseStockBatch(row, product.batchId || '')
        this.setSource(row, product)
        this.setUnit(row, product.unit || '')
        this.setDiscountOptions(row, product)
        const available = row.querySelector('[data-order-entry-target~="available"]')
        if (available) {
            available.textContent = this.formatQuantity(this.numberValue(row.dataset.available), row.dataset.unitPrecision)
        }

        this.activateRow(row)
        this.recalculateRow(row)
        this.updateCurrencyLabels(row)
        this.requestSummaryCalculation()
        this.toggleEmptyState()
        this.loadRelatedProducts(row)
    }

    loadRelatedProducts(row) {
        const panel = row.querySelector('[data-order-related-panel]')
        const productId = row.dataset.productId

        if (!panel || !productId || !panel.dataset.relatedUrl) {
            if (panel) {
                panel.innerHTML = ''
                panel.classList.add('d-none')
            }
            return
        }

        if (!this.relatedAbortControllers) {
            this.relatedAbortControllers = new WeakMap()
        }

        const previousController = this.relatedAbortControllers.get(panel)
        if (previousController) {
            previousController.abort()
        }

        const controller = new AbortController()
        this.relatedAbortControllers.set(panel, controller)

        const params = new URLSearchParams({productId: String(productId)})
        const currency = this.relatedCurrencyCode()
        if (currency) {
            params.set('currency', currency)
        }

        fetch(`${panel.dataset.relatedUrl}?${params.toString()}`, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: controller.signal,
        })
            .then((response) => response.json())
            .then((data) => this.renderRelatedProducts(panel, data.products || []))
            .catch((error) => {
                if (error.name !== 'AbortError') {
                    panel.innerHTML = ''
                    panel.classList.add('d-none')
                }
            })
    }

    renderRelatedProducts(panel, products) {
        if (!products.length) {
            panel.innerHTML = ''
            panel.classList.add('d-none')
            return
        }

        const buttons = products
            .map((product) => {
                const option = this.relatedDefaultOption(product)
                const label = `${this.escapeRelatedHtml(product.name || product.text || '')}`
                return `<button type="button" class="btn btn-sm btn-outline-secondary order-entry-related-add" data-related-payload="${this.escapeRelatedHtml(JSON.stringify(option))}"><i class="fa-solid fa-plus me-1"></i>${label}</button>`
            })
            .join('')

        panel.innerHTML = `<div class="text-muted small mb-1">${this.escapeRelatedHtml(trans('order.related.title', 'Related products'))}</div><div class="d-flex flex-wrap gap-1">${buttons}</div>`
        panel.classList.remove('d-none')

        panel.querySelectorAll('.order-entry-related-add').forEach((button) => {
            button.addEventListener('click', () => {
                let payload = {}
                try {
                    payload = JSON.parse(button.dataset.relatedPayload)
                } catch (error) {
                    payload = {}
                }
                this.element.dispatchEvent(new CustomEvent('product-search:add', {bubbles: true, detail: {product: payload}}))
            })
        })
    }

    relatedDefaultOption(product) {
        const stockOption = (product.stockOptions || [])[0]
        const base = {
            id: product.id,
            text: product.text,
            code: product.code,
            unit: product.unit,
            unitPrecision: product.unitPrecision,
            discountRules: product.discountRules || [],
            defaultDiscountRuleId: product.defaultDiscountRuleId || '',
        }

        if (stockOption) {
            return {
                ...base,
                price: stockOption.price || product.price,
                available: stockOption.available || '0.0000',
                warehouseId: stockOption.warehouseId || '',
                warehouseName: stockOption.warehouseName || '',
                batchId: stockOption.batchId || '',
                batchReceivedAt: stockOption.batchReceivedAt || '',
                batchLayers: stockOption.batchLayers || [],
                sourceType: 'stock',
                sourceDetail: stockOption.batchId
                    ? `${stockOption.warehouseName || ''} · Batch #${stockOption.batchId}`
                    : (stockOption.warehouseName || ''),
            }
        }

        if (product.productionOption) {
            return {
                ...base,
                price: product.productionOption.price || product.price,
                available: '0.0000',
                warehouseId: '',
                warehouseName: '',
                batchId: '',
                batchReceivedAt: '',
                batchLayers: [],
                sourceType: 'production',
                sourceDetail: trans('order.source.production', 'For production'),
            }
        }

        return {
            ...base,
            price: product.price,
            available: '0.0000',
            warehouseId: '',
            warehouseName: '',
            batchId: '',
            batchReceivedAt: '',
            batchLayers: [],
            sourceType: 'stock',
            sourceDetail: trans('order.source.unavailable', 'No warehouse stock'),
        }
    }

    relatedCurrencyCode() {
        const currencyInput = this.element.querySelector('[name$="[currency]"]')

        return currencyInput ? currencyInput.value : ''
    }

    escapeRelatedHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
    }

    createRow() {
        const index = this.nextIndex++
        const template = this.prototypeTarget.innerHTML.replace(/__name__/g, index)
        const wrapper = document.createElement('div')
        wrapper.innerHTML = template.trim()

        return wrapper.firstElementChild
    }

    initializeRow(row) {
        row.querySelectorAll('select.select2').forEach((select) => this.initializeSelect2(select))
        this.recalculateRow(row)
    }

    initializeSelect2(select) {
        if (!window.$ || !window.$.fn || !window.$.fn.select2 || window.$(select).hasClass('select2-hidden-accessible')) {
            return
        }

        const ajaxUrl = select.dataset.select2AjaxUrl
        const minimumInputLength = select.dataset.select2MinimumInputLength || 3
        const isRequired = select.hasAttribute('required')
        const isMultiple = select.hasAttribute('multiple')
        const options = {
            class: 'form-control',
            allowClear: !isRequired || isMultiple,
            closeOnSelect: !isMultiple,
        }

        if (ajaxUrl) {
            options.minimumInputLength = minimumInputLength
            options.ajax = {
                url: ajaxUrl,
                dataType: 'json',
                delay: 250,
                data: function (params) {
                    return {q: params.term}
                },
                processResults: function (data) {
                    return data
                },
            }
        }

        window.$(select)
            .select2(options)
            .on('select2:select select2:clear', function () {
                select.dispatchEvent(new Event('change', {bubbles: true}))
            })
    }

    setProduct(select, product) {
        if (!select) {
            return
        }

        let option = Array.from(select.options).find((item) => item.value === String(product.id))
        if (!option) {
            option = new Option(product.text, product.id, true, true)
            select.appendChild(option)
        }

        option.selected = true
        option.dataset.code = product.code || ''
        option.dataset.unit = product.unit || ''
        option.dataset.unitPrecision = product.unitPrecision ?? '4'
        option.dataset.price = product.price || ''
        option.dataset.available = product.available || '0.0000'
        option.dataset.batchId = product.batchId || ''
        option.dataset.batchLayers = JSON.stringify(product.batchLayers || [])
        option.dataset.discountRules = JSON.stringify(product.discountRules || [])
        option.dataset.defaultDiscountRuleId = product.defaultDiscountRuleId || ''

        if (window.$ && window.$(select).data('select2')) {
            window.$(select).trigger('change')
        } else {
            select.dispatchEvent(new Event('change', {bubbles: true}))
        }
    }

    setWarehouse(select, warehouseId) {
        select.value = String(warehouseId)

        if (window.$ && window.$(select).data('select2')) {
            window.$(select).trigger('change')
        } else {
            select.dispatchEvent(new Event('change', {bubbles: true}))
        }
    }

    setWarehouseStockBatch(row, batchId) {
        const input = row.querySelector('[data-order-entry-target~="warehouseStockBatch"]')

        row.dataset.batchId = batchId ? String(batchId) : ''
        if (input) {
            input.value = batchId ? String(batchId) : ''
        }
    }

    setSource(row, product) {
        const sourceType = product.sourceType || row.dataset.sourceType || (product.warehouseId ? 'stock' : 'production')
        const sourceLabelKey = SOURCE_LABEL_KEYS[sourceType]
        const sourceLabel = sourceLabelKey ? trans(sourceLabelKey[0], sourceLabelKey[1]) : sourceType || trans('common.source', 'Source')
        const sourceDetail = product.sourceDetail || product.warehouseName || sourceLabel
        const sourceLabelTarget = row.querySelector('[data-order-entry-target~="sourceLabel"]')
        const sourceMetaTarget = row.querySelector('[data-order-entry-target~="sourceMeta"]')
        const sourceSelect = row.querySelector('[data-order-entry-target~="source"]')

        row.dataset.sourceType = sourceType
        if (sourceSelect) {
            sourceSelect.value = sourceType
        }

        if (sourceLabelTarget) {
            sourceLabelTarget.textContent = sourceDetail || trans('order.source.undefined', 'Not defined')
        }

        if (sourceMetaTarget) {
            sourceMetaTarget.textContent = sourceLabel
        }

        // Production lines have no stock: hide the "Availability: 0" hint, show a neutral note.
        const isProduction = sourceType === 'production'
        const availEl = row.querySelector('.order-entry-head__avail')
        const noteEl = row.querySelector('.order-entry-head__production')
        if (availEl) {
            availEl.classList.toggle('d-none', isProduction)
        }
        if (noteEl) {
            noteEl.classList.toggle('d-none', !isProduction)
        }
    }

    setUnit(row, unit) {
        row.querySelectorAll('[data-order-entry-target~="unitLabel"]').forEach((target) => {
            target.textContent = unit || ''
        })
    }


    syncProductData(row) {
        const productSelect = row.querySelector('[data-order-entry-target~="product"]')
        const quantityInput = row.querySelector('[data-order-entry-target~="quantity"]')
        const unitPriceInput = row.querySelector('[data-order-entry-target~="unitPrice"]')
        const availableTarget = row.querySelector('[data-order-entry-target~="available"]')
        const productLabel = row.querySelector('[data-order-entry-target~="productLabel"]')
        const productMeta = row.querySelector('[data-order-entry-target~="productMeta"]')
        const product = this.getSelectedProductData(productSelect)

        if (!product) {
            return
        }

        row.dataset.productId = product.id || ''
        row.dataset.unitPrecision = product.unitPrecision ?? '4'
        this.applyQuantityPrecision(quantityInput, product.unitPrecision)

		if (product.available !== undefined) {
			row.dataset.available = product.available || '0.0000'
			if (availableTarget) {
				availableTarget.textContent = this.formatQuantity(this.numberValue(row.dataset.available), product.unitPrecision)
			}
		}

		if (product.batchId !== undefined) {
			this.setWarehouseStockBatch(row, product.batchId || '')
		}
		row.dataset.batchLayers = JSON.stringify(product.batchLayers || [])

		if (product.price && unitPriceInput && !this.numberValue(unitPriceInput.value)) {
			unitPriceInput.value = this.format(this.numberValue(product.price))
        }

        if (productLabel) {
            productLabel.textContent = this.productName(product.text || '', product.code)
        }

        if (productMeta) {
            productMeta.textContent = product.code || ''
        }

        this.setUnit(row, product.unit || '')
        this.setDiscountOptions(row, product)
    }

    getSelectedProductData(select) {
        if (!select || !select.value) {
            return null
        }

        if (window.$ && window.$(select).data('select2')) {
            const data = window.$(select).select2('data')
            if (data && data.length > 0) {
                return data[0]
            }
        }

        const option = select.selectedOptions[0]
        if (!option) {
            return null
        }

        return {
            id: option.value,
            text: option.textContent,
            code: option.dataset.code,
            unit: option.dataset.unit,
            unitPrecision: option.dataset.unitPrecision,
            price: option.dataset.price,
            available: option.dataset.available,
            batchId: option.dataset.batchId,
            batchLayers: this.parseJson(option.dataset.batchLayers, []),
            discountRules: this.parseJson(option.dataset.discountRules, []),
            defaultDiscountRuleId: option.dataset.defaultDiscountRuleId,
        }
    }

    setDiscountOptions(row, product) {
        const ruleSelect = row.querySelector('[data-order-entry-target~="discountRule"]')
        const modeInput = row.querySelector('[data-order-entry-target~="discountMode"]')
        const percentInput = row.querySelector('[data-order-entry-target~="discountPercent"]')

        if (!ruleSelect) {
            return
        }

        const rules = Array.isArray(product.discountRules) ? product.discountRules : []
        const currentValue = ruleSelect.value
        const defaultRuleId = product.defaultDiscountRuleId ? String(product.defaultDiscountRuleId) : ''
        ruleSelect.innerHTML = `<option value="">${this.escapeHtml(trans('order.discount.none', 'No discount'))}</option>`

        rules.forEach((rule) => {
            const option = new Option(this.discountRuleLabel(rule), rule.id, false, false)
            option.dataset.percent = rule.percent || ''
            option.dataset.name = rule.name || ''
            ruleSelect.appendChild(option)
        })

        if (currentValue && Array.from(ruleSelect.options).some((option) => option.value === currentValue)) {
            ruleSelect.value = currentValue
        } else if (defaultRuleId && Array.from(ruleSelect.options).some((option) => option.value === defaultRuleId)) {
            ruleSelect.value = defaultRuleId
        } else {
            ruleSelect.value = ''
        }

        const selected = ruleSelect.selectedOptions[0]
        if (selected && selected.value) {
            if (modeInput) modeInput.value = 'rule'
            if (percentInput) percentInput.value = selected.dataset.percent ? this.formatPercent(selected.dataset.percent) : ''
        } else if (modeInput && modeInput.value !== 'manual_override') {
            modeInput.value = 'none'
            if (percentInput) percentInput.value = ''
        }

        const discountMeta = row.querySelector('[data-order-entry-target~="discountMeta"]')
        if (discountMeta) {
            discountMeta.textContent = selected && selected.value && selected.dataset.percent ? `${this.formatPercent(selected.dataset.percent)}%` : ''
        }
    }

    entryChanged(event) {
        if (event.detail.productChanged) {
            this.syncProductData(event.detail.row)
        }

        this.recalculateRow(event.detail.row)
        this.requestSummaryCalculation()
    }

    removeEntry(event) {
        const row = event.detail.row
        if (this.activeRow === row) this.activeRow = null
        row.remove()
        this.requestSummaryCalculation()
        this.toggleEmptyState()
    }

    activateRow(row) {
        this.rowTargets.forEach((item) => item.classList.remove('table-active'))
        row.classList.add('table-active')
        this.activeRow = row
    }

    recalculateRow(row) {
        const quantity = this.numberValue(this.getRowValue(row, 'quantity'))
        const available = this.numberValue(row.dataset.available || '0')
        const warning = row.querySelector('[data-order-entry-target~="availabilityWarning"]')

        if (warning) {
            warning.classList.toggle('d-none', !(available > 0 && quantity > available))
        }
    }

    recalculateSummary() {
        this.requestSummaryCalculation()
    }

    requestSummaryCalculation() {
        window.clearTimeout(this.summaryTimeout)
        this.summaryTimeout = window.setTimeout(() => this.loadSummaryCalculation(), 250)
    }

    loadSummaryCalculation() {
        const url = this.formTarget.dataset.orderFormSummaryUrl

        if (!url) {
            return
        }

        if (this.summaryAbortController) {
            this.summaryAbortController.abort()
        }

        this.summaryAbortController = new AbortController()
        const requestId = ++this.summaryRequestId
        this.setSummaryLoading(true)
        const formData = this.buildSummaryFormData()

        fetch(url, {
            method: 'POST',
            body: formData,
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: this.summaryAbortController.signal,
        })
            .then((response) => response.json())
            .then((data) => {
                if (requestId === this.summaryRequestId) {
                    this.applySummary(data)
                }
            })
            .catch((error) => {
                if (requestId === this.summaryRequestId && error.name !== 'AbortError') {
                    this.setSummaryLoading(false)
                }
            })
            .finally(() => {
                if (requestId === this.summaryRequestId) {
                    this.setSummaryLoading(false)
                }
            })
    }

    buildSummaryFormData() {
        const formData = new FormData(this.formTarget)

        this.rowTargets.forEach((row) => {
            if (this.getRowValue(row, 'product')) {
                return
            }

            const productId = row.dataset.productId
            const quantity = row.querySelector('[data-order-entry-target~="quantity"]')
            const discountMode = row.querySelector('[data-order-entry-target~="discountMode"]')
            const discountRule = row.querySelector('[data-order-entry-target~="discountRule"]')
            const discountPercent = row.querySelector('[data-order-entry-target~="discountPercent"]')

            if (!productId || !quantity || !quantity.name) {
                return
            }

            formData.set(quantity.name.replace(/\[quantity\]$/, '[product]'), productId)
            if (discountMode && discountMode.name) {
                formData.set(discountMode.name, discountMode.value)
            }
            if (discountRule && discountRule.name) {
                formData.set(discountRule.name, discountRule.value)
            }
            if (discountPercent && discountPercent.name) {
                formData.set(discountPercent.name, discountPercent.value)
            }
        })

        return formData
    }

    applySummary(data) {
        // summaryCount/summaryTotal can appear twice (summary card + sticky footer mirror),
        // so update every matching target; subtotal/discount live only in the summary card.
        const count = String(data.count || 0)
        const total = this.formatMoney(data.total || '0.00')
        this.summaryCountTargets.forEach((target) => { target.textContent = count })
        this.summaryTotalTargets.forEach((target) => { target.textContent = total })
        this.summarySubtotalTarget.textContent = this.formatMoney(data.subtotal || '0.00')
        this.summaryDiscountTarget.textContent = this.formatMoney(data.discount || '0.00')

        Object.entries(data.lineTotals || {}).forEach(([index, total]) => {
            const row = this.entriesTarget.querySelector(`[data-index="${index}"]`)
            const totalTarget = row ? row.querySelector('[data-order-entry-target~="lineTotal"]') : null

            if (totalTarget) {
                totalTarget.textContent = this.formatMoney(total)
            }
        })

        Object.entries(data.lineDiscounts || {}).forEach(([index, discount]) => {
            const row = this.entriesTarget.querySelector(`[data-index="${index}"]`)
            const discountTarget = row ? row.querySelector('[data-order-entry-target~="discountAmount"]') : null

            if (discountTarget) {
                discountTarget.textContent = this.formatMoney(discount)
            }
        })
    }

    setSummaryLoading(isLoading) {
        if (this.hasSummaryStatusTarget) {
            this.summaryStatusTarget.classList.toggle('d-none', !isLoading)
        }

        if (this.hasSummaryTarget) {
            this.summaryTarget.classList.toggle('is-loading', isLoading)
        }
    }

    updateCurrencyLabels(root) {
        const currency = this.currentCurrency()
        const container = root || this.element

        container.querySelectorAll('[data-order-currency-label]').forEach((target) => {
            target.textContent = currency
        })
    }

    currentCurrency() {
        const currency = Array.from(this.formTarget.elements).find((field) => field.name && field.name.endsWith('[currency]'))

        if (!currency) {
            return ''
        }

        const selected = currency.selectedOptions ? currency.selectedOptions[0] : null

        return selected && selected.textContent.trim()
            ? selected.textContent.trim()
            : currency.value
    }

    validate(event) {
        let isValid = true
        const customerFields = [
            this.hasCustomerPhoneTarget ? this.customerPhoneTarget : null,
            this.hasCustomerNameTarget ? this.customerNameTarget : null,
            this.hasCustomerLastNameTarget ? this.customerLastNameTarget : null,
        ]

        customerFields.forEach((field) => {
            if (field && !field.value.trim()) {
                this.markInvalid(field)
                isValid = false
            }
        })

        this.rowTargets.forEach((row) => {
            const product = row.querySelector('[data-order-entry-target~="product"]')
            const quantity = row.querySelector('[data-order-entry-target~="quantity"]')
            const unitPrice = row.querySelector('[data-order-entry-target~="unitPrice"]')

            this.ensureProductValue(row, product)

            if (!product || !product.value) {
                this.markInvalid(row)
                isValid = false
            }

            if (!quantity || this.numberValue(quantity.value) <= 0) {
                this.markInvalid(quantity)
                isValid = false
            }

            if (!unitPrice || this.numberValue(unitPrice.value) < 0 || unitPrice.value === '') {
                this.markInvalid(unitPrice)
                isValid = false
            }
        })

        if (!isValid) {
            event.preventDefault()
            event.stopPropagation()
        }
    }

    ensureProductValue(row, select) {
        if (!select || select.value || !row.dataset.productId) {
            return
        }

        let option = Array.from(select.options).find((item) => item.value === String(row.dataset.productId))
        if (!option) {
            const productLabel = row.querySelector('[data-order-entry-target~="productLabel"]')
            const productMeta = row.querySelector('[data-order-entry-target~="productMeta"]')
            const text = productMeta && productMeta.textContent.trim()
                ? `${productLabel ? productLabel.textContent.trim() : row.dataset.productId} (${productMeta.textContent.trim()})`
                : (productLabel ? productLabel.textContent.trim() : row.dataset.productId)

            option = new Option(text, row.dataset.productId, true, true)
            select.appendChild(option)
        }

        option.selected = true
    }

    markInvalid(input) {
        if (!input) {
            return
        }

        input.classList.add('is-invalid')
        input.addEventListener('input', () => input.classList.remove('is-invalid'), {once: true})
        input.addEventListener('change', () => input.classList.remove('is-invalid'), {once: true})
        input.addEventListener('click', () => input.classList.remove('is-invalid'), {once: true})
    }

    toggleEmptyState() {
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('d-none', this.rowTargets.length > 0)
        }
    }

    getRowValue(row, targetName) {
        const input = row.querySelector(`[data-order-entry-target~="${targetName}"]`)

        return input ? input.value : ''
    }

    setRowValue(row, targetName, value) {
        const input = row.querySelector(`[data-order-entry-target~="${targetName}"]`)

        if (input) {
            input.value = value
        }
    }

    parseJson(value, fallback) {
        if (!value) {
            return fallback
        }

        try {
            return JSON.parse(value)
        } catch (error) {
            return fallback
        }
    }

    numberValue(value) {
        const number = Number.parseFloat(String(value || '').replace(/\s/g, '').replace(',', '.'))

        return Number.isFinite(number) ? number : 0
    }

    format(value) {
        // Trim trailing zeros so price inputs read as "6900" / "6900.5", not "6900.0000".
        return String(Number.parseFloat(value.toFixed(4)))
    }

    formatMoney(value) {
        const number = this.numberValue(value)

        return number.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    }

    formatPercent(value) {
        const number = this.numberValue(value)

        return String(Number.parseFloat(number.toFixed(2)))
    }

    // Avoid "10 % (10%)" when the rule name already contains the percent.
    discountRuleLabel(rule) {
        const percent = this.formatPercent(rule.percent)
        const name = rule.name || ''

        return name.includes(`${percent}%`) || name.includes(`${percent} %`)
            ? name
            : `${name} (${percent}%)`
    }

    formatQuantity(value, precision) {
        const unitPrecision = this.normalizedUnitPrecision(precision)

        if (unitPrecision === 0) {
            return String(Math.trunc(value))
        }

        return value.toFixed(unitPrecision).replace(/\.?0+$/, '')
    }

    applyQuantityPrecision(input, precision) {
        if (!input) {
            return
        }

        const unitPrecision = this.normalizedUnitPrecision(precision)
        const step = unitPrecision === 0 ? '1' : `0.${'0'.repeat(unitPrecision - 1)}1`

        input.step = step
        input.min = step
    }

    normalizedUnitPrecision(precision) {
        const unitPrecision = Number.parseInt(precision, 10)

        return Number.isFinite(unitPrecision) ? Math.min(4, Math.max(0, unitPrecision)) : 4
    }

    productName(text, code) {
        if (!code) {
            return text
        }

        return text.replace(new RegExp(`\\s*\\(${this.escapeRegExp(code)}\\)\\s*$`), '')
    }

    escapeRegExp(value) {
        return String(value).replace(/[.*+?^${}()|[\]\\]/g, '\\$&')
    }

    escapeHtml(value) {
        const div = document.createElement('div')
        div.textContent = String(value)

        return div.innerHTML
    }
}
