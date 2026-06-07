import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'row',
        'product',
        'productLabel',
        'productMeta',
        'warehouse',
        'warehouseStockBatch',
        'sourceLabel',
        'sourceMeta',
        'available',
        'unitLabel',
        'availabilityWarning',
        'quantity',
        'unitPrice',
        'discountMode',
        'discountRule',
        'discountSelect',
        'discountPercent',
        'discountAmount',
        'discountMeta',
        'lineTotal',
        'manualDiscount',
        'manualToggle',
    ]

    connect() {
        this.normalizeQuantityInput()
        this.normalizeUnitPriceInput()
        this.normalizeManualPercentInput()
        this.applyInitialDiscountState()
        this.recalculate()
        this.formatAvailable()
        this.updateAvailabilityVisibility()
    }

    // Strip excess precision from the initial field values ("49600.0000" -> "49600",
    // "10.0000" -> "10"). Display only: the values still submit/parse identically.
    normalizeQuantityInput() {
        if (!this.hasQuantityTarget || this.quantityTarget.value === '') {
            return
        }

        this.quantityTarget.value = this.formatQuantity(
            this.numberValue(this.quantityTarget.value),
            this.element.dataset.unitPrecision || '4'
        )
    }

    normalizeManualPercentInput() {
        if (!this.hasDiscountPercentTarget || this.discountPercentTarget.value === '') {
            return
        }

        this.discountPercentTarget.value = this.formatPercent(this.discountPercentTarget.value)
    }

    normalizeUnitPriceInput() {
        if (!this.hasUnitPriceTarget || this.unitPriceTarget.value === '') {
            return
        }

        this.unitPriceTarget.value = String(Number.parseFloat(this.numberValue(this.unitPriceTarget.value).toFixed(4)))
    }

    // Respect a pre-set discount mode on load: never silently reset a saved manual override
    // to "none". Only re-derive the rule/none state from the select.
    applyInitialDiscountState() {
        if (this.hasDiscountModeTarget && this.discountModeTarget.value === 'manual_override') {
            if (this.hasManualToggleTarget) {
                this.manualToggleTarget.checked = true
            }
            if (this.hasDiscountSelectTarget) {
                this.discountSelectTarget.classList.add('d-none')
            }
            if (this.hasManualDiscountTarget) {
                this.manualDiscountTarget.classList.remove('d-none')
            }

            return
        }

        this.syncDiscount(null)
    }

    // Production lines have no warehouse stock, so hide the "Availability: 0" hint and show
    // a neutral note instead, so the line does not look like an error.
    updateAvailabilityVisibility() {
        const isProduction = this.element.dataset.sourceType === 'production'
        const avail = this.element.querySelector('.order-entry-head__avail')
        const note = this.element.querySelector('.order-entry-head__production')

        if (avail) {
            avail.classList.toggle('d-none', isProduction)
        }
        if (note) {
            note.classList.toggle('d-none', !isProduction)
        }
    }

    changed(event) {
        const target = event ? event.target : null

        this.syncDiscount(target)
        this.recalculate()
        this.dispatch('changed', {
            detail: {
                row: this.element,
                productChanged: target === this.productTarget,
            },
        })
    }

    remove(event) {
        event.preventDefault()
        event.stopPropagation()
        this.dispatch('remove', {detail: {row: this.element}})
    }

    // Switch between rule-based discount (select) and manual override (percent input).
    // When on: hide the select, show the input, set manual_override and clear the rule.
    // When off: restore the select and re-derive the rule/none state.
    toggleManualDiscount() {
        const on = this.hasManualToggleTarget ? this.manualToggleTarget.checked : false

        if (this.hasDiscountSelectTarget) {
            this.discountSelectTarget.classList.toggle('d-none', on)
        }
        if (this.hasManualDiscountTarget) {
            this.manualDiscountTarget.classList.toggle('d-none', !on)
        }

        if (on) {
            if (this.hasDiscountModeTarget) {
                this.discountModeTarget.value = 'manual_override'
            }
            if (this.hasDiscountRuleTarget) {
                this.discountRuleTarget.value = ''
            }
            if (this.hasDiscountPercentTarget) {
                this.discountPercentTarget.focus()
            }
        } else {
            if (this.hasDiscountPercentTarget) {
                this.discountPercentTarget.value = ''
            }
            this.syncDiscount(null)
        }

        this.recalculate()
        this.dispatch('changed', {detail: {row: this.element, productChanged: false}})
    }

    recalculate() {
        const quantity = this.numberValue(this.quantityTarget.value)
        const available = this.numberValue(this.element.dataset.available || '0')

        this.availabilityWarningTarget.classList.toggle('d-none', !(available > 0 && quantity > available))

        const selectedProduct = this.productTarget.selectedOptions[0]
        if (selectedProduct && selectedProduct.value && this.hasProductLabelTarget) {
            const productName = this.productName(selectedProduct.textContent, selectedProduct.dataset.code)
            if (productName) {
                this.productLabelTarget.textContent = productName
            }
        }
        if (selectedProduct && selectedProduct.value && this.hasProductMetaTarget && selectedProduct.dataset.code) {
            this.productMetaTarget.textContent = selectedProduct.dataset.code || ''
        }

        this.updateDiscountMeta()
    }

    syncDiscount(target) {
        if (!this.hasDiscountModeTarget || !this.hasDiscountPercentTarget || !this.hasDiscountRuleTarget) {
            return
        }

        // While the manual switch is on, keep manual override regardless of which field changed
        // (editing quantity/price must not silently reset the manual discount).
        if (this.hasManualToggleTarget && this.manualToggleTarget.checked) {
            this.discountModeTarget.value = 'manual_override'
            this.discountRuleTarget.value = ''
            this.updateDiscountMeta()
            return
        }

        if (target === this.discountPercentTarget && this.numberValue(this.discountPercentTarget.value) > 0) {
            this.discountModeTarget.value = 'manual_override'
            this.discountRuleTarget.value = ''
            this.updateDiscountMeta()
            return
        }

        const selectedRule = this.discountRuleTarget.selectedOptions[0]
        if (selectedRule && selectedRule.value) {
            this.discountModeTarget.value = 'rule'
            this.discountPercentTarget.value = selectedRule.dataset.percent ? this.formatPercent(selectedRule.dataset.percent) : ''
            this.updateDiscountMeta()
            return
        }

        this.discountModeTarget.value = 'none'
        this.discountPercentTarget.value = ''
        this.updateDiscountMeta()
    }

    updateDiscountMeta() {
        if (!this.hasDiscountMetaTarget || !this.hasDiscountModeTarget || !this.hasDiscountPercentTarget) {
            return
        }

        const percent = this.numberValue(this.discountPercentTarget.value)
        if (this.discountModeTarget.value === 'manual_override' && percent > 0) {
            this.discountMetaTarget.textContent = `${this.formatPercent(this.discountPercentTarget.value)}%`
            return
        }

        const selectedRule = this.hasDiscountRuleTarget ? this.discountRuleTarget.selectedOptions[0] : null
        this.discountMetaTarget.textContent = selectedRule && selectedRule.value && selectedRule.dataset.percent
            ? `${this.formatPercent(selectedRule.dataset.percent)}%`
            : ''
    }

    formatPercent(value) {
        const number = this.numberValue(value)

        return String(Number.parseFloat(number.toFixed(2)))
    }

    formatAvailable() {
        if (!this.hasAvailableTarget) {
            return
        }

        this.availableTarget.textContent = this.formatQuantity(
            this.numberValue(this.element.dataset.available || this.availableTarget.textContent),
            this.element.dataset.unitPrecision || '4'
        )
    }

    numberValue(value) {
        const number = Number.parseFloat(String(value || '').replace(',', '.'))

        return Number.isFinite(number) ? number : 0
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

    formatQuantity(value, precision) {
        const unitPrecision = Number.parseInt(precision, 10)
        const normalizedPrecision = Number.isFinite(unitPrecision) ? Math.min(4, Math.max(0, unitPrecision)) : 4

        if (normalizedPrecision === 0) {
            return String(Math.trunc(value))
        }

        return value.toFixed(normalizedPrecision).replace(/\.?0+$/, '')
    }
}
