import { Controller } from '@hotwired/stimulus';
import $ from 'jquery'

export default class extends Controller {
    static targets = ['card']

    async reloadCard(event) {
        let eventTarget = event.currentTarget

        if (this.cardTarget.dataset.cardId === eventTarget.id) return

        let card = await $.ajax(eventTarget.dataset.link + $(location).attr('search'))
        let div = document.createElement('div')

        div.innerHTML = card

        this.cardTarget.replaceWith(div.firstChild)
    }

    async tableActivate(event) {
        let eventTarget = event.currentTarget,
            tableActive = document.getElementsByClassName('table-active')

        if (tableActive[0]) tableActive[0].classList.remove('table-active')

        eventTarget.classList.add('table-active')
    }
}