import {Controller} from '@hotwired/stimulus'

export default class extends Controller {
    static targets = ['form']

    connect() {
        let form = this.formTarget

        if (form) {
            $(form).find('.select2').each(function (key, select2) {

                let $select2 = $(select2),
                    options = ($select2.data('select2-options') ? JSON.parse($select2.data('select2-options')) : {}),
                    isRequired = $select2.attr('required'),
                    isMultiple = $select2.attr('multiple')

                options.class = 'form-control'
                options.allowClear = !isRequired || isMultiple
                options.closeOnSelect = !isMultiple

                $select2.select2(options)
                    .on('select2:clear', function (e) {
                        $(this).data('is_clear', true)

                        if ($(this).data('is_open')) {
                            e.preventDefault()
                        }
                    })
                    .on('select2:unselect', function () {
                        $(this).data('is_unselect', true)
                    })
                    .on('select2:closing', function (e) {
                        if ($(this).data('is_unselect') || $(this).data('is_clear')) {
                            $(this).removeData('is_clear')
                            $(this).removeData('is_unselect')
                            e.preventDefault()
                        }
                    })
                    .on('select2:opening', function (e) {
                        if ($(this).data('is_unselect')) {
                            $(this).removeData('is_unselect')

                            if ($(this).val().length) {
                                $(this).data('need-send-ajax', false)
                                e.preventDefault()
                            }
                        }
                    })
                    .on('select2:open', function () {
                        if (!isMultiple) {
                            setTimeout(function () {
                                $('.select2-search__field').last()[0].focus()
                            }, 0)
                        }
                    })
            })
        }
    }
}