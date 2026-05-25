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
        'discount',
        'lineTotal',
    ]

    connect() {
        this.recalculate()
        this.formatAvailable()
    }

    changed() {
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
