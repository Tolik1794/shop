import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['card']

    async reloadCard(event) {
        let eventTarget = event.currentTarget

        if (this.cardTarget.dataset.cardId === eventTarget.id) return

        let card = await $.ajax(eventTarget.dataset.link + $(location).attr('search'))
        let div = document.createElement('div')

        div.innerHTML = card

        this.cardTarget.replaceWith(div.firstElementChild)
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
}