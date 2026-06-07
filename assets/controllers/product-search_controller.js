import {Controller} from '@hotwired/stimulus'

function trans(key, fallback, parameters = {}) {
    let value = window.adminTranslations && window.adminTranslations[key] ? window.adminTranslations[key] : fallback

    Object.entries(parameters).forEach(([name, replacement]) => {
        value = value.replace(`%${name}%`, replacement)
    })

    return value
}

export default class extends Controller {
    static targets = ['query', 'results', 'error']

    connect() {
        this.abortController = null
        this.searchTimeout = null
        this.page = 1
        this.limit = 20
        this.hasMore = false
        this.isLoading = false
        this.currentQuery = ''
        this.requestId = 0
        this.currencyChangedHandler = this.currencyChanged.bind(this)
        this.element.addEventListener('change', this.currencyChangedHandler)
    }

    disconnect() {
        this.element.removeEventListener('change', this.currencyChangedHandler)
    }

    search() {
        window.clearTimeout(this.searchTimeout)
        this.searchTimeout = window.setTimeout(() => this.load(true), 250)
    }

    open() {
        if (this.resultsTarget.innerHTML.trim()) {
            this.resultsTarget.classList.remove('d-none')
        }
    }

    scroll() {
        if (!this.hasMore || this.isLoading) {
            return
        }

        const bottomOffset = this.resultsTarget.scrollHeight - this.resultsTarget.scrollTop - this.resultsTarget.clientHeight
        if (bottomOffset > 80) {
            return
        }

        this.page += 1
        this.load(false)
    }

