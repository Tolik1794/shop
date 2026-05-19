import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'documentType',
        'orderGroup',
        'purchaseGroup',
        'order',
        'purchase',
        'direction',
        'amount',
        'currency',
    ]

    connect() {
        this.toggle()
        this.bindDocumentDefaults()
    }

    disconnect() {
        this.unbindDocumentDefaults()
    }

    toggle() {
        const documentType = this.selectedDocumentType()

        this.toggleGroup(this.orderGroupTarget, documentType === 'order')
        this.toggleGroup(this.purchaseGroupTarget, documentType === 'purchase')
        this.toggleSelect(this.orderTarget, documentType === 'order')
        this.toggleSelect(this.purchaseTarget, documentType === 'purchase')
    }

    selectedDocumentType() {
        const selectedTarget = this.documentTypeTargets.find((target) => target.checked)

        if (selectedTarget) {
            return selectedTarget.value
        }

        if (this.hasOrderTarget && this.orderTarget.value) {
            return 'order'
        }

        if (this.hasPurchaseTarget && this.purchaseTarget.value) {
            return 'purchase'
        }

        return null
    }

    toggleGroup(group, visible) {
        group.classList.toggle('d-none', !visible)
    }

    toggleSelect(select, enabled) {
        select.disabled = !enabled

        if (enabled) {
            return
        }

        if (window.$ && window.$(select).data('select2')) {
            window.$(select).val(null).trigger('change')

            return
        }

        select.value = ''
    }

    bindDocumentDefaults() {
        if (!window.$) {
            return
        }

        if (this.hasOrderTarget) {
            window.$(this.orderTarget).on('select2:select.payment-form', (event) => {
                this.applyDocumentDefaults(event.params.data.paymentDefaults)
            })
        }

        if (this.hasPurchaseTarget) {
            window.$(this.purchaseTarget).on('select2:select.payment-form', (event) => {
                this.applyDocumentDefaults(event.params.data.paymentDefaults)
            })
        }
    }

    unbindDocumentDefaults() {
        if (!window.$) {
            return
        }

        if (this.hasOrderTarget) {
            window.$(this.orderTarget).off('.payment-form')
        }

        if (this.hasPurchaseTarget) {
            window.$(this.purchaseTarget).off('.payment-form')
        }
    }

    applyDocumentDefaults(defaults) {
        if (!defaults) {
            return
        }

        this.setSelectValue(this.directionTarget, defaults.direction)
        this.setSelectValue(this.currencyTarget, defaults.currency)
        this.amountTarget.value = defaults.amount || ''
    }

    setSelectValue(select, value) {
        if (!value) {
            return
        }

        select.value = value

        if (window.$ && window.$(select).data('select2')) {
            window.$(select).trigger('change')
        } else {
            select.dispatchEvent(new Event('change', {bubbles: true}))
        }
    }
}
