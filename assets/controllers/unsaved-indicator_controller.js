import {Controller} from '@hotwired/stimulus'

// Lightweight "unsaved changes" hint for the sticky action bar. Mirrors the dirty signal
// already used by assets/admin/turbo-shell.js (input/change marks dirty, submit clears),
// kept self-contained so it does not touch the shared navigation logic. The controller
// lives on the action bar and binds to its surrounding or explicitly associated <form>.
export default class extends Controller {
    static targets = ['indicator']
    static values = {formId: String}

    connect() {
        const explicitFormId = this.hasFormIdValue ? this.formIdValue : ''

        this.form = explicitFormId
            ? document.getElementById(explicitFormId)
            : this.element.closest('form')

        if (!this.form) {
            return
        }

        this.changeEventSource = explicitFormId ? document : this.form
        this.dirty = false
        this.onChange = (event) => {
            if (event.target.form === this.form || this.form.contains(event.target)) {
                this.markDirty()
            }
        }
        this.onSubmit = () => this.clear()

        this.changeEventSource.addEventListener('input', this.onChange)
        this.changeEventSource.addEventListener('change', this.onChange)
        this.form.addEventListener('submit', this.onSubmit)
    }

    disconnect() {
        if (!this.form) {
            return
        }

        this.changeEventSource.removeEventListener('input', this.onChange)
        this.changeEventSource.removeEventListener('change', this.onChange)
        this.form.removeEventListener('submit', this.onSubmit)
    }

    markDirty() {
        if (this.dirty) {
            return
        }

        this.dirty = true
        if (this.hasIndicatorTarget) {
            this.indicatorTarget.classList.remove('d-none')
        }
    }

    clear() {
        this.dirty = false
        if (this.hasIndicatorTarget) {
            this.indicatorTarget.classList.add('d-none')
        }
    }
}
