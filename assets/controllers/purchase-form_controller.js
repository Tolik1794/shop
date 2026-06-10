import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'prototype',
        'entries',
        'row',
        'emptyState',
        'currency',
        'deliveryCost',
        'summaryEntryCount',
        'summaryQuantity',
        'summaryPurchaseTotal',
        'summaryDelivery',
        'summaryPurchaseWithDelivery',
        'summarySaleTotal',
        'summaryMargin',
        'summaryEmpty',
        'summaryContent',
        'incompleteSaleWarning',
    ]

    connect() {
        this.nextIndex = this.rowTargets.length
        this.rowTargets.forEach((row) => this.initializeRow(row))
        this.toggleEmptyState()
        this.recalculate()
    }

    addEntry() {
        const template = this.prototypeTarget.innerHTML.replace(/__name__/g, this.nextIndex++)
        const wrapper = document.createElement('div')
        wrapper.innerHTML = template.trim()
        const row = wrapper.firstElementChild

        this.entriesTarget.appendChild(row)
        this.initializeRow(row)
        this.toggleEmptyState()
        this.recalculate()
    }

    removeEntry(event) {
        event.preventDefault()
        event.currentTarget.closest('[data-purchase-form-target~="row"]').remove()
        this.toggleEmptyState()
        this.recalculate()
    }

    toggleEmptyState() {
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('d-none', this.rowTargets.length > 0)
        }
    }

    initializeRow(row) {
        row.querySelectorAll('select.select2').forEach((select) => this.initializeSelect2(select))
    }

    recalculate() {
        let entryCount = 0
        let totalQuantity = 0
        let purchaseTotal = 0
        let saleTotal = 0
        let hasMissingSalePrice = false

        this.rowTargets.forEach((row) => {
            const product = this.rowField(row, 'product')
            const warehouse = this.rowField(row, 'warehouse')
            const quantityField = this.rowField(row, 'quantity')
            const unitCostField = this.rowField(row, 'unitCost')
            const salePriceField = this.rowField(row, 'salePrice')
            const quantity = this.numberValue(quantityField?.value)
            const unitCost = this.numberValue(unitCostField?.value)
            const salePrice = this.numberValue(salePriceField?.value)
            const hasSalePrice = Boolean(salePriceField?.value)
            const isEmpty = !product?.value
                && !warehouse?.value
                && quantity === 0
                && unitCost === 0
                && !hasSalePrice

            row.classList.toggle('is-empty', isEmpty)
            row.classList.toggle('is-filled', !isEmpty)

            if (isEmpty) {
                return
            }

            const rowPurchaseTotal = quantity * unitCost
            const rowSaleTotal = quantity * salePrice
            const rowMargin = rowSaleTotal - rowPurchaseTotal
            const rowMarginPercent = rowPurchaseTotal > 0 ? (rowMargin / rowPurchaseTotal) * 100 : null
            const belowCost = hasSalePrice && salePrice < unitCost

            entryCount += 1
            totalQuantity += quantity
            purchaseTotal += rowPurchaseTotal
            saleTotal += rowSaleTotal
            hasMissingSalePrice = hasMissingSalePrice || !hasSalePrice

            this.setRowText(row, 'rowPurchaseTotal', this.formatMoney(rowPurchaseTotal))
            this.setRowText(row, 'rowSaleTotal', hasSalePrice ? this.formatMoney(rowSaleTotal) : '-')
            this.setRowText(row, 'rowMargin', hasSalePrice ? this.formatMoney(rowMargin) : '-')
            this.setRowText(row, 'rowMarginPercent', hasSalePrice && rowMarginPercent !== null ? `(${this.formatPercent(rowMarginPercent)}%)` : '')
            this.toggleRowTarget(row, 'belowCostWarning', belowCost)
            this.setMarginState(row.querySelector('[data-purchase-form-target~="rowMargin"]'), hasSalePrice ? rowMargin : null)
        })

        const delivery = this.numberValue(this.hasDeliveryCostTarget ? this.deliveryCostTarget.value : 0)
        const purchaseWithDelivery = purchaseTotal + delivery
        const margin = saleTotal - purchaseWithDelivery

        this.summaryEntryCountTarget.textContent = entryCount
        this.summaryQuantityTarget.textContent = this.formatQuantity(totalQuantity)
        this.summaryPurchaseTotalTarget.textContent = this.formatMoney(purchaseTotal)
        this.summaryDeliveryTarget.textContent = this.formatMoney(delivery)
        this.summaryPurchaseWithDeliveryTarget.textContent = this.formatMoney(purchaseWithDelivery)
        this.summarySaleTotalTarget.textContent = this.formatMoney(saleTotal)
        this.summaryMarginTarget.textContent = this.formatMoney(margin)
        this.setMarginState(this.summaryMarginTarget, margin)
        this.incompleteSaleWarningTarget.classList.toggle('d-none', !hasMissingSalePrice)
        const showSummaryEmpty = entryCount === 0 && delivery === 0
        this.summaryEmptyTarget.classList.toggle('d-none', !showSummaryEmpty)
        this.summaryContentTarget.classList.toggle('d-none', showSummaryEmpty)
        this.updateCurrencyLabels()
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

    rowField(row, target) {
        return row.querySelector(`[data-purchase-form-target~="${target}"]`)
    }

    setRowText(row, target, value) {
        const element = this.rowField(row, target)

        if (element) {
            element.textContent = value
        }
    }

    toggleRowTarget(row, target, visible) {
        const element = this.rowField(row, target)

        if (element) {
            element.classList.toggle('d-none', !visible)
        }
    }

    updateCurrencyLabels() {
        const option = this.hasCurrencyTarget
            ? this.currencyTarget.options[this.currencyTarget.selectedIndex]
            : null
        const currency = option?.textContent?.trim() || ''

        this.element.querySelectorAll('[data-purchase-currency-label]').forEach((label) => {
            label.textContent = currency
        })
    }

    setMarginState(element, margin) {
        if (!element) {
            return
        }

        element.classList.toggle('text-success', margin !== null && margin >= 0)
        element.classList.toggle('text-danger', margin !== null && margin < 0)
    }

    numberValue(value) {
        const number = Number.parseFloat(String(value || '').replace(/\s/g, '').replace(',', '.'))

        return Number.isFinite(number) ? number : 0
    }

    formatMoney(value) {
        return this.numberValue(value).toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    }

    formatQuantity(value) {
        return String(Number.parseFloat(this.numberValue(value).toFixed(4)))
    }

    formatPercent(value) {
        return String(Number.parseFloat(this.numberValue(value).toFixed(2)))
    }
}
