import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['list', 'emptyState', 'newBody', 'prototype']
    static values = {nextIndex: Number}

    connect() {
        this.nextIndexValue = Math.max(this.nextIndexValue, this.findNextIndex())
    }

    add(event) {
        event.preventDefault()

        const body = this.newBodyTarget.value.trim()

        if (body === '') {
            this.newBodyTarget.focus()

            return
        }

        const item = this.buildItem(body)

        this.listTarget.append(item)
        this.nextIndexValue++
        this.newBodyTarget.value = ''
        this.refreshEmptyState()
    }

    toggleEdit(event) {
        const item = event.currentTarget.closest('[data-draft-order-comments-item]')
        const form = item ? item.querySelector('[data-draft-order-comments-edit-form]') : null

        if (!form) {
            return
        }

        form.classList.toggle('d-none')

        if (!form.classList.contains('d-none')) {
            const textarea = form.querySelector('[data-draft-order-comments-edit-body]')

            if (textarea) {
                textarea.focus()
            }
        }
    }

    saveEdit(event) {
        event.preventDefault()

        const item = event.currentTarget.closest('[data-draft-order-comments-item]')
        const textarea = item ? item.querySelector('[data-draft-order-comments-edit-body]') : null
        const input = item ? item.querySelector('[data-draft-order-comments-field]') : null
        const bodyElement = item ? item.querySelector('[data-draft-order-comments-body]') : null

        if (!textarea || !input || !bodyElement) {
            return
        }

        const body = textarea.value.trim()

        if (body === '') {
            textarea.focus()

            return
        }

        input.value = body
        textarea.value = body
        bodyElement.textContent = body
        event.currentTarget.classList.add('d-none')
    }

    remove(event) {
        const item = event.currentTarget.closest('[data-draft-order-comments-item]')

        if (!item) {
            return
        }

        if (event.currentTarget.dataset.confirm && !window.confirm(event.currentTarget.dataset.confirm)) {
            return
        }

        item.remove()
        this.refreshEmptyState()
    }

    buildItem(body) {
        const fragment = this.prototypeTarget.content.cloneNode(true)
        const item = fragment.querySelector('[data-draft-order-comments-item]')
        const input = item.querySelector('[data-draft-order-comments-field]')
        const bodyElement = item.querySelector('[data-draft-order-comments-body]')
        const textarea = item.querySelector('[data-draft-order-comments-edit-body]')

        input.name = input.name.replace('__name__', this.nextIndexValue)
        input.value = body
        bodyElement.textContent = body
        textarea.value = body

        return item
    }

    refreshEmptyState() {
        this.emptyStateTarget.classList.toggle('d-none', this.hasComments())
    }

    hasComments() {
        return this.listTarget.querySelector('[data-draft-order-comments-item]') !== null
    }

    findNextIndex() {
        const indexes = Array.from(this.listTarget.querySelectorAll('[data-draft-order-comments-field]'))
            .map((input) => input.name.match(/\[(\d+)]$/))
            .filter((match) => match !== null)
            .map((match) => Number.parseInt(match[1], 10))

        return indexes.length > 0 ? Math.max(...indexes) + 1 : 0
    }
}
