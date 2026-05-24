var cache = {};

function adminTrans(key, fallback, parameters) {
    var value = window.adminTranslations && window.adminTranslations[key] ? window.adminTranslations[key] : fallback;

    if (parameters) {
        Object.keys(parameters).forEach(function (name) {
            value = value.replace('%' + name + '%', parameters[name]);
        });
    }

    return value;
}

$('.js-select').each(function () {
    var $select = $(this),
        is_required = $select.attr('required'),
        is_multiple = $select.attr('multiple'),
        is_autocomplete = $select.attr('autocomplete'),
        is_ajax = $select.hasClass('js-is-ajax');

    $select.data('need-send-ajax', is_ajax);

    $select.select2({
        minimumInputLength: is_autocomplete ? 1 : null,
        width: '100%',
        class: 'form-control',
        // Close the select after choosing a value only for single selects.
        closeOnSelect: !is_multiple,
        minimumResultsForSearch: is_ajax ? 1 : Infinity,
        // Show the clear control for optional and multi-select fields.
        allowClear: !is_required || is_multiple,
        placeholder: '',
        language: {
            inputTooShort: function (args) {
                var remainingChars = args.minimum - args.input.length;

                return adminTrans('select2.input_too_short', 'Enter %count% or more characters.', {count: remainingChars});
            },
            noResults: function () {
                return adminTrans('select2.no_results', 'No matches found.');
            }
        },
        ajax: is_ajax ? {
            delay: is_autocomplete ? 250 : null,
            url: function (params) {
                return this.data('options-route');
            },
            transport: function (params, success, failure) {
                if ($select.data('need-send-ajax') && !cache[this.url]) {
                    var $request = $.ajax(params);

                    $request.then(success);
                    $request.fail(failure);

                    return $request;
                } else {
                    $select.data('need-send-ajax', true);

                    if (cache[this.url]) {
                        success(cache[this.url]);
                    }

                    return false;
                }
            },
            processResults: function (data, params) {
                var results = {
                        results: []
                    },
                    url = this.options.options.optionsRoute;

                if (!is_autocomplete && !cache[url]) cache[url] = data;

                $.each(data.results, function (index, value) {
                    if ($.fn.select2.defaults.defaults.matcher(params, value)) {
                        results.results.push(value);
                    }
                })

                return results;
            },
        } : null
    })
    .on('select2:clear', function (e) {
        // Mark full clear.
        $(this).data('is_clear', true);

        // Keep the dropdown open while clearing all values.
        if ($(this).data('is_open')) {
            e.preventDefault();
        }
    }).on('select2:unselect', function () {
        // Mark single value removal.
        $(this).data('is_unselect', true);
    }).on('select2:closing', function (e) {
        // Keep the dropdown open after removal.
        if ($(this).data('is_unselect') || $(this).data('is_clear')) {
            $(this).removeData('is_clear');
            $(this).removeData('is_unselect');

            e.preventDefault();
        }
    }).on('select2:opening', function (e) {
        // After removal.
        if ($(this).data('is_unselect')) {
            $(this).removeData('is_unselect');

            // If other selected values remain.
            if ($(this).val().length) {
                // Do not send an AJAX request.
                $(this).data('need-send-ajax', false);
                // Do not open the dropdown.
                e.preventDefault();
            }
        }
    }).on('select2:open', function (){
        if(!is_multiple) {
            setTimeout(function () {
                $('.select2-search__field').last()[0].focus();
            }, 0);
        }
    });
});
