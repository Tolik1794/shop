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
        'discountPercent',
        'discountAmount',
        'discountMeta',
        'lineTotal',
    ]

    connect() {
        this.syncDiscount(null)
        this.recalculate()
        this.formatAvailable()
    }

    changed(event) {
        this.syncDiscount(event ? event.target : null)
        this.recalculate()
        this.dispatch('changed', {detail: {row: this.element}})
    }

    remove(event) {
        event.preventDefault()
        event.stopPropagation()
        this.dispatch('remove', {detail: {row: this.element}})
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

        if (target === this.discountPercentTarget && this.numberValue(this.discountPercentTarget.value) > 0) {
            this.discountModeTarget.value = 'manual_override'
            this.discountRuleTarget.value = ''
            this.updateDiscountMeta()
            return
        }

        const selectedRule = this.discountRuleTarget.selectedOptions[0]
        if (selectedRule && selectedRule.value) {
            this.discountModeTarget.value = 'rule'
            this.discountPercentTarget.value = selectedRule.dataset.percent || ''
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
            this.discountMetaTarget.textContent = `${percent.toFixed(2)}%`
            return
        }

        const selectedRule = this.hasDiscountRuleTarget ? this.discountRuleTarget.selectedOptions[0] : null
        this.discountMetaTarget.textContent = selectedRule && selectedRule.value && selectedRule.dataset.percent
            ? `${selectedRule.dataset.percent}%`
            : ''
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

        return value.toFixed(normalizedPrecision)
    }
}
