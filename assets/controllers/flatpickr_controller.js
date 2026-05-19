import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['date']

    connect() {
        let date = this.dateTarget
        if (date) {
            let parameters = {}, availableParameters = ['minDate', 'maxDate', 'altInput', 'altFormat', 'dateFormat']

            availableParameters.forEach(function (value) {
                if (date.dataset[value]) {
                    parameters[value] = date.dataset[value === 'true' ? true : value]
                }
            })

            $(this.dateTarget).flatpickr()
        }
    }
}