    load(reset) {
        const query = this.queryTarget.value.trim()

        if (query.length < 3) {
            this.clearResults()
            this.clearError()
            return
        }

        if (reset) {
            this.page = 1
            this.hasMore = false
            this.currentQuery = query
        }

        if (query !== this.currentQuery) {
            this.page = 1
            this.hasMore = false
            this.currentQuery = query
            reset = true
        }

        if (this.abortController && reset) {
            this.abortController.abort()
        }

        this.isLoading = true
        this.setLoading(true)
        this.abortController = new AbortController()
        const requestId = ++this.requestId
        this.renderLoading(reset)

        const params = new URLSearchParams({
            q: query,
            page: String(this.page),
            limit: String(this.limit),
        })
        const currency = this.currencyCode()

        if (currency) {
            params.set('currency', currency)
        }

        this.excludedOptions().forEach((optionKey) => params.append('excludedOptions[]', optionKey))

        const url = `${this.queryTarget.dataset.productSearchUrl}?${params.toString()}`
        fetch(url, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
            signal: this.abortController.signal,
        })
            .then((response) => response.json())
            .then((data) => {
                if (requestId !== this.requestId) {
                    return
                }

                this.hasMore = Boolean(data.hasMore)
                this.renderProducts(data.products || [], !reset)
            })
            .catch((error) => {
                if (requestId === this.requestId && error.name !== 'AbortError') {
                    this.showError(trans('common.load_error', 'Could not load data.'))
                }
            })
            .finally(() => {
                if (requestId !== this.requestId) {
                    return
                }

                this.isLoading = false
                this.setLoading(false)
                this.removeLoading()
            })
    }

    choose(event) {
        const button = event.currentTarget
        const product = {
            id: button.dataset.productId,
            text: button.dataset.productText,
            code: button.dataset.productCode,
            unit: button.dataset.productUnit,
            unitPrecision: button.dataset.productUnitPrecision,
            price: button.dataset.productPrice,
            available: button.dataset.available,
            warehouseId: button.dataset.warehouseId,
            warehouseName: button.dataset.warehouseName,
            batchId: button.dataset.batchId,
            batchReceivedAt: button.dataset.batchReceivedAt,
            batchLayers: this.parseJson(button.dataset.batchLayers, []),
            sourceType: button.dataset.sourceType,
            sourceDetail: button.dataset.sourceDetail,
            discountRules: this.parseJson(button.dataset.discountRules, []),
            defaultDiscountRuleId: button.dataset.defaultDiscountRuleId,
        }

        this.dispatch('add', {detail: {product: product}})
        this.queryTarget.value = ''
        this.clearResults()
        this.clearError()
    }

    renderProducts(products, append) {
        if (!append && products.length === 0) {
            this.resultsTarget.innerHTML = `<div class="order-product-results-empty">${this.escapeHtml(trans('common.no_results', 'No results found.'))}</div>`
            this.resultsTarget.classList.remove('d-none')
            return
        }

        const html = products.map((product) => this.renderProduct(product)).join('')
        this.resultsTarget.innerHTML = append ? this.resultsTarget.innerHTML + html : html
        this.resultsTarget.classList.remove('d-none')
    }

    renderLoading(reset) {
        if (reset) {
            this.resultsTarget.innerHTML = this.loadingHtml()
            this.resultsTarget.classList.remove('d-none')
            return
        }

        this.removeLoading()
        this.resultsTarget.insertAdjacentHTML('beforeend', this.loadingHtml())
    }

    removeLoading() {
        this.resultsTarget.querySelectorAll('.order-product-results-loading').forEach((element) => element.remove())
    }

    renderProduct(product) {
        const stockOptions = (product.stockOptions || []).map((option) => this.renderStockOption(product, option)).join('')
        const productionOption = product.productionOption ? this.renderProductionOption(product, product.productionOption) : ''
        const options = stockOptions || productionOption
            ? stockOptions + productionOption
            : this.renderUnavailableOption(product)

        return `
            <div class="order-product-result">
                <div class="order-product-result-header">
                    <strong class="order-product-result-name">${this.escapeHtml(product.name || '')}</strong>
                    <span class="order-product-result-code">${this.escapeHtml(product.code || '')}</span>
                </div>
                <div class="order-product-result-rows">
                    ${options}
                </div>
            </div>
        `
    }

    renderStockOption(product, option) {
        const batchLayers = option.batchLayers || []

        return this.renderOptionButton({
            product: product,
            sourceType: 'stock',
            label: option.warehouseName || trans('order.source.stock', 'Stock'),
            available: option.available,
            price: option.price || product.price,
            warehouseId: option.warehouseId || '',
            warehouseName: option.warehouseName || '',
            batchId: option.batchId || '',
            batchReceivedAt: option.batchReceivedAt || '',
            sourceDetail: option.batchId ? `${option.warehouseName || ''} · Batch #${option.batchId}` : option.warehouseName || '',
            batchLayers: batchLayers,
        })
    }

    renderProductionOption(product, option) {
        // Ignore the DTO's English label and use the (translatable) project term instead.
        const productionLabel = trans('order.source.production', 'Make to order')

        return this.renderOptionButton({
            product: product,
            sourceType: 'production',
            modifier: 'order-product-result-row--production',
            label: productionLabel,
            available: '',
            price: option.price || product.price,
            warehouseId: '',
            warehouseName: '',
            batchId: '',
            batchReceivedAt: '',
            sourceDetail: productionLabel,
            batchLayers: [],
        })
    }

    renderUnavailableOption(product) {
        const unavailableLabel = trans('order.source.unavailable', 'No warehouse stock')

        return this.renderOptionButton({
            product: product,
            sourceType: 'stock',
            modifier: 'order-product-result-row--unavailable',
            label: unavailableLabel,
            available: '',
            price: product.price,
            warehouseId: '',
            warehouseName: '',
            batchId: '',
            batchReceivedAt: '',
            sourceDetail: unavailableLabel,
            batchLayers: [],
        })
    }

    renderOptionButton({product, sourceType, label, available, price, warehouseId, warehouseName, batchId, batchReceivedAt, sourceDetail, batchLayers, modifier}) {
        const batchLayersJson = JSON.stringify(batchLayers || [])
        const discountRulesJson = JSON.stringify(product.discountRules || [])
        const modifierClass = modifier ? ` ${modifier}` : ''

        const infoParts = [`<span class="oprr-cell oprr-cell--source">${this.escapeHtml(label)}</span>`]
        if (batchId) {
            infoParts.push(`<span class="oprr-cell oprr-cell--batch">#${this.escapeHtml(batchId)}</span>`)
        }
        if (available !== '' && available !== undefined && available !== null && sourceType === 'stock' && (batchId || warehouseId)) {
            infoParts.push(`<span class="oprr-cell oprr-cell--available">${this.escapeHtml(trans('order.product.available', 'Available'))}: ${this.escapeHtml(this.formatQuantity(this.numberValue(available), product.unitPrecision))}</span>`)
        }
        infoParts.push(this.priceHtml(product, price))
        if (batchReceivedAt) {
            infoParts.push(`<span class="oprr-cell oprr-cell--date">${this.escapeHtml(batchReceivedAt)}</span>`)
        }

        return `
            <button
                    type="button"
                    class="order-product-result-row${modifierClass}"
                    data-action="product-search#choose"
                    data-product-id="${this.escapeHtml(product.id)}"
                    data-product-text="${this.escapeHtml(product.name)}"
                    data-product-code="${this.escapeHtml(product.code || '')}"
                    data-product-unit="${this.escapeHtml(product.unit || '')}"
                    data-product-unit-precision="${this.escapeHtml(product.unitPrecision ?? 4)}"
                    data-product-price="${this.escapeHtml(price || '')}"
                    data-available="${this.escapeHtml(available || '0.0000')}"
                    data-warehouse-id="${this.escapeHtml(warehouseId || '')}"
                    data-warehouse-name="${this.escapeHtml(warehouseName || '')}"
                    data-batch-id="${this.escapeHtml(batchId || '')}"
                    data-batch-received-at="${this.escapeHtml(batchReceivedAt || '')}"
                    data-batch-layers="${this.escapeHtml(batchLayersJson)}"
                    data-source-type="${this.escapeHtml(sourceType)}"
                    data-source-detail="${this.escapeHtml(sourceDetail || label || '')}"
                    data-discount-rules="${this.escapeHtml(discountRulesJson)}"
                    data-default-discount-rule-id="${this.escapeHtml(product.defaultDiscountRuleId || '')}"
            >
                <span class="order-product-result-row__info">${infoParts.join('')}</span>
                <span class="order-product-result-row__action"><i class="fa-solid fa-plus"></i><span>${this.escapeHtml(trans('order.product.add', 'Add'))}</span></span>
            </button>
        `
    }

    clearResults() {
        this.resultsTarget.innerHTML = ''
        this.resultsTarget.classList.add('d-none')
        this.page = 1
        this.hasMore = false
        this.isLoading = false
        this.setLoading(false)
    }

    setLoading(isLoading) {
        this.element.classList.toggle('is-loading', isLoading)
    }

    loadingHtml() {
        return `<div class="order-product-results-loading order-search-loading"><span class="order-search-spinner"></span><span>${this.escapeHtml(trans('common.loading', 'Loading...'))}</span></div>`
    }

    showError(message) {
        this.errorTarget.textContent = message
    }

    clearError() {
        this.errorTarget.textContent = ''
    }

    currencyChanged(event) {
        if (!event.target || !event.target.name || !event.target.name.endsWith('[currency]')) {
            return
        }

        if (this.queryTarget.value.trim().length >= 3) {
            this.load(true)
            return
        }

        this.clearResults()
    }

    currencyCode() {
        const currencyInput = this.element.querySelector('[name$="[currency]"]')

        return currencyInput ? currencyInput.value : ''
    }

    excludedOptions() {
        const optionKeys = []

        this.element.querySelectorAll('[data-order-entry-target~="row"]').forEach((row) => {
            const productId = row.dataset.productId || this.rowValue(row, 'product')

            if (!productId) {
                return
            }

            const warehouseId = this.rowValue(row, 'warehouse')
            const batchId = row.dataset.batchId || this.rowValue(row, 'warehouseStockBatch')
            const sourceType = row.dataset.sourceType || (warehouseId ? 'stock' : 'production')
            optionKeys.push(sourceType === 'stock'
                ? `stock:${productId}:${warehouseId || ''}${batchId ? `:${batchId}` : ''}`
                : `production:${productId}`
            )
        })

        return optionKeys
    }

    rowValue(row, targetName) {
        const input = row.querySelector(`[data-order-entry-target~="${targetName}"]`)

        return input ? input.value : ''
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

    formatMoney(value) {
        const number = this.numberValue(value)

        return number.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ' ')
    }

    priceHtml(product, price) {
        const basePrice = price || product.price
        const formattedBasePrice = this.escapeHtml(this.formatMoney(basePrice || '0.00'))
        const defaultRule = this.defaultDiscountRule(product)

        if (!basePrice || !defaultRule) {
            return `<span class="oprr-cell oprr-cell--price">${formattedBasePrice}</span>`
        }

        const percent = this.numberValue(defaultRule.percent)
        const discountedPrice = this.numberValue(basePrice) * (1 - percent / 100)

        return `<span class="oprr-cell oprr-cell--price oprr-cell--price-discounted"><span class="oprr-price-original">${formattedBasePrice}</span><span class="oprr-price-discounted">${this.escapeHtml(this.formatMoney(discountedPrice))}</span></span>`
    }

    defaultDiscountRule(product) {
        const defaultRuleId = product.defaultDiscountRuleId ? String(product.defaultDiscountRuleId) : ''

        if (!defaultRuleId || !Array.isArray(product.discountRules)) {
            return null
        }

        return product.discountRules.find((rule) => String(rule.id) === defaultRuleId) || null
    }

    formatQuantity(value, precision) {
        const unitPrecision = this.normalizedUnitPrecision(precision)

        if (unitPrecision === 0) {
            return String(Math.trunc(value))
        }

        // Trim trailing zeros so quantities read as "12.5" / "100" instead of "12.5000".
        return value.toFixed(unitPrecision).replace(/\.?0+$/, '')
    }

    normalizedUnitPrecision(precision) {
        const unitPrecision = Number.parseInt(precision, 10)

        return Number.isFinite(unitPrecision) ? Math.min(4, Math.max(0, unitPrecision)) : 4
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
    }
}
