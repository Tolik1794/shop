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
            sourceType: button.dataset.sourceType,
            sourceDetail: button.dataset.sourceDetail,
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
                    <strong>${this.escapeHtml(product.name || '')}</strong>
                    <span>${this.escapeHtml(product.code || '')}</span>
                </div>
                <div class="order-product-result-options">
                    ${options}
                </div>
            </div>
        `
    }

    renderStockOption(product, option) {
        return this.renderOptionButton({
            product: product,
            sourceType: 'stock',
            label: `${trans('order.source.stock', 'Stock')}: ${option.warehouseName || ''}`,
            meta: `${trans('order.product.available', 'Available')}: ${option.available || '0.0000'} · ${trans('order.product.price', 'Price')}: ${option.price || product.price || '0.00'}`,
            available: option.available,
            price: option.price || product.price,
            warehouseId: option.warehouseId || '',
            warehouseName: option.warehouseName || '',
            sourceDetail: option.warehouseName || '',
        })
    }

    renderProductionOption(product, option) {
        const productionLabel = trans('order.source.production', 'For production')

        return this.renderOptionButton({
            product: product,
            sourceType: 'production',
            label: option.label || productionLabel,
            meta: `${trans('order.product.price', 'Price')}: ${option.price || product.price || '0.00'}`,
            available: '0.0000',
            price: option.price || product.price,
            warehouseId: '',
            warehouseName: '',
            sourceDetail: option.label || productionLabel,
        })
    }

    renderUnavailableOption(product) {
        const unavailableLabel = trans('order.source.unavailable', 'No warehouse stock')

        return this.renderOptionButton({
            product: product,
            sourceType: 'stock',
            label: unavailableLabel,
            meta: `${trans('order.product.price', 'Price')}: ${product.price || '0.00'}`,
            available: '0.0000',
            price: product.price,
            warehouseId: '',
            warehouseName: '',
            sourceDetail: unavailableLabel,
        })
    }

    renderOptionButton({product, sourceType, label, meta, available, price, warehouseId, warehouseName, sourceDetail}) {
        return `
            <button
                    type="button"
                    class="order-product-result-option"
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
                    data-source-type="${this.escapeHtml(sourceType)}"
                    data-source-detail="${this.escapeHtml(sourceDetail || label || '')}"
            >
                <span>${this.escapeHtml(label)}</span>
                <small>${this.escapeHtml(meta)}</small>
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
            const sourceType = row.dataset.sourceType || (warehouseId ? 'stock' : 'production')
            optionKeys.push(sourceType === 'stock'
                ? `stock:${productId}:${warehouseId || ''}`
                : `production:${productId}`
            )
        })

        return optionKeys
    }

    rowValue(row, targetName) {
        const input = row.querySelector(`[data-order-entry-target~="${targetName}"]`)

        return input ? input.value : ''
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
