import { Controller } from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['search', 'node', 'children', 'toggle', 'empty']

    toggle(event) {
        event.stopPropagation()

        const toggle = event.currentTarget
        const children = this.childrenFor(toggle.dataset.categoryId)

        if (!children) {
            return
        }

        this.setExpanded(toggle, children.classList.contains('d-none'))
    }

    search() {
        const query = this.normalize(this.searchTarget.value)

        if (!query) {
            this.restoreInitialState()
            return
        }

        const visibleIds = new Set()

        this.nodeTargets.forEach((node) => {
            if (this.normalize(node.dataset.categoryName).includes(query)) {
                this.addNodeAndAncestors(node, visibleIds)
            }
        })

        this.nodeTargets.forEach((node) => {
            node.classList.toggle('d-none', !visibleIds.has(node.dataset.categoryId))
        })

        this.childrenTargets.forEach((children) => {
            const isVisible = visibleIds.has(children.dataset.categoryParentId)

            children.classList.toggle('d-none', !isVisible)
            const toggle = this.toggleFor(children.dataset.categoryParentId)
            if (toggle) {
                toggle.setAttribute('aria-expanded', isVisible ? 'true' : 'false')
                this.updateToggleLabel(toggle, isVisible)
            }
        })

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.toggle('d-none', visibleIds.size > 0)
        }
    }

    restoreInitialState() {
        this.nodeTargets.forEach((node) => node.classList.remove('d-none'))

        this.childrenTargets.forEach((children) => {
            const expanded = children.dataset.initialExpanded === 'true'
            children.classList.toggle('d-none', !expanded)

            const toggle = this.toggleFor(children.dataset.categoryParentId)
            if (toggle) {
                toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false')
                this.updateToggleLabel(toggle, expanded)
            }
        })

        if (this.hasEmptyTarget) {
            this.emptyTarget.classList.add('d-none')
        }
    }

    addNodeAndAncestors(node, visibleIds) {
        let current = node

        while (current) {
            visibleIds.add(current.dataset.categoryId)
            current = this.nodeFor(current.dataset.categoryParentId)
        }
    }

    nodeFor(id) {
        return this.nodeTargets.find((node) => node.dataset.categoryId === id)
    }

    childrenFor(id) {
        return this.childrenTargets.find((children) => children.dataset.categoryParentId === id)
    }

    toggleFor(id) {
        return this.toggleTargets.find((toggle) => toggle.dataset.categoryId === id)
    }

    setExpanded(toggle, expanded) {
        const children = this.childrenFor(toggle.dataset.categoryId)

        if (!children) {
            return
        }

        children.classList.toggle('d-none', !expanded)
        toggle.setAttribute('aria-expanded', expanded ? 'true' : 'false')
        this.updateToggleLabel(toggle, expanded)
    }

    updateToggleLabel(toggle, expanded) {
        toggle.setAttribute('aria-label', expanded ? toggle.dataset.collapseLabel : toggle.dataset.expandLabel)
    }

    normalize(value) {
        return (value || '').trim().toLocaleLowerCase()
    }
}
