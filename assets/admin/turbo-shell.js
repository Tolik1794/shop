const shellSelector = '[data-admin-shell-context]'
const shellContextKey = 'adminShellContext'
const loadingClass = 'admin-navigation-loading'

let lastFetchResponseUrl = null
let lastVisitUrl = null
let formIsDirty = false
let formIsSubmitting = false

const translate = key => window.adminTranslations && window.adminTranslations[key]
    ? window.adminTranslations[key]
    : key

const getShell = root => root.querySelector(shellSelector)

const isPrefetchRequest = event => {
    const headers = event.detail.fetchOptions.headers

    if (!headers) {
        return false
    }

    return headers['X-Sec-Purpose'] === 'prefetch'
        || (typeof headers.get === 'function' && headers.get('X-Sec-Purpose') === 'prefetch')
}

const getShellContext = root => {
    const shell = getShell(root)

    return shell ? shell.dataset[shellContextKey] : null
}

const syncInnerHtml = (selector, newRoot) => {
    const currentElement = document.querySelector(selector)
    const newElement = newRoot.querySelector(selector)

    if (!currentElement || !newElement) {
        return
    }

    currentElement.innerHTML = newElement.innerHTML
}

const syncElementList = (selector, newRoot, callback) => {
    const currentElements = Array.from(document.querySelectorAll(selector))
    const newElements = Array.from(newRoot.querySelectorAll(selector))

    currentElements.forEach((currentElement, index) => {
        const newElement = newElements[index]

        if (!newElement) {
            return
        }

        callback(currentElement, newElement)
    })
}

const syncSidebarNavigation = newRoot => {
    syncElementList('#sidebar .sidebar-item', newRoot, (currentElement, newElement) => {
        const keepSubmenuOpen = currentElement.classList.contains('sidebar-submenu-open')

        currentElement.className = newElement.className

        if (keepSubmenuOpen) {
            currentElement.classList.add('sidebar-submenu-open')
        }
    })

    syncElementList('#sidebar .sidebar-link', newRoot, (currentElement, newElement) => {
        currentElement.className = newElement.className

        if (newElement.hasAttribute('aria-expanded')) {
            currentElement.setAttribute('aria-expanded', newElement.getAttribute('aria-expanded'))
        } else {
            currentElement.removeAttribute('aria-expanded')
        }

        if (newElement.hasAttribute('href')) {
            currentElement.setAttribute('href', newElement.getAttribute('href'))
        }
    })

    syncElementList('#sidebar .sidebar-dropdown', newRoot, (currentElement, newElement) => {
        currentElement.className = newElement.className
    })
}

const syncShell = newBody => {
    syncSidebarNavigation(newBody)
    syncInnerHtml('#admin-navbar-breadcrumb', newBody)
    syncInnerHtml('#admin-navbar-locale', newBody)
}

const renderAdminShell = (currentBody, newBody) => {
    const currentContent = currentBody.querySelector('#admin-main-content')
    const newContent = newBody.querySelector('#admin-main-content')

    if (!currentContent || !newContent) {
        currentBody.replaceWith(newBody)
        return
    }

    syncShell(newBody)
    currentContent.replaceWith(newContent)
    document.dispatchEvent(new CustomEvent('admin:shell-refreshed'))
}

const disableTurboForCustomAjaxForms = () => {
    document
        .querySelectorAll('form[data-action*="submit->"]')
        .forEach(form => form.setAttribute('data-turbo', 'false'))
}

const isTrackableFormElement = element => {
    const form = element.closest('#admin-main-content form')

    return !!form && form.dataset.turboDirtyIgnore !== 'true'
}

const markFormDirty = event => {
    if (!isTrackableFormElement(event.target)) {
        return
    }

    formIsDirty = true
}

const confirmNavigationIfDirty = event => {
    if (!formIsDirty || formIsSubmitting) {
        return
    }

    if (confirm(translate('navigation.unsaved_changes'))) {
        formIsDirty = false
        return
    }

    event.preventDefault()
}

const handleBeforeRender = event => {
    const currentContext = getShellContext(document)
    const nextContext = getShellContext(event.detail.newBody)

    if (!currentContext || !nextContext || currentContext !== nextContext) {
        event.preventDefault()
        window.location.assign(lastFetchResponseUrl || lastVisitUrl || window.location.href)
        return
    }

    event.detail.render = renderAdminShell
}

document.addEventListener('input', markFormDirty, true)
document.addEventListener('change', markFormDirty, true)

window.addEventListener('beforeunload', event => {
    if (!formIsDirty || formIsSubmitting) {
        return
    }

    event.preventDefault()
    event.returnValue = ''
})

document.addEventListener('DOMContentLoaded', disableTurboForCustomAjaxForms)
document.addEventListener('turbo:load', () => {
    document.documentElement.classList.remove(loadingClass)
    formIsSubmitting = false
    disableTurboForCustomAjaxForms()
})
document.addEventListener('turbo:before-visit', event => {
    lastVisitUrl = event.detail.url
    confirmNavigationIfDirty(event)
})
document.addEventListener('turbo:before-fetch-request', event => {
    if (isPrefetchRequest(event)) {
        return
    }

    document.documentElement.classList.add(loadingClass)
})
document.addEventListener('turbo:before-fetch-response', event => {
    lastFetchResponseUrl = event.detail.fetchResponse.response.url
})
document.addEventListener('turbo:before-render', handleBeforeRender)
document.addEventListener('turbo:fetch-request-error', () => {
    document.documentElement.classList.remove(loadingClass)
})
document.addEventListener('turbo:submit-start', () => {
    formIsSubmitting = true
    formIsDirty = false
})
document.addEventListener('turbo:submit-end', () => {
    formIsSubmitting = false
})
