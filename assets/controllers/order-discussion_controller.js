import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        refreshCommentsUrl: String,
        refreshHistoryUrl: String,
    }

    applyTemplate(event) {
        const button = event.currentTarget
        const form = button.closest('form')

        if (!form) {
            return
        }

        const body = form.querySelector('textarea[name="body"]')
        const type = form.querySelector('select[name="type"]')
        const important = form.querySelector('input[name="important"]')

        if (body) {
            body.value = button.dataset.templateBody || ''
            body.focus()
        }

        if (type && button.dataset.templateType) {
            type.value = button.dataset.templateType
        }

        if (important) {
            important.checked = button.dataset.templateImportant === '1'
        }
    }

    toggleEdit(event) {
        const comment = event.currentTarget.closest('[data-order-discussion-comment]')
        const form = comment ? comment.querySelector('[data-order-discussion-target~="editForm"]') : null

        if (!form) {
            return
        }

        form.classList.toggle('d-none')

        if (!form.classList.contains('d-none')) {
            const textarea = form.querySelector('textarea')

            if (textarea) {
                textarea.focus()
            }
        }
    }

    async submit(event) {
        event.preventDefault()

        const form = event.currentTarget
        const submitButton = event.submitter

        if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) {
            return
        }

        if (submitButton) {
            submitButton.disabled = true
        }

        try {
            const response = await fetch(form.action, {
                method: form.method || 'POST',
                body: new FormData(form),
                headers: {'X-Requested-With': 'XMLHttpRequest'},
            })

            if (!response.ok) {
                return
            }

            if (this.hasRefreshCommentsUrlValue) {
                await this.refreshStandaloneCards()

                return
            }

            const wrapper = document.createElement('div')
            wrapper.innerHTML = await response.text()

            if (wrapper.firstElementChild) {
                this.element.replaceWith(wrapper.firstElementChild)
            }
        } finally {
            if (submitButton && document.body.contains(submitButton)) {
                submitButton.disabled = false
            }
        }
    }

    async refreshStandaloneCards() {
        const reloadCard = this.element.closest('[data-controller~="reload-card"]')
        const historyCard = reloadCard ? reloadCard.querySelector('[data-reload-card-target~="historyCard"]') : null
        const [commentsReplacement, historyReplacement] = await Promise.all([
            this.fetchReplacement(this.refreshCommentsUrlValue),
            this.hasRefreshHistoryUrlValue && historyCard ? this.fetchReplacement(this.refreshHistoryUrlValue) : null,
        ])

        if (historyCard && historyReplacement) {
            historyCard.replaceWith(historyReplacement)
        }

        if (commentsReplacement) {
            this.element.replaceWith(commentsReplacement)
        }
    }

    async fetchReplacement(url) {
        const response = await fetch(url, {
            headers: {'X-Requested-With': 'XMLHttpRequest'},
        })

        if (!response.ok) {
            return null
        }

        const wrapper = document.createElement('div')
        wrapper.innerHTML = await response.text()

        return wrapper.firstElementChild
    }
}
