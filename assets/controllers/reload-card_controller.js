import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'commentsCard', 'customerHistoryCard', 'historyCard', 'paymentsCard', 'paymentTabIcon', 'flashContainer']

    connect() {
        if (!this.hasFlashContainerTarget) {
            return
        }

        this.flashContainerTarget
            .querySelectorAll('.quick-action-flash')
            .forEach((flash) => this.prepareFlash(flash))
    }

    async reloadCard(event) {
        let eventTarget = event.currentTarget

        if (this.cardTarget.dataset.cardId === eventTarget.id) return

        let card = await $.ajax(eventTarget.dataset.link + $(location).attr('search'))
        let div = document.createElement('div')

        div.innerHTML = card

        this.cardTarget.replaceWith(div.firstElementChild)
    }

    async reloadOrderCards(event) {
        const eventTarget = event.currentTarget

        if (this.hasCardTarget && this.cardTarget.dataset.cardId === eventTarget.id) {
            this.updatePaymentTabIcon(eventTarget)
            return
        }

        await Promise.all([
            this.replaceCard(this.hasCardTarget ? this.cardTarget : null, eventTarget.dataset.showLink),
            this.replaceCard(this.hasPaymentsCardTarget ? this.paymentsCardTarget : null, eventTarget.dataset.paymentsLink),
            this.replaceCard(this.hasCommentsCardTarget ? this.commentsCardTarget : null, eventTarget.dataset.commentsLink),
            this.replaceCard(this.hasCustomerHistoryCardTarget ? this.customerHistoryCardTarget : null, eventTarget.dataset.customerHistoryLink),
            this.replaceCard(this.hasHistoryCardTarget ? this.historyCardTarget : null, eventTarget.dataset.historyLink),
        ])

        this.updatePaymentTabIcon(eventTarget)
    }

    async reloadDocumentCards(event) {
        const eventTarget = event.currentTarget

        if (this.hasCardTarget && this.cardTarget.dataset.cardId === eventTarget.id) {
            this.updatePaymentTabIcon(eventTarget)
            return
        }

        await Promise.all([
            this.replaceCard(this.hasCardTarget ? this.cardTarget : null, eventTarget.dataset.showLink),
            this.replaceCard(this.hasPaymentsCardTarget ? this.paymentsCardTarget : null, eventTarget.dataset.paymentsLink),
            this.replaceCard(this.hasHistoryCardTarget ? this.historyCardTarget : null, eventTarget.dataset.historyLink),
        ])

        this.updatePaymentTabIcon(eventTarget)
    }

    async replaceCard(target, url) {
        if (!target || !url) {
            return
        }

        const card = await $.ajax(url + $(location).attr('search'))
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
        let eventTarget = event.currentTarget,
            tableActive = document.getElementsByClassName('table-active')

        var urlParams = new URLSearchParams(window.location.search)

        urlParams.set(eventTarget.dataset.idColumn, eventTarget.id.toString())

        history.pushState({}, '', window.location.href.split('?')[0] + '?' + urlParams.toString());

        if (tableActive[0]) tableActive[0].classList.remove('table-active')

        eventTarget.classList.add('table-active')
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
