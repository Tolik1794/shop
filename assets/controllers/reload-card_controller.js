import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'commentsCard', 'customerHistoryCard', 'historyCard', 'paymentsCard', 'paymentTabIcon', 'flashContainer']

    connect() {
        this.popStateHandler = this.popStateHandler || (() => this.syncFromLocation())
        window.addEventListener('popstate', this.popStateHandler)

        if (this.hasFlashContainerTarget) {
            this.flashContainerTarget
                .querySelectorAll('.quick-action-flash')
                .forEach((flash) => this.prepareFlash(flash))
        }
    }

    disconnect() {
        if (this.popStateHandler) {
            window.removeEventListener('popstate', this.popStateHandler)
        }
    }

    async reloadCard(event) {
        let eventTarget = event.currentTarget

        if (this.cardTarget.dataset.cardId === eventTarget.id) return

        await this.replaceCard(this.cardTarget, eventTarget.dataset.link)
    }

    async reloadOrderCards(event) {
        const eventTarget = event.currentTarget

        if (this.hasCardTarget && this.cardTarget.dataset.cardId === eventTarget.id) {
            this.updatePaymentTabIcon(eventTarget)
            return
        }

        await this.reloadCardsForRow(eventTarget)
        this.updatePaymentTabIcon(eventTarget)
    }

    async reloadDocumentCards(event) {
        const eventTarget = event.currentTarget

        if (this.hasCardTarget && this.cardTarget.dataset.cardId === eventTarget.id) {
            this.updatePaymentTabIcon(eventTarget)
            return
        }

        await this.reloadCardsForRow(eventTarget)
        this.updatePaymentTabIcon(eventTarget)
    }

    async reloadCardsForRow(row) {
        if (row.dataset.showLink) {
            await Promise.all([
                this.replaceCard(this.hasCardTarget ? this.cardTarget : null, row.dataset.showLink),
                this.replaceCard(this.hasPaymentsCardTarget ? this.paymentsCardTarget : null, row.dataset.paymentsLink),
                this.replaceCard(this.hasCommentsCardTarget ? this.commentsCardTarget : null, row.dataset.commentsLink),
                this.replaceCard(this.hasCustomerHistoryCardTarget ? this.customerHistoryCardTarget : null, row.dataset.customerHistoryLink),
                this.replaceCard(this.hasHistoryCardTarget ? this.historyCardTarget : null, row.dataset.historyLink),
            ])

            return
        }

        if (row.dataset.link) {
            await this.replaceCard(this.hasCardTarget ? this.cardTarget : null, row.dataset.link)
        }
    }

    async replaceCard(target, url) {
        if (!target || !url) {
            return
        }

        const card = await $.ajax(url + window.location.search)
        const div = document.createElement('div')

        div.innerHTML = card
        target.replaceWith(div.firstElementChild)
    }

    async submitQuickAction(event) {
        event.preventDefault()

        const form = event.currentTarget
        const confirmMessage = form.dataset.confirmMessage

        if (confirmMessage && !confirm(confirmMessage)) {
            return
        }

        const submitButton = form.querySelector('[type="submit"]')

        if (submitButton) {
            submitButton.disabled = true
        }

        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json',
                },
            })
            const data = await response.json()

            this.showFlashes(data.flashes || [])
            this.replaceQuickActionFragments(data.fragments || {})
        } catch (error) {
            this.showFlashes([{
                type: 'danger',
                message: 'Action failed. Please try again.',
            }])
        } finally {
            if (submitButton) {
                submitButton.disabled = false
            }
        }
    }

    replaceQuickActionFragments(fragments) {
        if (fragments.card && this.hasCardTarget) {
            const div = document.createElement('div')

            div.innerHTML = fragments.card.trim()
            this.cardTarget.replaceWith(div.firstElementChild)
        }

        if (fragments.row) {
            const tbody = document.createElement('tbody')

            tbody.innerHTML = fragments.row.trim()

            const row = tbody.firstElementChild
            const currentRow = row ? document.getElementById(row.id) : null

            if (row && currentRow) {
                currentRow.replaceWith(row)
                row.classList.add('table-active')
                this.updatePaymentTabIcon(row)
            }
        }

        if (fragments.history && this.hasHistoryCardTarget) {
            const div = document.createElement('div')

            div.innerHTML = fragments.history.trim()
            this.historyCardTarget.replaceWith(div.firstElementChild)
        }

        if (fragments.payments && this.hasPaymentsCardTarget) {
            const div = document.createElement('div')

            div.innerHTML = fragments.payments.trim()
            this.paymentsCardTarget.replaceWith(div.firstElementChild)
        }
    }

    dismissFlash(event) {
        const flash = event.currentTarget.closest('.quick-action-flash')

        if (flash) {
            this.closeFlash(flash)
        }
    }

    showFlashes(flashes) {
        if (!this.hasFlashContainerTarget) {
            return
        }

        flashes.forEach((flash) => {
            const type = flash.type || 'info'
            const alertType = type === 'error' ? 'danger' : type
            const message = flash.message || ''
            const div = document.createElement('div')

            div.className = `alert alert-${alertType} alert-dismissible mb-2 quick-action-flash`
            div.setAttribute('role', 'alert')
            div.dataset.alertType = alertType
            div.innerHTML = `
                <button type="button" class="btn-close" aria-label="Close" data-action="reload-card#dismissFlash"></button>
                <div class="alert-message">${this.escapeHtml(message)}</div>
            `

            this.flashContainerTarget.prepend(div)
            this.prepareFlash(div)
        })
    }

    prepareFlash(flash) {
        if (flash.dataset.flashReady === '1') {
            return
        }

        flash.dataset.flashReady = '1'

        if (this.isErrorFlash(flash)) {
            return
        }

        flash.dataset.dismissTimerId = window.setTimeout(() => {
            this.closeFlash(flash)
        }, 3000).toString()
    }

    closeFlash(flash) {
        if (flash.classList.contains('quick-action-flash--dismiss')) {
            return
        }

        if (flash.dataset.dismissTimerId) {
            window.clearTimeout(Number(flash.dataset.dismissTimerId))
            delete flash.dataset.dismissTimerId
        }

        flash.classList.add('quick-action-flash--dismiss')

        window.setTimeout(() => {
            flash.remove()
        }, 260)
    }

    isErrorFlash(flash) {
        return flash.dataset.alertType === 'danger'
            || flash.dataset.alertType === 'error'
            || flash.classList.contains('alert-danger')
    }

    escapeHtml(value) {
        const div = document.createElement('div')

        div.textContent = value

        return div.innerHTML
    }

    async tableActivate(event) {
        let eventTarget = event.currentTarget

        var urlParams = new URLSearchParams(window.location.search)
        const idColumn = eventTarget.dataset.idColumn
        const idValue = eventTarget.id.toString()

        urlParams.set(idColumn, idValue)

        if (new URLSearchParams(window.location.search).get(idColumn) !== idValue) {
            history.pushState({}, '', this.urlWithParams(urlParams))
        }

        this.activateTableRow(eventTarget)
    }

    tabActivate(event) {
        const tabKey = event.currentTarget.dataset.tabKey

        if (!tabKey) {
            return
        }

        const urlParams = new URLSearchParams(window.location.search)

        urlParams.set('active_tab', tabKey)
        history.replaceState({}, '', this.urlWithParams(urlParams))
    }

    async syncFromLocation() {
        const row = this.rowFromLocation()

        if (!row) {
            window.location.reload()
            return
        }

        if (!this.hasCardTarget || this.cardTarget.dataset.cardId !== row.id) {
            await this.reloadCardsForRow(row)
        }

        this.activateTableRow(row)
        this.updatePaymentTabIcon(row)
        this.activateTabFromLocation()
    }

    rowFromLocation() {
        const firstRow = this.element.querySelector('[data-id-column]')
        const activeRow = this.element.querySelector('.table-active[data-id-column]')

        if (!firstRow && !activeRow) {
            return null
        }

        const idColumn = (activeRow || firstRow).dataset.idColumn
        const idValue = new URLSearchParams(window.location.search).get(idColumn)

        if (!idValue) {
            return firstRow
        }

        return document.getElementById(idValue)
    }

    activateTableRow(row) {
        const tableActive = document.getElementsByClassName('table-active')

        if (tableActive[0]) tableActive[0].classList.remove('table-active')

        row.classList.add('table-active')
    }

    activateTabFromLocation() {
        const tabs = Array.from(this.element.querySelectorAll('[data-tab-key]'))

        if (tabs.length === 0) {
            return
        }

        const urlParams = new URLSearchParams(window.location.search)
        const tabKey = urlParams.get('active_tab') || 'show'
        const tab = tabs.find((candidate) => candidate.dataset.tabKey === tabKey)
            || tabs.find((candidate) => candidate.dataset.tabKey === 'show')
            || tabs[0]

        if (window.bootstrap && window.bootstrap.Tab) {
            window.bootstrap.Tab.getOrCreateInstance(tab).show()
            return
        }

        tab.click()
    }

    urlWithParams(urlParams) {
        const query = urlParams.toString()

        return window.location.pathname + (query ? '?' + query : '') + window.location.hash
    }

    updatePaymentTabIcon(row) {
        if (!this.hasPaymentTabIconTarget || row.dataset.paymentComplete === undefined) {
            return
        }

        const isComplete = row.dataset.paymentComplete === '1'

        this.paymentTabIconTarget.classList.toggle('text-success', isComplete)
        this.paymentTabIconTarget.classList.toggle('text-danger', !isComplete)
    }
}
