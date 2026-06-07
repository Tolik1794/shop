import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['list', 'emptyState', 'newBody', 'newType', 'newImportant', 'prototype']
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

        const item = this.buildItem(body, this.newTypeTarget.value, this.newImportantTarget.checked)

        this.listTarget.append(item)
        this.nextIndexValue++
        this.newBodyTarget.value = ''
        this.newTypeTarget.value = 'general'
        this.newImportantTarget.checked = false
        this.refreshEmptyState()
    }

    applyTemplate(event) {
        const button = event.currentTarget

        this.newBodyTarget.value = button.dataset.templateBody || ''
        this.newTypeTarget.value = button.dataset.templateType || 'general'
        this.newImportantTarget.checked = button.dataset.templateImportant === '1'
        this.newBodyTarget.focus()
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
        const type = item ? item.querySelector('[data-draft-order-comments-edit-type]') : null
        const important = item ? item.querySelector('[data-draft-order-comments-edit-important]') : null
        const bodyElement = item ? item.querySelector('[data-draft-order-comments-body]') : null

        if (!textarea || !type || !important || !bodyElement) {
            return
        }

        const body = textarea.value.trim()

        if (body === '') {
            textarea.focus()

            return
        }

        this.setFieldValue(item, 'body', body)
        this.setFieldValue(item, 'type', type.value)
        this.setFieldValue(item, 'important', important.checked ? '1' : '0')
        textarea.value = body
        bodyElement.textContent = body
        this.updateItemAppearance(item, type.value, important.checked)
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

    buildItem(body, type, important) {
        const fragment = this.prototypeTarget.content.cloneNode(true)
        const item = fragment.querySelector('[data-draft-order-comments-item]')
        const bodyElement = item.querySelector('[data-draft-order-comments-body]')
        const textarea = item.querySelector('[data-draft-order-comments-edit-body]')
        const typeSelect = item.querySelector('[data-draft-order-comments-edit-type]')
        const importantInput = item.querySelector('[data-draft-order-comments-edit-important]')

        item.querySelectorAll('[data-draft-order-comments-field]').forEach((input) => {
            input.name = input.name.replace('__name__', this.nextIndexValue)
            input.id = input.id.replace('__name__', this.nextIndexValue)
        })
        if (importantInput) {
            const previousId = importantInput.id
            const importantLabel = item.querySelector(`label[for="${previousId}"]`)

            importantInput.id = previousId.replace('__name__', this.nextIndexValue)
            if (importantLabel) {
                importantLabel.htmlFor = importantInput.id
            }
        }
        this.setFieldValue(item, 'body', body)
        this.setFieldValue(item, 'type', type)
        this.setFieldValue(item, 'important', important ? '1' : '0')
        bodyElement.textContent = body
        textarea.value = body
        typeSelect.value = type
        importantInput.checked = important
        this.updateItemAppearance(item, type, important)

        return item
    }

    setFieldValue(item, name, value) {
        const field = item.querySelector(`[data-draft-order-comments-field-name="${name}"]`)

        if (field) {
            field.value = value
        }
    }

    updateItemAppearance(item, type, important) {
        item.classList.toggle('order-comment--important', important)
        item.classList.toggle('border-danger', important)
        item.classList.toggle('border-2', important)
        item.classList.toggle('bg-danger-subtle', important)

        const meta = item.querySelector('[data-draft-order-comments-meta]')
        const typeSelect = item.querySelector('[data-draft-order-comments-edit-type]')
        const selectedType = typeSelect ? typeSelect.options[typeSelect.selectedIndex] : null

        if (!meta) {
            return
        }

        const typeClasses = {
            general: 'bg-light text-dark border',
            manager: 'bg-primary',
            warehouse: 'bg-info text-dark',
            accounting: 'bg-success',
            internal: 'bg-secondary',
            call_result: 'bg-light text-dark border',
            callback_needed: 'bg-warning text-dark',
            complaint: 'bg-danger',
        }
        const importantLabel = meta.dataset.importantLabel || 'Important'
        const importantBadge = important
            ? `<span class="badge rounded-pill bg-danger"><i class="fa-solid fa-triangle-exclamation me-1"></i>${this.escapeHtml(importantLabel)}</span>`
            : ''
        const typeClass = typeClasses[type] || typeClasses.general
        const typeLabel = selectedType ? selectedType.textContent : type

        meta.innerHTML = `<span class="comment-meta d-inline-flex align-items-center gap-1">${importantBadge}<span class="badge rounded-pill ${typeClass}">${this.escapeHtml(typeLabel)}</span></span>`
    }

    escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;')
    }

    refreshEmptyState() {
        this.emptyStateTarget.classList.toggle('d-none', this.hasComments())
    }

    hasComments() {
        return this.listTarget.querySelector('[data-draft-order-comments-item]') !== null
    }

    findNextIndex() {
        const indexes = Array.from(this.listTarget.querySelectorAll('[data-draft-order-comments-field-name="body"]'))
            .map((input) => input.name.match(/\[(\d+)]$/))
            .filter((match) => match !== null)
            .map((match) => Number.parseInt(match[1], 10))

        return indexes.length > 0 ? Math.max(...indexes) + 1 : 0
    }
}
