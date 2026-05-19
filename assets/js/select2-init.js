var cache = {};

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
        //закрывать селект при выборе только для обычного
        closeOnSelect: !is_multiple,
        minimumResultsForSearch: is_ajax ? 1 : Infinity,
        //выводить крестик для очистки всего для необязательного поля и мультиплселекта
        allowClear: !is_required || is_multiple,
        placeholder: '',
        language: {
            inputTooShort: function (args) {
                var remainingChars = args.minimum - args.input.length;

                return 'Введите ' + remainingChars + ' или больше символов.';
            },
            noResults: function () {
                return "Совпадений не найдено";
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
        //метка что удаляем всё
        $(this).data('is_clear', true);

        //не закрывать окно при удалении всех
        if ($(this).data('is_open')) {
            e.preventDefault();
        }
    }).on('select2:unselect', function () {
        //метка удаления одного варианта
        $(this).data('is_unselect', true);
    }).on('select2:closing', function (e) {
        //не закрывать после удаление
        if ($(this).data('is_unselect') || $(this).data('is_clear')) {
            $(this).removeData('is_clear');
            $(this).removeData('is_unselect');

            e.preventDefault();
        }
    }).on('select2:opening', function (e) {
        // после удаления
        if ($(this).data('is_unselect')) {
            $(this).removeData('is_unselect');

            //если есть другие варианта выбранные
            if ($(this).val().length) {
                //не делать запрос ajax
                $(this).data('need-send-ajax', false);
                //не открывать
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

