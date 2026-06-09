import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static values = {
        text: String,
        message: { type: String, default: 'Copied' },
    }

    copy(event) {
        event.stopPropagation()

        navigator.clipboard.writeText(this.textValue).then(() => {
            this.showFlash(this.messageValue)
        }).catch(() => {
            try {
                const el = document.createElement('textarea')
                el.value = this.textValue
                el.style.cssText = 'position:fixed;opacity:0'
                document.body.appendChild(el)
                el.select()
                document.execCommand('copy')
                el.remove()
                this.showFlash(this.messageValue)
            } catch (_) {
                // silent fail
            }
        })
    }

    showFlash(message) {
        let container = document.querySelector('.quick-action-flashes')

        if (!container) {
            container = document.createElement('div')
            container.className = 'position-fixed bottom-0 end-0 p-3 quick-action-flashes'
            container.style.cssText = 'z-index: 1080; max-width: min(420px, calc(100vw - 1rem));'
            document.body.appendChild(container)
        }

        const div = document.createElement('div')
        div.className = 'alert alert-success alert-dismissible mb-2 quick-action-flash'
        div.setAttribute('role', 'alert')
        div.dataset.alertType = 'success'
        div.innerHTML = `
            <button type="button" class="btn-close" aria-label="Close" data-action="reload-card#dismissFlash"></button>
            <div class="alert-message">${this.escapeHtml(message)}</div>
        `
        container.prepend(div)

        div.dataset.dismissTimerId = window.setTimeout(() => {
            div.classList.add('quick-action-flash--dismiss')
            window.setTimeout(() => div.remove(), 260)
        }, 3000).toString()
    }

    escapeHtml(value) {
        const div = document.createElement('div')
        div.textContent = value
        return div.innerHTML
    }
}
