import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'commentsCard', 'historyCard', 'paymentsCard', 'paymentTabIcon', 'flashContainer']

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
    }

    dismissFlash(event) {
        const flash = event.currentTarget.closest('.quick-action-flash')

        if (flash) {
            flash.remove()
        }
    }

    showFlashes(flashes) {
        if (!this.hasFlashContainerTarget) {
            return
        }

        flashes.forEach((flash) => {
            const type = flash.type || 'info'
            const message = flash.message || ''
            const div = document.createElement('div')

            div.className = `alert alert-${type} alert-dismissible mb-2 quick-action-flash`
            div.setAttribute('role', 'alert')
            div.innerHTML = `
                <button type="button" class="btn-close" aria-label="Close" data-action="reload-card#dismissFlash"></button>
                <div class="alert-message">${this.escapeHtml(message)}</div>
            `

            this.flashContainerTarget.prepend(div)
        })
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
