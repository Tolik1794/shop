import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = [
        'form',
        'prototype',
        'targets',
        'row',
        'emptyState',
        'percent',
        'targetType',
        'productGroup',
        'categoryGroup',
        'product',
        'price',
        'cost',
        'discountedPrice',
        'belowCost',
    ]

    connect() {
        this.nextIndex = this.rowTargets.length
        this.rowTargets.forEach((row) => this.initializeRow(row))
        this.toggleEmptyState()
        this.recalculate()
    }

    addTarget() {
        const template = this.prototypeTarget.innerHTML.replace(/__name__/g, this.nextIndex++)
        const wrapper = document.createElement('div')
        wrapper.innerHTML = template.trim()
        const row = wrapper.firstElementChild

        this.targetsTarget.appendChild(row)
        this.initializeRow(row)
        this.toggleEmptyState()
    }

    removeTarget(event) {
        event.preventDefault()
        event.currentTarget.closest('[data-discount-form-target~="row"]').remove()
        this.toggleEmptyState()
    }

    targetTypeChanged(event) {
        this.updateRowMode(event.currentTarget.closest('[data-discount-form-target~="row"]'))
    }

    productChanged(event) {
        const row = event.currentTarget.closest('[data-discount-form-target~="row"]')
        const data = this.selectedProductData(event.currentTarget)

        if (data) {
            row.dataset.productPrice = data.price || ''
            row.dataset.productCost = data.cost || ''
        }

        this.recalculateRow(row)
    }

    recalculate() {
        this.rowTargets.forEach((row) => this.recalculateRow(row))
    }

    initializeRow(row) {
        row.querySelectorAll('select.select2').forEach((select) => this.initializeSelect2(select, row))
        this.updateRowMode(row)
        this.recalculateRow(row)
    }

    initializeSelect2(select, row) {
        if (!window.$ || !window.$.fn || !window.$.fn.select2 || window.$(select).hasClass('select2-hidden-accessible')) {
            return
        }

        const ajaxUrl = select.dataset.select2AjaxUrl
        const options = {
            class: 'form-control',
            width: '100%',
            allowClear: true,
            closeOnSelect: true,
        }

        if (ajaxUrl) {
            options.minimumInputLength = select.dataset.select2MinimumInputLength || 3
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
            .on('select2:select select2:clear', () => {
                select.dispatchEvent(new Event('change', {bubbles: true}))
                this.recalculateRow(row)
            })
    }

    updateRowMode(row) {
        const type = row.querySelector('[data-discount-form-target~="targetType"]')
        const isCategory = type && type.value === 'category'

        row.querySelectorAll('[data-discount-form-target~="productGroup"]').forEach((group) => group.classList.toggle('d-none', isCategory))
        row.querySelectorAll('[data-discount-form-target~="categoryGroup"]').forEach((group) => group.classList.toggle('d-none', !isCategory))
    }

    recalculateRow(row) {
        const price = this.numberValue(row.dataset.productPrice)
        const cost = this.numberValue(row.dataset.productCost)
        const percent = this.numberValue(this.hasPercentTarget ? this.percentTarget.value : 0)
        const discountedPrice = price > 0 ? price - (price * percent / 100) : 0

        this.setTargetText(row, 'price', price > 0 ? this.formatMoney(price) : '-')
        this.setTargetText(row, 'cost', cost > 0 ? this.formatMoney(cost) : '-')
        this.setTargetText(row, 'discountedPrice', discountedPrice > 0 ? this.formatMoney(discountedPrice) : '-')

        row.querySelectorAll('[data-discount-form-target~="belowCost"]').forEach((target) => {
            target.classList.toggle('d-none', !(price > 0 && cost > 0 && discountedPrice < cost))
        })
    }

    selectedProductData(select) {
        if (!select || !select.value || !window.$ || !window.$(select).data('select2')) {
            return null
        }

        const data = window.$(select).select2('data')

        return data && data.length > 0 ? data[0] : null
    }

    setTargetText(row, targetName, value) {
        row.querySelectorAll(`[data-discount-form-target~="${targetName}"]`).forEach((target) => {
            target.textContent = value
        })
    }

    toggleEmptyState() {
        if (this.hasEmptyStateTarget) {
            this.emptyStateTarget.classList.toggle('d-none', this.rowTargets.length > 0)
        }
    }

    numberValue(value) {
        const number = Number.parseFloat(String(value || '').replace(',', '.'))

        return Number.isFinite(number) ? number : 0
    }

    formatMoney(value) {
        return value.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    }
}
