import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card', 'commentsCard', 'historyCard', 'paymentsCard', 'paymentTabIcon']

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
